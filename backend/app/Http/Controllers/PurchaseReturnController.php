<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseReturnRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(private PurchaseReturnService $returnService) {}

    public function index()
    {
        return view('purchase-returns.index');
    }

    public function all(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'purchase_order_id' => 'nullable|integer|exists:purchase_orders,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PurchaseReturn::with('items', 'supplier')
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->purchase_order_id, fn ($q) => $q->where('purchase_order_id', $request->purchase_order_id))
            ->latest();

        return $this->success(['purchase_returns' => $query->paginate($request->per_page ?? 15)]);
    }

    public function store(StorePurchaseReturnRequest $request)
    {
        $this->authorize('create', PurchaseReturn::class);

        try {
            $return = $this->returnService->processReturn($request->validated());
            $this->audit('purchase_return.created', PurchaseReturn::class, (int) $return->id, [
                'return_number' => $return->return_number,
            ]);

            return $this->success(['purchase_return' => $return], '', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function returnableItems(PurchaseOrder $purchaseOrder)
    {
        if (! in_array($purchaseOrder->status, ['received', 'partial'])) {
            return $this->error('This PO has not been received yet. Receive the PO before creating a return.', 422);
        }

        $purchaseOrder->load('items.product.unit');
        $returnableQtys = $this->returnService->getReturnableQuantities($purchaseOrder);

        $items = $purchaseOrder->items
            ->filter(fn ($item) => ($returnableQtys[$item->product_id] ?? 0) > 0)
            ->map(fn ($item) => [
                'id'                 => $item->id,
                'product_id'         => $item->product_id,
                'product_name'       => $item->product_name,
                'quantity'           => $item->received_quantity,
                'unit_cost'          => $item->cost_price,
                'returnable_quantity' => $returnableQtys[$item->product_id] ?? 0,
                'unit_abbreviation'  => $item->product?->unit?->abbreviation ?? $item->product?->unit?->name,
            ])
            ->values();

        return $this->success(['items' => $items]);
    }
}
