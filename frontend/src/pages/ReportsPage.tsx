import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useQuery } from "@tanstack/react-query";
import { apiGet, apiPost } from "@/services/api";
import { usePermission } from "@/hooks/usePermission";
import LoadingSpinner from "@/components/common/LoadingSpinner";
import {
  BarChart2,
  TrendingUp,
  Package,
  Truck,
  Download,
  RefreshCw,
  DollarSign,
  ShoppingCart,
  RotateCcw,
  Users,
  AlertTriangle,
} from "lucide-react";
import { clsx } from "clsx";

type ReportType =
  | "sales"
  | "returns"
  | "income"
  | "cashflow"
  | "balance_sheet"
  | "inventory_valuation"
  | "aged_receivables"
  | "aged_payables"
  | "best_selling"
  | "cashier_performance"
  | "near_expiry"
  | "net_profit"
  | "tax"
  | "waste";

interface DateRange { from: string; to: string }

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
  .toISOString().slice(0, 10);

const REPORT_DEFS: Array<{
  key: ReportType;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  endpoint: string;
  method: "get" | "post";
}> = [
  { key: "sales",               label: "Sales Report",          icon: ShoppingCart, endpoint: "/reports/sales",              method: "post" },
  { key: "returns",             label: "Returns Report",         icon: RotateCcw,    endpoint: "/reports/returns",             method: "post" },
  { key: "income",              label: "Income Statement",       icon: TrendingUp,   endpoint: "/reports/income-statement",    method: "post" },
  { key: "cashflow",            label: "Cash Flow",              icon: DollarSign,   endpoint: "/reports/cash-flow",           method: "post" },
  { key: "balance_sheet",       label: "Balance Sheet",          icon: BarChart2,    endpoint: "/reports/balance-sheet",       method: "get"  },
  { key: "inventory_valuation", label: "Inventory Valuation",    icon: Package,      endpoint: "/reports/inventory-valuation", method: "get"  },
  { key: "aged_receivables",    label: "Aged Receivables",       icon: Users,        endpoint: "/reports/aged-receivables",    method: "get"  },
  { key: "aged_payables",       label: "Aged Payables",          icon: Truck,        endpoint: "/reports/aged-payables",       method: "get"  },
  { key: "best_selling",        label: "Best Selling Products",  icon: TrendingUp,   endpoint: "/reports/best-selling",        method: "post" },
  { key: "cashier_performance", label: "Cashier Performance",    icon: Users,        endpoint: "/reports/cashier-performance", method: "post" },
  { key: "near_expiry",         label: "Near Expiry",            icon: AlertTriangle,endpoint: "/reports/near-expiry",         method: "get"  },
  { key: "net_profit",          label: "Net Profit",             icon: DollarSign,   endpoint: "/reports/net-profit",          method: "post" },
  { key: "tax",                 label: "Tax Report",             icon: BarChart2,    endpoint: "/reports/tax",                 method: "post" },
  { key: "waste",               label: "Waste Ratio",            icon: AlertTriangle,endpoint: "/reports/waste-ratio",         method: "get"  },
];

// ── Column filtering ─────────────────────────────────────────────────────────

const ALWAYS_SKIP = new Set([
  "offline_uuid","eta_uuid","eta_long_id","eta_submission_id","eta_status",
  "eta_response","eta_submission_status","updated_at","deleted_at",
  "cashier_id","customer_id","branch_id","warehouse_id","product_id",
  "supplier_id","unit_id","category_id","tax_category_id","items",
  "payments","is_active","parent_id","is_default","is_locked",
  "locked_by","locked_at","requested_by","received_by","received_at",
]);

