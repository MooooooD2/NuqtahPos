<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Phase 11 — Customer Self-Service Kiosk Controller
 */
class KioskController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /** GET /kiosk — show kiosk screen */
    public function index()
    {
        return view('kiosk.index');
    }

    /** GET /api/kiosk/products — public product listing for kiosk */
    public function products(): JsonResponse
    {
        // Product schema: category (string), image (string), price (decimal), quantity (int)
        $products = \App\Models\Product::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'price', 'image', 'category', 'quantity', 'description', 'barcode'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sale_price' => (float) $p->price,
                'category_name' => $p->category,
                'image_url' => $p->image ? asset('storage/' . $p->image) : null,
                'in_stock' => $p->quantity > 0,
            ]);

        return response()->json(['success' => true, 'products' => $products]);
    }

    /** POST /api/kiosk/checkout — create a kiosk invoice */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1|max:999',
            'payment_method' => 'required|in:cash,card',
        ]);

        // Fetch prices from DB — never trust client-submitted prices
        $productIds = collect($request->items)->pluck('product_id')->unique()->toArray();
        $products = \App\Models\Product::whereIn('id', $productIds)
            ->where('is_active', true)
            ->pluck('price', 'id');

        $items = [];
        foreach ($request->items as $item) {
            $pid = $item['product_id'];
            if (! isset($products[$pid])) {
                return response()->json(['success' => false, 'message' => __('pos.product_not_found')], 422);
            }
            $items[] = [
                'product_id' => $pid,
                'quantity' => $item['quantity'],
                'unit_price' => (float) $products[$pid],
            ];
        }

        try {
            $invoice = $this->invoiceService->createInvoice([
                'items' => $items,
                'payment_method' => $request->payment_method,
                'source' => 'kiosk',
                'status' => 'completed',
                'discount' => 0,
            ]);

            return response()->json([
                'success' => true,
                'invoice_number' => $invoice->invoice_number,
                'total' => $invoice->total,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
