<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 11 — Customer Mobile App API
 * Loyalty points, order history, QR ordering, promotions.
 */
class CustomerMobileController extends Controller
{
    // ── Home / Profile ────────────────────────────────────────────────────────

    /** GET /api/v1/customer/profile */
    public function profile(Request $request): JsonResponse
    {
        $customer = $request->user()->customer ?? null;

        if (! $customer) {
            return response()->json(['error' => 'No customer profile linked'], 404);
        }

        $cashback = \App\Models\CashbackTransaction::where('customer_id', $customer->id)
            ->sum('amount');

        return response()->json([
            'customer' => $customer->only(['id', 'name', 'email', 'phone', 'loyalty_points']),
            'cashback_balance' => (float) $cashback,
            'tier' => $customer->loyalty_tier ?? 'bronze',
        ]);
    }

    // ── Order History ─────────────────────────────────────────────────────────

    /** GET /api/v1/customer/orders */
    public function orders(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return response()->json(['orders' => []]);
        }

        $orders = \App\Models\Invoice::with('items.product')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['completed', 'paid'])
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    /** GET /api/v1/customer/orders/{id} */
    public function orderDetail(Request $request, int $id): JsonResponse
    {
        $customer = $request->user()->customer;

        $invoice = \App\Models\Invoice::with('items.product', 'items.unit')
            ->where('customer_id', $customer?->id)
            ->findOrFail($id);

        return response()->json(['order' => $invoice]);
    }

    // ── Promotions ────────────────────────────────────────────────────────────

    /** GET /api/v1/customer/promotions */
    public function promotions(): JsonResponse
    {
        $promotions = \App\Models\Promotion::active()
            ->select(['id', 'name', 'description', 'discount_type', 'discount_value',
                'minimum_amount', 'starts_at', 'ends_at', 'image_path'])
            ->get();

        return response()->json(['promotions' => $promotions]);
    }

    // ── Loyalty ───────────────────────────────────────────────────────────────

    /** GET /api/v1/customer/loyalty */
    public function loyalty(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return response()->json(['points' => 0, 'transactions' => []]);
        }

        $transactions = \App\Models\CashbackTransaction::where('customer_id', $customer->id)
            ->latest()
            ->take(20)
            ->get(['id', 'type', 'amount', 'description', 'created_at']);

        return response()->json([
            'points' => $customer->loyalty_points ?? 0,
            'cashback_balance' => (float) $transactions->where('type', 'earn')->sum('amount')
                                - (float) $transactions->where('type', 'redeem')->sum('amount'),
            'transactions' => $transactions,
        ]);
    }

    // ── QR Ordering ───────────────────────────────────────────────────────────

    /** GET /api/v1/customer/menu */
    public function menu(Request $request): JsonResponse
    {
        $products = \App\Models\Product::with('category')
            ->where('is_active', true)
            ->where('show_in_qr', true)
            ->select(['id', 'name', 'description', 'sale_price', 'image_path', 'category_id'])
            ->get();

        return response()->json(['menu' => $products]);
    }

    /** POST /api/v1/customer/qr-order */
    public function placeQrOrder(Request $request): JsonResponse
    {
        $request->validate([
            'table_id' => 'nullable|exists:qr_tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = \App\Models\QrOrder::create([
            'qr_table_id' => $request->table_id,
            'customer_id' => $request->user()->customer?->id,
            'notes' => $request->notes,
            'status' => 'pending',
            'total' => 0,
        ]);

        $total = 0;
        foreach ($request->items as $item) {
            $product = \App\Models\Product::findOrFail($item['product_id']);
            $lineTotal = $product->sale_price * $item['quantity'];
            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->sale_price,
                'total' => $lineTotal,
            ]);
            $total += $lineTotal;
        }

        $order->update(['total' => $total]);

        event(new \App\Events\NewQrOrderPlaced($order));

        return response()->json(['success' => true, 'order_id' => $order->id, 'total' => $total]);
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    /** GET /api/v1/customer/notifications */
    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->take(30)
            ->get(['id', 'type', 'data', 'read_at', 'created_at']);

        return response()->json(['notifications' => $notifications]);
    }

    /** POST /api/v1/customer/notifications/{id}/read */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['success' => true]);
    }
}
