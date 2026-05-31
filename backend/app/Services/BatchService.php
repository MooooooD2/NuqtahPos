<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Dedicated service for ProductBatch lifecycle management.
 *
 * Previously batch creation was handled inside WarehouseService but bypassed
 * StockService entirely — no movement was logged, no cost layer was created, and
 * avg_cost was never updated.  This service fixes all three issues.
 *
 * WarehouseService still delegates to this class via a compatibility shim so
 * existing callers are unaffected.
 */
class BatchService
{
    public function __construct(private StockService $stockService) {}

    // ── Queries ──────────────────────────────────────────────────────────────

    /**
     * All batches for a product, FEFO-ordered, with warehouse eager-loaded.
     */
    public function allForProduct(int $productId, ?int $warehouseId = null): Collection
    {
        return ProductBatch::with('warehouse:id,name,code')
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->get();
    }

    /**
     * All active batches with stock, ready for sale (FEFO order).
     */
    public function availableForProduct(int $productId, ?int $warehouseId = null): Collection
    {
        return ProductBatch::with('warehouse:id,name')
            ->active()
            ->where('product_id', $productId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('remaining_qty', '>', 0)
            ->fefo()
            ->get();
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Create a new batch and add its quantity to stock.
     *
     * FIX over WarehouseService::createBatch():
     *   - Uses StockService::addStock() so a StockMovement is created, the cost
     *     layer is registered for FIFO/LIFO, and avg_cost is updated.
     *   - Previously directly incremented warehouse_stock and products tables
     *     without any of the above.
     */
    public function create(array $data): ProductBatch
    {
        return DB::transaction(function () use ($data) {
            /** @var Product $product */
            $product = Product::findOrFail($data['product_id']);

            $batch = ProductBatch::create([
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch_number' => $data['batch_number'],
                'lot_number' => $data['lot_number'] ?? null,
                'manufacture_date' => $data['manufacture_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'original_qty' => $data['original_qty'],
                'remaining_qty' => $data['original_qty'],
                'cost_price' => $data['cost_price'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
            ]);

            // Route through StockService → logs movement, creates cost layer, updates avg_cost
            $this->stockService->addStock(
                $product,
                (int) $data['original_qty'],
                __('pos.batch_received_reason', ['batch' => $batch->batch_number]),
                $batch->id,
                'batch',
                isset($data['cost_price']) && $data['cost_price'] > 0
                    ? (float) $data['cost_price']
                    : null,
                (int) $data['warehouse_id'],
                $batch->id,
            );

            Log::channel('audit')->info('batch.created', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product_id' => $batch->product_id,
                'qty' => $batch->original_qty,
                'warehouse_id' => $batch->warehouse_id,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Correct the remaining_qty on an existing batch.
     *
     * The difference is applied as an adjustment movement so the stock ledger
     * stays in sync with the batch record.
     */
    public function adjust(ProductBatch $batch, int $newQty, string $reason): ProductBatch
    {
        if ($newQty < 0) {
            throw new InvalidArgumentException('Batch quantity cannot be negative');
        }

        return DB::transaction(function () use ($batch, $newQty, $reason) {
            /** @var Product $product */
            $product = Product::findOrFail($batch->product_id);
            $oldQty = $batch->remaining_qty;
            $diff = $newQty - $oldQty;

            if ($diff === 0) {
                return $batch;
            }

            if ($diff > 0) {
                $this->stockService->addStock(
                    $product,
                    $diff,
                    $reason,
                    $batch->id,
                    'batch_adjustment',
                    $batch->cost_price > 0 ? (float) $batch->cost_price : null,
                    $batch->warehouse_id,
                    $batch->id,
                );
            } else {
                $this->stockService->deductStock(
                    $product,
                    abs($diff),
                    'adjustment_remove',
                    $reason,
                    $batch->id,
                    'batch_adjustment',
                    $batch->warehouse_id,
                    $batch->id,
                );
            }

            $batch->update([
                'remaining_qty' => $newQty,
                'status' => $newQty <= 0 ? 'exhausted' : 'active',
            ]);

            Log::channel('audit')->info('batch.adjusted', [
                'batch_id' => $batch->id,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'diff' => $diff,
                'reason' => $reason,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Write off a single batch (damaged, expired, etc.).
     *
     * Deducts the batch's remaining quantity from the product and warehouse stock,
     * then marks the batch as 'exhausted'.  If the product stock is already lower
     * than the batch's remaining_qty (data inconsistency), deducts only what is
     * actually available.
     *
     * @return array Summary of what was written off.
     */
    public function writeOff(ProductBatch $batch, ?string $reason = null): array
    {
        return DB::transaction(function () use ($batch, $reason) {
            /** @var Product $product */
            $product = Product::lockForUpdate()->findOrFail($batch->product_id);
            $qty = min($batch->remaining_qty, $product->quantity);

            if ($batch->remaining_qty <= 0) {
                return [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'qty_written_off' => 0,
                    'status' => 'already_empty',
                ];
            }

            $writeOffReason = $reason
                ?? __('pos.batch_write_off_reason', ['batch' => $batch->batch_number]);

            if ($qty > 0) {
                // Product already locked — use the locked variant to avoid a second lock
                $this->stockService->deductLockedStock(
                    $product,
                    $qty,
                    'write_off',
                    $writeOffReason,
                    $batch->id,
                    'batch_write_off',
                    $batch->warehouse_id,
                    $batch->id,
                );
            }

            $batch->update(['status' => 'exhausted', 'remaining_qty' => 0]);

            Log::channel('audit')->info('batch.written_off', [
                'batch_id' => $batch->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'reason' => $writeOffReason,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'qty_written_off' => $qty,
                'status' => 'written_off',
            ];
        });
    }

    /**
     * Mark batches whose expiry_date has passed as 'expired' (status update only).
     *
     * This does NOT deduct stock — expired stock is still physically present until
     * written off via writeOff() or StockAlertService::writeOffExpiredBatches().
     *
     * @return int Number of batches transitioned to 'expired' status.
     */
    public function markExpired(): int
    {
        return ProductBatch::where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }
}
