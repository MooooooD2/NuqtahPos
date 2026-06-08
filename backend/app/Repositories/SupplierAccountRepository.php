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

    public function totalsBySupplier(int $supplierId, ?string $dateFrom = null, ?string $dateTo = null): object
    {
        return SupplierAccount::where('supplier_id', $supplierId)
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debt, COALESCE(SUM(credit), 0) as total_payment')
            ->first();
    }

    public function entriesBySupplier(int $supplierId, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        return SupplierAccount::where('supplier_id', $supplierId)
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderBy('created_at')
            ->get([
                'id',
                'created_at as date',
                'transaction_type as movement_type',
                'reference_number as reference',
                'debit',
                'credit',
                'balance',
                'notes',
            ]);
    }
}
