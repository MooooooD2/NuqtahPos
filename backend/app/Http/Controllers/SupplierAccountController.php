<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Models\Supplier;

class SupplierAccountController extends Controller
{
    public function __construct(private SupplierAccountRepositoryInterface $supplierAccountRepo) {}

    public function index()
    {
        return view('supplier-accounts.index');
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('view_supplier_payments');

        $totals = $this->supplierAccountRepo->totalsBySupplier((int) $supplier->id);
        $entries = $this->supplierAccountRepo->entriesBySupplier((int) $supplier->id);

        $totalDebt = (float) $totals->total_debt;
        $totalPayment = (float) $totals->total_payment;

        return response()->json([
            'supplier' => $supplier,
            'entries' => $entries,
            'total_debt' => $totalDebt,
            'total_payment' => $totalPayment,
            'balance' => $totalDebt - $totalPayment,
        ]);
    }
}
