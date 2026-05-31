<?php

namespace App\Repositories;

use App\Contracts\Repositories\DashboardRepositoryInterface;
use App\Models\Invoice;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardRepository extends BaseRepository implements DashboardRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Invoice;
    }

    public function todaySalesStats(string $date): object
    {
        return Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, SUM(final_total) as total')
            ->first();
    }

    public function yesterdaySalesTotal(string $date): object
    {
        return Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->selectRaw('SUM(final_total) as total')
            ->first();
    }

    public function topProducts(string $from, string $to, int $limit): SupportCollection
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereBetween('invoices.created_at', [$from, $to])
            ->where('invoices.status', 'completed')
            ->selectRaw('products.name, SUM(invoice_items.quantity) as total_quantity, SUM(invoice_items.subtotal) as total_sales')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    public function recentInvoices(int $limit): Collection
    {
        return Invoice::where('status', 'completed')
            ->latest()
            ->limit($limit)
            ->get(['invoice_number', 'total', 'final_total', 'payment_method', 'cashier_name', 'created_at']);
    }

    public function recentMovements(int $limit): Collection
    {
        return StockMovement::latest()
            ->limit($limit)
            ->get(['id', 'product_id', 'movement_type', 'quantity', 'reason', 'created_at']);
    }

    public function productStats(): object
    {
        return DB::table('products')->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN quantity > 0 AND quantity <= min_stock THEN 1 ELSE 0 END) as low_stock
        ')->first();
    }

    public function totalRevenue(): float
    {
        // Cache for 10 minutes — full-table SUM is expensive on large invoice sets.
        // Invalidated automatically after each new invoice via InvoiceService.
        return (float) Cache::remember('dashboard_total_revenue', 600, function () {
            return Invoice::where('status', 'completed')->sum('final_total');
        });
    }

    public function totalSuppliers(): int
    {
        return Cache::remember('dashboard_total_suppliers', 600, function () {
            return DB::table('suppliers')->whereNull('deleted_at')->count();
        });
    }
}
