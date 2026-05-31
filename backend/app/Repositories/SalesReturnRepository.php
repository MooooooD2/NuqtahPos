<?php

namespace App\Repositories;

use App\Contracts\Repositories\SalesReturnRepositoryInterface;
use App\Models\ReturnItem;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesReturnRepository extends BaseRepository implements SalesReturnRepositoryInterface
{
    public function __construct()
    {
        $this->model = new SalesReturn;
    }

    public function create(array $data): SalesReturn
    {
        return SalesReturn::create($data);
    }

    public function createItem(array $data): void
    {
        ReturnItem::create($data);
    }

    public function returnedQuantities(int $invoiceId): Collection
    {
        return ReturnItem::whereHas(
            'salesReturn',
            fn ($q) => $q->where('invoice_id', $invoiceId)->where('status', 'completed'),
        )->selectRaw('product_id, SUM(quantity) as total_returned')
            ->groupBy('product_id')
            ->get();
    }

    public function sumByDateRange(string $start, string $end, ?string $status = 'completed'): object
    {
        $q = SalesReturn::whereBetween('return_date', [$start, $end]);
        if ($status) {
            $q->where('status', $status);
        }

        return $q->selectRaw('COUNT(*) as total_count, SUM(total_amount) as total_returned')->first();
    }

    public function topReturnedProducts(string $start, string $end, int $limit = 10): Collection
    {
        return DB::table('return_items')
            ->join('sales_returns', 'return_items.return_id', '=', 'sales_returns.id')
            ->whereBetween('sales_returns.return_date', [$start, $end])
            ->where('sales_returns.status', 'completed')
            ->selectRaw('return_items.product_name, SUM(return_items.quantity) as total_qty, SUM(return_items.subtotal) as total_amount')
            ->groupBy('return_items.product_id', 'return_items.product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();
    }

    public function paginate(string $start, string $end, ?string $status, int $perPage = 50): object
    {
        $q = SalesReturn::whereBetween('return_date', [$start, $end]);
        if ($status) {
            $q->where('status', $status);
        }

        return $q->with(['items'])->orderByDesc('return_date')->paginate($perPage);
    }
}
