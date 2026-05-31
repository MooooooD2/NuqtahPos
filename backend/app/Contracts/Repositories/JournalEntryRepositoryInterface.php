<?php

namespace App\Contracts\Repositories;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface JournalEntryRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function create(array $data): JournalEntry;

    public function createLine(array $data): JournalEntryLine;

    public function linesByAccount(int $accountId, string $start, string $end): Collection;
}
