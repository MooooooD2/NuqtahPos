<?php

namespace App\Http\Controllers;

use App\Exports\NetProfitReportExport;
use App\Exports\ProfitableProductsExport;
use App\Exports\ReturnsReportExport;
use App\Exports\SalesReportExport;
use App\Exports\StockReportExport;
use App\Models\Account;
use App\Models\AuditLog as AuditLogModel;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InventoryValuationService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private InventoryValuationService $valuationService,
    ) {}

    public function index()
    {
        return view('reports.index');
    }

    public function financialReports()
    {
        Gate::authorize('report.financial');

        return view('financial-reports.index');
    }

    public function salesReport(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'payment_method' => 'nullable|in:cash,card,transfer,wallet',
            'cashier_id' => 'nullable|exists:users,id',
        ]);

        return response()->json($this->reportService->salesReport($data));
    }

    public function returnsReport(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:completed,cancelled',
        ]);

        return response()->json($this->reportService->returnsReport($data));
    }

    public function stockReport()
    {
        return response()->json($this->reportService->stockReport());
    }

    public function exportSales(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => [
                'required', 'date', 'after_or_equal:start_date',
                'before_or_equal:' . now()->toDateString(),
                function ($attribute, $value, $fail) use ($request) {
                    if (Carbon::parse($request->start_date)->diffInDays($value) > 365) {
                        $fail('النطاق الزمني لا يتجاوز سنة واحدة.');
                    }
                },
            ],
            'payment_method' => 'nullable|in:cash,card,transfer,wallet',
            'format' => 'required|in:csv,pdf',
        ]);

        $invoices = $this->reportService->salesReportForExport($data);
        $filename = "sales-{$data['start_date']}-{$data['end_date']}";

        if ($data['format'] === 'pdf') {
            $totals = $this->reportService->salesReport($data)['totals'];

            return Pdf::loadView('reports.pdf.sales', [
                'invoices' => $invoices,
                'totals' => $totals,
                'start' => $data['start_date'],
                'end' => $data['end_date'],
            ])->setPaper('a4', 'landscape')->download("{$filename}.pdf");
        }

        return Excel::download(new SalesReportExport($invoices), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportReturns(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => [
                'required', 'date', 'after_or_equal:start_date',
                'before_or_equal:' . now()->toDateString(),
                function ($attribute, $value, $fail) use ($request) {
                    if (Carbon::parse($request->start_date)->diffInDays($value) > 365) {
                        $fail('النطاق الزمني لا يتجاوز سنة واحدة.');
                    }
                },
            ],
            'status' => 'nullable|in:completed,cancelled',
            'format' => 'required|in:csv,pdf',
        ]);

        $returns = $this->reportService->returnsReportForExport($data);
        $filename = "returns-{$data['start_date']}-{$data['end_date']}";

        if ($data['format'] === 'pdf') {
            $totals = $this->reportService->returnsReport($data)['totals'];

            return Pdf::loadView('reports.pdf.returns', [
                'returns' => $returns,
                'totals' => $totals,
                'start' => $data['start_date'],
                'end' => $data['end_date'],
            ])->setPaper('a4', 'landscape')->download("{$filename}.pdf");
        }

        return Excel::download(new ReturnsReportExport($returns), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportStock(Request $request)
    {
        $data = $request->validate(['format' => 'required|in:csv,pdf']);
        $report = $this->reportService->stockReport();

        if ($data['format'] === 'pdf') {
            return Pdf::loadView('reports.pdf.stock', $report)
                ->setPaper('a4', 'landscape')
                ->download('stock-report.pdf');
        }

        return Excel::download(
            new StockReportExport(collect($report['products'])),
            'stock-report.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function incomeStatement(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json($this->reportService->incomeStatement($data['start_date'], $data['end_date']));
    }

    public function balanceSheet()
    {
        return response()->json($this->reportService->balanceSheet());
    }

    public function accountStatement(Request $request, Account $account)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        return response()->json(
            $this->reportService->accountStatement($account, $data['start_date'], $data['end_date']),
        );
    }

    public function cashFlowReport(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $result = $this->reportService->cashFlowReport($data['start_date'], $data['end_date']);
        $groupBy = $data['group_by'] ?? 'day';

        // Aggregate daily rows into weekly or monthly periods if requested
        if ($groupBy !== 'day' && ! empty($result['daily'])) {
            $result['daily'] = collect($result['daily'])->groupBy(function ($row) use ($groupBy) {
                $date = Carbon::parse($row['date']);

                return $groupBy === 'week'
                    ? $date->format('o-\WW')   // ISO week: 2026-W21
                    : $date->format('Y-m');
            })->map(function ($group, $period) {
                return [
                    'date' => $period,
                    'inflow' => round($group->sum('inflow'), 2),
                    'outflow' => round($group->sum('outflow'), 2),
                    'net' => round($group->sum('net'), 2),
                ];
            })->values()->all();
        }

        $result['group_by'] = $groupBy;

        return response()->json($result);
    }

    public function inventoryMovements(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'product_id' => 'nullable|integer|exists:products,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'movement_type' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        return response()->json($this->reportService->inventoryMovements($data));
    }

    public function agedReceivables()
    {
        Gate::authorize('report.aged');

        return response()->json($this->reportService->agedReceivables());
    }

    public function agedPayables()
    {
        Gate::authorize('report.aged');

        return response()->json($this->reportService->agedPayables());
    }

    public function bestSellingProducts(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:5|max:100',
        ]);

        return response()->json(
            $this->reportService->bestSellingProducts($data['start_date'], $data['end_date'], (int) ($data['limit'] ?? 20)),
        );
    }

    public function cashierPerformance(Request $request)
    {
        Gate::authorize('report.cashier-performance');
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json(
            $this->reportService->cashierPerformance($data['start_date'], $data['end_date']),
        );
    }

    public function nearExpiryProducts(Request $request)
    {
        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        return response()->json($this->reportService->nearExpiryProducts((int) ($data['days'] ?? 30)));
    }

    /**
     * Inventory valuation report — compares WAC, FIFO, and LIFO values side by side.
     */
    public function inventoryValuation(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;

        return response()->json($this->valuationService->valuationReport($warehouseId));
    }

    /**
     * Permissions audit report — current permission matrix + recent changes + auth failures.
     */
    public function permissionsAudit(Request $request)
    {
        Gate::authorize('report.permissions-audit');
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $since = now()->subDays($request->integer('days', 30));

        // Current permission matrix: all users with their roles and effective permissions
        $matrix = User::with('roles.permissions')
            ->orderBy('username')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'full_name' => $u->full_name,
                'is_active' => (bool) $u->is_active,
                'roles' => $u->getRoleNames()->values(),
                'permissions' => $u->getAllPermissions()->pluck('name')->sort()->values(),
            ]);

        // Recent role/permission changes from the audit trail
        $permissionChanges = AuditLogModel::whereIn('action', [
            'role.created', 'role.updated', 'role.deleted',
            'role.permissions_synced', 'user.role_assigned',
        ])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['action', 'model', 'record_id', 'username', 'ip_address', 'changes', 'created_at']);

        // Auth event summary over the period
        $authSummary = AuditLogModel::selectRaw('action, COUNT(*) as count')
            ->whereIn('action', ['auth.login_success', 'auth.login_failed', 'auth.login_blocked', 'auth.logout'])
            ->where('created_at', '>=', $since)
            ->groupBy('action')
            ->pluck('count', 'action');

        // Top failed-login IPs (potential brute-force)
        $suspiciousIps = AuditLogModel::where('action', 'auth.login_failed')
            ->where('created_at', '>=', $since)
            ->selectRaw('ip_address, COUNT(*) as attempts')
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        return response()->json([
            'period_days' => $request->integer('days', 30),
            'matrix' => $matrix,
            'permission_changes' => $permissionChanges,
            'auth_summary' => $authSummary,
            'suspicious_ips' => $suspiciousIps,
        ]);
    }

    public function netProfitReport(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json($this->reportService->netProfitReport($data['start_date'], $data['end_date']));
    }

    public function profitableProducts(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:5|max:100',
        ]);

        return response()->json(
            $this->reportService->profitableProductsByMargin($data['start_date'], $data['end_date'], (int) ($data['limit'] ?? 20)),
        );
    }

    public function exportNetProfit(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        $report = $this->reportService->netProfitReport($data['start_date'], $data['end_date']);
        $file = "net_profit_{$data['start_date']}_{$data['end_date']}.xlsx";

        return Excel::download(new NetProfitReportExport($report, $data['start_date'], $data['end_date']), $file);
    }

    public function exportProfitableProducts(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:5|max:100',
        ]);
        $report = $this->reportService->profitableProductsByMargin($data['start_date'], $data['end_date'], (int) ($data['limit'] ?? 20));
        $products = collect($report['products']);
        $file = "profitable_products_{$data['start_date']}_{$data['end_date']}.xlsx";

        return Excel::download(new ProfitableProductsExport($products), $file);
    }
    // Export routes use POST to prevent date parameters from leaking into server logs and browser history

    public function supplierRating(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json($this->reportService->supplierRatingReport($data['start_date'], $data['end_date']));
    }

    public function weeklyExpenses(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json($this->reportService->weeklyExpenses($data['start_date'], $data['end_date']));
    }

    public function breakEvenReport(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json($this->reportService->breakEvenReport($data['start_date'], $data['end_date']));
    }

    public function kpiDashboard(Request $request)
    {
        $data = $request->validate(['date' => 'nullable|date']);

        return response()->json($this->reportService->kpiDashboard($data['date'] ?? null));
    }

    public function inventoryTurnover(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json($this->reportService->inventoryTurnover($data['start_date'], $data['end_date']));
    }

    public function monthlyWasteRatio(Request $request)
    {
        $data = $request->validate(['year' => 'nullable|integer|min:2020|max:2100']);

        return response()->json($this->reportService->monthlyWasteRatio((int) ($data['year'] ?? now()->year)));
    }

    /**
     * Revenue monitoring: daily revenue + tax trend within a date range.
     * Useful for charts showing gross revenue vs tax collected over time.
     */
    public function revenueMonitoring(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $groupBy = $data['group_by'] ?? 'day';
        $dateExpr = match ($groupBy) {
            'month' => "DATE_FORMAT(invoices.date, '%Y-%m')",
            'week' => "DATE_FORMAT(invoices.date, '%x-W%v')",
            default => 'DATE(invoices.date)',
        };

        $rows = Invoice::query()
            ->where('status', 'completed')
            ->whereBetween('date', [$data['start_date'], $data['end_date']])
            ->selectRaw("
                {$dateExpr}           AS period,
                SUM(total)            AS gross_revenue,
                SUM(discount)         AS total_discount,
                SUM(tax_amount)       AS tax_collected,
                SUM(final_total)      AS net_revenue,
                COUNT(*)              AS invoice_count
            ")
            ->groupByRaw($dateExpr)
            ->orderByRaw($dateExpr)
            ->get()
            ->map(fn ($r) => [
                'period' => $r->period,
                'gross_revenue' => round((float) $r->gross_revenue, 2),
                'total_discount' => round((float) $r->total_discount, 2),
                'tax_collected' => round((float) $r->tax_collected, 2),
                'net_revenue' => round((float) $r->net_revenue, 2),
                'invoice_count' => (int) $r->invoice_count,
            ]);

        return response()->json([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'group_by' => $groupBy,
            'rows' => $rows->values(),
            'totals' => [
                'gross_revenue' => round($rows->sum('gross_revenue'), 2),
                'total_discount' => round($rows->sum('total_discount'), 2),
                'tax_collected' => round($rows->sum('tax_collected'), 2),
                'net_revenue' => round($rows->sum('net_revenue'), 2),
                'invoice_count' => $rows->sum('invoice_count'),
            ],
        ]);
    }
}
