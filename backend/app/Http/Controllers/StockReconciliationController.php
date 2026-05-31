<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Http\Requests\StockReconciliationRequest;
use App\Services\StockReconciliationService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Illuminate\Http\Request;

class StockReconciliationController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private StockReconciliationService $service,
        private ProductRepositoryInterface $productRepo,
    ) {}

    public function index()
    {
        return view('warehouse.reconciliation');
    }

    public function reconcile(StockReconciliationRequest $request)
    {
        $result = $this->service->reconcile($request->validated()['items']);
        $this->audit('stock.reconciliation', 'Product', 0, [
            'total_checked' => $result['total_checked'],
            'total_discrepant' => $result['total_discrepant'],
        ]);

        return $this->success($result);
    }

    public function auditTrail(Request $request, int $productId)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $this->productRepo->findOrFail($productId);

        return $this->success(
            $this->service->productAuditTrail($productId, $request->from, $request->to),
        );
    }
}
