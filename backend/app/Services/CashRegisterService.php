<?php

namespace App\Services;

use App\Contracts\Repositories\CashRegisterSessionRepositoryInterface;
use App\Models\Account;
use App\Models\CashRegisterSession;
use App\Models\CashSessionMovement;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SalesReturn;
use App\Models\Setting;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CashRegisterService
{
    public function __construct(
        private CashRegisterSessionRepositoryInterface $sessionRepo,
        private AccountingService $accountingService,
    ) {}

    public function currentSession(): ?CashRegisterSession
    {
        $session = $this->sessionRepo->currentOpen(Auth::id());
        if (! $session) {
            return null;
        }

        $stats = $this->calcSessionStats($session);
        $session->setRelation('stats', (object) $stats);

        return $session;
    }

    public function open(array $data): CashRegisterSession
    {
        return DB::transaction(function () use ($data) {
            // Lock any existing open sessions to prevent race condition on concurrent opens
            $existing = CashRegisterSession::where('cashier_id', Auth::id())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new Exception('يوجد جلسة مفتوحة بالفعل. أغلقها أولاً.');
            }

            return $this->sessionRepo->create([
                'session_number' => SequenceService::next('session', 'SES'),
                'cashier_id' => Auth::id(),
                'cashier_name' => Auth::user()?->full_name ?? '',
                'opening_amount' => $data['opening_amount'],
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
                'opened_at' => now(),
            ]);
        });
    }

    public function close(CashRegisterSession $session, array $data): CashRegisterSession
    {
        $stats = $this->calcSessionStats($session);
        $expectedCash = $session->opening_amount
            + $stats['cash_sales']
            - $stats['cash_returns']
            + $stats['deposits']
            - $stats['withdrawals'];
        $actualCash = (float) $data['actual_cash'];
        $difference = $actualCash - $expectedCash;

        DB::transaction(function () use ($session, $stats, $expectedCash, $actualCash, $difference, $data) {
            // Re-fetch with lock to prevent double-close race condition
            $locked = CashRegisterSession::lockForUpdate()->findOrFail($session->id);

            if ($locked->cashier_id !== Auth::id()) {
                throw new Exception('لا يمكنك إغلاق جلسة كاشير آخر.');
            }
            if ($locked->status === 'closed') {
                throw new Exception('الجلسة مغلقة بالفعل.');
            }

            $this->sessionRepo->update($locked, [
                'expected_cash' => round($expectedCash, 2),
                'actual_cash' => round($actualCash, 2),
                'difference' => round($difference, 2),
                'total_sales' => round($stats['total_sales'], 2),
                'total_returns' => round($stats['total_returns'], 2),
                'total_card' => round($stats['card_sales'], 2),
                'total_transfer' => round($stats['transfer_sales'], 2),
                'invoices_count' => $stats['invoices_count'],
                'status' => 'closed',
                'notes' => $data['notes'] ?? null,
                'closed_at' => now(),
            ]);
        });

        if ($difference < -5) {
            Log::channel('audit')->warning('cash_session.shortage', [
                'session_id' => $session->id,
                'cashier_id' => Auth::id(),
                'expected' => $expectedCash,
                'actual' => $actualCash,
                'shortage' => abs($difference),
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // Link to financial reports: auto-create journal entry if accounting is configured
        $this->postSessionToAccounting($session, $stats, round($actualCash, 2));

        return $session->fresh();
    }

    /**
     * Record a manual cash drawer movement (deposit or withdrawal) during an open session.
     * Returns the movement plus any threshold warnings.
     *
     * @return array{movement: CashSessionMovement, warnings: string[]}
     */
    public function recordMovement(CashRegisterSession $session, string $type, float $amount, ?string $reason): array
    {
        if ($session->status !== 'open') {
            throw new Exception('لا يمكن تسجيل حركة على جلسة مغلقة.');
        }
        if ($session->cashier_id !== Auth::id()) {
            throw new Exception('لا يمكنك تسجيل حركة على جلسة كاشير آخر.');
        }
        if ($amount <= 0) {
            throw new Exception('يجب أن يكون المبلغ أكبر من صفر.');
        }

        // Daily withdrawal limit check
        if ($type === 'withdrawal') {
            $maxDailyWithdrawal = (float) Setting::get('max_daily_withdrawal', 0);
            if ($maxDailyWithdrawal > 0) {
                $todayWithdrawals = CashSessionMovement::whereHas('session', fn ($q) => $q->where('cashier_id', Auth::id()))
                    ->where('type', 'withdrawal')
                    ->whereDate('created_at', today())
                    ->sum('amount');

                if ($todayWithdrawals + $amount > $maxDailyWithdrawal) {
                    throw new Exception(__('pos.daily_withdrawal_limit_exceeded', [
                        'limit' => number_format($maxDailyWithdrawal, 2),
                        'used' => number_format($todayWithdrawals, 2),
                    ]));
                }
            }
        }

        $movement = CashSessionMovement::create([
            'cash_session_id' => $session->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'reason' => $reason,
            'user_id' => Auth::id(),
        ]);

        // Low balance alert check after withdrawal
        $warnings = [];
        if ($type === 'withdrawal') {
            $minBalance = (float) Setting::get('min_cash_balance', 0);
            if ($minBalance > 0) {
                $estimatedBalance = $this->estimatedCashBalance($session);
                if ($estimatedBalance < $minBalance) {
                    $warnings[] = __('pos.low_cash_balance_alert', [
                        'balance' => number_format($estimatedBalance, 2),
                        'min' => number_format($minBalance, 2),
                    ]);
                    Log::channel('audit')->warning('cash_session.low_balance', [
                        'session_id' => $session->id,
                        'cashier_id' => Auth::id(),
                        'estimated_balance' => $estimatedBalance,
                        'min_balance' => $minBalance,
                    ]);
                }
            }
        }

        return compact('movement', 'warnings');
    }

    /**
     * Estimate the current cash balance in an open session.
     */
    public function estimatedCashBalance(CashRegisterSession $session): float
    {
        $stats = $this->calcSessionStats($session);

        return round(
            $session->opening_amount
                + $stats['cash_sales']
                - $stats['cash_returns']
                + $stats['deposits']
                - $stats['withdrawals'],
            2,
        );
    }

    public function history(array $filters): LengthAwarePaginator
    {
        $user = Auth::user();
        $canSeeAll = $user->hasPermissionTo('view_reports') || $user->hasPermissionTo('manage_roles');

        return $this->sessionRepo->history($filters, $canSeeAll, $user->id);
    }

    /**
     * Optionally post a summary journal entry when a session closes.
     *
     * SECURITY FIX: previously this method created JournalEntry and JournalEntryLine
     * rows directly, bypassing AccountingService entirely — no balance check, no fiscal
     * period check, no sequence generation, and is_posted was set to true by hand.
     *
     * Now routes through AccountingService::createJournalEntry() + postEntry() so all
     * invariants (balanced lines, open period, write-locked balance updates, sequence
     * numbering) are enforced consistently.
     *
     * Entry represents only the cash portion of the session (debit cash, credit revenue
     * for the cash-sales amount) so the two lines are always balanced. Card / transfer
     * sales follow separate settlement flows.
     */
    private function postSessionToAccounting(CashRegisterSession $session, array $stats, float $actualCash): void
    {
        try {
            $cashAccount = Account::where('account_code', Setting::get('cash_account_code', ''))->first();
            $revenueAccount = Account::where('account_code', Setting::get('revenue_account_code', ''))->first();

            $cashSales = round((float) $stats['cash_sales'], 2);

            if (! $cashAccount || ! $revenueAccount || $cashSales <= 0) {
                return;
            }

            // Idempotency guard — do not double-post if somehow called twice
            if (JournalEntry::where('reference_type', 'cash_session')
                ->where('reference_id', $session->id)->exists()) {
                return;
            }

            $entryDate = $session->closed_at?->toDateString() ?? today()->toDateString();
            $description = __('pos.cash_session_journal', ['number' => $session->session_number]);

            $entry = $this->accountingService->createJournalEntry([
                'entry_date' => $entryDate,
                'description' => $description,
                'reference_type' => 'cash_session',
                'reference_id' => $session->id,
                'lines' => [
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => $cashSales,
                        'credit' => 0,
                        'description' => __('pos.cash_session_cash_line', ['number' => $session->session_number]),
                    ],
                    [
                        'account_id' => $revenueAccount->id,
                        'debit' => 0,
                        'credit' => $cashSales,
                        'description' => __('pos.cash_session_revenue_line', ['number' => $session->session_number]),
                    ],
                ],
            ]);

            $this->accountingService->postEntry($entry);

        } catch (Throwable $e) {
            // Non-fatal: accounting link is optional; log and continue
            Log::warning('cash_session.accounting_link_failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function calcSessionStats(CashRegisterSession $session): array
    {
        // Use start-of-day so the entire shift's activity is captured even when the
        // session was opened mid-day or after midnight in local time.
        $from = $session->opened_at->copy()->startOfDay();
        $to   = $session->closed_at ?? now();

        $invoices = Invoice::where('cashier_id', $session->cashier_id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('payment_method, COUNT(*) as cnt, SUM(final_total) as total')
            ->groupBy('payment_method')
            ->get()->keyBy('payment_method');

        // Use created_at (datetime) for returns so time-of-day filtering works;
        // return_date is date-only and cannot be narrowed to a shift window.
        $returnsBase = SalesReturn::where('processed_by', $session->cashier_id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to]);

        $totalReturns = (float) (clone $returnsBase)->sum('total_amount');
        $cashReturns  = (float) (clone $returnsBase)->where('refund_method', 'cash')->sum('refund_amount');

        $cashSales = $invoices->get('cash')?->total ?? 0;
        $cardSales = $invoices->get('card')?->total ?? 0;
        $transferSales = $invoices->get('transfer')?->total ?? 0;
        $totalSales = collect($invoices)->sum('total');
        $invoicesCount = collect($invoices)->sum('cnt');

        $movements = CashSessionMovement::where('cash_session_id', $session->id)->get();
        $deposits    = (float) $movements->where('type', 'deposit')->sum('amount');
        $withdrawals = (float) $movements->where('type', 'withdrawal')->sum('amount');

        return [
            'cash_sales'    => $cashSales,
            'card_sales'    => $cardSales,
            'transfer_sales' => $transferSales,
            'total_sales'   => $totalSales,
            'total_returns' => $totalReturns,
            'cash_returns'  => $cashReturns,
            'invoices_count' => $invoicesCount,
            'deposits'      => $deposits,
            'withdrawals'   => $withdrawals,
        ];
    }
}
