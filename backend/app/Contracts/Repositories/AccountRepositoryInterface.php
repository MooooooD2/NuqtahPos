<?php

namespace App\Contracts\Repositories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;

interface AccountRepositoryInterface
{
    public function allWithTree(): Collection;

    public function findOrFail(int $id): Account;

    /**
     * Fetch an account row with a write (FOR UPDATE) lock.
     * Must be called inside an active DB::transaction.
     * Prevents concurrent balance updates from reading a stale balance.
     */
    public function findOrFailLocked(int $id): Account;

    public function create(array $data): Account;

    public function update(Account $account, array $data): Account;

    public function delete(Account $account): void;

    public function hasChildren(Account $account): bool;

    public function hasLines(Account $account): bool;

    public function rootsByType(string $type): Collection;

    public function totalsByType(string $type, string $start, string $end): array;

    public function updateBalance(Account $account, float $debit, float $credit): void;

    /**
     * Recompute one account's balance from its posted journal-entry lines.
     * Replaces the stored balance with the authoritative value derived from the ledger.
     */
    public function recalculateBalance(Account $account): void;

    /**
     * Recompute balances for every account from posted journal-entry lines.
     * Accounts with no posted activity are zeroed.
     * Returns the total number of accounts updated.
     */
    public function recalculateAllBalances(): int;
}