const REPORT_COLS: Partial<Record<ReportType, string[]>> = {
  sales: [
    "invoice_number","date","created_at","payment_method",
    "cashier_name","final_total","discount","tax_amount","status",
  ],
  returns: [
    "return_number","invoice_number","customer_name",
    "total_amount","tax_amount","reason","status","return_date",
  ],
  cashflow: ["date","inflow","outflow","net"],
  best_selling: ["product_name","barcode","category","total_qty","total_revenue","gross_profit","gross_profit_margin","invoice_count"],
  cashier_performance: ["cashier_name","invoice_count","total_sales","avg_invoice","total_discount","total_returns","net_sales"],
  near_expiry: ["product_name","barcode","batch_number","expiry_date","days_to_expiry","remaining_qty","warehouse_name","status"],
  tax: ["tax_rate","taxable_amount","tax_collected","invoice_count"],
  waste: ["month","waste_value","purchase_value","waste_ratio_pct"],
  inventory_valuation: ["product_name","quantity","wac_unit","wac_value","fifo_value","lifo_value","layers_count"],
  aged_receivables: ["name","phone","current","31_60","61_90","over_90","total"],
  aged_payables: ["name","phone","current","31_60","61_90","over_90","total"],
};

// Include `total` so income-statement / balance-sheet section tables display summed amounts
const ACCOUNT_COLS = ["account_code","code","account_name","name","account_type","type","total","balance","description"];

function pickColumns(type: ReportType, rows: Record<string, unknown>[]): string[] {
  if (rows.length === 0) return [];
  const all = Object.keys(rows[0]);
  const explicit = REPORT_COLS[type];
  if (explicit) return explicit.filter((c) => all.includes(c));
  return all.filter((c) => !ALWAYS_SKIP.has(c));
}

// ── Formatting helpers ───────────────────────────────────────────────────────

const COUNT_KEYS = new Set([
  "total_count","invoice_count","expired_count","expiring_count","days_window",
  "total_invoices","total_products","return_count","total_rows","total_in","total_out",
  "month","year","layers_count","quantity",
]);

