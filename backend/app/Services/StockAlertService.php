<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centralised stock-health service.
 *
 * Previously the StockAlertCommand queried models directly and could not be
 * called from API controllers.  This service extracts every alert type into
 * reusable, testable methods so both the scheduled command and the REST API
 * can call the same logic.
 */
class StockAlertService
{
    public function __construct(private StockService $stockService) {}

    // ── Alert queries ────────────────────────────────────────────────────────

    /**
     * Products at or below their configured min_stock (but not zero).
     * Pass a warehouse ID to scope to a single warehouse's quantities.
     */
    public function getLowStock(?int $warehouseId = null): Collection
    {
        if ($warehouseId) {
            return WarehouseStock::with([
                'product:id,name,min_stock,reorder_point,supplier,category',
            ])
                ->where('warehouse_id', $warehouseId)
                ->whereHas('product', fn ($q) => $q->where('min_stock', '>', 0))
                ->whereRaw('warehouse_stock.quantity > 0')
                ->whereRaw('warehouse_stock.quantity <= (SELECT min_stock FROM products WHERE id = warehouse_stock.product_id)')
                ->get()
                ->map(fn ($ws) => [
                    'product_id' => $ws->product_id,
                    'product_name' => $ws->product?->name,
                    'quantity' => $ws->quantity,
                    'min_stock' => $ws->product?->min_stock,
                    'reserved_qty' => $ws->reserved_qty,
                    'available_qty' => $ws->available_qty,
                    'reorder_point' => $ws->product?->reorder_point,
                    'supplier' => $ws->product?->supplier,
                    'category' => $ws->product?->category,
                    'severity' => $this->severityLevel($ws->quantity, $ws->product?->min_stock),
                    'warehouse_id' => $warehouseId,
                ]);
        }

        return Product::where('quantity', '>', 0)
            ->where('min_stock', '>', 0)
            ->whereColumn('quantity', '<=', 'min_stock')
            ->orderBy('quantity')
            ->get(['id', 'name', 'quantity', 'min_stock', 'reorder_point', 'supplier', 'category'])
            ->map(fn ($p) => [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'quantity' => $p->quantity,
                'min_stock' => $p->min_stock,
                'reorder_point' => $p->reorder_point,
                'supplier' => $p->supplier,
                'category' => $p->category,
                'severity' => $this->severityLevel($p->quantity, $p->min_stock),
                'warehouse_id' => null,
            ]);
    }

