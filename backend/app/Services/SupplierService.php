<?php

namespace App\Services;

use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Models\Supplier;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierService
{
    public function __construct(private SupplierRepositoryInterface $supplierRepo) {}

    public function all(array $filters, bool $fetchAll = false): Collection|LengthAwarePaginator
    {
        return $this->supplierRepo->all($filters, $fetchAll);
    }

    public function create(array $data): Supplier
    {
        return $this->supplierRepo->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        return $this->supplierRepo->update($supplier, $data);
    }

    public function delete(Supplier $supplier): void
    {
        if ($this->supplierRepo->hasActiveOrders($supplier)) {
            throw new Exception(__('pos.supplier_has_active_orders'));
        }
        $this->supplierRepo->delete($supplier);
    }
}
