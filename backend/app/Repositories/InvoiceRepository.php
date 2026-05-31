<?php

namespace App\Repositories;

use App\Contracts\Repositories\InvoiceRepositoryInterface;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Invoice;
    }

    public function findByNumber(string $number): ?Invoice
    {
        return Invoice::with('items')->where('invoice_number', $number)->first();
    }

    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function createItem(array $data): void
    {
        InvoiceItem::create($data);
    }

    public function todayStats(string $date): object
    {
        return Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count, SUM(final_total) as total')
            ->first();
    }

    public function yesterdayTotal(string $date): object
    {
        return Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->selectRaw('SUM(final_total) as total')
            ->first();
    }

    public function recent(int $limit): Collection
    {
        return Invoice::where('status', 'completed')
            ->latest()
            ->limit($limit)
            ->get(['invoice_number', 'total', 'final_total', 'payment_method', 'cashier_name', 'created_at']);
    }

    public function totalRevenue(): float
    {
        return (float) Invoice::where('status', 'completed')->sum('final_total');
    }

    public function salesReport(string $start, string $end, array $filters): array
    {
        $base = Invoice::where('status', 'completed')->whereBetween('created_at', [$start, $end]);

        if (! empty($filters['payment_method'])) {
            $base->where('payment_method', $filters['payment_method']);
        }
        if (! empty($filters['cashier_id'])) {
            $base->where('cashier_id', $filters['cashier_id']);
        }

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as total_count, SUM(final_total) as total_revenue, SUM(tax_amount) as total_tax, SUM(discount) as total_discount')
            ->first();

        $byPayment = (clone $base)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(final_total) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $topProducts = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.created_at', [$start, $end])
            ->selectRaw('invoice_items.product_name, SUM(invoice_items.quantity) as total_qty, SUM(invoice_items.subtotal) as total_sales')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();

        $invoices = (clone $base)->with('items')->orderByDesc('created_at')->paginate(50);

        return compact('invoices', 'totals', 'byPayment', 'topProducts');
    }

    public function returnedQtyByProduct(int $invoiceId): Collection
    {
        return ReturnItem::whereHas(
            'salesReturn',
            fn ($q) => $q->where('invoice_id', $invoiceId)->where('status', 'completed'),
        )->selectRaw('product_id, SUM(quantity) as total_returned')
            ->groupBy('product_id')
            ->get();
    }
}
