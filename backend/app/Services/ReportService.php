<?php

namespace App\Services;

use App\Contracts\Repositories\ReportRepositoryInterface;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\Setting;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportService
{
    public function __construct(
        private ReportRepositoryInterface $reportRepo,
        private AccountingService $accountingService,
    ) {}

    public function salesReport(array $filters): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'] . ' 23:59:59';

        return $this->reportRepo->salesReport($start, $end, $filters);
    }

    public function salesReportForExport(array $filters): Collection
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'] . ' 23:59:59';

        return $this->reportRepo->salesReportAll($start, $end, $filters);
    }

    public function returnsReport(array $filters): array
    {
        return $this->reportRepo->returnsReport(
            $filters['start_date'],
            $filters['end_date'],
            $filters['status'] ?? null,
        );
    }

    public function returnsReportForExport(array $filters): Collection
    {
        return $this->reportRepo->returnsReportAll(
            $filters['start_date'],
            $filters['end_date'],
            $filters['status'] ?? null,
        );
    }

    public function stockReport(): array
    {
        return $this->reportRepo->stockReport();
    }

    public function incomeStatement(string $start, string $end): array
    {
        return $this->accountingService->incomeStatement($start, $end);
    }

    public function balanceSheet(): array
    {
        return $this->accountingService->balanceSheet();
    }

    public function accountStatement(Account $account, string $start, string $end): array
    {
        return $this->reportRepo->accountStatement($account, $start, $end);
    }

    public function profitByProduct(array $filters): array
    {
        return $this->reportRepo->profitByProduct(
            $filters['start_date'],
            $filters['end_date'],
            $filters['category'] ?? null,
        );
    }

    public function profitDaily(array $filters): array
    {
        return $this->reportRepo->profitDaily($filters['start_date'], $filters['end_date']);
    }

    public function inventoryMovements(array $filters): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'] . ' 23:59:59';
        $perPage = (int) ($filters['per_page'] ?? 50);

        $query = DB::table('stock_movements')
            ->leftJoin('products', 'products.id', '=', 'stock_movements.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->whereBetween('stock_movements.created_at', [$start, $end]);

        if (! empty($filters['product_id'])) {
            $query->where('stock_movements.product_id', $filters['product_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $query->where('stock_movements.warehouse_id', $filters['warehouse_id']);
        }
        if (! empty($filters['movement_type'])) {
            $query->where('stock_movements.movement_type', $filters['movement_type']);
        }
        if (! empty($filters['search'])) {
            // FIX: escape LIKE wildcards to prevent injection via user-supplied % or _
            $s = '%' . addcslashes($filters['search'], '\%_') . '%';
            $query->where(
                fn ($q) => $q
                    ->where('stock_movements.product_name', 'like', $s)
                    ->orWhere('stock_movements.reason', 'like', $s),
            );
        }

        $totals = (clone $query)
            ->selectRaw('
                SUM(CASE WHEN movement_type LIKE "%add%" OR movement_type IN("purchase_order","return","adjustment_add","transfer_in") THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN movement_type LIKE "%sale%" OR movement_type IN("adjustment_remove","transfer_out") THEN quantity ELSE 0 END) as total_out,
                COUNT(*) as total_rows
            ')
            ->first();

        $rows = $query
            ->selectRaw('
                stock_movements.id,
                stock_movements.product_id,
                stock_movements.product_name,
                stock_movements.quantity,
                stock_movements.balance_after,
                stock_movements.movement_type,
                stock_movements.reference_type,
                stock_movements.reference_id,
                stock_movements.reason,
                stock_movements.employee_name,
                stock_movements.created_at,
                warehouses.name as warehouse_name
            ')
            ->orderByDesc('stock_movements.created_at')
            ->paginate($perPage);

        return [
            'movements' => $rows,
            'totals' => [
                'total_in' => (int) ($totals->total_in ?? 0),
                'total_out' => (int) ($totals->total_out ?? 0),
                'total_rows' => (int) ($totals->total_rows ?? 0),
            ],
            'start_date' => $start,
            'end_date' => $filters['end_date'],
        ];
    }

    public function agedReceivables(): array
    {
        $buckets = [
            'current' => [0, 30],
            '31_60' => [31, 60],
            '61_90' => [61, 90],
            'over_90' => [91, PHP_INT_MAX],
        ];

        $rows = DB::table('customers')
            ->join('customer_accounts', 'customers.id', '=', 'customer_accounts.customer_id')
            ->join('invoices', function ($j) {
                $j->on('invoices.id', '=', 'customer_accounts.reference_id')
                    ->where('customer_accounts.reference_type', 'invoice');
            })
            ->where('customer_accounts.debit', '>', 0)
            ->where('customers.balance', '>', 0)
            ->selectRaw('
                customers.id,
                customers.name,
                customers.phone,
                invoices.invoice_number,
                customer_accounts.debit as amount,
                invoices.date as invoice_date
            ')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            // FIX: compute age in PHP — DATEDIFF() is MySQL-only and breaks SQLite/PostgreSQL tests
            $ageDays = now()->startOfDay()->diffInDays(Carbon::parse($row->invoice_date)->startOfDay());
            $bucket = 'over_90';
            foreach ($buckets as $key => [$min, $max]) {
                if ($ageDays >= $min && $ageDays <= $max) {
                    $bucket = $key;
                    break;
                }
            }
            $cid = $row->id;
            if (! isset($result[$cid])) {
                $result[$cid] = [
                    'customer_id' => $cid,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'current' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0, 'total' => 0,
                ];
            }
            $result[$cid][$bucket] += $row->amount;
            $result[$cid]['total'] += $row->amount;
        }

        return [
            'rows' => array_values($result),
            'totals' => [
                'current' => collect($result)->sum('current'),
                '31_60' => collect($result)->sum('31_60'),
                '61_90' => collect($result)->sum('61_90'),
                'over_90' => collect($result)->sum('over_90'),
                'total' => collect($result)->sum('total'),
            ],
        ];
    }

    public function agedPayables(): array
    {
        $buckets = [
            'current' => [0, 30],
            '31_60' => [31, 60],
            '61_90' => [61, 90],
            'over_90' => [91, PHP_INT_MAX],
        ];

        $rows = DB::table('suppliers')
            ->join('supplier_accounts', 'suppliers.id', '=', 'supplier_accounts.supplier_id')
            ->join('purchase_orders', function ($j) {
                $j->on('purchase_orders.id', '=', 'supplier_accounts.reference_id')
                    ->where('supplier_accounts.transaction_type', 'purchase_order');
            })
            ->where('supplier_accounts.debit', '>', 0)
            ->selectRaw('
                suppliers.id,
                suppliers.name,
                suppliers.phone,
                purchase_orders.po_number,
                supplier_accounts.debit as amount,
                purchase_orders.order_date as po_order_date
            ')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            // FIX: compute age in PHP — DATEDIFF() is MySQL-only
            $ageDays = now()->startOfDay()->diffInDays(Carbon::parse($row->po_order_date)->startOfDay());
            $bucket = 'over_90';
            foreach ($buckets as $key => [$min, $max]) {
                if ($ageDays >= $min && $ageDays <= $max) {
                    $bucket = $key;
                    break;
                }
            }
            $sid = $row->id;
            if (! isset($result[$sid])) {
                $result[$sid] = [
                    'supplier_id' => $sid,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'current' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0, 'total' => 0,
                ];
            }
            $result[$sid][$bucket] += $row->amount;
            $result[$sid]['total'] += $row->amount;
        }

        return [
            'rows' => array_values($result),
            'totals' => [
                'current' => collect($result)->sum('current'),
                '31_60' => collect($result)->sum('31_60'),
                '61_90' => collect($result)->sum('61_90'),
                'over_90' => collect($result)->sum('over_90'),
                'total' => collect($result)->sum('total'),
            ],
        ];
    }

    public function bestSellingProducts(string $start, string $end, int $limit = 20): array
    {
        $endOfDay = $end . ' 23:59:59';

        $products = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->whereBetween('invoices.created_at', [$start, $endOfDay])
            ->where('invoices.status', 'completed')
            ->selectRaw('
                invoice_items.product_id,
                invoice_items.product_name,
                products.barcode,
                products.category,
                SUM(invoice_items.quantity) as total_qty,
                SUM(invoice_items.subtotal) as total_revenue,
                SUM(invoice_items.quantity * invoice_items.cost_price) as total_cost,
                COUNT(DISTINCT invoices.id) as invoice_count
            ')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name', 'products.barcode', 'products.category')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => array_merge((array) $r, [
                'gross_profit' => round($r->total_revenue - $r->total_cost, 2),
                'gross_profit_margin' => $r->total_revenue > 0
                    ? round(($r->total_revenue - $r->total_cost) / $r->total_revenue * 100, 2)
                    : 0,
            ]));

        return [
            'products' => $products,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    public function cashierPerformance(string $start, string $end): array
    {
        $endOfDay = $end . ' 23:59:59';

        $stats = DB::table('invoices')
            ->whereBetween('created_at', [$start, $endOfDay])
            ->where('status', 'completed')
            ->selectRaw('
                cashier_id,
                cashier_name,
                COUNT(*) as invoice_count,
                SUM(final_total) as total_sales,
                AVG(final_total) as avg_invoice,
                SUM(discount) as total_discount,
                SUM(tax_amount) as total_tax,
                MAX(final_total) as max_invoice
            ')
            ->groupBy('cashier_id', 'cashier_name')
            ->orderByDesc('total_sales')
            ->get();

        $returnsByUser = DB::table('sales_returns')
            ->whereBetween('return_date', [$start, $end])
            ->where('status', 'completed')
            ->selectRaw('processed_by, SUM(total_amount) as total_returns, COUNT(*) as return_count')
            ->groupBy('processed_by')
            ->get()
            ->keyBy('processed_by');

        $result = $stats->map(function ($row) use ($returnsByUser) {
            $ret = $returnsByUser->get($row->cashier_id);

            return [
                'cashier_id' => $row->cashier_id,
                'cashier_name' => $row->cashier_name,
                'invoice_count' => (int) $row->invoice_count,
                'total_sales' => round($row->total_sales, 2),
                'avg_invoice' => round($row->avg_invoice, 2),
                'max_invoice' => round($row->max_invoice, 2),
                'total_discount' => round($row->total_discount, 2),
                'total_tax' => round($row->total_tax, 2),
                'total_returns' => $ret ? round($ret->total_returns, 2) : 0,
                'return_count' => $ret ? (int) $ret->return_count : 0,
                'net_sales' => round($row->total_sales - ($ret ? $ret->total_returns : 0), 2),
            ];
        });

        return [
            'cashiers' => $result,
            'start_date' => $start,
            'end_date' => $end,
            'totals' => [
                'invoice_count' => $result->sum('invoice_count'),
                'total_sales' => round($result->sum('total_sales'), 2),
                'total_returns' => round($result->sum('total_returns'), 2),
                'net_sales' => round($result->sum('net_sales'), 2),
            ],
        ];
    }

    public function nearExpiryProducts(int $days = 30): array
    {
        $cutoff = now()->addDays($days)->toDateString();

        $batches = DB::table('product_batches')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'product_batches.warehouse_id')
            ->whereNotNull('product_batches.expiry_date')
            ->where('product_batches.expiry_date', '<=', $cutoff)
            ->where('product_batches.remaining_qty', '>', 0)
            ->where('product_batches.status', '!=', 'exhausted')
            ->selectRaw('
                product_batches.id as batch_id,
                product_batches.batch_number,
                product_batches.expiry_date,
                product_batches.remaining_qty,
                product_batches.status,
                products.id as product_id,
                products.name as product_name,
                products.barcode,
                products.category,
                products.price,
                warehouses.name as warehouse_name
            ')
            ->orderBy('product_batches.expiry_date')
            ->get()
            // FIX: compute days_to_expiry in PHP — DATEDIFF() is MySQL-only.
            // Signed: positive = days until expiry, negative = days past expiry.
            ->transform(function ($batch) {
                $batch->days_to_expiry = (int) now()->startOfDay()->diffInDays(
                    Carbon::parse($batch->expiry_date)->startOfDay(),
                    false,
                );

                return $batch;
            });

        $expired = $batches->where('days_to_expiry', '<', 0);
        $expiring = $batches->where('days_to_expiry', '>=', 0);

        return [
            'days_window' => $days,
            'expired_count' => $expired->count(),
            'expiring_count' => $expiring->count(),
            'expired' => $expired->values(),
            'expiring_soon' => $expiring->values(),
        ];
    }

    public function cashFlowReport(string $start, string $end): array
    {
        $endOfDay = $end . ' 23:59:59';

        // Inflows: sales by payment method (all methods shown; 'credit' is AR, not cash)
        $salesInflows = InvoicePayment::whereHas(
            'invoice',
            fn ($q) => $q->whereBetween('created_at', [$start, $endOfDay])
                ->whereNotIn('status', ['cancelled', 'draft']),
        )->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        // Inflows: purchase returns refunded in cash
        $purchaseReturnRefunds = (float) PurchaseReturn::whereBetween('return_date', [$start, $end])
            ->where('refund_method', 'cash')
            ->where('status', 'completed')
            ->sum('total_amount');

        // Outflows: supplier payments
        $supplierPaymentsByMethod = SupplierPayment::whereBetween('payment_date', [$start, $end])
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        // Outflows: operating expenses
        $expensesByCategory = Expense::whereBetween('expense_date', [$start, $end])
            ->with('category:id,name')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->mapWithKeys(fn ($r) => [
                ($r->category?->name ?? __('pos.uncategorized')) => (float) $r->total,
            ]);

        // Outflows: cash refunds paid back to customers on sales returns
        // FIX: was missing entirely — cash leaves the business when refund_method='cash'
        $salesReturnCashRefunds = (float) SalesReturn::whereBetween('return_date', [$start, $end])
            ->where('refund_method', 'cash')
            ->where('status', 'completed')
            ->sum('refund_amount');

        // FIX: 'credit' method payments are accounts-receivable entries, not cash received.
        //      Keep the per-method breakdown intact for display; exclude 'credit' from the total.
        $totalCashSalesInflow = (float) $salesInflows->reject(fn ($v, $k) => $k === 'credit')->sum();
        $totalSupplierOutflow = (float) $supplierPaymentsByMethod->sum();
        $totalExpenseOutflow = (float) $expensesByCategory->sum();
        $totalInflows = $totalCashSalesInflow + $purchaseReturnRefunds;
        // FIX: include sales-return cash refunds in total outflows
        $totalOutflows = $totalSupplierOutflow + $totalExpenseOutflow + $salesReturnCashRefunds;

        // Daily breakdown
        // FIX: exclude 'credit' method so daily inflows reflect only actual cash received
        $dailyInflows = InvoicePayment::whereHas(
            'invoice',
            fn ($q) => $q->whereBetween('created_at', [$start, $endOfDay])
                ->whereNotIn('status', ['cancelled', 'draft']),
        )->where('method', '!=', 'credit')
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $dailyExpenses = Expense::whereBetween('expense_date', [$start, $end])
            ->selectRaw('expense_date as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // FIX: supplier payments were missing from the daily breakdown
        $dailySupplierPayments = SupplierPayment::whereBetween('payment_date', [$start, $end])
            ->selectRaw('payment_date as day, SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // FIX: cash refunds to customers were missing from the daily breakdown
        $dailySalesReturnRefunds = SalesReturn::whereBetween('return_date', [$start, $end])
            ->where('refund_method', 'cash')
            ->where('status', 'completed')
            ->selectRaw('return_date as day, SUM(refund_amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $dailyDates = collect(
            $dailyInflows->keys()
                ->merge($dailyExpenses->keys())
                ->merge($dailySupplierPayments->keys())
                ->merge($dailySalesReturnRefunds->keys())
                ->unique()
                ->sort(),
        );

        $dailyRows = $dailyDates->map(fn ($date) => [
            'date' => $date,
            'inflow' => round((float) ($dailyInflows[$date] ?? 0), 2),
            'outflow' => round(
                (float) ($dailyExpenses[$date] ?? 0)
                + (float) ($dailySupplierPayments[$date] ?? 0)
                + (float) ($dailySalesReturnRefunds[$date] ?? 0),
                2,
            ),
            'net' => round(
                (float) ($dailyInflows[$date] ?? 0)
                - (float) ($dailyExpenses[$date] ?? 0)
                - (float) ($dailySupplierPayments[$date] ?? 0)
                - (float) ($dailySalesReturnRefunds[$date] ?? 0),
                2,
            ),
        ])->values();

        return [
            'period' => ['from' => $start, 'to' => $end],
            'inflows' => [
                // Full per-method breakdown (includes 'credit' for AR visibility)
                'sales' => $salesInflows->map(fn ($v) => round((float) $v, 2))->toArray(),
                // FIX: expose AR total so consumers can distinguish cash vs. credit-sale AR
                'credit_sales_ar' => round((float) ($salesInflows->get('credit', 0)), 2),
                'purchase_refunds' => round($purchaseReturnRefunds, 2),
                'total' => round($totalInflows, 2),
            ],
            'outflows' => [
                'supplier_payments' => $supplierPaymentsByMethod->map(fn ($v) => round((float) $v, 2))->toArray(),
                'expenses' => $expensesByCategory->map(fn ($v) => round($v, 2))->toArray(),
                // FIX: was missing
                'sales_return_refunds' => round($salesReturnCashRefunds, 2),
                'total' => round($totalOutflows, 2),
            ],
            'net_cash_flow' => round($totalInflows - $totalOutflows, 2),
            'daily' => $dailyRows,
        ];
    }

    public function budgetVsActual(int $year, ?int $month = null): array
    {
        $months = $month ? [$month] : range(1, 12);

        $rows = [];
        foreach ($months as $m) {
            $startDate = sprintf('%04d-%02d-01', $year, $m);
            $endDate = date('Y-m-t', strtotime($startDate));
            $endOfDay = $endDate . ' 23:59:59';

            // Actual revenue: completed invoices final_total
            $actualRevenue = (float) Invoice::whereBetween('date', [$startDate, $endOfDay])
                ->where('status', 'completed')
                ->sum('final_total');

            // Actual expenses by category
            $actualExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->with('category:id,name')
                ->selectRaw('category_id, SUM(amount) as total')
                ->groupBy('category_id')
                ->get()
                ->mapWithKeys(fn ($r) => [
                    ($r->category?->name ?? __('pos.uncategorized')) => (float) $r->total,
                ]);

            $actualTotalExpense = $actualExpenses->sum();

            // Budgeted amounts for this month
            $budgets = Budget::where('year', $year)->where('month', $m)->get();

            $budgetRevenue = (float) $budgets->where('type', 'revenue')->sum('amount');
            $budgetExpenseByType = $budgets->where('type', 'expense')
                ->mapWithKeys(fn ($b) => [$b->category ?? __('pos.general') => (float) $b->amount]);
            $budgetTotalExpense = (float) $budgetExpenseByType->sum();

            $revenueVariance = $actualRevenue - $budgetRevenue;
            $expenseVariance = $budgetTotalExpense - $actualTotalExpense; // positive = underspent (good)

            $rows[] = [
                'year' => $year,
                'month' => $m,
                'month_label' => date('F', mktime(0, 0, 0, $m, 1)),
                'revenue' => [
                    'budget' => round($budgetRevenue, 2),
                    'actual' => round($actualRevenue, 2),
                    'variance' => round($revenueVariance, 2),
                    'variance_pct' => $budgetRevenue > 0 ? round($revenueVariance / $budgetRevenue * 100, 2) : null,
                ],
                'expenses' => [
                    'budget' => round($budgetTotalExpense, 2),
                    'actual' => round($actualTotalExpense, 2),
                    'variance' => round($expenseVariance, 2),
                    'variance_pct' => $budgetTotalExpense > 0 ? round($expenseVariance / $budgetTotalExpense * 100, 2) : null,
                    'by_category' => [
                        'budget' => $budgetExpenseByType->map(fn ($v) => round($v, 2))->toArray(),
                        'actual' => $actualExpenses->map(fn ($v) => round($v, 2))->toArray(),
                    ],
                ],
                'net' => [
                    'budget' => round($budgetRevenue - $budgetTotalExpense, 2),
                    'actual' => round($actualRevenue - $actualTotalExpense, 2),
                ],
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'rows' => $rows,
            'totals' => [
                'revenue' => [
                    'budget' => round(collect($rows)->sum('revenue.budget'), 2),
                    'actual' => round(collect($rows)->sum('revenue.actual'), 2),
                ],
                'expenses' => [
                    'budget' => round(collect($rows)->sum('expenses.budget'), 2),
                    'actual' => round(collect($rows)->sum('expenses.actual'), 2),
                ],
            ],
        ];
    }

    /**
     * Item 7: معدل دوران المخزون لكل منتج خلال فترة
     * Inventory Turnover = COGS_sold / avg_stock_value
     */
    public function inventoryTurnover(string $start, string $end): array
    {
        $endOfDay = $end . ' 23:59:59';

        $sold = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.date', [$start, $endOfDay])
            ->selectRaw('
                invoice_items.product_id,
                SUM(invoice_items.quantity)                            AS units_sold,
                SUM(invoice_items.quantity * invoice_items.cost_price) AS cogs
            ')
            ->groupBy('invoice_items.product_id')
            ->get()
            ->keyBy('product_id');

        $productIds = $sold->keys()->toArray();

        $products = DB::table('products')
            ->whereIn('id', $productIds)
            ->select('id', 'name', 'quantity', 'avg_cost', 'cost_price')
            ->get();

        // FIX: use average stock = (opening_qty + closing_qty) / 2 instead of end-period stock.
        // Opening qty = balance_after of the last stock movement BEFORE the period start.
        // Subquery: for each product, find the most recent movement id before $start.
        $lastMovementIds = DB::table('stock_movements')
            ->whereIn('product_id', $productIds)
            ->where('created_at', '<', $start)
            ->selectRaw('product_id, MAX(id) as last_id')
            ->groupBy('product_id');

        $openingQtys = DB::table('stock_movements')
            ->joinSub($lastMovementIds, 'lm', fn ($j) => $j->on('stock_movements.id', '=', 'lm.last_id'))
            ->pluck('balance_after', 'stock_movements.product_id');

        $rows = $products->map(function ($p) use ($sold, $openingQtys) {
            $s = $sold[$p->id];
            $cogs = (float) $s->cogs;
            $unitCost = $p->avg_cost > 0 ? (float) $p->avg_cost : (float) $p->cost_price;
            $closingQty = (int) $p->quantity;

            // If no movement exists before the period, fall back to closing qty as the estimate
            $openingQty = isset($openingQtys[$p->id])
                ? (int) $openingQtys[$p->id]
                : $closingQty;

            $avgQty = ($openingQty + $closingQty) / 2;
            $avgStockVal = $avgQty * $unitCost;
            $turnover = $avgStockVal > 0 ? round($cogs / $avgStockVal, 2) : null;

            return [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'units_sold' => (int) $s->units_sold,
                'cogs' => round($cogs, 2),
                'opening_stock' => $openingQty,
                'closing_stock' => $closingQty,
                'avg_stock_value' => round($avgStockVal, 2),
                'turnover_rate' => $turnover,
            ];
        })->sortByDesc('turnover_rate')->values()->toArray();

        return ['start' => $start, 'end' => $end, 'rows' => $rows];
    }

    // ── Section 5 additions ────────────────────────────────────────────────────

    /**
     * Net profit: revenue - COGS - operating expenses, with prior-period comparison.
     */
    public function netProfitReport(string $start, string $end): array
    {
        $current = $this->netProfitData($start, $end);
        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        $prevEnd = Carbon::parse($start)->subDay()->toDateString();
        $prevStart = Carbon::parse($prevEnd)->subDays($days - 1)->toDateString();
        $previous = $this->netProfitData($prevStart, $prevEnd);

        $pct = fn ($cur, $prev) => $prev != 0 ? round(($cur - $prev) / abs($prev) * 100, 1) : null;

        $marginTarget = (float) Setting::get('profit_margin_target', 0);

        return array_merge($current, [
            'start_date' => $start,
            'end_date' => $end,
            'margin_target_pct' => $marginTarget,
            'below_target' => $marginTarget > 0 && $current['gross_margin_pct'] < $marginTarget,
            'comparison' => [
                'prev_start_date' => $prevStart,
                'prev_end_date' => $prevEnd,
                'prev_net_sales' => $previous['net_sales'],
                'prev_cogs' => $previous['cogs'],
                'prev_gross_profit' => $previous['gross_profit'],
                'prev_operating_expenses' => $previous['operating_expenses'],
                'prev_net_profit' => $previous['net_profit'],
                'prev_gross_margin_pct' => $previous['gross_margin_pct'],
                'net_sales_change_pct' => $pct($current['net_sales'], $previous['net_sales']),
                'cogs_change_pct' => $pct($current['cogs'], $previous['cogs']),
                'gross_profit_change_pct' => $pct($current['gross_profit'], $previous['gross_profit']),
                'operating_expenses_change_pct' => $pct($current['operating_expenses'], $previous['operating_expenses']),
                'net_profit_change_pct' => $pct($current['net_profit'], $previous['net_profit']),
            ],
        ]);
    }

    private function netProfitData(string $start, string $end): array
    {
        $inv = DB::table('invoices')
            ->where('status', 'completed')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('SUM(total) AS gross_sales, SUM(discount) AS discounts, SUM(tax_amount) AS tax, SUM(final_total) AS net_sales, COUNT(*) AS invoice_count')
            ->first();

        $cogs = (float) DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.date', [$start, $end])
            ->selectRaw('SUM(invoice_items.quantity * invoice_items.cost_price) AS cogs')
            ->value('cogs');

        $returns = (float) DB::table('sales_returns')->where('status', 'completed')->whereBetween('return_date', [$start, $end])->sum('total_amount');
        $expenses = (float) DB::table('expenses')->whereBetween('expense_date', [$start, $end])->sum('amount');

        $grossSales = round((float) ($inv->gross_sales ?? 0), 2);
        $discounts = round((float) ($inv->discounts ?? 0), 2);
        $tax = round((float) ($inv->tax ?? 0), 2);
        $netSales = round((float) ($inv->net_sales ?? 0), 2);
        $cogs = round($cogs, 2);
        $returns = round($returns, 2);
        $expenses = round($expenses, 2);

        $netRevenue = round($netSales - $returns, 2);
        $netRevExTax = round($netRevenue - $tax, 2);
        $grossProfit = round($netRevExTax - $cogs, 2);
        $netProfit = round($grossProfit - $expenses, 2);
        $grossMarginPct = $netRevExTax > 0 ? round($grossProfit / $netRevExTax * 100, 2) : 0.0;
        $netMarginPct = $netRevenue > 0 ? round($netProfit / $netRevenue * 100, 2) : 0.0;

        return [
            'invoice_count' => (int) ($inv->invoice_count ?? 0),
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'tax' => $tax,
            'net_sales' => $netSales,
            'returns' => $returns,
            'net_revenue' => $netRevenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $expenses,
            'net_profit' => $netProfit,
            'gross_margin_pct' => $grossMarginPct,
            'net_margin_pct' => $netMarginPct,
        ];
    }

    /**
     * Products sorted by gross profit margin (highest first).
     */
    public function profitableProductsByMargin(string $start, string $end, int $limit = 20): array
    {
        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.date', [$start, $end])
            ->selectRaw('
                invoice_items.product_id,
                invoice_items.product_name,
                products.category,
                products.barcode,
                SUM(invoice_items.quantity)                                            AS total_qty,
                SUM(invoice_items.subtotal)                                            AS total_revenue,
                SUM(invoice_items.quantity * invoice_items.cost_price)                 AS total_cost,
                SUM(invoice_items.subtotal) - SUM(invoice_items.quantity * invoice_items.cost_price) AS gross_profit
            ')
            ->groupBy('invoice_items.product_id', 'invoice_items.product_name', 'products.category', 'products.barcode')
            ->having('total_revenue', '>', 0)
            ->get()
            ->map(function ($r) {
                $r->profit_margin = round((float) $r->gross_profit / (float) $r->total_revenue * 100, 2);
                $r->total_revenue = round((float) $r->total_revenue, 2);
                $r->total_cost = round((float) $r->total_cost, 2);
                $r->gross_profit = round((float) $r->gross_profit, 2);
                $r->total_qty = (int) $r->total_qty;

                return $r;
            })
            ->sortByDesc('profit_margin')
            ->take($limit)
            ->values();

        return [
            'products' => $rows,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    /**
     * Weekly operational expenses grouped by week and category.
     */
    public function weeklyExpenses(string $start, string $end): array
    {
        $rows = DB::table('expenses')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->whereBetween('expense_date', [$start, $end])
            ->selectRaw('
                YEARWEEK(expense_date, 1)   AS week_key,
                MIN(expense_date)           AS week_start,
                expense_categories.name     AS category,
                SUM(amount)                 AS total,
                COUNT(*)                    AS expense_count
            ')
            ->groupByRaw('YEARWEEK(expense_date, 1), expense_categories.name')
            ->orderBy('week_key')
            ->get();

        $weeks = $rows->groupBy('week_key')->map(fn ($g) => [
            'week_key' => $g->first()->week_key,
            'week_start' => $g->first()->week_start,
            'total' => round((float) $g->sum('total'), 2),
            'by_category' => $g->map(fn ($r) => [
                'category' => $r->category ?? 'غير مصنف',
                'total' => round((float) $r->total, 2),
                'count' => (int) $r->expense_count,
            ])->values(),
        ])->values();

        $byCategory = $rows->groupBy('category')->map(fn ($g, $cat) => [
            'category' => $cat ?? 'غير مصنف',
            'total' => round((float) $g->sum('total'), 2),
            'count' => (int) $g->sum('expense_count'),
        ])->sortByDesc('total')->values();

        return [
            'start_date' => $start,
            'end_date' => $end,
            'weeks' => $weeks,
            'by_category' => $byCategory,
            'total' => round((float) $rows->sum('total'), 2),
        ];
    }

    /**
     * Break-even: fixed costs / contribution margin ratio.
     */
    public function breakEvenReport(string $start, string $end): array
    {
        $inv = DB::table('invoices')
            ->where('status', 'completed')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('SUM(final_total) AS revenue, SUM(tax_amount) AS tax, COUNT(*) AS count, AVG(final_total) AS avg_order')
            ->first();

        $cogs = (float) DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.date', [$start, $end])
            ->selectRaw('SUM(invoice_items.quantity * invoice_items.cost_price) AS cogs')
            ->value('cogs');

        $fixedCosts = (float) DB::table('expenses')
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');

        $revenue = round((float) ($inv->revenue ?? 0), 2);
        $tax = round((float) ($inv->tax ?? 0), 2);
        $cogs = round($cogs, 2);
        $fixed = round($fixedCosts, 2);
        $revenueExTax = max(0.0, $revenue - $tax);

        $vcRatio = $revenueExTax > 0 ? $cogs / $revenueExTax : 0;
        $cmRatio = 1 - $vcRatio;
        $beRev = $cmRatio > 0 ? round($fixed / $cmRatio, 2) : null;

        $days = max(1, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
        $dailyBE = $beRev !== null ? round($beRev / $days, 2) : null;
        $avgOrder = round((float) ($inv->avg_order ?? 0), 2);
        $beOrders = ($beRev !== null && $avgOrder > 0) ? (int) ceil($beRev / $avgOrder) : null;

        return [
            'start_date' => $start,
            'end_date' => $end,
            'period_days' => $days,
            'revenue' => $revenue,
            'revenue_ex_tax' => round($revenueExTax, 2),
            'cogs' => $cogs,
            'fixed_costs' => $fixed,
            'variable_cost_ratio_pct' => round($vcRatio * 100, 2),
            'contribution_margin_ratio_pct' => round($cmRatio * 100, 2),
            'break_even_revenue' => $beRev,
            'daily_break_even' => $dailyBE,
            'break_even_orders' => $beOrders,
            'avg_order_value' => $avgOrder,
            'invoice_count' => (int) ($inv->count ?? 0),
            'is_profitable' => $beRev !== null && $revenue >= $beRev,
            'margin_of_safety' => $beRev !== null ? round($revenue - $beRev, 2) : null,
        ];
    }

    /**
     * Real-time KPI dashboard for a given date (defaults to today).
     */
    public function kpiDashboard(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $today = DB::table('invoices')
            ->where('status', 'completed')
            ->whereBetween('date', [$date, $date])
            ->selectRaw('COUNT(*) as cnt, SUM(final_total) as revenue, AVG(final_total) as avg_val, SUM(tax_amount) as tax')
            ->first();

        $month = DB::table('invoices')
            ->where('status', 'completed')
            ->whereBetween('date', [$monthStart, $date])
            ->selectRaw('COUNT(*) as cnt, SUM(final_total) as revenue, AVG(final_total) as avg_val')
            ->first();

        $todayCogs = (float) DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.date', [$date, $date])
            ->selectRaw('SUM(invoice_items.quantity * invoice_items.cost_price) AS cogs')
            ->value('cogs');

        $todayExpenses = (float) DB::table('expenses')->where('expense_date', $date)->sum('amount');
        $todayReturns = (float) DB::table('sales_returns')->where('status', 'completed')->where('return_date', $date)->sum('total_amount');

        $lowStock = DB::table('products')->whereColumn('quantity', '<=', 'min_stock')->where('quantity', '>', 0)->count();
        $outOfStock = DB::table('products')->where('quantity', '<=', 0)->count();

        $rev = round((float) ($today->revenue ?? 0), 2);
        $cogs = round($todayCogs, 2);
        $gross = round($rev - $cogs, 2);
        $net = round($gross - $todayExpenses, 2);
        $pct = $rev > 0 ? round($gross / $rev * 100, 2) : 0;

        $marginTarget = (float) Setting::get('profit_margin_target', 0);

        return [
            'date' => $date,
            'today' => [
                'revenue' => $rev,
                'invoice_count' => (int) ($today->cnt ?? 0),
                'avg_invoice' => round((float) ($today->avg_val ?? 0), 2),
                'cogs' => $cogs,
                'gross_profit' => $gross,
                'expenses' => round($todayExpenses, 2),
                'net_profit' => $net,
                'returns' => round($todayReturns, 2),
                'tax_collected' => round((float) ($today->tax ?? 0), 2),
                'gross_margin_pct' => $pct,
            ],
            'month' => [
                'revenue' => round((float) ($month->revenue ?? 0), 2),
                'invoice_count' => (int) ($month->cnt ?? 0),
                'avg_invoice' => round((float) ($month->avg_val ?? 0), 2),
            ],
            'alerts' => [
                'low_stock_count' => $lowStock,
                'out_of_stock_count' => $outOfStock,
                'below_margin_target' => $marginTarget > 0 && $pct < $marginTarget,
                'margin_target_pct' => $marginTarget,
            ],
        ];
    }

    /**
     * Item 8: نسبة الهدر الشهري = قيمة الهالك / إجمالي المشتريات
     * Monthly Waste Ratio Report
     */
    public function monthlyWasteRatio(int $year): array
    {
        $wasteByMonth = DB::table('waste_records')
            ->selectRaw('MONTH(created_at) AS month, SUM(total_value) AS waste_value')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('waste_value', 'month');

        $purchasesByMonth = DB::table('purchase_orders')
            ->selectRaw('MONTH(order_date) AS month, SUM(total_amount) AS purchase_value')
            ->where('status', 'received')
            ->whereYear('order_date', $year)
            ->groupByRaw('MONTH(order_date)')
            ->pluck('purchase_value', 'month');

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $waste = (float) ($wasteByMonth[$m] ?? 0);
            $purchases = (float) ($purchasesByMonth[$m] ?? 0);
            $rows[] = [
                'month' => $m,
                'waste_value' => round($waste, 2),
                'purchase_value' => round($purchases, 2),
                'waste_ratio_pct' => $purchases > 0 ? round($waste / $purchases * 100, 2) : null,
            ];
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'total_waste' => round($wasteByMonth->sum(), 2),
            'total_purchases' => round($purchasesByMonth->sum(), 2),
        ];
    }

    /**
     * Supplier rating: on-time delivery rate, average lead time, PO counts.
     * On-time = received_date <= expected_date (only POs that have both dates).
     */
    public function supplierRatingReport(string $start, string $end): array
    {
        // FIX: use portable date-diff helper so the query runs on MySQL, SQLite, and PostgreSQL
        $leadDiff = $this->datediffSql('purchase_orders.received_date', 'purchase_orders.order_date');

        $rows = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereBetween('purchase_orders.order_date', [$start, $end])
            ->selectRaw("
                purchase_orders.supplier_id,
                suppliers.name                              AS supplier_name,
                COUNT(*)                                    AS total_pos,
                SUM(CASE WHEN purchase_orders.status = 'received'  THEN 1 ELSE 0 END)  AS received_count,
                SUM(CASE WHEN purchase_orders.status = 'cancelled' THEN 1 ELSE 0 END)  AS cancelled_count,
                SUM(CASE WHEN purchase_orders.status = 'received'
                          AND purchase_orders.expected_date IS NOT NULL
                          AND purchase_orders.received_date <= purchase_orders.expected_date
                          THEN 1 ELSE 0 END)                AS on_time_count,
                SUM(CASE WHEN purchase_orders.status = 'received'
                          AND purchase_orders.expected_date IS NOT NULL
                          THEN 1 ELSE 0 END)                AS with_deadline_count,
                AVG(CASE WHEN purchase_orders.status = 'received' AND purchase_orders.received_date IS NOT NULL
                          THEN {$leadDiff}
                          END)                              AS avg_lead_days,
                SUM(CASE WHEN purchase_orders.status = 'received' THEN purchase_orders.total_amount ELSE 0 END) AS total_value
            ")
            ->groupBy('purchase_orders.supplier_id', 'suppliers.name')
            ->orderByDesc('total_value')
            ->get()
            ->map(function ($r) {
                $onTimePct = $r->with_deadline_count > 0
                    ? round($r->on_time_count / $r->with_deadline_count * 100, 1)
                    : null;

                return [
                    'supplier_id' => $r->supplier_id,
                    'supplier_name' => $r->supplier_name,
                    'total_pos' => (int) $r->total_pos,
                    'received_count' => (int) $r->received_count,
                    'cancelled_count' => (int) $r->cancelled_count,
                    'on_time_pct' => $onTimePct,
                    'avg_lead_days' => $r->avg_lead_days !== null ? round((float) $r->avg_lead_days, 1) : null,
                    'total_value' => round((float) $r->total_value, 2),
                ];
            });

        return [
            'start_date' => $start,
            'end_date' => $end,
            'suppliers' => $rows->values(),
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns a portable SQL expression for (dateA - dateB) in whole days.
     * Works on MySQL/MariaDB (default), SQLite, and PostgreSQL.
     *
     * @param string $dateA Column or SQL expression for the minuend date
     * @param string $dateB Column or SQL expression for the subtrahend date
     */
    private function datediffSql(string $dateA, string $dateB): string
    {
        $nowA = $dateA === 'NOW()' || $dateA === 'now()';
        $nowB = $dateB === 'NOW()' || $dateB === 'now()';

        return match (DB::getDriverName()) {
            'sqlite' => sprintf(
                'CAST(JULIANDAY(%s) - JULIANDAY(%s) AS INTEGER)',
                $nowA ? "'now'" : $dateA,
                $nowB ? "'now'" : $dateB,
            ),
            'pgsql' => sprintf(
                'EXTRACT(EPOCH FROM (%s::date - %s::date))::INTEGER',
                $nowA ? 'CURRENT_DATE' : $dateA,
                $nowB ? 'CURRENT_DATE' : $dateB,
            ),
            default => "DATEDIFF($dateA, $dateB)",   // MySQL / MariaDB
        };
    }
}
