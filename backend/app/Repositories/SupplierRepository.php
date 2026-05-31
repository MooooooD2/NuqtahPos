<?php

namespace App\Repositories;

use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Supplier;
    }

    public function findOrFail(int $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    public function all(array $filters = [], bool $fetchAll = false): Collection|LengthAwarePaginator
    {
        $query = Supplier::orderByDesc('id');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }

        if ($fetchAll) {
            return $query->select('id', 'name', 'phone', 'email', 'address')->get();
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(Supplier|Model $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(Supplier|Model $supplier): void
    {
        $supplier->delete();
    }

    public function hasActiveOrders(Supplier $supplier): bool
    {
        return $supplier->purchaseOrders()->whereIn('status', ['pending', 'partial'])->exists();
    }

    public function count(): int
    {
        return DB::table('suppliers')->whereNull('deleted_at')->count();
    }
}
