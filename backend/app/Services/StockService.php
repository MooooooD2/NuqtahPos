<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockMovementRepositoryInterface;
use App\Jobs\ProcessStockAlert;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use InvalidArgumentException;

class StockService
{
    public function __construct(
        private ProductRepositoryInterface $productRepo,
        private StockMovementRepositoryInterface $movementRepo,
        private InventoryValuationService $valuationService,
    ) {}

    // ── Public API ───────────────────────────────────────────────────────────

    public function addStock(
        Product $product,
        int $quantity,
        string $reason,
        ?int $referenceId = null,
        string $referenceType = 'manual',
        ?float $unitCost = null,
        ?int $warehouseId = null,
        ?int $batchId = null,
    ): void {
        // FIX: validate quantity
        if ($quantity <= 0) {
            throw new InvalidArgumentException("addStock: quantity must be > 0 (got {$quantity})");
        }

        DB::transaction(function () use ($product, $quantity, $reason, $referenceId, $referenceType, $unitCost, $warehouseId, $batchId) {
            /** @var Product $fresh */
            $fresh = $this->productRepo->lockForUpdate([$product->id])->firstOrFail();

            if ($unitCost !== null && $unitCost > 0) {
                $currentQty = $fresh->quantity;
                $currentAvg = (float) ($fresh->avg_cost ?? $fresh->cost_price ?? 0);
                $newQty = $currentQty + $quantity;
                $newAvgCost = $newQty > 0
                    ? ($currentQty * $currentAvg + $quantity * $unitCost) / $newQty
                    : $unitCost;

                // Cast to string: Eloquent declares these columns as decimal (stored as string)
                $fresh->avg_cost = (string) round($newAvgCost, 4);
                $fresh->last_cost = (string) round($unitCost, 4);
                $fresh->save();

                $this->valuationService->createLayer($fresh, $quantity, $unitCost, $referenceType, $referenceId, $warehouseId);
            }

            // FIX: compute balance_after BEFORE increment (prevents stale read)
            $balanceAfter = $fresh->quantity + $quantity;

            $fresh->increment('quantity', $quantity);
            $this->syncWarehouseStock($fresh->id, $warehouseId, $quantity);
            $this->logMovement($fresh, $quantity, 'add', $reason, $referenceId, $referenceType, $warehouseId, $batchId, $balanceAfter);
        });
    }

    /**
     * Deduct stock that was already locked by lockForUpdate.
     * Returns the unit cost consumed (WAC, FIFO, or LIFO depending on setting).
     */
    public function deductLockedStock(
        Product $lockedProduct,
        int $quantity,
        string $type,
        string $reason,
        ?int $referenceId = null,
        string $referenceType = 'manual',
        ?int $warehouseId = null,
        ?int $batchId = null,
    ): float {
        // FIX: validate quantity
        if ($quantity <= 0) {
            throw new InvalidArgumentException("deductLockedStock: quantity must be > 0 (got {$quantity})");
        }

        if ($lockedProduct->quantity < $quantity) {
            throw new Exception(__('pos.insufficient_stock', ['name' => $lockedProduct->name]));
        }

        // Deduct from FIFO/LIFO cost layers and get the actual unit COGS
        $unitCost = $this->valuationService->deductLayers($lockedProduct, $quantity, $warehouseId);

        // FIX: compute balance_after BEFORE decrement (prevents stale read)
        $balanceAfter = $lockedProduct->quantity - $quantity;

        $lockedProduct->decrement('quantity', $quantity);
        $this->syncWarehouseStock($lockedProduct->id, $warehouseId, -$quantity);
        $this->logMovement($lockedProduct, $quantity, $type, $reason, $referenceId, $referenceType, $warehouseId, $batchId, $balanceAfter);

        // Dispatch alert when quantity crosses min_stock threshold (job runs after outer transaction commits)
        $minStock = (int) ($lockedProduct->min_stock ?? 0);
        if ($minStock > 0 && $balanceAfter <= $minStock) {
            $alertType = $balanceAfter <= 0 ? 'out_of_stock' : 'low_stock';
            ProcessStockAlert::dispatch($lockedProduct->id, $balanceAfter, $alertType)
                ->afterCommit();
        }

        return $unitCost;
    }

