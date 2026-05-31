<?php

namespace App\Services;

use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseService
{
    public function __construct(
        private AccountingService $accountingService,
        private SettingRepositoryInterface $settingRepo,
    ) {}

    public function all(array $filters): LengthAwarePaginator
    {
        return Expense::with('category')
            ->when($filters['category_id'] ?? null, fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when($filters['payment_method'] ?? null, fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->when($filters['date_from'] ?? null, fn ($q) => $q->whereDate('expense_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($q) => $q->whereDate('expense_date', '<=', $filters['date_to']))
            ->latest('expense_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    private function assertOpenPeriod(string $date): void
    {
        $period = FiscalPeriod::forDate($date);
        if ($period && $period->isClosed()) {
            throw new DomainException(__('pos.expense_period_closed'));
        }
    }

    public function create(array $data): Expense
    {
        $this->assertOpenPeriod($data['expense_date']);

        return DB::transaction(function () use ($data) {
            $number = SequenceService::next('expense', 'EXP');

            $expense = Expense::create([
                'expense_number' => $number,
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'amount' => round((float) $data['amount'], 2),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'expense_date' => $data['expense_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()?->full_name ?? '',
            ]);

            // FIX: create double-entry journal entry for this expense
            // DR Expense account, CR Cash/Bank account
            $this->postExpenseEntry($expense);

            Log::channel('audit')->info('expense.created', [
                'expense_number' => $number,
                'title' => $expense->title,
                'amount' => $expense->amount,
                'payment_method' => $expense->payment_method,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $expense->load('category');
        });
    }

    public function update(Expense $expense, array $data): Expense
    {
        $targetDate = $data['expense_date'] ?? $expense->expense_date->format('Y-m-d');
        $this->assertOpenPeriod($targetDate);

        return DB::transaction(function () use ($expense, $data) {
            // FIX: reverse any existing posted journal entry for this expense before updating,
            //      then post a fresh entry reflecting the new amounts/method.
            $existingEntry = $this->findExpenseEntry($expense);
            if ($existingEntry) {
                $this->accountingService->reverseEntry(
                    $existingEntry,
                    __('pos.expense_update_reversal', ['number' => $expense->expense_number]),
                );
            }

            $expense->update([
                'category_id' => $data['category_id'] ?? $expense->category_id,
                'title' => $data['title'] ?? $expense->title,
                'amount' => isset($data['amount']) ? round((float) $data['amount'], 2) : $expense->amount,
                'payment_method' => $data['payment_method'] ?? $expense->payment_method,
                'reference' => $data['reference'] ?? $expense->reference,
                'expense_date' => $data['expense_date'] ?? $expense->expense_date,
                'notes' => $data['notes'] ?? $expense->notes,
            ]);

            // Post a new entry reflecting the updated expense
            $this->postExpenseEntry($expense->fresh());

            Log::channel('audit')->info('expense.updated', [
                'expense_number' => $expense->expense_number,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $expense->fresh('category');
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            // FIX: reverse the posted journal entry so account balances are restored
            $existingEntry = $this->findExpenseEntry($expense);
            if ($existingEntry) {
                $this->accountingService->reverseEntry(
                    $existingEntry,
                    __('pos.expense_deletion_reversal', ['number' => $expense->expense_number]),
                );
            }

            Log::channel('audit')->info('expense.deleted', [
                'expense_number' => $expense->expense_number,
                'amount' => $expense->amount,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $expense->delete();
        });
    }

    public function categories(): Collection
    {
        return ExpenseCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function summary(string $dateFrom, string $dateTo): array
    {
        $rows = Expense::whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('category_id, SUM(amount) as total, COUNT(*) as count')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get();

        $grandTotal = $rows->sum('total');

        return [
            'by_category' => $rows->map(fn ($r) => [
                'category' => $r->category?->name ?? __('pos.uncategorized'),
                'total' => round((float) $r->total, 2),
                'count' => (int) $r->count,
            ]),
            'grand_total' => round((float) $grandTotal, 2),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Find the latest posted, unreversed journal entry for this expense.
     * Used to locate the entry to reverse on update/delete.
     */
    private function findExpenseEntry(Expense $expense): ?JournalEntry
    {
        return JournalEntry::where('reference_type', 'expense')
            ->where('reference_id', $expense->id)
            ->where('is_posted', true)
            ->whereDoesntHave('reversals')
            ->latest()
            ->first();
    }

    /**
     * Resolve the contra (credit) account code for the given payment method.
     * cash → cash_account_code setting
     * bank_transfer / bank → bank_account_code setting
     * Falls back to cash_account_code for unknown methods.
     */
    private function contraAccountCode(string $paymentMethod): ?string
    {
        $key = match (strtolower($paymentMethod)) {
            'bank_transfer', 'bank' => 'bank_account_code',
            default => 'cash_account_code',
        };

        return $this->settingRepo->get($key) ?: null;
    }

    /**
     * Build and post the double-entry journal record for an expense.
     *
     * DR  Expense account  (expense_account_code setting)
     * CR  Cash/Bank        (cash_account_code / bank_account_code setting)
     *
     * Graceful degradation: if either account code is unconfigured or the
     * account row does not exist, logs a warning and skips entry creation so
     * the POS remains fully operational even before the chart of accounts is
     * configured.
     */
    private function postExpenseEntry(Expense $expense): void
    {
        $expenseCode = $this->settingRepo->get('expense_account_code') ?: null;
        $contraCode = $this->contraAccountCode((string) $expense->payment_method);

        if (! $expenseCode || ! $contraCode) {
            Log::warning('expense.journal_skipped: account codes not configured', [
                'expense_number' => $expense->expense_number,
                'expense_account_code' => $expenseCode,
                'contra_account_code' => $contraCode,
            ]);

            return;
        }

        $expenseAccount = Account::where('account_code', $expenseCode)->first();
        $contraAccount = Account::where('account_code', $contraCode)->first();

        if (! $expenseAccount || ! $contraAccount) {
            Log::warning('expense.journal_skipped: account not found', [
                'expense_number' => $expense->expense_number,
                'expense_account_code' => $expenseCode,
                'contra_account_code' => $contraCode,
                'expense_account_found' => (bool) $expenseAccount,
                'contra_account_found' => (bool) $contraAccount,
            ]);

            return;
        }

        $amount = round((float) $expense->amount, 2);
        $desc = __('pos.expense_journal_description', [
            'title' => $expense->title,
            'number' => $expense->expense_number,
        ]);

        $entry = $this->accountingService->createJournalEntry([
            'entry_date' => $expense->expense_date->format('Y-m-d'),
            'description' => $desc,
            'reference_type' => 'expense',
            'reference_id' => $expense->id,
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $desc,
                ],
                [
                    'account_id' => $contraAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $desc,
                ],
            ],
        ]);

        $this->accountingService->postEntry($entry);
    }
}
