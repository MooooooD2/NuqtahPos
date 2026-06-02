<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseTransfer;
use App\Services\StockService;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    public function __construct(
        private WarehouseService $service,
        private StockService $stockService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->all()]);
    }

    public function allProducts(Request $request): JsonResponse
    {
        $products = Product::select('id', 'name', 'quantity')
            ->orderBy('name')
            ->paginate(200);

        return response()->json(['success' => true, 'products' => $products]);
    }

    public function page()
    {
        return view('warehouses.index');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $conn = app('db')->getDefaultConnection();
        $data = $request->validate([
            'branch_id' => "nullable|exists:{$conn}.branches,id",
            'name' => 'required|string|max:100',
            'code' => "required|string|max:20|unique:{$conn}.warehouses,code",
            'address' => 'nullable|string|max:255',
            'keeper_name' => 'nullable|string|max:100',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return response()->json(['success' => true, 'warehouse' => $this->service->create($data)], 201);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'sometimes|string|max:100',
            'code' => "sometimes|string|max:20|unique:warehouses,code,{$warehouse->id}",
            'address' => 'nullable|string|max:255',
            'keeper_name' => 'nullable|string|max:100',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return response()->json(['success' => true, 'warehouse' => $this->service->update($warehouse, $data)]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $this->service->delete($warehouse);

        return response()->json(['success' => true, 'message' => __('pos.warehouse_deleted')]);
    }

    // ── Stock ────────────────────────────────────────────────────────────────

    public function stock(Warehouse $warehouse): JsonResponse
    {
        $stock = $this->service->stockList($warehouse);

        return response()->json(['success' => true, 'stock' => $stock]);
    }

    public function syncStock(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouseStocks = WarehouseStock::where('warehouse_id', $warehouse->id)
            ->with('product')
            ->get()
            ->filter(fn ($ws) => $ws->product && $ws->quantity != $ws->product->quantity);

        $updated = 0;

        DB::transaction(function () use ($warehouse, $warehouseStocks, &$updated) {
            foreach ($warehouseStocks as $ws) {
                $product = Product::lockForUpdate()->find($ws->product_id);
                if (! $product) {
                    continue;
                }

                $delta = $product->quantity - $ws->quantity;
                if ($delta === 0) {
                    continue;
                }

                $ws->update(['quantity' => $product->quantity]);

                $type = $delta > 0 ? 'adjustment_add' : 'adjustment_remove';
                $this->stockService->logMovement(
                    $product,
                    abs($delta),
                    $type,
                    'warehouse_stock_sync',
                    $warehouse->id,
                    'warehouse_sync',
                    $warehouse->id,
                );

                $updated++;
            }
        });

        Log::channel('audit')->info('warehouse.stock_synced', [
            'warehouse_id' => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'rows_updated' => $updated,
            'user_id' => auth()->id(),
            'username' => auth()->user()?->username,
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => __('pos.stock_synced'),
        ]);
    }

    public function adjustStock(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $wStock = WarehouseStock::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $data['product_id']],
            ['quantity' => 0, 'reserved_qty' => 0, 'min_stock' => 0],
        );

        $delta = $data['new_quantity'] - $wStock->quantity;
        if ($delta === 0) {
            return response()->json(['success' => true]);
        }

        $wStock->update(['quantity' => $data['new_quantity']]);

        $product = Product::findOrFail($data['product_id']);
        $product->increment('quantity', $delta);
        $product->refresh();

        $type = $delta >= 0 ? 'adjustment_add' : 'adjustment_remove';
        $this->stockService->logMovement(
            $product,
            abs($delta),
            $type,
            $data['reason'] ?? 'Manual stock adjustment',
            null,
            'adjustment',
            $warehouse->id,
        );

        return response()->json(['success' => true, 'message' => __('pos.stock_adjusted')]);
    }

    public function toggleLock(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->is_locked) {
            $warehouse->update(['is_locked' => false, 'locked_by' => null, 'locked_at' => null]);
            $message = __('pos.warehouse_unlocked');
        } else {
            $warehouse->update([
                'is_locked' => true,
                'locked_by' => auth()->id(),
                'locked_at' => now(),
            ]);
            $message = __('pos.warehouse_locked_success');
        }

        Log::channel('audit')->info('warehouse.lock_toggled', [
            'warehouse_id' => $warehouse->id,
            'is_locked' => $warehouse->is_locked,
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json(['success' => true, 'is_locked' => $warehouse->is_locked, 'message' => $message]);
    }

    // ── Transfers ────────────────────────────────────────────────────────────

    public function transfers(Request $request): JsonResponse
    {
        $query = WarehouseTransfer::with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'requestedBy:id,full_name'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->warehouse_id, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('from_warehouse_id', $request->warehouse_id)
                    ->orWhere('to_warehouse_id', $request->warehouse_id);
            }))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($query);
    }

    public function createTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.batch_id' => 'nullable|exists:product_batches,id',
        ]);

        try {
            $transfer = $this->service->createTransfer($data);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'transfer' => $transfer], 201);
    }

    public function receiveTransfer(WarehouseTransfer $transfer): JsonResponse
    {
        try {
            $transfer = $this->service->receiveTransfer($transfer);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'transfer' => $transfer]);
    }

    public function cancelTransfer(WarehouseTransfer $transfer): JsonResponse
    {
        try {
            $this->service->cancelTransfer($transfer);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => __('pos.transfer_cancelled')]);
    }

    // ── Product Batches ──────────────────────────────────────────────────────

    public function batches(Request $request): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);
        $warehouseId = $request->warehouse_id;

        return response()->json($this->service->batchesForProduct($product, $warehouseId));
    }

    public function createBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'batch_number' => 'required|string|max:100',
            'lot_number' => 'nullable|string|max:100',
            'manufacture_date' => 'nullable|date|before_or_equal:today',
            'expiry_date' => 'nullable|date|after:today',
            'original_qty' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        return response()->json(['success' => true, 'batch' => $this->service->createBatch($data)], 201);
    }
}