    public function deductStock(
        Product $product,
        int $quantity,
        string $type,
        string $reason,
        ?int $referenceId = null,
        string $referenceType = 'manual',
        ?int $warehouseId = null,
        ?int $batchId = null,
    ): void {
        // FIX: validate quantity
        if ($quantity <= 0) {
            throw new InvalidArgumentException("deductStock: quantity must be > 0 (got {$quantity})");
        }

        DB::transaction(function () use ($product, $quantity, $type, $reason, $referenceId, $referenceType, $warehouseId, $batchId) {
            /** @var Product $fresh */
            $fresh = $this->productRepo->lockForUpdate([$product->id])->firstOrFail();

            if ($fresh->quantity < $quantity) {
                throw new Exception(__('pos.insufficient_stock', ['name' => $fresh->name]));
            }

            // FIX: consume cost layers (was missing entirely — caused FIFO/LIFO valuation drift)
            $this->valuationService->deductLayers($fresh, $quantity, $warehouseId);

            // FIX: compute balance_after BEFORE decrement (prevents stale read)
            $balanceAfter = $fresh->quantity - $quantity;

            $fresh->decrement('quantity', $quantity);
            $this->syncWarehouseStock($fresh->id, $warehouseId, -$quantity);
            $this->logMovement($fresh, $quantity, $type, $reason, $referenceId, $referenceType, $warehouseId, $batchId, $balanceAfter);

            // Dispatch alert when quantity crosses min_stock threshold (after transaction commits)
            $minStock = (int) ($fresh->min_stock ?? 0);
            if ($minStock > 0 && $balanceAfter <= $minStock) {
                $alertType = $balanceAfter <= 0 ? 'out_of_stock' : 'low_stock';
                ProcessStockAlert::dispatch($fresh->id, $balanceAfter, $alertType)
                    ->afterCommit();
            }
        });
    }

    /**
     * FEFO batch deduction — returns array of [batch_id, quantity] allocations.
     * Called by InvoiceService when product->track_batches is true.
     */
    public function deductBatchStock(
        Product $product,
        int $quantity,
        string $type,
        string $reason,
        ?int $referenceId = null,
        string $referenceType = 'manual',
        ?int $warehouseId = null,
    ): array {
        // FIX: validate quantity
        if ($quantity <= 0) {
            throw new InvalidArgumentException("deductBatchStock: quantity must be > 0 (got {$quantity})");
        }

        $allocations = [];

        DB::transaction(function () use ($product, $quantity, $type, $reason, $referenceId, $referenceType, $warehouseId, &$allocations) {
            /** @var Product $fresh */
            $fresh = $this->productRepo->lockForUpdate([$product->id])->firstOrFail();

            if ($fresh->quantity < $quantity) {
                throw new Exception(__('pos.insufficient_stock', ['name' => $fresh->name]));
            }

            // FIX: deduct cost layers for the full quantity before batch-level allocation.
            // Previously missing — caused FIFO/LIFO cost layers to accumulate and never
            // be consumed when stock was sold via FEFO batch deduction, causing the
            // inventory valuation report to overstate value over time.
            $this->valuationService->deductLayers($fresh, $quantity, $warehouseId);

            // FEFO: nearest expiry first, nulls last
            $batches = ProductBatch::where('product_id', $fresh->id)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->fefo()
                ->lockForUpdate()
                ->get();

            // FIX: track running balance to avoid stale read in logMovement
            // compute new balance BEFORE decrement (PHP attribute is stale after decrement())
            $runningBalance = $fresh->quantity;   // = old total

            $fresh->decrement('quantity', $quantity);
            $this->syncWarehouseStock($fresh->id, $warehouseId, -$quantity);

            $remaining = $quantity;
            foreach ($batches as $batch) {
                /** @var ProductBatch $batch */
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->remaining_qty, $remaining);

                // FIX: update running balance for accurate balance_after per movement
                $runningBalance -= $take;

                $batch->decrement('remaining_qty', $take);

                if ($batch->remaining_qty <= 0) {
                    $batch->update(['status' => 'exhausted']);
                }

                $allocations[] = ['batch_id' => $batch->id, 'quantity' => $take];
                $remaining -= $take;

                $this->logMovement($fresh, $take, $type, $reason, $referenceId, $referenceType, $batch->warehouse_id, $batch->id, $runningBalance);
            }

            if ($remaining > 0) {
                throw new Exception(__('pos.insufficient_batch_stock', ['name' => $fresh->name]));
            }

            // FIX: dispatch stock alert when FEFO deduction crosses min_stock threshold
            // ($runningBalance is the final balance after all batch allocations)
            $minStock = (int) ($fresh->min_stock ?? 0);
            if ($minStock > 0 && $runningBalance <= $minStock) {
                $alertType = $runningBalance <= 0 ? 'out_of_stock' : 'low_stock';
                ProcessStockAlert::dispatch($fresh->id, $runningBalance, $alertType)
                    ->afterCommit();
            }
        });

