<?php

namespace App\Contracts\Repositories;

use App\Models\SupplierAccount;
use Illuminate\Database\Eloquent\Collection;

interface SupplierAccountRepositoryInterface
{
    public function latestEntry(int $supplierId): ?SupplierAccount;

    public function create(array $data): SupplierAccount;

    public function totalsBySupplier(int $supplierId): object;

    public function entriesBySupplier(int $supplierId): Collection;
}