function formatKpi(key: string, value: unknown): string {
  if (typeof value !== "number") return String(value ?? "—");
  if (COUNT_KEYS.has(key) || Number.isInteger(value)) return value.toLocaleString();
  return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCell(col: string, val: unknown): string {
  if (val === null || val === undefined) return "—";
  if (typeof val === "object") return JSON.stringify(val);
  if (typeof val === "number") {
    if (COUNT_KEYS.has(col) || Number.isInteger(val)) return val.toLocaleString();
    return val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return String(val);
}

// ── Data extraction ──────────────────────────────────────────────────────────

function extractRows(data: Record<string, unknown>): Record<string, unknown>[] {
  if (Array.isArray(data)) return data as Record<string, unknown>[];
  if (Array.isArray(data.data)) return data.data as Record<string, unknown>[];

  for (const key of ["rows","products","cashiers","daily","by_rate","revenues","assets"] as const) {
    if (Array.isArray(data[key])) return data[key] as Record<string, unknown>[];
  }

  // Near-expiry: merge expiring_soon + expired
  if (Array.isArray(data.expiring_soon) || Array.isArray(data.expired)) {
    return [
      ...((data.expiring_soon as Record<string, unknown>[]) ?? []),
      ...((data.expired as Record<string, unknown>[]) ?? []),
    ];
  }

  // Paginated sub-objects: invoices.data, returns.data
  for (const key of ["invoices","returns","movements"]) {
    const sub = data[key] as Record<string, unknown> | undefined;
    if (sub && Array.isArray(sub.data)) return sub.data as Record<string, unknown>[];
  }

  return [];
}

interface Kpi { key: string; value: unknown }

const SELF_KPIS = new Set<ReportType>(["income","balance_sheet"]);

function extractKpis(type: ReportType, data: Record<string, unknown>): Kpi[] {
  if (SELF_KPIS.has(type)) return [];

  const result: Kpi[] = [];
  const seen = new Set<string>();

  const add = (key: string, val: unknown) => {
    if (seen.has(key)) return;
    if (typeof val !== "number" && typeof val !== "string") return;
    if (typeof val === "string" && (val === "" || isNaN(Number(val)))) return;
    seen.add(key);
    result.push({ key, value: val });
  };

  const rootKpis = [
    "total_count","total_invoices","invoice_count",
    "total_sales","total_revenue","net_sales","gross_sales",
    "gross_profit","net_profit","cogs","operating_expenses",
    "total_amount","total_returned","total_returns","total_tax","total_discount",
    "total_products","total_waste","total_purchases",
    "net_cash_flow","net_revenue","discounts","tax",
    "gross_margin_pct","net_margin_pct",
    "expired_count","expiring_count","days_window",
    "wac_total","fifo_total","lifo_total",
  ];
  for (const k of rootKpis) {
    if (k in data) add(k, data[k]);
  }

  if (data.totals && typeof data.totals === "object") {
    for (const [k, v] of Object.entries(data.totals as Record<string, unknown>)) {
      add(k, v);
    }
  }

  const inflows = data.inflows as Record<string, unknown> | undefined;
  const outflows = data.outflows as Record<string, unknown> | undefined;
  if (inflows?.total !== undefined) add("inflows_total", inflows.total);
  if (outflows?.total !== undefined) add("outflows_total", outflows.total);

  return result;
}

// ── Build query params per report type ──────────────────────────────────────

function buildParams(type: ReportType, dateRange: DateRange): Record<string, unknown> {
  // Waste-ratio only accepts `year`
  if (type === "waste") {
    return { year: new Date(dateRange.from).getFullYear() };
  }
  // Near-expiry only accepts `days` (days from today)
  if (type === "near_expiry") {
    const ms = new Date(dateRange.to).getTime() - new Date().getTime();
    const days = Math.max(1, Math.round(ms / 86_400_000));
    return { days };
  }
  return { start_date: dateRange.from, end_date: dateRange.to };
}

// ── Page component ───────────────────────────────────────────────────────────

export default function ReportsPage() {
  const { t } = useTranslation('pos');
  const { hasPermission } = usePermission();
  const [activeReport, setActiveReport] = useState<ReportType>("sales");
  const [dateRange, setDateRange] = useState<DateRange>({ from: firstOfMonth, to: today });

  const activeDef = REPORT_DEFS.find((r) => r.key === activeReport)!;

  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ["report", activeReport, dateRange],
    queryFn: () => {
      const params = buildParams(activeReport, dateRange);
      return activeDef.method === "post"
        ? apiPost<unknown>(activeDef.endpoint, params)
        : apiGet<unknown>(activeDef.endpoint, params);
    },
    staleTime: 60_000,
    enabled: hasPermission("view_reports"),
    retry: false,
  });

  const handleExportCSV = () => {
    if (!data) return;
    const rows = extractRows(data as Record<string, unknown>);
    if (!rows.length) return;
    const cols = pickColumns(activeReport, rows);
    const csv = [
      cols.join(","),
      ...rows.map((r) => cols.map((c) => JSON.stringify(r[c] ?? "")).join(",")),
    ].join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${activeReport}-report.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  if (!hasPermission("view_reports")) {
    return (
      <div className="card p-12 text-center">
        <BarChart2 className="h-12 w-12 mx-auto mb-3 text-gray-300" />
        <p className="text-gray-500 font-medium">{t('access_denied')}</p>
        <p className="text-sm text-gray-400 mt-1">{t('access_denied_msg')}</p>
      </div>
    );
  }

  const reportData = data as Record<string, unknown> | null;
  const httpError = error as { response?: { status?: number }; message?: string } | null;
  const is403 = httpError?.response?.status === 403;
  const hasError = !!error;

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <BarChart2 className="h-6 w-6 text-primary-500" />
          {t('reports')}
        </h1>
        <div className="flex gap-2">
          <button
            onClick={() => refetch()}
            disabled={isFetching}
            className="btn btn-secondary flex items-center gap-2 text-sm"
            title={t('refresh')}
          >
            <RefreshCw className={clsx("h-4 w-4", isFetching && "animate-spin")} />
            <span className="hidden sm:inline">{t('refresh')}</span>
          </button>
          <button
            onClick={handleExportCSV}
            disabled={!data || isLoading}
            className="btn btn-secondary flex items-center gap-2 text-sm"
          >
            <Download className="h-4 w-4" />
            <span className="hidden sm:inline">{t('export_csv')}</span>
          </button>
        </div>
      </div>

      {/* Date range bar */}
      <div className="card px-4 py-3 flex flex-wrap gap-4 items-center">
        <span className="text-sm font-medium text-gray-500 dark:text-gray-400">{t('select_period')}:</span>
        {(["from","to"] as const).map((side) => (
          <div key={side} className="flex gap-2 items-center">
            <label className="text-xs text-gray-400 uppercase tracking-wide">
              {side === "from" ? t('from_date') : t('to_date')}
            </label>
            <input
              type="date"
              value={dateRange[side]}
              max={today}
              onChange={(e) => setDateRange((p) => ({ ...p, [side]: e.target.value }))}
              className="input text-sm py-1.5"
            />
          </div>
        ))}
        {activeReport === "waste" && (
          <span className="text-xs text-amber-500 dark:text-amber-400">
            ⚠ Waste report is filtered by year ({new Date(dateRange.from).getFullYear()})
          </span>
        )}
        {activeReport === "near_expiry" && (
          <span className="text-xs text-amber-500 dark:text-amber-400">
            ⚠ Near-expiry shows products expiring within {Math.max(1, Math.round((new Date(dateRange.to).getTime() - new Date().getTime()) / 86_400_000))} days from today
          </span>
        )}
      </div>

      <div className="flex gap-4">
        {/* Sidebar */}
        <div className="w-52 flex-shrink-0">
          <div className="card overflow-hidden">
            <div className="px-3 py-2.5 border-b border-gray-100 dark:border-gray-700">
              <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{t('reports')}</p>
            </div>
            <nav className="p-1.5 space-y-0.5 max-h-[calc(100vh-280px)] overflow-y-auto">
              {REPORT_DEFS.map((r) => (
                <button
                  key={r.key}
                  onClick={() => setActiveReport(r.key)}
                  className={clsx(
                    "w-full text-left px-3 py-2 rounded-md text-sm font-medium transition-colors flex items-center gap-2.5",
                    activeReport === r.key
                      ? "bg-primary-600 text-white shadow-sm"
                      : "text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/60 hover:text-gray-900 dark:hover:text-white",
                  )}
                >
                  <r.icon className={clsx("h-4 w-4 flex-shrink-0", activeReport === r.key ? "text-white" : "text-gray-400")} />
                  <span className="truncate leading-tight">{r.label}</span>
                </button>
              ))}
            </nav>
          </div>
        </div>

        {/* Content panel */}
        <div className="flex-1 card overflow-hidden min-h-96">
          {/* Panel header */}
          <div className="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2.5">
            <activeDef.icon className="h-5 w-5 text-primary-500 flex-shrink-0" />
            <h2 className="font-semibold text-gray-900 dark:text-white">{activeDef.label}</h2>
            <span className="ml-auto text-xs text-gray-400 bg-gray-50 dark:bg-gray-700 px-2 py-0.5 rounded">
              {activeReport === "waste"
                ? `Year ${new Date(dateRange.from).getFullYear()}`
                : `${dateRange.from} — ${dateRange.to}`}
            </span>
          </div>

          {isLoading ? (
            <div className="flex h-64 items-center justify-center">
              <LoadingSpinner size="lg" />
            </div>
          ) : is403 ? (
            <EmptyState
              icon={<BarChart2 className="h-10 w-10" />}
              title={t('access_denied')}
              message={t('access_denied_msg')}
            />
          ) : hasError ? (
            <ErrorState error={httpError} />
          ) : (
            <div className="p-5 overflow-auto">
              {!reportData ? (
                <EmptyState
                  icon={<BarChart2 className="h-10 w-10" />}
                  title={t('no_data')}
                  message={t('select_period_view')}
                />
              ) : (
                <ReportContent type={activeReport} data={reportData} />
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Shared empty / error states ──────────────────────────────────────────────

function EmptyState({ icon, title, message }: { icon: React.ReactNode; title: string; message: string }) {
  return (
    <div className="flex h-64 items-center justify-center text-center p-8">
      <div>
        <div className="h-10 w-10 mx-auto mb-3 text-gray-300 dark:text-gray-600 flex items-center justify-center">
          {icon}
        </div>
        <p className="font-medium text-gray-600 dark:text-gray-400">{title}</p>
        <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">{message}</p>
      </div>
    </div>
  );
}

function ErrorState({ error }: { error: { response?: { status?: number; data?: unknown }; message?: string } | null }) {
  const status = error?.response?.status;
  const msg = (error?.response?.data as { message?: string })?.message ?? error?.message ?? "Unknown error";
  return (
    <div className="flex h-64 items-center justify-center text-center p-8">
      <div>
        <AlertTriangle className="h-10 w-10 mx-auto mb-3 text-red-400 opacity-70" />
        <p className="font-medium text-gray-700 dark:text-gray-300">
          {status ? `Error ${status}` : "Request failed"}
        </p>
        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs">{msg}</p>
      </div>
    </div>
  );
}

// ── Report content dispatcher ────────────────────────────────────────────────

function ReportContent({ type, data }: { type: ReportType; data: Record<string, unknown> }) {
  const { t } = useTranslation('pos');
  const rows  = extractRows(data);
  const kpis  = extractKpis(type, data);
  const cols  = pickColumns(type, rows);

  const empty = rows.length === 0 && kpis.length === 0;

  if (type === "income")        return <IncomeStatementView data={data} />;
  if (type === "balance_sheet") return <BalanceSheetView data={data} />;
  if (type === "net_profit")    return <NetProfitView data={data} />;
  if (type === "cashflow")      return <CashFlowView data={data} rows={rows} />;

  return (
    <div className="space-y-5">
      {/* KPI cards */}
      {kpis.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          {kpis.map(({ key, value }) => (
            <KpiCard key={key} label={key.replace(/_/g, " ")} value={value} />
          ))}
        </div>
      )}

      {/* Near-expiry badges */}
      {type === "near_expiry" && (
        <div className="flex gap-2 flex-wrap">
          {Array.isArray(data.expiring_soon) && (
            <span className="badge badge-warning text-xs px-2 py-1">
              {t('expiring_soon')}: {(data.expiring_soon as unknown[]).length}
            </span>
          )}
          {Array.isArray(data.expired) && (
            <span className="badge badge-danger text-xs px-2 py-1">
              {t('expired')}: {(data.expired as unknown[]).length}
            </span>
          )}
        </div>
      )}

      {/* Generic table */}
      {rows.length > 0 && cols.length > 0 && (
        <GenericTable cols={cols} rows={rows} />
      )}

      {empty && (
        <EmptyState
          icon={<BarChart2 className="h-10 w-10" />}
          title={t('no_data')}
          message={t('select_dates')}
        />
      )}
    </div>
  );
}

// ── Generic table ────────────────────────────────────────────────────────────

function GenericTable({ cols, rows }: { cols: string[]; rows: Record<string, unknown>[] }) {
  const { t } = useTranslation('pos');
  const displayed = rows.slice(0, 200);
  return (
    <div>
      <div className="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-700">
        <table className="w-full text-xs">
          <thead>
            <tr className="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
              {cols.map((col) => (
                <th key={col} className="py-2.5 px-3 text-left font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap text-[11px]">
                  {col.replace(/_/g, " ")}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
            {displayed.map((row, i) => (
              <tr key={i} className="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                {cols.map((col) => (
                  <td key={col} className="py-2 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    {formatCell(col, row[col])}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between mt-2 px-1">
        <p className="text-xs text-gray-400">
          {rows.length > displayed.length
            ? `${t('showing_first')} ${displayed.length} ${t('of')} ${rows.length}`
            : `${rows.length} ${t('rows')}`}
        </p>
        {rows.length > displayed.length && (
          <p className="text-xs text-gray-400">{t('export_csv')}</p>
        )}
      </div>
    </div>
  );
}

// ── Section table (income / balance sheet sub-tables) ────────────────────────

function SectionTable({ title, rows, totalLabel, total }: {
  title: string;
  rows: Record<string, unknown>[];
  totalLabel?: string;
  total?: number;
}) {
  if (!rows || rows.length === 0) return null;
  const all  = Object.keys(rows[0]);
  const cols = ACCOUNT_COLS.filter((c) => all.includes(c));
  const display = cols.length ? cols : all.filter((c) => !ALWAYS_SKIP.has(c));

  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">{title}</h3>
        {total !== undefined && (
          <span className="text-sm font-bold text-gray-900 dark:text-white">
            {totalLabel ?? "Total"}: {total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
          </span>
        )}
      </div>
      <div className="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-700 mb-1">
        <table className="w-full text-xs">
          <thead>
            <tr className="bg-gray-50 dark:bg-gray-700/50">
              {display.map((c) => (
                <th key={c} className="py-2 px-3 text-left font-semibold text-[11px] uppercase tracking-wide text-gray-400 whitespace-nowrap">
                  {c === "total" ? "Amount" : c.replace(/_/g, " ")}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
            {rows.map((row, i) => (
              <tr key={i} className="hover:bg-gray-50/80 dark:hover:bg-gray-700/30">
                {display.map((col) => (
                  <td key={col} className="py-1.5 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    {formatCell(col, row[col])}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── KPI card ─────────────────────────────────────────────────────────────────

function KpiCard({ label, value, color }: { label: string; value: unknown; color?: string }) {
  const formatted = typeof value === "number"
    ? Number.isInteger(value)
      ? value.toLocaleString()
      : value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : String(value ?? "—");

  return (
    <div className="bg-gray-50 dark:bg-gray-700/60 rounded-xl p-3.5 border border-gray-100 dark:border-gray-600/30">
      <p className="text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide truncate capitalize">{label}</p>
      <p className={clsx("text-xl font-bold mt-1 leading-tight", color ?? "text-gray-900 dark:text-white")}>
        {formatted}
      </p>
    </div>
  );
}

// ── Specialized views ────────────────────────────────────────────────────────

function IncomeStatementView({ data }: { data: Record<string, unknown> }) {
  const revenues = (data.revenues as Record<string, unknown>[]) ?? [];
  const expenses = (data.expenses as Record<string, unknown>[]) ?? [];
  const totalRevenue = data.totalRevenue as number | undefined;
  const totalExpense = data.totalExpense as number | undefined;
  const netIncome    = data.netIncome    as number | undefined;
  const hasData = revenues.length > 0 || expenses.length > 0 ||
                  totalRevenue !== undefined || netIncome !== undefined;

  if (!hasData) {
    return (
      <EmptyState
        icon={<BarChart2 className="h-10 w-10" />}
        title="No income statement data"
        message="No posted journal entries found for this period."
      />
    );
  }

  return (
    <div className="space-y-5">
      <div className="grid grid-cols-3 gap-3">
        <KpiCard label="Total Revenue"  value={totalRevenue} />
        <KpiCard label="Total Expenses" value={totalExpense} />
        <KpiCard
          label="Net Income"
          value={netIncome}
          color={typeof netIncome === "number" && netIncome >= 0
            ? "text-green-600 dark:text-green-400"
            : "text-red-500 dark:text-red-400"}
        />
      </div>
      <SectionTable title="Revenue Accounts" rows={revenues} totalLabel="Total Revenue" total={totalRevenue} />
      <SectionTable title="Expense Accounts" rows={expenses} totalLabel="Total Expenses" total={totalExpense} />
    </div>
  );
}

function BalanceSheetView({ data }: { data: Record<string, unknown> }) {
  const assets      = (data.assets      as Record<string, unknown>[]) ?? [];
  const liabilities = (data.liabilities as Record<string, unknown>[]) ?? [];
  const equity      = (data.equity      as Record<string, unknown>[]) ?? [];

  if (!assets.length && !liabilities.length && !equity.length) {
    return (
      <EmptyState
        icon={<BarChart2 className="h-10 w-10" />}
        title="No balance sheet data"
        message="No account data found."
      />
    );
  }

  const sum = (arr: Record<string, unknown>[]) =>
    arr.reduce((acc, r) => acc + (typeof r.balance === "number" ? r.balance : parseFloat(String(r.balance ?? 0))), 0);

  return (
    <div className="space-y-5">
      <SectionTable title="Assets"      rows={assets}      totalLabel="Total Assets"      total={sum(assets)} />
      <SectionTable title="Liabilities" rows={liabilities} totalLabel="Total Liabilities" total={sum(liabilities)} />
      <SectionTable title="Equity"      rows={equity}      totalLabel="Total Equity"      total={sum(equity)} />
    </div>
  );
}

function NetProfitView({ data }: { data: Record<string, unknown> }) {
  const kpiDefs: Array<{ key: string; label: string; color?: string }> = [
    { key: "invoice_count",        label: "Invoices" },
    { key: "gross_sales",          label: "Gross Sales" },
    { key: "discounts",            label: "Discounts" },
    { key: "tax",                  label: "Tax" },
    { key: "net_sales",            label: "Net Sales" },
    { key: "returns",              label: "Returns" },
    { key: "net_revenue",          label: "Net Revenue" },
    { key: "cogs",                 label: "COGS" },
    { key: "gross_profit",         label: "Gross Profit",
      color: "text-emerald-600 dark:text-emerald-400" },
    { key: "operating_expenses",   label: "Operating Exp." },
    { key: "net_profit",           label: "Net Profit",
      color: typeof data.net_profit === "number" && (data.net_profit as number) >= 0
        ? "text-green-600 dark:text-green-400" : "text-red-500 dark:text-red-400" },
    { key: "gross_margin_pct",     label: "Gross Margin %" },
    { key: "net_margin_pct",       label: "Net Margin %" },
  ];

  const visible = kpiDefs.filter(({ key }) => key in data && data[key] !== undefined);

  if (!visible.length) {
    return (
      <EmptyState
        icon={<BarChart2 className="h-10 w-10" />}
        title="No data for this period"
        message="No completed invoices found."
      />
    );
  }

  const cmp = data.comparison as Record<string, unknown> | undefined;

  return (
    <div className="space-y-5">
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        {visible.map(({ key, label, color }) => (
          <KpiCard key={key} label={label} value={data[key]} color={color} />
        ))}
      </div>

      {cmp && (
        <div>
          <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">vs Previous Period</h3>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            {[
              { k: "prev_net_sales",          label: "Prev Net Sales" },
              { k: "prev_gross_profit",        label: "Prev Gross Profit" },
              { k: "prev_net_profit",          label: "Prev Net Profit" },
              { k: "net_profit_change_pct",    label: "Net Profit Δ %" },
              { k: "gross_profit_change_pct",  label: "Gross Profit Δ %" },
            ].filter(({ k }) => k in cmp).map(({ k, label }) => (
              <KpiCard key={k} label={label} value={cmp[k]} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function CashFlowView({ data, rows }: { data: Record<string, unknown>; rows: Record<string, unknown>[] }) {
  const netCashFlow  = data.net_cash_flow as number | undefined;
  const inflowsObj   = data.inflows  as Record<string, unknown> | undefined;
  const outflowsObj  = data.outflows as Record<string, unknown> | undefined;
  const totalIn      = inflowsObj?.total  as number | undefined;
  const totalOut     = outflowsObj?.total as number | undefined;

  return (
    <div className="space-y-5">
      <div className="grid grid-cols-3 gap-3">
        <KpiCard label="Total Inflows"  value={totalIn}   color="text-green-600 dark:text-green-400" />
        <KpiCard label="Total Outflows" value={totalOut}  color="text-red-500 dark:text-red-400" />
        <KpiCard
          label="Net Cash Flow"
          value={netCashFlow}
          color={typeof netCashFlow === "number" && netCashFlow >= 0
            ? "text-green-600 dark:text-green-400" : "text-red-500 dark:text-red-400"}
        />
      </div>

      {rows.length > 0 && (
        <div>
          <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Daily Breakdown</h3>
          <GenericTable cols={["date","inflow","outflow","net"]} rows={rows} />
        </div>
      )}
    </div>
  );
}