    /**
     * Products with zero (or negative) on-hand quantity.
     * When a warehouse ID is given, scopes to that warehouse's WarehouseStock rows.
     */
    public function getOutOfStock(?int $warehouseId = null): Collection
    {
        if ($warehouseId) {
            return WarehouseStock::with([
                'product:id,name,min_stock,reorder_point,supplier,category',
            ])
                ->where('warehouse_id', $warehouseId)
                ->where('quantity', '<=', 0)
                ->whereHas('product')
                ->get()
                ->map(fn ($ws) => [
                    'product_id' => $ws->product_id,
                    'product_name' => $ws->product?->name,
                    'quantity' => 0,
                    'min_stock' => $ws->product?->min_stock,
                    'reorder_point' => $ws->product?->reorder_point,
                    'supplier' => $ws->product?->supplier,
                    'category' => $ws->product?->category,
                    'severity' => 'critical',
                    'warehouse_id' => $warehouseId,
                ]);
        }

        return Product::where('quantity', '<=', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'min_stock', 'reorder_point', 'supplier', 'category'])
            ->map(fn ($p) => [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'quantity' => 0,
                'min_stock' => $p->min_stock,
                'reorder_point' => $p->reorder_point,
                'supplier' => $p->supplier,
                'category' => $p->category,
                'severity' => 'critical',
                'warehouse_id' => null,
            ]);
    }

    /**
     * Active batches expiring within $days calendar days (FEFO order).
     */
    public function getNearExpiryBatches(int $days = 30, ?int $warehouseId = null): Collection
    {
        $today = now()->toDateString();
        $cutoff = now()->addDays($days)->toDateString();

        return ProductBatch::with([
            'product:id,name',
            'warehouse:id,name',
        ])
            ->active()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $cutoff])
            ->where('remaining_qty', '>', 0)
            ->fefo()
            ->get()
            ->map(fn ($b) => [
                'batch_id' => $b->id,
                'batch_number' => $b->batch_number,
                'product_id' => $b->product_id,
                'product_name' => $b->product?->name,
                'warehouse_id' => $b->warehouse_id,
                'warehouse_name' => $b->warehouse?->name,
                'expiry_date' => $b->expiry_date?->toDateString(),
                'days_to_expiry' => (int) now()->diffInDays($b->expiry_date, false),
                'remaining_qty' => $b->remaining_qty,
                'severity' => now()->diffInDays($b->expiry_date) <= 7 ? 'critical' : 'warning',
            ]);
    }

    /**
     * Active batches whose expiry_date is in the past but still hold stock.
     * These should be written off.
     *
     * Note: scopeActive() explicitly excludes expired dates — we bypass it here
     * to find the exact rows that slipped through without being written off.
     */
    public function getExpiredBatches(?int $warehouseId = null): Collection
    {
        // FIX: include both 'active' and 'expired' statuses — after markExpired() runs,
        // batches transition from 'active' to 'expired' but still hold stock.
        // Querying only 'active' would miss them entirely.
        return ProductBatch::with([
            'product:id,name',
            'warehouse:id,name',
        ])
            ->whereIn('status', ['active', 'expired'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->where('remaining_qty', '>', 0)
            ->orderBy('expiry_date')
            ->get()
            ->map(fn ($b) => [
                'batch_id' => $b->id,
                'batch_number' => $b->batch_number,
                'product_id' => $b->product_id,
                'product_name' => $b->product?->name,
                'warehouse_id' => $b->warehouse_id,
                'warehouse_name' => $b->warehouse?->name,
                'expiry_date' => $b->expiry_date?->toDateString(),
                'expired_days_ago' => (int) now()->diffInDays($b->expiry_date),
                'remaining_qty' => $b->remaining_qty,
                'severity' => 'critical',
            ]);
    }

    /**
     * Products at or below their reorder_point, with a smart suggested order quantity
     * computed from recent consumption velocity.
     *
     * FIX: uses the correct column name `movement_type` (not `type`).
     *
     * @param int $lookbackDays Days of history used to estimate avg daily consumption.
     * @param int $leadTimeDays Assumed replenishment lead time for suggested-qty calculation.
     */
    public function getReorderSuggestions(int $lookbackDays = 7, int $leadTimeDays = 14): array
    {
        $since = now()->subDays($lookbackDays)->toDateTimeString();

        // Single aggregate query — one row per product
        $consumption = StockMovement::where('movement_type', 'sale')
            ->where('created_at', '>=', $since)
            ->selectRaw('product_id, SUM(quantity) as total_sold')
            ->groupBy('product_id')
            ->pluck('total_sold', 'product_id');

        return Product::where('reorder_point', '>', 0)
            ->whereColumn('quantity', '<=', 'reorder_point')
            ->orderBy('quantity')
            ->get(['id', 'name', 'quantity', 'reorder_point', 'reorder_qty', 'supplier', 'avg_cost', 'cost_price', 'category'])
            ->map(function (Product $p) use ($consumption, $lookbackDays, $leadTimeDays) {
                $totalSold = (float) ($consumption[$p->id] ?? 0);
                $avgVelocity = $lookbackDays > 0 ? $totalSold / $lookbackDays : 0;

                $suggestedQty = max(
                    (int) ($p->reorder_qty ?? 0),
                    (int) ceil($avgVelocity * $leadTimeDays),
                );
                $suggestedQty = max(1, $suggestedQty);

                $daysOfStock = $avgVelocity > 0
                    ? round($p->quantity / $avgVelocity, 1)
                    : null;

                $unitCost = (float) ($p->avg_cost > 0 ? $p->avg_cost : ($p->cost_price ?? 0));

                return [
                    'product_id' => $p->id,
                    'product_name' => $p->name,
                    'category' => $p->category,
                    'quantity' => $p->quantity,
                    'reorder_point' => $p->reorder_point,
                    'suggested_order_qty' => $suggestedQty,
                    'avg_daily_velocity' => round($avgVelocity, 2),
                    'days_of_stock_left' => $daysOfStock,
                    'estimated_cost' => round($suggestedQty * $unitCost, 2),
                    'supplier' => $p->supplier,
                    'urgency' => ($daysOfStock !== null && $daysOfStock <= 3) ? 'urgent' : 'normal',
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Combined stock-health summary for the dashboard or a single API call.
     */
    public function getStockHealth(?int $warehouseId = null): array
    {
        $lowStock = $this->getLowStock($warehouseId);
        $outOfStock = $this->getOutOfStock($warehouseId);
        $nearExpiry = $this->getNearExpiryBatches(30, $warehouseId);
        $expired = $this->getExpiredBatches($warehouseId);
        $reorders = $this->getReorderSuggestions();

        $criticalCount =
            $lowStock->where('severity', 'critical')->count()
            + $outOfStock->count()
            + $nearExpiry->where('severity', 'critical')->count()
            + $expired->count();

        return [
            'summary' => [
                'low_stock_count' => $lowStock->count(),
                'out_of_stock_count' => $outOfStock->count(),
                'near_expiry_count' => $nearExpiry->count(),
                'expired_count' => $expired->count(),
                'reorder_count' => count($reorders),
                'critical_count' => $criticalCount,
            ],
            'low_stock' => $lowStock->values(),
            'out_of_stock' => $outOfStock->values(),
            'near_expiry' => $nearExpiry->values(),
            'expired' => $expired->values(),
            'reorders' => collect($reorders),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ── Write-off ────────────────────────────────────────────────────────────

    /**
     * Write off all expired batches: deducts their remaining quantity from product
     * stock through StockService (so movement logging, cost layers, and warehouse
     * sync are all handled correctly), then marks the batch as 'exhausted'.
     *
     * Failures on individual batches are logged and skipped — other batches still proceed.
     *
     * @return array List of batches that were successfully written off.
     */
    public function writeOffExpiredBatches(?int $warehouseId = null, string $reason = ''): array
    {
        // FIX: include 'expired' status — after markExpired() runs, batches transition
        // from 'active' → 'expired' but still hold physical stock until written off.
        $expired = ProductBatch::with('product')
            ->whereIn('status', ['active', 'expired'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->where('remaining_qty', '>', 0)
            ->get();

        $writtenOff = [];

        foreach ($expired as $batch) {
            /** @var ProductBatch $batch */
            if (! $batch->product_id) {
                continue;
            }

            try {
                DB::transaction(function () use ($batch, $reason, &$writtenOff) {
                    // FIX: fresh-lock the product inside the transaction so that when
                    // multiple expired batches share the same product we always see the
                    // up-to-date quantity, not the pre-loop stale copy.
                    $fresh = Product::lockForUpdate()->find($batch->product_id);
                    if (! $fresh) {
                        return;
                    }

                    // Cap at actual available quantity (defensive against data inconsistency)
                    $qty = min($batch->remaining_qty, $fresh->quantity);

                    if ($qty > 0) {
                        // FIX: use deductLockedStock since $fresh is already write-locked above;
                        // avoids a second lockForUpdate and returns the correct unit cost.
                        $this->stockService->deductLockedStock(
                            $fresh,
                            $qty,
                            'write_off_expired',
                            $reason ?: __('pos.write_off_expired_reason', ['batch' => $batch->batch_number]),
                            $batch->id,
                            'batch_write_off',
                            $batch->warehouse_id,
                            $batch->id,
                        );
                    }

                    $batch->update(['status' => 'exhausted', 'remaining_qty' => 0]);

                    $writtenOff[] = [
                        'batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'product_id' => $fresh->id,
                        'product_name' => $fresh->name,
                        'quantity' => $qty,
                        'expiry_date' => $batch->expiry_date?->toDateString(),
                    ];
                });
            } catch (Throwable $e) {
                Log::warning('stock_alert.write_off_failed', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $writtenOff;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Map quantity + min_stock to a severity label.
     * critical → quantity ≤ ½ × min_stock; warning → quantity ≤ min_stock.
     */
    private function severityLevel(int $quantity, ?int $minStock): string
    {
        if ($quantity === 0 || ! $minStock || $minStock <= 0) {
            return 'critical';
        }
        if ($quantity <= intdiv($minStock, 2)) {
            return 'critical';
        }

        return 'warning';
    }
}
