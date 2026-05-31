<?php

namespace App\Contracts\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    public function findByNumber(string $number): ?Invoice;

    public function create(array $data): Invoice;

    public function createItem(array $data): void;

    public function todayStats(string $date): object;

    public function yesterdayTotal(string $date): object;

    public function recent(int $limit): Collection;

    public function totalRevenue(): float;

    public function salesReport(string $start, string $end, array $filters): array;

    public function returnedQtyByProduct(int $invoiceId): Collection;
}
