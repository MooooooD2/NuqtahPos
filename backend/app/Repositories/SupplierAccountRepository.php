<?php

namespace App\Repositories;

use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Models\SupplierAccount;
use Illuminate\Database\Eloquent\Collection;

class SupplierAccountRepository extends BaseRepository implements SupplierAccountRepositoryInterface
{
    public function __construct()
    {
        $this->model = new SupplierAccount;
    }

    public function latestEntry(int $supplierId): ?SupplierAccount
    {
        return SupplierAccount::where('supplier_id', $supplierId)
            ->lockForUpdate()
            ->latest()
            ->first();
    }

    public function create(array $data): SupplierAccount
    {
        return SupplierAccount::create($data);
    }

    public function totalsBySupplier(int $supplierId): object
    {
        return SupplierAccount::where('supplier_id', $supplierId)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debt, COALESCE(SUM(credit), 0) as total_payment')
            ->first();
    }

    public function entriesBySupplier(int $supplierId): Collection
    {
        return SupplierAccount::where('supplier_id', $supplierId)
            ->orderBy('created_at')
            ->get();
    }
}
