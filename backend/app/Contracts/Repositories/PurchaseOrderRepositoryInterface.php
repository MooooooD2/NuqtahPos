<?php

namespace App\Contracts\Repositories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    public function findOrFail(int $id): PurchaseOrder;

    public function paginate(array $filters): LengthAwarePaginator;

    public function create(array $data): PurchaseOrder;

    public function update(PurchaseOrder $po, array $data): PurchaseOrder;

    public function createItem(array $data): PurchaseOrderItem;

    public function findItem(int $itemId): ?PurchaseOrderItem;

    public function updateItem(PurchaseOrderItem $item, array $data): PurchaseOrderItem;
}
