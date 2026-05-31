<?php

namespace App\Contracts\Repositories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SupplierRepositoryInterface
{
    public function findOrFail(int $id): Supplier;

    public function all(array $filters = [], bool $fetchAll = false): Collection|LengthAwarePaginator;

    public function create(array $data): Supplier;

    public function update(Supplier $supplier, array $data): Supplier;

    public function delete(Supplier $supplier): void;

    public function hasActiveOrders(Supplier $supplier): bool;

    public function count(): int;
}
