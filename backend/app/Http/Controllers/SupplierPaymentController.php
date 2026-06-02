<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use DomainException;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(private SupplierPaymentService $paymentService) {}

    public function index()
    {
        return view('supplier-payments.index');
    }

    public function all(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->paymentService->all($request->only(['supplier_id', 'date_from', 'date_to', 'per_page']));

        return $this->success([
            'data'  => $paginator->items(),
            'total' => $paginator->total(),
        ]);
    }

    public function store(StoreSupplierPaymentRequest $request)
    {
        $this->authorize('create', SupplierPayment::class);
        $data = $request->validated();

        try {
            $payment = $this->paymentService->create($data);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $this->audit('payment.created', SupplierPayment::class, (int) $payment->id, [
            'supplier_id' => $data['supplier_id'],
            'amount' => $data['amount'],
        ]);

        return $this->success(message: __('pos.payment_created'), code: 201);
    }
}
