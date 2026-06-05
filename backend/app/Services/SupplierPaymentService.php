<?php

namespace App\Services;

use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Contracts\Repositories\SupplierPaymentRepositoryInterface;
use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Models\SupplierPayment;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private SupplierPaymentRepositoryInterface $paymentRepo,
        private SupplierAccountRepositoryInterface $accountRepo,
        private SupplierRepositoryInterface $supplierRepo,
    ) {}

    public function all(array $filters): LengthAwarePaginator
    {
        return $this->paymentRepo->paginate($filters);
    }

    public function create(array $data): SupplierPayment
    {
        $supplier = $this->supplierRepo->findOrFail($data['supplier_id']);

        return DB::transaction(function () use ($data, $supplier) {
            // Get the running balance (may be 0 for new suppliers or advance payments)
            $last = $this->accountRepo->latestEntry($data['supplier_id']);
            $balance = $last ? (float) $last->balance : 0.0;
            $payAmount = (float) $data['amount'];

            $paymentNumber = SequenceService::next('payment');

            $payment = $this->paymentRepo->create(array_merge($data, [
                'payment_number' => $paymentNumber,
                'supplier_name'  => $supplier->name,
                'created_by'     => Auth::id(),
                'created_by_name' => Auth::user()?->full_name ?? '',
            ]));

            $this->accountRepo->create([
                'supplier_id'      => $data['supplier_id'],
                'transaction_type' => 'payment',
                'reference_id'     => (int) $payment->id,
                'reference_number' => $paymentNumber,
                'debit'            => 0,
                'credit'           => $data['amount'],
                'balance'          => $balance - $payAmount,
                'notes'            => $data['notes'] ?? null,
                'created_by'       => Auth::id(),
            ]);

            return $payment;
        });
    }
}