        return $allocations;
    }

    public function adjustStock(Product $product, int $newQuantity, string $reason, ?int $warehouseId = null): void
    {
        DB::transaction(function () use ($product, $newQuantity, $reason, $warehouseId) {
            $fresh = $this->productRepo->lockForUpdate([$product->id])->firstOrFail();
            $difference = $newQuantity - $fresh->quantity;

            if ($difference === 0) {
                return;
            }

            // FIX: consume / restore cost layers on adjustment
            if ($difference < 0) {
                $this->valuationService->deductLayers($fresh, abs($difference), $warehouseId);
            }

            $fresh->quantity = $newQuantity;
            $fresh->save();

            $this->syncWarehouseStock($fresh->id, $warehouseId, $difference);

            $type = $difference >= 0 ? 'adjustment_add' : 'adjustment_remove';
            // balance_after is $newQuantity (already set on model, no stale issue here)
            $this->logMovement($fresh, abs($difference), $type, $reason, null, 'adjustment', $warehouseId, null, $newQuantity);
        });
    }

    // ── Transfer movement logger (called by WarehouseService) ───────────────

    public function logTransferMovement(
        Product $product,
        int $quantity,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $transferId,
        ?int $batchId = null,
    ): void {
        // balance_after = total product quantity (transfers don't change total, only split)
        $balance = $product->quantity;

        $this->movementRepo->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'movement_type' => 'transfer_out',
            'reference_type' => 'transfer',
            'reference_id' => $transferId,
            'warehouse_id' => $fromWarehouseId,
            'batch_id' => $batchId,
            'reason' => "Transfer to warehouse #{$toWarehouseId}",
            'employee_id' => Auth::id(),
            'employee_name' => Auth::user()?->full_name,
            'ip_address' => Request::ip(),
        ]);

        $this->movementRepo->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'movement_type' => 'transfer_in',
            'reference_type' => 'transfer',
            'reference_id' => $transferId,
            'warehouse_id' => $toWarehouseId,
            'batch_id' => $batchId,
            'reason' => "Transfer from warehouse #{$fromWarehouseId}",
            'employee_id' => Auth::id(),
            'employee_name' => Auth::user()?->full_name,
            'ip_address' => Request::ip(),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function syncWarehouseStock(int $productId, ?int $warehouseId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $wid = $warehouseId ?? Warehouse::where('is_default', true)->value('id');
        if (! $wid) {
            return;
        }

        WarehouseStock::updateOrCreate(
            ['warehouse_id' => $wid, 'product_id' => $productId],
            ['quantity' => 0, 'reserved_qty' => 0, 'min_stock' => 0],
        );

        if ($delta > 0) {
            WarehouseStock::where('warehouse_id', $wid)
                ->where('product_id', $productId)
                ->increment('quantity', $delta);
        } else {
            WarehouseStock::where('warehouse_id', $wid)
                ->where('product_id', $productId)
                ->decrement('quantity', abs($delta));
        }
    }

    public function logMovement(
        Product $product,
        int $quantity,
        string $type,
        string $reason,
        ?int $referenceId,
        string $referenceType,
        ?int $warehouseId = null,
        ?int $batchId = null,
        // FIX: explicit balance_after prevents stale PHP-object reads after increment/decrement
        ?int $balanceAfter = null,
    ): void {
        $this->movementRepo->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter ?? $product->quantity,
            'movement_type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'reason' => $reason,
            'employee_id' => Auth::id(),
            'employee_name' => Auth::user()?->full_name,
            'ip_address' => Request::ip(),
        ]);
    }
}
