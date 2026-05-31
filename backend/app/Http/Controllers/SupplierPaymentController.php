<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
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
        $request->validate(['supplier_id' => 'nullable|integer|exists:suppliers,id']);

        return $this->success(['payments' => $this->paymentService->all($request->only(['supplier_id']))]);
    }

    public function store(StoreSupplierPaymentRequest $request)
    {
        $this->authorize('create', SupplierPayment::class);
        $data = $request->validated();
        $payment = $this->paymentService->create($data);
        $this->audit('payment.created', SupplierPayment::class, (int) $payment->id, [
            'supplier_id' => $data['supplier_id'],
            'amount' => $data['amount'],
        ]);

        return $this->success(message: __('pos.payment_created'), code: 201);
    }
}
