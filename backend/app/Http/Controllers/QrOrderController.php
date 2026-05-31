<?php

namespace App\Http\Controllers;

use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Product;
use App\Models\QrOrder;
use App\Models\QrOrderItem;
use App\Models\QrTable;
use App\Services\KitchenDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrOrderController extends Controller
{
    public function __construct(private KitchenDisplayService $kds) {}

    /* ─── Public: Customer-Facing ────────────────────────────────────── */

    /**
     * Show the QR menu page (public — no auth required).
     */
    public function menu(string $token): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $table = QrTable::where('token', $token)->where('is_active', true)->firstOrFail();

        $products = Product::where('is_active', true)
            ->select('id', 'name', 'price', 'image', 'category', 'description')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('qr.menu', compact('table', 'products'));
    }

    /**
     * Public API: get products for a QR table menu.
     */
    public function products(string $token): JsonResponse
    {
        $table = QrTable::where('token', $token)->where('is_active', true)->firstOrFail();

        $products = Product::where('is_active', true)
            ->select('id', 'name', 'price', 'image', 'category', 'description')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['table' => $table->table_name, 'products' => $products]);
    }

    /**
     * Place an order via QR code (public).
     */
    public function placeOrder(Request $request, string $token): JsonResponse
    {
        $table = QrTable::where('token', $token)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.5',
            'items.*.notes' => 'nullable|string|max:200',
        ]);

        $order = DB::transaction(function () use ($data, $table) {
            $total = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = $product->price * $item['quantity'];
                $total += $subtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $order = QrOrder::create([
                'qr_table_id' => $table->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'total' => $total,
            ]);

            foreach ($itemsData as $item) {
                QrOrderItem::create(array_merge($item, ['qr_order_id' => $order->id]));
            }

            // Auto-create KDS order
            $kitchenOrder = KitchenOrder::create([
                'branch_id' => $table->branch_id,
                'order_number' => 'QR' . date('md') . str_pad($order->id, 3, '0', STR_PAD_LEFT),
                'table_number' => $table->table_name,
                'order_type' => 'qr',
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                KitchenOrderItem::create([
                    'kitchen_order_id' => $kitchenOrder->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'],
                    'status' => 'pending',
                ]);
            }

            $order->update(['kitchen_order_id' => $kitchenOrder->id]);

            return $order;
        });

        return response()->json([
            'order_id' => $order->id,
            'total' => $order->total,
            'status' => $order->status,
            'message' => 'Your order has been placed! We\'ll prepare it shortly.',
        ], 201);
    }

    /**
     * Public: Check order status.
     */
    public function status(string $token, int $orderId): JsonResponse
    {
        $order = QrOrder::with(['items', 'qrTable'])->findOrFail($orderId);

        // Security: ensure order belongs to this table
        if ($order->qrTable->token !== $token) {
            abort(403);
        }

        $kitchenStatus = null;
        if ($order->kitchen_order_id) {
            $kitchenStatus = KitchenOrder::find($order->kitchen_order_id)?->status;
        }

        return response()->json([
            'id' => $order->id,
            'status' => $order->status,
            'kitchen_status' => $kitchenStatus,
            'total' => $order->total,
            'items' => $order->items,
        ]);
    }

    public function orderStatus(int $id): JsonResponse
    {
        $order = QrOrder::with('items')->findOrFail($id);

        return response()->json(['status' => $order->status, 'order' => $order]);
    }

    /* ─── Admin: QR Table Management ────────────────────────────────── */

    /**
     * QR tables management page.
     */
    public function manage(): \Illuminate\View\View
    {
        $tables = QrTable::withCount(['orders' => fn ($q) => $q->whereDate('created_at', today())])
            ->orderBy('table_name')
            ->get();

        return view('qr.manage', compact('tables'));
    }

    /**
     * Generate a new QR table.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_name' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:50',
            'branch_id' => 'nullable|integer',
        ]);

        $table = QrTable::create([
            'branch_id' => $data['branch_id'] ?? auth()->user()->branch_id,
            'table_name' => $data['table_name'],
            'token' => QrTable::generateToken(),
            'capacity' => $data['capacity'] ?? 4,
            'is_active' => true,
        ]);

        return response()->json([
            'table' => $table,
            'menu_url' => $table->menu_url,
            'message' => 'QR table created. Share the link or print the QR code.',
        ], 201);
    }
}
