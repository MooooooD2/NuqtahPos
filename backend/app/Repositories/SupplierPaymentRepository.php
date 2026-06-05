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

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->paginate($perPage);
    }

    public function create(array $data): SupplierPayment
    {
        return SupplierPayment::create($data);
    }
}
