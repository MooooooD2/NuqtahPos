<?php

namespace App\Repositories;

use App\Contracts\Repositories\SupplierPaymentRepositoryInterface;
use App\Models\SupplierPayment;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierPaymentRepository extends BaseRepository implements SupplierPaymentRepositoryInterface
{
    public function __construct()
    {
        $this->model = new SupplierPayment;
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = SupplierPayment::with('supplier')->orderByDesc('id');

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return $query->paginate(20);
    }

    public function create(array $data): SupplierPayment
    {
        return SupplierPayment::create($data);
    }
}
