<?php

namespace App\Services;

use App\Models\InventoryCostLayer;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryValuationService
{
    public const METHODS = [
        'weighted_average' => ['ar' => 'المتوسط المرجح', 'en' => 'Weighted Average'],
        'fifo' => ['ar' => 'أول داخل أول خارج (FIFO)', 'en' => 'First In First Out (FIFO)'],
        'lifo' => ['ar' => 'آخر داخل أول خارج (LIFO)', 'en' => 'Last In First Out (LIFO)'],
    ];

    public function getMethod(): string
    {
        return Setting::get('inventory_valuation_method', 'weighted_average');
    }

    /**
     * Create a cost layer when stock is added (purchase/manual).
     * Only creates layers when method is FIFO or LIFO.
     */
    public function createLayer(
        Product $product,
        int $quantity,
        float $unitCost,
        string $referenceType,
        ?int $referenceId,
        ?int $warehouseId = null,
    ): void {
        if (! in_array($this->getMethod(), ['fifo', 'lifo'])) {
            return;
        }

        if ($unitCost <= 0 || $quantity <= 0) {
            return;
        }

        InventoryCostLayer::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'original_qty' => $quantity,
            'remaining_qty' => $quantity,
            'unit_cost' => round($unitCost, 4),
        ]);
    }

    /**
     * Deduct from cost layers on sale and return the weighted average unit cost consumed.
     * Must be called inside a DB transaction with rows already locked.
     *
     * FIX: exhausted layers are deleted after deduction to keep the table lean and
     *      prevent the withStock() scope from scanning zero-qty phantom rows.
     */
    public function deductLayers(
        Product $product,
        int $quantity,
        ?int $warehouseId = null,
    ): float {
        $method = $this->getMethod();

        if ($method === 'weighted_average') {
            return $product->avg_cost > 0 ? (float) $product->avg_cost : (float) ($product->cost_price ?? 0);
        }

        $query = InventoryCostLayer::where('product_id', $product->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->lockForUpdate();

        $layers = $method === 'fifo'
            ? $query->fifo()->get()
            : $query->lifo()->get();

        $remaining = $quantity;
        $totalCost = 0.0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($layer->remaining_qty, $remaining);
            $totalCost += $take * (float) $layer->unit_cost;

            // FIX: compute new remaining BEFORE decrement (decrement() updates DB only,
            //      not the PHP model attribute — same stale-read trap as Product::quantity)
            $newRemaining = $layer->remaining_qty - $take;
            $layer->decrement('remaining_qty', $take);

            // FIX: delete exhausted layers immediately — prevents zero-qty ghost rows
            //      accumulating over time and slowing the withStock() index scan
            if ($newRemaining <= 0) {
                $layer->delete();
            }

            $remaining -= $take;
        }

        // Fallback for quantity not covered by existing layers (data inconsistency safeguard)
        if ($remaining > 0) {
            $fallback = $product->avg_cost > 0 ? (float) $product->avg_cost : (float) ($product->cost_price ?? 0);
            $totalCost += $remaining * $fallback;
        }

        return $quantity > 0 ? round($totalCost / $quantity, 4) : 0.0;
    }

    /**
     * Return current inventory valuation report comparing all three methods.
     *
     * FIX: eliminated N+1 queries (was: 2N layerValue DB calls + N COUNT calls per product).
     *      Now always exactly 3 queries regardless of catalogue size:
     *        1. Products + warehouseStock (eager-loaded)
     *        2. All active layers in FIFO order for all products  (single IN query)
     *        3. Layer count per product                           (single GROUP BY query)
     */
    public function valuationReport(?int $warehouseId = null): array
    {
        $products = Product::withoutTrashed()
            ->with('warehouseStock')
            ->orderBy('name')
            ->get();

        $productIds = $products->pluck('id');

        // ── Query 2: all active layers in FIFO order, grouped by product in PHP ──
        /** @var Collection<int, Collection<int, InventoryCostLayer>> $allLayersFifo */
        $allLayersFifo = InventoryCostLayer::whereIn('product_id', $productIds)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->fifo()   // withStock() + ORDER BY created_at ASC, id ASC
            ->get()
            ->groupBy('product_id');

        // ── Query 3: layer count per product — one GROUP BY instead of N COUNT calls ──
        $layerCounts = InventoryCostLayer::whereIn('product_id', $productIds)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->withStock()
            ->selectRaw('product_id, COUNT(*) as cnt')
            ->groupBy('product_id')
            ->pluck('cnt', 'product_id');

        $rows = [];

        foreach ($products as $product) {
            $qty = $warehouseId
                ? (int) ($product->warehouseStock->where('warehouse_id', $warehouseId)->first()?->quantity ?? 0)
                : (int) $product->quantity;

            $wacUnit = $product->avg_cost > 0 ? (float) $product->avg_cost : (float) ($product->cost_price ?? 0);

            // FIFO: layers in forward order; LIFO: reverse in-memory — zero extra DB queries
            $layers = $allLayersFifo->get($product->id) ?? collect();
            $fifoValue = $this->layerValueFromCollection($layers, $qty, $wacUnit);
            $lifoValue = $this->layerValueFromCollection($layers->reverse(), $qty, $wacUnit);

            $rows[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'wac_unit' => round($wacUnit, 4),
                'wac_value' => round($qty * $wacUnit, 2),
                'fifo_value' => round($fifoValue, 2),
                'lifo_value' => round($lifoValue, 2),
                'layers_count' => (int) ($layerCounts->get($product->id) ?? 0),
            ];
        }

        $totals = [
            'wac_total' => round(collect($rows)->sum('wac_value'), 2),
            'fifo_total' => round(collect($rows)->sum('fifo_value'), 2),
            'lifo_total' => round(collect($rows)->sum('lifo_value'), 2),
        ];

        return [
            'method' => $this->getMethod(),
            'methods' => self::METHODS,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Calculate the value of on-hand stock from an already-loaded (ordered) layer collection.
     * Pure PHP — no DB queries. FIFO → pass layers as-is; LIFO → pass layers->reverse().
     */
    private function layerValueFromCollection(Collection $layers, int $totalQty, float $fallbackUnit): float
    {
        if ($totalQty <= 0) {
            return 0.0;
        }

        $remaining = $totalQty;
        $total = 0.0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($layer->remaining_qty, $remaining);
            $total += $take * (float) $layer->unit_cost;
            $remaining -= $take;
        }

        // Quantity not covered by layers — fall back to weighted average cost
        if ($remaining > 0) {
            $total += $remaining * $fallbackUnit;
        }

        return $total;
    }
}
