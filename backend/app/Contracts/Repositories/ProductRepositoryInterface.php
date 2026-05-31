<?php

namespace App\Contracts\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findOrFail(int $id): Product;

    public function findByBarcode(string $barcode): ?Product;

    public function search(string $query, bool $exact = false): mixed;

    public function all(array $filters = [], bool $fetchAll = false): Collection|LengthAwarePaginator;

    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): void;

    public function lockForUpdate(array $ids): Collection;

    public function lowStock(): Collection;

    public function outOfStock(): Collection;

    public function stats(): object;
}
