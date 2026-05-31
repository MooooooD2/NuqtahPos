<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface DashboardRepositoryInterface
{
    public function todaySalesStats(string $date): object;

    public function yesterdaySalesTotal(string $date): object;

    public function topProducts(string $from, string $to, int $limit): SupportCollection;

    public function recentInvoices(int $limit): Collection;

    public function recentMovements(int $limit): Collection;

    public function productStats(): object;

    public function totalRevenue(): float;

    public function totalSuppliers(): int;
}
