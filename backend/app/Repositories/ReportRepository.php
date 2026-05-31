<?php

namespace App\Repositories;

use App\Contracts\Repositories\ReportRepositoryInterface;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesReturn;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportRepository extends BaseRepository implements ReportRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Invoice;
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

        $topProducts = $this->topProducts($start, $end, 10);
        $invoices = (clone $base)->with('items')->orderByDesc('created_at')->paginate(50);

        return compact('invoices', 'totals', 'byPayment', 'topProducts');
    }

    public function salesReportAll(string $start, string $end, array $filters): Collection
    {
        $base = Invoice::where('status', 'completed')->whereBetween('created_at', [$start, $end]);
        if (! empty($filters['payment_method'])) {
            $base->where('payment_method', $filters['payment_method']);
        }
        if (! empty($filters['cashier_id'])) {
            $base->where('cashier_id', $filters['cashier_id']);
        }

        return (clone $base)->orderByDesc('created_at')->get();
    }

    public function returnsReport(string $start, string $end, ?string $status): array
    {
        $query = SalesReturn::whereBetween('return_date', [$start, $end]);
        if ($status) {
            $query->where('status', $status);
        }

        $totals = (clone $query)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as total_count, SUM(total_amount) as total_returned')
            ->first();

        $topReturnedProducts = DB::table('return_items')
            ->join('sales_returns', 'return_items.return_id', '=', 'sales_returns.id')
            ->whereBetween('sales_returns.return_date', [$start, $end])
            ->where('sales_returns.status', 'completed')
            ->selectRaw('return_items.product_name, SUM(return_items.quantity) as total_qty, SUM(return_items.subtotal) as total_amount')
            ->groupBy('return_items.product_id', 'return_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $returns = (clone $query)->with(['items'])->orderByDesc('return_date')->paginate(50);

        return compact('returns', 'totals', 'topReturnedProducts');
    }

    public function returnsReportAll(string $start, string $end, ?string $status): Collection
    {
        $query = SalesReturn::whereBetween('return_date', [$start, $end]);
        if ($status) {
            $query->where('status', $status);
        }

        return (clone $query)->orderByDesc('return_date')->get();
    }

    public function stockReport(): array
    {
        return Cache::remember('stock_report', 120, function () {
            $products = Product::with('unit:id,name,abbreviation')->orderBy('category')->orderBy('name')->get()
                ->map(fn ($p) => array_merge($p->toArray(), [
                    'unit_name' => $p->unit?->name,
                    'unit_abbreviation' => $p->unit?->abbreviation ?? $p->unit?->name,
                    'stock_value' => $p->quantity * $p->cost_price,
                    'potential_value' => $p->quantity * $p->price,
                    'low_stock' => $p->low_stock,
                ]));

            return [
                'products' => $products,
                'total_stock_value' => $products->sum('stock_value'),
                'low_stock_count' => $products->where('low_stock', true)->count(),
                'out_of_stock' => $products->where('quantity', 0)->count(),
            ];
        });
    }

    public function profitByProduct(string $start, string $end, ?string $category): array
    {
        $query = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.created_at', [$start, $end . ' 23:59:59'])
            ->selectRaw('
                products.id,
                invoice_items.product_name,
                products.category,
                SUM(invoice_items.quantity) as total_qty,
                AVG(products.cost_price) as avg_cost,
                AVG(invoice_items.price) as avg_sell,
                SUM(invoice_items.subtotal) as total_revenue,
                SUM(invoice_items.quantity * products.cost_price) as total_cost,
                SUM(invoice_items.subtotal) - SUM(invoice_items.quantity * products.cost_price) as gross_profit
            ')
            ->groupBy('products.id', 'invoice_items.product_name', 'products.category')
            ->orderByDesc('gross_profit');

        if ($category) {
            $query->where('products.category', $category);
        }

        $rows = $query->get()->map(function ($r) {
            $r->profit_margin = $r->total_revenue > 0
                ? round(($r->gross_profit / $r->total_revenue) * 100, 2)
                : 0;

            return $r;
        });

        $totals = [
            'total_revenue' => round($rows->sum('total_revenue'), 2),
            'total_cost' => round($rows->sum('total_cost'), 2),
            'gross_profit' => round($rows->sum('gross_profit'), 2),
            'profit_margin' => $rows->sum('total_revenue') > 0
                ? round(($rows->sum('gross_profit') / $rows->sum('total_revenue')) * 100, 2) : 0,
            'products_count' => $rows->count(),
        ];

        return ['products' => $rows->values(), 'totals' => $totals];
    }

    public function profitDaily(string $start, string $end): array
    {
        $rows = DB::table('invoices')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as invoices_count, SUM(final_total) as revenue, SUM(discount) as total_discount')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        $costs = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.created_at', [$start, $end . ' 23:59:59'])
            ->selectRaw('DATE(invoices.created_at) as date, SUM(invoice_items.quantity * products.cost_price) as cost')
            ->groupByRaw('DATE(invoices.created_at)')
            ->pluck('cost', 'date');

        $daily = $rows->map(function ($r) use ($costs) {
            $cost = $costs[$r->date] ?? 0;
            $r->cost = round($cost, 2);
            $r->profit = round($r->revenue - $cost, 2);
            $r->margin = $r->revenue > 0 ? round(($r->profit / $r->revenue) * 100, 2) : 0;

            return $r;
        });

        $totals = [
            'revenue' => round($daily->sum('revenue'), 2),
            'cost' => round($daily->sum('cost'), 2),
            'profit' => round($daily->sum('profit'), 2),
        ];

        return compact('daily', 'totals');
    }

    public function accountStatement(Account $account, string $start, string $end): array
    {
        $lines = $account->lines()
            ->with('entry')
            ->whereHas('entry', fn ($q) => $q->whereBetween('entry_date', [$start, $end]))
            ->get();

        return [
            'account' => $account,
            'lines' => $lines,
            'total_debit' => $lines->sum('debit'),
            'total_credit' => $lines->sum('credit'),
            'net_balance' => $lines->sum('debit') - $lines->sum('credit'),
        ];
    }

    private function topProducts(string $start, string $end, int $limit): Collection
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.created_at', [$start, $end])
            ->selectRaw('invoice_items.product_name, SUM(invoice_items.quantity) as total_qty, SUM(invoice_items.subtotal) as total_sales')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get();
    }
}
