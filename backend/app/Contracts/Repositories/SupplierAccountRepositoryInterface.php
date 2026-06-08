<?php

namespace App\Contracts\Repositories;

use App\Models\SupplierAccount;
use Illuminate\Database\Eloquent\Collection;

interface SupplierAccountRepositoryInterface
{
    public function latestEntry(int $supplierId): ?SupplierAccount;

    public function create(array $data): SupplierAccount;

    public function totalsBySupplier(int $supplierId, ?string $dateFrom = null, ?string $dateTo = null): object;

    public function entriesBySupplier(int $supplierId, ?string $dateFrom = null, ?string $dateTo = null): Collection;
}
