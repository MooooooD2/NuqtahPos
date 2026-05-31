<?php

namespace App\Contracts\Repositories;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

interface StockMovementRepositoryInterface
{
    public function create(array $data): StockMovement;

    public function recent(int $limit): Collection;

    public function byProduct(int $productId, string $from, string $to): Collection;

    public function openingBalance(int $productId, string $before): int;
}
