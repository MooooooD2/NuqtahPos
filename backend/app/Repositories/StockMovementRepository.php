<?php

namespace App\Repositories;

use App\Contracts\Repositories\StockMovementRepositoryInterface;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

class StockMovementRepository extends BaseRepository implements StockMovementRepositoryInterface
{
    public function __construct()
    {
        $this->model = new StockMovement;
    }

    public function create(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    public function recent(int $limit): Collection
    {
        return StockMovement::latest()->limit($limit)->get();
    }

    public function byProduct(int $productId, string $from, string $to): Collection
    {
        return StockMovement::where('product_id', $productId)
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->orderBy('created_at')
            ->get();
    }

    public function openingBalance(int $productId, string $before): int
    {
        return (int) (StockMovement::where('product_id', $productId)
            ->where('created_at', '<', $before)
            ->latest()
            ->value('balance_after') ?? 0);
    }
}
