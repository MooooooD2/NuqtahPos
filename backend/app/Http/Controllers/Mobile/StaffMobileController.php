<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Phase 11 — Staff Mobile App API
 * Endpoints used by the Flutter staff app (POS, Kitchen, Waiter, Inventory).
 */
class StaffMobileController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly StockService $stockService,
    ) {}

    // ── Dashboard ─────────────────────────────────────────────────────────────

    /** GET /api/v1/staff/dashboard */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'shift' => $this->shiftService->activeShift($user),
            'low_stock' => \App\Models\Product::lowStock()->count(),
            'today_sales' => \App\Models\Invoice::whereDate('created_at', today())
                ->where('status', 'completed')->sum('total'),
            'open_orders' => \App\Models\KitchenOrder::where('status', 'pending')->count(),
        ]);
    }

    // ── Shift ─────────────────────────────────────────────────────────────────

    /** GET /api/v1/staff/shift */
    public function currentShift(Request $request): JsonResponse
    {
        return response()->json([
            'shift' => $this->shiftService->activeShift($request->user()),
        ]);
    }

    /** POST /api/v1/staff/shift/clock-in */
    public function clockIn(Request $request): JsonResponse
    {
        try {
            $shift = $this->shiftService->clockIn($request->user(), $request->all());

            return response()->json(['success' => true, 'shift' => $shift]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/v1/staff/shift/clock-out */
    public function clockOut(Request $request): JsonResponse
    {
        try {
            $shift = $this->shiftService->clockOut($request->user(), $request->all());

            return response()->json(['success' => true, 'shift' => $shift]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ── Inventory Check ───────────────────────────────────────────────────────

    /** GET /api/v1/staff/inventory */
    public function inventory(Request $request): JsonResponse
    {
        $products = \App\Models\Product::with('category', 'unit')
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->low_stock, fn ($q) => $q->lowStock())
            ->select(['id', 'name', 'barcode', 'sku', 'stock_quantity', 'reorder_point', 'category_id'])
            ->orderBy('name')
            ->paginate(50);

        return response()->json($products);
    }

    /** GET /api/v1/staff/inventory/{barcode} — barcode scan lookup */
    public function scanBarcode(string $barcode): JsonResponse
    {
        $product = \App\Models\Product::where('barcode', $barcode)->with('category', 'unit')->first();

        if (! $product) {
            return response()->json(['found' => false], 404);
        }

        return response()->json(['found' => true, 'product' => $product]);
    }

    // ── Kitchen ───────────────────────────────────────────────────────────────

    /** GET /api/v1/staff/kitchen/orders */
    public function kitchenOrders(): JsonResponse
    {
        $orders = \App\Models\KitchenOrder::with('items.product')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    /** PATCH /api/v1/staff/kitchen/orders/{id}/status */
    public function updateKitchenOrder(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|in:in_progress,ready,completed']);

        $order = \App\Models\KitchenOrder::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json(['success' => true, 'order' => $order]);
    }

    // ── Quick Sale (POS Mobile) ────────────────────────────────────────────────

    /** POST /api/v1/staff/pos/sale */
    public function quickSale(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'payment_method' => 'required|in:cash,card,transfer',
            'customer_id' => 'nullable|exists:customers,id',
            'discount' => 'nullable|numeric|min:0',
        ]);

        try {
            $invoice = app(\App\Services\InvoiceService::class)->createInvoice($request->only([
                'items', 'payment_method', 'customer_id', 'discount',
            ]));

            return response()->json(['success' => true, 'invoice' => $invoice]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
