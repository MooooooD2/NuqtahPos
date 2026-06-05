<?php

namespace App\Repositories;

use App\Contracts\Repositories\PurchaseOrderRepositoryInterface;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct()
    {
        $this->model = new PurchaseOrder;
    }

    public function findOrFail(int $id): PurchaseOrder
    {
        return PurchaseOrder::findOrFail($id);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = PurchaseOrder::with('supplier', 'items')->orderByDesc('id');

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($q2) use ($q) {
                $q2->where('po_number', 'like', "%{$q}%")
                   ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$q}%"));
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->paginate($perPage);
    }

    public function create(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function update(PurchaseOrder|Model $po, array $data): PurchaseOrder
    {
        $po->update($data);

        return $po->fresh();
    }

    public function createItem(array $data): PurchaseOrderItem
    {
        return PurchaseOrderItem::create($data);
    }

    public function findItem(int $itemId): ?PurchaseOrderItem
    {
        return PurchaseOrderItem::find($itemId);
    }

    public function updateItem(PurchaseOrderItem $item, array $data): PurchaseOrderItem
    {
        $item->update($data);

        return $item->fresh();
    }
}
