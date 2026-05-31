<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages the stock reservation lifecycle.
 *
 * The `warehouse_stock.reserved_qty` column exists but was never driven by a
 * service — any module that needed reservations incremented it ad-hoc (see
 * WarehouseService::cancelTransfer).  This service makes the contract explicit
 * and adds proper locking so concurrent requests cannot over-reserve.
 *
 * Reservation flow:
 *   1. reserve()  — when an order is placed / a transfer is initiated
 *   2. release()  — when an order is cancelled before fulfillment
 *   3. fulfill()  — when an order is confirmed: releases reservation + deducts actual stock
 *
 * All mutating methods are idempotent-safe via lockForUpdate inside a transaction.
 */
class StockReservationService
{
    public function __construct(private StockService $stockService) {}

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Reserve $qty units of a product in the given warehouse.
     *
     * @throws InvalidArgumentException If $qty ≤ 0.
     * @throws Exception If available stock < $qty.
     */
    public function reserve(Product $product, int $qty, ?int $warehouseId = null): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException("Reservation qty must be > 0 (got {$qty})");
        }

        DB::transaction(function () use ($product, $qty, $warehouseId) {
            $ws = $this->getLockedWarehouseStock($product->id, $warehouseId);

            $available = $ws
                ? ($ws->quantity - $ws->reserved_qty)
                : $product->quantity;   // fallback for products with no warehouse row

            if ($available < $qty) {
                throw new Exception(__('pos.insufficient_available_stock', [
                    'name' => $product->name,
                    'available' => $available,
                    'requested' => $qty,
                ]));
            }

            if ($ws) {
                $ws->increment('reserved_qty', $qty);
            }
        });
    }

    /**
     * Release a previously created reservation.
     * Safe to call even if no reservation exists — floors at 0.
     */
    public function release(Product $product, int $qty, ?int $warehouseId = null): void
    {
        if ($qty <= 0) {
            return;
        }

        DB::transaction(function () use ($product, $qty, $warehouseId) {
            $ws = $this->getLockedWarehouseStock($product->id, $warehouseId);

            if ($ws) {
                $newReserved = max(0, $ws->reserved_qty - $qty);
                $ws->update(['reserved_qty' => $newReserved]);
            }
        });
    }

    /**
     * Fulfill a reservation: release the reservation and deduct actual stock
     * atomically in a single transaction.
     *
     * FIX (two bugs):
     *   1. Previously called release() then deductStock() in SEPARATE transactions.
     *      If deductStock() failed, the reservation was permanently lost even
     *      though no stock was actually consumed.
     *   2. Returned the stale model's avg_cost which is wrong for FIFO/LIFO — now
     *      uses deductLockedStock() which returns the exact unit cost consumed.
     *
     * @return float The unit cost actually consumed (FIFO/LIFO/WAC from cost layers).
     */
    public function fulfill(
        Product $product,
        int $qty,
        string $movementType,
        string $reason,
        ?int $referenceId = null,
        string $referenceType = 'manual',
        ?int $warehouseId = null,
    ): float {
        if ($qty <= 0) {
            throw new InvalidArgumentException("Fulfillment qty must be > 0 (got {$qty})");
        }

        return DB::transaction(function () use ($product, $qty, $movementType, $reason, $referenceId, $referenceType, $warehouseId) {
            // Lock the product row first (ensures we see the latest quantity)
            $fresh = Product::lockForUpdate()->findOrFail($product->id);

            // Release reservation inside the same transaction — if deduction fails,
            // the whole transaction rolls back and the reservation is preserved.
            $ws = $this->getLockedWarehouseStock($product->id, $warehouseId);
            if ($ws) {
                $ws->update(['reserved_qty' => max(0, $ws->reserved_qty - $qty)]);
            }

            // Deduct using the locked variant (product already locked above).
            // Returns the actual unit cost consumed (correct for all valuation methods).
            return $this->stockService->deductLockedStock(
                $fresh,
                $qty,
                $movementType,
                $reason,
                $referenceId,
                $referenceType,
                $warehouseId,
            );
        });
    }

    /**
     * Return the unreserved (available) quantity for a product.
     *
     * If a warehouse ID is given, returns the warehouse-level availability.
     * Without a warehouse ID, uses the product's total quantity minus the
     * sum of all warehouse-level reservations.
     */
    public function getAvailableStock(Product $product, ?int $warehouseId = null): int
    {
        if ($warehouseId) {
            $ws = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('product_id', $product->id)
                ->first();

            return $ws ? $ws->available_qty : 0;
        }

        $totalReserved = WarehouseStock::where('product_id', $product->id)
            ->sum('reserved_qty');

        return max(0, $product->quantity - (int) $totalReserved);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Ensure the WarehouseStock row exists, then re-fetch it with a write lock.
     * Resolves the warehouse ID — falls back to the default warehouse.
     *
     * The two-step (ensure + lockForUpdate) is safe because we are always called
     * from inside a DB::transaction(), preventing anyone from deleting the row
     * between the two queries.
     */
    private function getLockedWarehouseStock(int $productId, ?int $warehouseId): ?WarehouseStock
    {
        $wid = $warehouseId ?? Warehouse::where('is_default', true)->value('id');

        if (! $wid) {
            return null;
        }

        // Ensure the row exists
        WarehouseStock::firstOrCreate(
            ['warehouse_id' => $wid, 'product_id' => $productId],
            ['quantity' => 0, 'reserved_qty' => 0, 'min_stock' => 0],
        );

        // Re-fetch with a row-level write lock so concurrent calls serialize correctly
        return WarehouseStock::where('warehouse_id', $wid)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }
}
