<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(private SupplierService $supplierService)
    {
    }

    public function index()
    {
        return view('suppliers.index');
    }

    public function all(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:5|max:100']);

        $filters = $request->only(['search', 'per_page']);
        $fetchAll = $request->boolean('all');
        $result = $this->supplierService->all($filters, $fetchAll);

        if ($fetchAll) {
            return $this->success(['data' => $result]);
        }

        return $this->success(['data' => SupplierResource::collection($result)]);
    }

    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);
        $supplier = $this->supplierService->create($request->validated());
        $this->audit('supplier.created', Supplier::class, (int) $supplier->id, ['name' => $supplier->name]);

        return $this->success(['supplier' => new SupplierResource($supplier)], '', 201);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $updated = $this->supplierService->update($supplier, $request->validated());
        $this->audit('supplier.updated', Supplier::class, (int) $updated->id);

        return $this->success(['supplier' => new SupplierResource($updated)]);
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete', $supplier);

        try {
            $this->supplierService->delete($supplier);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
        $this->audit('supplier.deleted', Supplier::class, (int) $supplier->id, ['name' => $supplier->name]);

        return $this->success();
    }
}
