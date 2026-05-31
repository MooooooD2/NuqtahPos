<?php

namespace App\Contracts\Repositories;

use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Collection;

interface SalesReturnRepositoryInterface
{
    public function create(array $data): SalesReturn;

    public function createItem(array $data): void;

    public function returnedQuantities(int $invoiceId): Collection;

    public function sumByDateRange(string $start, string $end, ?string $status = 'completed'): object;

    public function topReturnedProducts(string $start, string $end, int $limit = 10): Collection;

    public function paginate(string $start, string $end, ?string $status, int $perPage = 50): object;
}
