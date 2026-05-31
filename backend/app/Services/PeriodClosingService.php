<?php

namespace App\Services;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeriodClosingService
{
    public function __construct(
        private AccountingService $accountingService,
        private AccountRepositoryInterface $accountRepo,
    ) {}

    /**
     * Create a new open fiscal period, rejecting any date overlap with existing periods.
     */
    public function openPeriod(array $data): FiscalPeriod
    {
        $overlap = FiscalPeriod::where(function ($q) use ($data) {
            $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                ->orWhere(
                    fn ($q2) => $q2
                        ->where('start_date', '<=', $data['start_date'])
                        ->where('end_date', '>=', $data['end_date']),
                );
        })->exists();

        if ($overlap) {
            throw new DomainException(__('pos.period_overlap'));
        }

        return FiscalPeriod::create([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'open',
        ]);
    }

    /**
     * Preview what the closing journal entry would look like without committing anything.
     */
    public function previewClosingEntry(FiscalPeriod $period): array
    {
        return $this->buildClosingLines($period);
    }

    /**
     * Generate the closing journal entry, lock all revenue/expense accounts to zero,
     * and mark the period closed — all inside a single transaction.
     */
    /**
     * FIX: period row is now locked inside the transaction before the isClosed() check.
     * Without lockForUpdate, two concurrent requests could both pass the pre-transaction
     * guard, then race to generate two closing entries for the same period.
     */
    public function closePeriod(FiscalPeriod $period, int $retainedEarningsAccountId): FiscalPeriod
    {
        // Fast pre-flight check outside transaction (avoids unnecessary lock contention)
        if ($period->isClosed()) {
            throw new DomainException(__('pos.period_already_closed'));
        }

        $retainedEarnings = $this->accountRepo->findOrFail($retainedEarningsAccountId);
        if ($retainedEarnings->account_type !== 'equity') {
            throw new DomainException(__('pos.retained_earnings_must_be_equity'));
        }

        return DB::transaction(function () use ($period, $retainedEarnings) {
            // FIX: re-fetch with write lock and re-check inside transaction
            /** @var FiscalPeriod $locked */
            $locked = FiscalPeriod::lockForUpdate()->findOrFail($period->id);
            if ($locked->isClosed()) {
                throw new DomainException(__('pos.period_already_closed'));
            }

            $closingEntry = $this->generateClosingEntry($locked, $retainedEarnings);

            $locked->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closing_entry_id' => $closingEntry->id,
            ]);

            return $locked->fresh(['closingEntry', 'closedBy']);
        });
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function generateClosingEntry(FiscalPeriod $period, Account $retainedEarnings): JournalEntry
    {
        $preview = $this->buildClosingLines($period);
        $lines = $preview['lines'];
        $netIncome = $preview['net_income'];

        if (empty($lines) && $netIncome == 0) {
            throw new DomainException(__('pos.period_no_activity'));
        }

        // Add the retained earnings impact line
        if ($netIncome > 0) {
            $lines[] = [
                'account_id' => $retainedEarnings->id,
                'debit' => 0,
                'credit' => $netIncome,
                'description' => __('pos.closing_net_income_transfer'),
            ];
        } elseif ($netIncome < 0) {
            $lines[] = [
                'account_id' => $retainedEarnings->id,
                'debit' => abs($netIncome),
                'credit' => 0,
                'description' => __('pos.closing_net_loss_transfer'),
            ];
        }

        $entry = $this->accountingService->createJournalEntry([
            'entry_date' => $period->end_date->format('Y-m-d'),
            'description' => __('pos.closing_entry_description', ['name' => $period->name]),
            'reference_type' => 'fiscal_period',
            'reference_id' => $period->id,
            'lines' => $lines,
        ]);

        $this->accountingService->postEntry($entry);

        return $entry->fresh();
    }

    /**
     * Compute the debit/credit lines needed to zero out all revenue and expense accounts.
     * Revenue accounts (credit-normal): DR to zero out.
     * Expense accounts (debit-normal):  CR to zero out.
     */
    private function buildClosingLines(FiscalPeriod $period): array
    {
        $start = $period->start_date->format('Y-m-d');
        $end = $period->end_date->format('Y-m-d');

        $revenues = $this->accountRepo->totalsByType('revenue', $start, $end);
        $expenses = $this->accountRepo->totalsByType('expense', $start, $end);

        $lines = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($revenues as $rev) {
            $balance = round((float) ($rev['total'] ?? 0), 2);
            if ($balance > 0) {
                $lines[] = [
                    'account_id' => $rev['id'],
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => __('pos.closing_account', ['name' => $rev['account_name']]),
                ];
                $totalRevenue += $balance;
            }
        }

        foreach ($expenses as $exp) {
            $balance = round((float) ($exp['total'] ?? 0), 2);
            if ($balance > 0) {
                $lines[] = [
                    'account_id' => $exp['id'],
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => __('pos.closing_account', ['name' => $exp['account_name']]),
                ];
                $totalExpense += $balance;
            }
        }

        return [
            'lines' => $lines,
            'total_revenue' => round($totalRevenue, 2),
            'total_expense' => round($totalExpense, 2),
            'net_income' => round($totalRevenue - $totalExpense, 2),
        ];
    }
}
