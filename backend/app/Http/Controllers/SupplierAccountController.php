<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Models\Supplier;
use App\Traits\ApiResponse;

class SupplierAccountController extends Controller
{
    use ApiResponse;

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

        $totalDebt    = (float) $totals->total_debt;
        $totalPayment = (float) $totals->total_payment;

        return $this->success([
            'data' => [
                'supplier' => $supplier,
                'entries'  => $entries,
                'totals'   => [
                    'total_debt'  => number_format($totalDebt, 2, '.', ''),
                    'total_paid'  => number_format($totalPayment, 2, '.', ''),
                    'balance'     => number_format($totalDebt - $totalPayment, 2, '.', ''),
                ],
            ],
        ]);
    }
}
