<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\BatchService;
use App\Services\StockAlertService;
use App\Services\StockReservationService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for stock health, alerts, reservations, and batch management.
 *
 * All read endpoints require `view_reports`.
 * Write/mutate endpoints (write-off, adjust, reserve) require `add_stock`
 * or `manage_roles` depending on the sensitivity of the operation.
 */
class StockController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private StockAlertService $alertService,
        private StockReservationService $reservationService,
        private BatchService $batchService,
    ) {
    }

    // ── Health & Alerts ──────────────────────────────────────────────────────

    /**
     * GET /api/stock/health
     *
     * Comprehensive stock-health snapshot: low stock, out-of-stock, near-expiry,
     * expired batches, and reorder suggestions — all in a single response.
     * Cached at the HTTP layer; callers should add ?_bust=1 to bypass.
     */
    public function health(Request $request): JsonResponse
    {
        $this->authorize('view_reports');

        $request->validate(['warehouse_id' => 'nullable|integer|exists:warehouses,id']);
        $wid = $request->warehouse_id ? (int) $request->warehouse_id : null;

        $fullHealth = $this->alertService->getStockHealth($wid);
        $summary = $fullHealth['summary'];

        // Calculate total products and in-stock count
        $totalProducts = Product::count();
        $inStock = max(0, $totalProducts - $summary['out_of_stock_count']);

        // Return wrapped in 'data' key so ApiResponse merges correctly
        return $this->success([
            'data' => [
                'total_products' => $totalProducts,
                'in_stock' => $inStock,
                'low_stock' => $summary['low_stock_count'],
                'out_of_stock' => $summary['out_of_stock_count'],
            ]
        ]);
    }

    /**
     * GET /api/stock/low-stock
     *
     * Products at or below their configured min_stock (and min_stock > 0),
     * ordered by quantity ascending so the most critical appear first.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $this->authorize('view_reports');

        $request->validate(['warehouse_id' => 'nullable|integer|exists:warehouses,id']);
        $wid = $request->warehouse_id ? (int) $request->warehouse_id : null;
        $items = $this->alertService->getLowStock($wid);

        // Return wrapped in 'data' key so ApiResponse merges correctly
        return $this->success(['data' => $items->values()]);
    }

    /**
     * GET /api/stock/out-of-stock
     */
    public function outOfStock(): JsonResponse
    {
        $this->authorize('view_reports');

        $items = $this->alertService->getOutOfStock();

        // Return wrapped in 'data' key so ApiResponse merges correctly
        return $this->success(['data' => $items->values()]);
    }

    /**
     * GET /api/stock/near-expiry?days=30&warehouse_id=1
     *
     * Batches expiring within the next `days` calendar days (default 30).
     * Ordered FEFO so the most urgent appear first.
     */
    public function nearExpiry(Request $request): JsonResponse
    {
        $this->authorize('view_reports');

        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $days = (int) ($request->days ?? 30);
        $wid = $request->warehouse_id ? (int) $request->warehouse_id : null;
        $items = $this->alertService->getNearExpiryBatches($days, $wid);

        // Return wrapped in 'data' key so ApiResponse merges correctly
        return $this->success(['data' => $items->values()]);
    }

    /**
     * GET /api/stock/expired-batches
     *
     * Batches whose expiry_date is in the past but that still carry remaining_qty.
     * These should be written off via POST /stock/write-off-expired.
     */
    public function expiredBatches(Request $request): JsonResponse
    {
        $this->authorize('view_reports');

        $request->validate(['warehouse_id' => 'nullable|integer|exists:warehouses,id']);
        $wid = $request->warehouse_id ? (int) $request->warehouse_id : null;
        $items = $this->alertService->getExpiredBatches($wid);

        return $this->success(['items' => $items->values(), 'count' => $items->count()]);
    }

    /**
     * GET /api/stock/reorder-suggestions?lookback_days=7
     *
     * Smart reorder list: products at or below their reorder_point, with a
     * suggested order quantity derived from recent consumption velocity.
     */
    public function reorderSuggestions(Request $request): JsonResponse
    {
        $this->authorize('view_reports');

        $request->validate(['lookback_days' => 'nullable|integer|min:1|max:90']);
        $lookback = (int) ($request->lookback_days ?? 7);
        $suggestions = $this->alertService->getReorderSuggestions($lookback);

        return $this->success([
            'suggestions' => $suggestions,
            'count' => count($suggestions),
            'lookback_days' => $lookback,
        ]);
    }

    // ── Write-off ────────────────────────────────────────────────────────────

    /**
     * POST /api/stock/write-off-expired
     *
     * Bulk write-off of all expired batches.  Each batch deduction is routed
     * through StockService so a movement is logged and cost layers are consumed.
     *
     * Requires manage_roles because this is a destructive financial operation.
     */
    public function writeOffExpired(Request $request): JsonResponse
    {
        $this->authorize('manage_roles');

        $request->validate([
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $wid = $request->warehouse_id ? (int) $request->warehouse_id : null;
        $results = $this->alertService->writeOffExpiredBatches($wid, $request->reason ?? '');

        $this->audit('stock.bulk_write_off_expired', 'ProductBatch', 0, [
            'count' => count($results),
            'warehouse_id' => $wid,
        ]);

        return $this->success([
            'written_off' => $results,
            'count' => count($results),
        ]);
    }

    // ── Batch management ─────────────────────────────────────────────────────

    /**
     * GET /api/batches?product_id=1&warehouse_id=2
     */
    public function batches(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $batches = $this->batchService->allForProduct(
            (int) $request->product_id,
            $request->warehouse_id ? (int) $request->warehouse_id : null,
        );

        return $this->success(['batches' => $batches]);
    }

    /**
     * POST /api/batches
     *
     * Create a batch and add its qty to stock (routed through StockService).
     */
    public function createBatch(Request $request): JsonResponse
    {
        $this->authorize('add_stock');

        $conn = app('db')->getDefaultConnection();
        $data = $request->validate([
            'product_id' => "required|integer|exists:{$conn}.products,id",
            'warehouse_id' => "required|integer|exists:{$conn}.warehouses,id",
            'batch_number' => 'required|string|max:100',
            'lot_number' => 'nullable|string|max:100',
            'manufacture_date' => 'nullable|date|before_or_equal:today',
            'expiry_date' => 'nullable|date|after:today',
            'original_qty' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'supplier_id' => "nullable|integer|exists:{$conn}.suppliers,id",
            'notes' => 'nullable|string|max:500',
        ]);

        $batch = $this->batchService->create($data);

        $this->audit('batch.created', 'ProductBatch', $batch->id, [
            'batch_number' => $batch->batch_number,
            'product_id' => $batch->product_id,
            'qty' => $batch->original_qty,
        ]);

        return $this->success(['batch' => $batch], '', 201);
    }

    /**
     * PUT /api/batches/{batch}/adjust
     *
     * Correct the remaining_qty on a batch.  The diff is applied as a stock
     * adjustment movement so the ledger stays in sync.
     */
    public function adjustBatch(Request $request, ProductBatch $batch): JsonResponse
    {
        $this->authorize('manage_roles');

        $data = $request->validate([
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $updated = $this->batchService->adjust($batch, $data['new_quantity'], $data['reason']);

        $this->audit('batch.adjusted', 'ProductBatch', $batch->id, $data);

        return $this->success(['batch' => $updated]);
    }

    /**
     * POST /api/batches/{batch}/write-off
     *
     * Write off a single batch (damaged, expired, etc.).
     */
    public function writeOffBatch(Request $request, ProductBatch $batch): JsonResponse
    {
        $this->authorize('manage_roles');

        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $result = $this->batchService->writeOff($batch, $data['reason'] ?? null);

        $this->audit('batch.written_off', 'ProductBatch', $batch->id, $result);

        return $this->success($result);
    }

    // ── Reservations ─────────────────────────────────────────────────────────

    /**
     * GET /api/stock/available/{product}?warehouse_id=1
     *
     * Returns the unreserved available quantity.
     */
    public function available(Request $request, Product $product): JsonResponse
    {
        $request->validate(['warehouse_id' => 'nullable|integer|exists:warehouses,id']);
        $wid = $request->warehouse_id ? (int) $request->warehouse_id : null;

        return $this->success([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'total_qty' => $product->quantity,
            'available_qty' => $this->reservationService->getAvailableStock($product, $wid),
            'warehouse_id' => $wid,
        ]);
    }

    /**
     * POST /api/stock/reserve
     *
     * Reserve units for a pending order.  Increments reserved_qty; the product
     * is not deducted until fulfill() is called.
     */
    public function reserve(Request $request): JsonResponse
    {
        $this->authorize('add_stock');

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        /** @var Product $product */
        $product = Product::findOrFail($data['product_id']);
        $wid = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;

        $this->reservationService->reserve($product, $data['quantity'], $wid);

        return $this->success([
            'available_qty' => $this->reservationService->getAvailableStock($product, $wid),
        ]);
    }

    /**
     * POST /api/stock/release-reservation
     *
     * Release a previously created reservation (order cancelled).
     */
    public function releaseReservation(Request $request): JsonResponse
    {
        $this->authorize('add_stock');

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        /** @var Product $product */
        $product = Product::findOrFail($data['product_id']);
        $wid = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;

        $this->reservationService->release($product, $data['quantity'], $wid);

        return $this->success([
            'available_qty' => $this->reservationService->getAvailableStock($product, $wid),
        ]);
    }

    /**
     * POST /api/stock/adjustment
     *
     * Simple single-product stock quantity adjustment.
     */
    public function adjustSingle(Request $request): JsonResponse
    {
        $this->authorize('add_stock');

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var Product $product */
        $product = Product::findOrFail($data['product_id']);
        $qty = (int) $data['quantity'];

        // Update the product stock quantity directly
        $before = $product->quantity;
        $product->increment('quantity', $qty);
        $after = $product->fresh()->quantity;

        $this->audit('stock.adjusted', Product::class, $product->id, [
            'before' => $before,
            'after' => $after,
            'delta' => $qty,
            'reason' => $data['reason'] ?? 'manual',
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success([
            'product_id' => $product->id,
            'before' => $before,
            'after' => $after,
            'delta' => $qty,
        ], 'Stock adjusted');
    }
}
