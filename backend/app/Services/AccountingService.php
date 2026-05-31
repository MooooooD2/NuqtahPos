<?php

namespace App\Services;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\JournalEntryRepositoryInterface;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
// JournalEntryLine intentionally removed — was imported but never referenced directly here
use DomainException;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function __construct(
        private AccountRepositoryInterface $accountRepo,
        private JournalEntryRepositoryInterface $journalRepo,
    ) {}

    public function createJournalEntry(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            // Reject writes into a closed fiscal period
            $period = FiscalPeriod::forDate($data['entry_date']);
            if ($period && $period->isClosed()) {
                throw new DomainException(__('pos.period_is_closed', ['name' => $period->name]));
            }

            $totalDebit = collect($data['lines'])->sum('debit');
            $totalCredit = collect($data['lines'])->sum('credit');

            // FIX: standardize tolerance to 0.01 (1 cent) — previously used round() !== round()
            //      which silently accepts imbalances up to 0.004, differing from the Request
            //      validator (> 0.01) and the old controller check (> 0.001).
            if (abs(round($totalDebit, 2) - round($totalCredit, 2)) > 0.01) {
                throw new Exception(__('pos.journal_unbalanced'));
            }

            $entry = $this->journalRepo->create([
                'entry_number' => SequenceService::next('journal'),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['lines'] as $line) {
                $this->journalRepo->createLine([
                    'entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);

                // SECURITY FIX: use a write lock so concurrent transactions cannot
                // both read the same stale balance and double-apply the increment.
                // Safe here because we are already inside DB::transaction().
                $account = $this->accountRepo->findOrFailLocked($line['account_id']);
                $this->accountRepo->updateBalance($account, $line['debit'] ?? 0, $line['credit'] ?? 0);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Lock a journal entry so it can never be edited or deleted.
     *
     * FIX: wrapped in DB::transaction + lockForUpdate so two concurrent POST /post
     *      requests cannot both pass the is_posted check and double-post the entry.
     * FIX: use ->format('Y-m-d') instead of ->toDateString() — the latter is Carbon-only
     *      but the IDE infers the property as the generic PHP date type.
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            // Re-fetch with a write lock — prevents concurrent posts on the same entry
            /** @var JournalEntry $locked */
            $locked = JournalEntry::lockForUpdate()->findOrFail($entry->id);

            if ($locked->is_posted) {
                throw new DomainException(__('pos.journal_entry_already_posted'));
            }

            // Reject if entry_date falls in a closed period
            $period = FiscalPeriod::forDate($locked->entry_date->format('Y-m-d'));
            if ($period?->isClosed()) {
                throw new DomainException(__('pos.period_is_closed', ['name' => $period->name]));
            }

            // Bypass the immutability guard — posting IS the one allowed state change
            JournalEntry::withoutEvents(function () use ($locked, $period) {
                $locked->update([
                    'is_posted' => true,
                    'posted_at' => now(),
                    'posted_by' => Auth::id(),
                    'fiscal_period_id' => $period?->id,
                ]);
            });

            return $locked->fresh();
        });
    }

    /**
     * Reverse a posted entry by creating a new entry with negated amounts.
     * The original entry remains untouched.
     */
    public function reverseEntry(JournalEntry $entry, string $description): JournalEntry
    {
        if (! $entry->is_posted) {
            throw new DomainException(__('pos.journal_entry_not_posted'));
        }

        if ($entry->reversals()->exists()) {
            throw new DomainException(__('pos.journal_entry_already_reversed'));
        }

        return DB::transaction(function () use ($entry, $description) {
            $reversal = $this->journalRepo->create([
                'entry_number' => SequenceService::next('journal'),
                'entry_date' => now()->toDateString(),
                'description' => $description,
                'reference_type' => 'reversal',
                'reference_id' => $entry->id,
                'created_by' => Auth::id(),
                'reversal_of' => $entry->id,
            ]);

            foreach ($entry->lines as $line) {
                // Swap debit ↔ credit to negate the effect
                $this->journalRepo->createLine([
                    'entry_id' => $reversal->id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => __('pos.reversal_of_entry', ['number' => $entry->entry_number]),
                ]);

                // SECURITY FIX: write-lock before balance update (same as createJournalEntry)
                $account = $this->accountRepo->findOrFailLocked($line->account_id);
                // Reversal: apply the negated amounts
                $this->accountRepo->updateBalance($account, $line->credit, $line->debit);
            }

            // Auto-post the reversal so it too is immutable
            $this->postEntry($reversal);

            return $reversal->load('lines.account');
        });
    }

    public function incomeStatement(string $startDate, string $endDate): array
    {
        $revenues = $this->accountRepo->totalsByType('revenue', $startDate, $endDate);
        $expenses = $this->accountRepo->totalsByType('expense', $startDate, $endDate);

        $totalRevenue = collect($revenues)->sum('total');
        $totalExpense = collect($expenses)->sum('total');
        $netIncome = $totalRevenue - $totalExpense;

        return compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netIncome');
    }

    public function balanceSheet(): array
    {
        $assets = $this->accountRepo->rootsByType('asset');
        $liabilities = $this->accountRepo->rootsByType('liability');
        $equity = $this->accountRepo->rootsByType('equity');

        return compact('assets', 'liabilities', 'equity');
    }

    /**
     * Recompute every account's balance from scratch by re-aggregating all posted
     * journal-entry lines.  Wraps the bulk update in a transaction so balances are
     * never left in a partially-recalculated state.
     *
     * @return int Number of account rows processed.
     */
    public function recalculateAllBalances(): int
    {
        return DB::transaction(fn () => $this->accountRepo->recalculateAllBalances());
    }
}
