<?php

namespace App\Repositories;

use App\Contracts\Repositories\JournalEntryRepositoryInterface;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class JournalEntryRepository extends BaseRepository implements JournalEntryRepositoryInterface
{
    public function __construct()
    {
        $this->model = new JournalEntry;
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = JournalEntry::with('lines.account', 'creator')->orderByDesc('entry_date');

        if (! empty($filters['start_date'])) {
            $query->where('entry_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('entry_date', '<=', $filters['end_date']);
        }

        return $query->paginate(20);
    }

    public function create(array $data): JournalEntry
    {
        return JournalEntry::create($data);
    }

    public function createLine(array $data): JournalEntryLine
    {
        return JournalEntryLine::create($data);
    }

    public function linesByAccount(int $accountId, string $start, string $end): Collection
    {
        return JournalEntryLine::where('account_id', $accountId)
            ->with('entry')
            ->whereHas('entry', fn ($q) => $q->whereBetween('entry_date', [$start, $end]))
            ->get();
    }
}
