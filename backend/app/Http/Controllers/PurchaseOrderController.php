<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\PurchaseOrderRepositoryInterface;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\NotificationService;
use App\Services\PurchaseOrderService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchaseOrderController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private PurchaseOrderService $poService,
        private PurchaseOrderRepositoryInterface $poRepo,
        private NotificationService $notifier,
    ) {}

    public function index()
    {
        return view('purchase-orders.index');
    }

    public function all(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'status' => 'nullable|in:draft,pending,approved,partial,received,cancelled,rejected',
        ]);

        $paginated = $this->poRepo->paginate(
            $request->only(['supplier_id', 'status']),
        );
        return $this->success([
            'data'  => $paginated->items(),
            'total' => $paginated->total(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $this->authorize('create', PurchaseOrder::class);

        try {
            $po = $this->poService->createPurchaseOrder($request->validated());
            $this->audit('po.created', PurchaseOrder::class, (int) $po->id, ['po_number' => $po->po_number]);

            try {
                $supplierName = $po->supplier?->name ?? 'Unknown Supplier';
                $this->notifier->purchaseOrderCreated($po->po_number, $supplierName, (float) $po->total);
            } catch (Throwable $e) {
                Log::warning('po.notification_failed', ['error' => $e->getMessage()]);
            }

            return $this->success(['purchase_order' => $po], '', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // Submit a draft PO for approval
    public function submit(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('submit', $purchaseOrder);

        if ($purchaseOrder->status !== 'draft') {
            return $this->error(__('pos.po_not_draft'), 422);
        }

        $purchaseOrder->update(['status' => 'pending']);

        $this->audit('po.submitted', PurchaseOrder::class, $purchaseOrder->id, [
            'po_number' => $purchaseOrder->po_number,
        ]);

        return $this->success(['purchase_order' => $purchaseOrder->fresh()]);
    }

    // Approve a pending PO
    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve', $purchaseOrder);

        if ($purchaseOrder->status !== 'pending') {
            return $this->error(__('pos.po_not_pending'), 422);
        }

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->audit('po.approved', PurchaseOrder::class, $purchaseOrder->id, [
            'po_number' => $purchaseOrder->po_number,
        ]);

        return $this->success(['purchase_order' => $purchaseOrder->fresh()]);
    }

    // Reject a pending PO with a reason
    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve', $purchaseOrder);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($purchaseOrder->status !== 'pending') {
            return $this->error(__('pos.po_not_pending'), 422);
        }

        $purchaseOrder->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
        ]);

        $this->audit('po.rejected', PurchaseOrder::class, $purchaseOrder->id, [
            'po_number' => $purchaseOrder->po_number,
            'reason' => $data['reason'],
        ]);

        return $this->success(['purchase_order' => $purchaseOrder->fresh()]);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('receive', $purchaseOrder);

        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return $this->error(__('pos.po_already_closed'), 422);
        }
        if ($purchaseOrder->status !== 'approved') {
            return $this->error(__('pos.po_must_be_approved'), 422);
        }

        try {
            $po = $this->poService->receivePurchaseOrder($purchaseOrder, $request->validated()['items']);
            $this->audit('po.received', PurchaseOrder::class, (int) $po->id);

            return $this->success(['purchase_order' => $po]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
