<?php

namespace App\Repositories;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountRepository extends BaseRepository implements AccountRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Account;
    }

    public function allWithTree(): Collection
    {
        return Account::with('children', 'parent')->orderBy('account_code')->get();
    }

    public function findOrFail(int $id): Account
    {
        return Account::findOrFail($id);
    }

    public function findOrFailLocked(int $id): Account
    {
        return Account::lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Account
    {
        return Account::create($data);
    }

    public function update(Account|Model $account, array $data): Account
    {
        $account->update($data);

        return $account->fresh();
    }

    public function delete(Account|Model $account): void
    {
        $account->delete();
    }

    public function hasChildren(Account $account): bool
    {
        return $account->children()->exists();
    }

    public function hasLines(Account $account): bool
    {
        return $account->lines()->exists();
    }

    public function rootsByType(string $type): Collection
    {
        return Account::where('account_type', $type)->with('children')->whereNull('parent_id')->get();
    }

    public function totalsByType(string $type, string $start, string $end): array
    {
        $col = in_array($type, ['asset', 'expense']) ? 'debit' : 'credit';

        return Account::where('account_type', $type)
            ->whereNotNull('parent_id')
            ->withSum([
                'lines as total' => fn ($q) => $q->whereHas(
                    'entry',
                    fn ($q2) => $q2->whereBetween('entry_date', [$start, $end]),
                ),
            ], $col)
            ->get()
            ->toArray();
    }

    public function updateBalance(Account $account, float $debit, float $credit): void
    {
        if (in_array($account->account_type, ['asset', 'expense'])) {
            $account->increment('balance', $debit - $credit);
        } else {
            $account->increment('balance', $credit - $debit);
        }
    }

    public function recalculateBalance(Account $account): void
    {
        $row = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->where('je.is_posted', true)
            ->where('jel.account_id', $account->id)
            ->selectRaw('SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->first();

        $totalDebit = (float) ($row->total_debit ?? 0);
        $totalCredit = (float) ($row->total_credit ?? 0);

        $balance = in_array($account->account_type, ['asset', 'expense'])
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;

        // Use DB::table to bypass the Eloquent immutability observer on JournalEntryLine
        DB::table('accounts')->where('id', $account->id)->update(['balance' => round($balance, 4)]);
        $account->balance = (string) round($balance, 4);
    }

    public function recalculateAllBalances(): int
    {
        // Single aggregation query across all posted lines
        $computed = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.is_posted', true)
            ->selectRaw('jel.account_id, a.account_type, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->groupBy('jel.account_id', 'a.account_type')
            ->get();

        // Zero out all accounts first so accounts with no posted activity land at 0
        $count = Account::count();
        DB::table('accounts')->update(['balance' => 0]);

        foreach ($computed as $row) {
            $balance = in_array($row->account_type, ['asset', 'expense'])
                ? (float) $row->total_debit - (float) $row->total_credit
                : (float) $row->total_credit - (float) $row->total_debit;

            DB::table('accounts')
                ->where('id', $row->account_id)
                ->update(['balance' => round($balance, 4)]);
        }

        return $count;
    }
}
