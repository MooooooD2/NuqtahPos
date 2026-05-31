<?php

namespace App\Http\Controllers;

use App\Services\KitchenDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenDisplayController extends Controller
{
    public function __construct(private KitchenDisplayService $kds) {}

    /* ─── Web Views ──────────────────────────────────────────────────── */

    /**
     * Kitchen Display Screen — full-screen TV view.
     */
    public function display(): \Illuminate\View\View
    {
        return view('kitchen.display');
    }

    /**
     * Kitchen management page (admin).
     */
    public function index(): \Illuminate\View\View
    {
        return view('kitchen.index');
    }

    /* ─── API ────────────────────────────────────────────────────────── */

    /**
     * Poll active orders for the KDS.
     */
    public function orders(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id ?? null;
        $orders = $this->kds->getActiveOrders($branchId);
        $stats = $this->kds->getStats($branchId);

        return response()->json([
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }

    /**
     * Create a manual kitchen order.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_number' => 'nullable|string|max:20',
            'order_type' => 'required|in:dine_in,takeaway,delivery,qr',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:200',
            'items.*.product_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.notes' => 'nullable|string|max:300',
        ]);

        $data['branch_id'] = $request->user()->branch_id ?? null;
        $order = $this->kds->createManual($data);

        return response()->json(['order' => $order, 'message' => 'Order sent to kitchen'], 201);
    }

    /**
     * Accept / start preparing.
     */
    public function accept(int $id): JsonResponse
    {
        return response()->json(['order' => $this->kds->accept($id)]);
    }

    /**
     * Mark as ready.
     */
    public function ready(int $id): JsonResponse
    {
        return response()->json(['order' => $this->kds->markReady($id)]);
    }

    /**
     * Mark as served.
     */
    public function served(int $id): JsonResponse
    {
        return response()->json(['order' => $this->kds->markServed($id)]);
    }

    /**
     * Cancel order.
     */
    public function cancel(int $id): JsonResponse
    {
        return response()->json(['order' => $this->kds->cancel($id)]);
    }

    /**
     * Update single item status.
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,preparing,done,cancelled',
        ]);

        return response()->json(['item' => $this->kds->updateItemStatus($itemId, $data['status'])]);
    }

    /**
     * KDS Statistics for today.
     */
    public function stats(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id ?? null;

        return response()->json($this->kds->getStats($branchId));
    }
}
