<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Product;
    }

    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function findOrFail(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function findByBarcode(string $barcode): ?Product
    {
        return Product::where('barcode', $barcode)->first();
    }

    public function search(string $query, bool $exact = false): mixed
    {
        $query = trim(strip_tags($query));
        if (! $query) {
            return collect();
        }

        // Exclude batch-tracked products whose every batch is expired (no active batch left)
        $hasActiveBatch = fn ($q) => $q->whereNot(function ($sub) {
            $sub->where('track_batches', true)
                ->whereDoesntHave('batches', fn ($b) => $b->active());
        });

        if ($exact) {
            return Product::with('unit:id,name,abbreviation')
                ->where('barcode', $query)
                ->where('quantity', '>', 0)
                ->tap($hasActiveBatch)
                ->first();
        }

        $exactMatch = Product::with('unit:id,name,abbreviation')
            ->where('barcode', $query)
            ->tap($hasActiveBatch)
            ->first();
        if ($exactMatch) {
            return collect([$exactMatch]);
        }

        return Product::with('unit:id,name,abbreviation')
            ->where(
                fn ($q) => $q
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('barcode', 'like', '%' . $query . '%'),
            )
            ->tap($hasActiveBatch)
            ->orderByDesc('quantity')
            ->limit(10)
            ->get();
    }

    public function all(array $filters = [], bool $fetchAll = false): Collection|LengthAwarePaginator
    {
        $query = Product::query()->with('unit')->orderByDesc('id');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('barcode', 'like', "%{$s}%"));
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (! empty($filters['low_stock'])) {
            $query->whereRaw('quantity <= min_stock AND quantity > 0');
        }

        if ($fetchAll) {
            return $query->select('id', 'name', 'price', 'barcode', 'quantity', 'min_stock', 'category')->get();
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product|Model $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product|Model $product): void
    {
        $product->delete();
    }

    public function lockForUpdate(array $ids): Collection
    {
        return Product::with('taxCategory')->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
    }

    public function lowStock(): Collection
    {
        return Product::whereRaw('quantity > 0 AND quantity <= min_stock')
            ->select('id', 'name', 'category', 'quantity', 'min_stock')
            ->orderBy('quantity')
            ->limit(20)
            ->get();
    }

    public function outOfStock(): Collection
    {
        return Product::where('quantity', 0)
            ->select('id', 'name', 'category', 'quantity', 'min_stock')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function stats(): object
    {
        return DB::table('products')->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN quantity > 0 AND quantity <= min_stock THEN 1 ELSE 0 END) as low_stock
        ')->first();
    }
}
