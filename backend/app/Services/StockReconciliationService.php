<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockMovementRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockReconciliationService
{
    public function __construct(
        private StockService $stockService,
        private ProductRepositoryInterface $productRepo,
        private StockMovementRepositoryInterface $movementRepo,
    ) {}

    public function reconcile(array $physicalCounts): array
    {
        return DB::transaction(function () use ($physicalCounts) {
            $discrepancies = [];
            $reconciled = [];

            foreach ($physicalCounts as $item) {
                $product = $this->productRepo->findOrFail($item['product_id']);
                $system = $product->quantity;
                $physical = (int) $item['physical_count'];
                $diff = $physical - $system;

                $discrepancies[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'system_qty' => $system,
                    'physical_qty' => $physical,
                    'difference' => $diff,
                    'status' => $diff === 0 ? 'match' : ($diff > 0 ? 'surplus' : 'shortage'),
                ];

                if ($diff !== 0 && ($item['auto_adjust'] ?? false)) {
                    $this->stockService->adjustStock(
                        $product,
                        $physical,
                        $item['reason'] ?? 'جرد دوري — تعديل تلقائي',
                    );
                    $reconciled[] = $product->id;
                }
            }

            return [
                'discrepancies' => $discrepancies,
                'reconciled_ids' => $reconciled,
                'total_checked' => count($physicalCounts),
                'total_matched' => collect($discrepancies)->where('status', 'match')->count(),
                'total_discrepant' => collect($discrepancies)->where('status', '!=', 'match')->count(),
                'reconciled_at' => now()->toDateTimeString(),
                'performed_by' => Auth::user()?->full_name,
            ];
        }); // end DB::transaction
    }

    public function productAuditTrail(int $productId, string $from, string $to): array
    {
        $movements = $this->movementRepo->byProduct($productId, $from, $to);
        $opening = $this->movementRepo->openingBalance($productId, $from);

        $product = $this->productRepo->findOrFail($productId);

        return [
            'product' => $product->only(['id', 'name', 'quantity']),
            'period' => ['from' => $from, 'to' => $to],
            'opening_balance' => $opening,
            'closing_balance' => $movements->last()?->balance_after ?? $opening,
            'movements' => $movements,
            'total_in' => $movements->whereIn('movement_type', ['add', 'return', 'purchase', 'initial', 'adjustment_add'])->sum('quantity'),
            'total_out' => $movements->whereIn('movement_type', ['sale', 'adjustment_remove'])->sum('quantity'),
        ];
    }
}
