import { useState } from "react";
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

interface DateRange {
  from: string;
  to: string;
}

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = new Date(
  new Date().getFullYear(),
  new Date().getMonth(),
  1,
)
  .toISOString()
  .slice(0, 10);

const REPORT_DEFS: Array<{
  key: ReportType;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  color: string;
  endpoint: string;
  method: "get" | "post";
}> = [
  {
    key: "sales",
    label: "Sales Report",
    icon: ShoppingCart,
    color: "blue",
    endpoint: "/reports/sales",
    method: "post",
  },
  {
    key: "returns",
    label: "Returns Report",
    icon: RotateCcw,
    color: "orange",
    endpoint: "/reports/returns",
    method: "post",
  },
  {
    key: "income",
    label: "Income Statement",
    icon: TrendingUp,
    color: "green",
    endpoint: "/reports/income-statement",
    method: "post",
  },
  {
    key: "cashflow",
    label: "Cash Flow",
    icon: DollarSign,
    color: "emerald",
    endpoint: "/reports/cash-flow",
    method: "post",
  },
  {
    key: "balance_sheet",
    label: "Balance Sheet",
    icon: BarChart2,
    color: "indigo",
    endpoint: "/reports/balance-sheet",
    method: "get",
  },
  {
    key: "inventory_valuation",
    label: "Inventory Valuation",
    icon: Package,
    color: "purple",
    endpoint: "/reports/inventory-valuation",
    method: "get",
  },
  {
    key: "aged_receivables",
    label: "Aged Receivables",
    icon: Users,
    color: "red",
    endpoint: "/reports/aged-receivables",
    method: "get",
  },
  {
    key: "aged_payables",
    label: "Aged Payables",
    icon: Truck,
    color: "amber",
    endpoint: "/reports/aged-payables",
    method: "get",
  },
  {
    key: "best_selling",
    label: "Best Selling Products",
    icon: TrendingUp,
    color: "sky",
    endpoint: "/reports/best-selling",
    method: "post",
  },
  {
    key: "cashier_performance",
    label: "Cashier Performance",
    icon: Users,
    color: "violet",
    endpoint: "/reports/cashier-performance",
    method: "post",
  },
  {
    key: "near_expiry",
    label: "Near Expiry",
    icon: AlertTriangle,
    color: "yellow",
    endpoint: "/reports/near-expiry",
    method: "get",
  },
  {
    key: "net_profit",
    label: "Net Profit",
    icon: DollarSign,
    color: "green",
    endpoint: "/reports/net-profit",
    method: "post",
  },
  {
    key: "tax",
    label: "Tax Report",
    icon: BarChart2,
    color: "slate",
    endpoint: "/reports/tax",
    method: "post",
  },
  {
    key: "waste",
    label: "Waste Ratio",
    icon: AlertTriangle,
    color: "red",
    endpoint: "/reports/waste-ratio",
    method: "get",
  },
];

export default function ReportsPage() {
  const { hasPermission } = usePermission();
  const [activeReport, setActiveReport] = useState<ReportType>("sales");
  const [dateRange, setDateRange] = useState<DateRange>({
    from: firstOfMonth,
    to: today,
  });

  const activeDef = REPORT_DEFS.find((r) => r.key === activeReport)!;

  const { data, isLoading, refetch, isFetching } = useQuery({
    queryKey: ["report", activeReport, dateRange],
    queryFn: () => {
      const params = { start_date: dateRange.from, end_date: dateRange.to };
      return activeDef.method === "post"
        ? apiPost<unknown>(activeDef.endpoint, params)
        : apiGet<unknown>(activeDef.endpoint, params);
    },
    staleTime: 60_000,
    enabled: hasPermission("view_reports"),
  });

  const handleExportCSV = () => {
    if (!data) return;
    const rows = Array.isArray(data)
      ? data
      : ((data as { data?: unknown[] })?.data ?? []);
    if (!Array.isArray(rows) || rows.length === 0) return;
    const headers = Object.keys(rows[0] as object);
    const csv = [
      headers.join(","),
      ...rows.map((r) =>
        headers
          .map((h) => JSON.stringify((r as Record<string, unknown>)[h] ?? ""))
          .join(","),
      ),
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
      <div className="card p-8 text-center text-gray-400">
        <BarChart2 className="h-12 w-12 mx-auto mb-3 opacity-40" />
        <p>Access to reports requires view_reports permission</p>
      </div>
    );
  }

  const reportData = data as Record<string, unknown> | null;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <BarChart2 className="h-6 w-6 text-primary-500" /> Reports
        </h1>
        <div className="flex gap-2">
          <button
            onClick={() => refetch()}
            disabled={isFetching}
            className="btn btn-secondary flex items-center gap-2 text-sm"
          >
            <RefreshCw
              className={clsx("h-4 w-4", isFetching && "animate-spin")}
            />
          </button>
          <button
            onClick={handleExportCSV}
            className="btn btn-secondary flex items-center gap-2 text-sm"
          >
            <Download className="h-4 w-4" /> Export CSV
          </button>
        </div>
      </div>

      <div className="flex gap-4 flex-wrap">
        <div className="flex gap-2 items-center">
          <label className="text-sm text-gray-500">From:</label>
          <input
            type="date"
            value={dateRange.from}
            onChange={(e) =>
              setDateRange((p) => ({ ...p, from: e.target.value }))
            }
            className="input text-sm"
          />
        </div>
        <div className="flex gap-2 items-center">
          <label className="text-sm text-gray-500">To:</label>
          <input
            type="date"
            value={dateRange.to}
            onChange={(e) =>
              setDateRange((p) => ({ ...p, to: e.target.value }))
            }
            className="input text-sm"
          />
        </div>
      </div>

      <div className="flex gap-4">
        {/* Report list */}
        <div className="w-56 flex-shrink-0 space-y-1">
          {REPORT_DEFS.map((r) => (
            <button
              key={r.key}
              onClick={() => setActiveReport(r.key)}
              className={clsx(
                "w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-2.5",
                activeReport === r.key
                  ? "bg-primary-600 text-white"
                  : "text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white",
              )}
            >
              <r.icon className="h-4 w-4 flex-shrink-0" />
              <span className="truncate">{r.label}</span>
            </button>
          ))}
        </div>

        {/* Report content */}
        <div className="flex-1 card overflow-hidden min-h-96">
          <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <activeDef.icon className="h-5 w-5 text-primary-500" />
            <h2 className="font-semibold text-gray-900 dark:text-white">
              {activeDef.label}
            </h2>
            <span className="text-xs text-gray-400 ml-auto">
              {dateRange.from} — {dateRange.to}
            </span>
          </div>

          {isLoading ? (
            <div className="flex h-64 items-center justify-center">
              <LoadingSpinner size="lg" />
            </div>
          ) : (
            <div className="p-4 overflow-auto">
              {!reportData ? (
                <div className="text-center text-gray-400 py-12">
                  No data available for this period
                </div>
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

function ReportContent({
  type,
  data,
}: {
  type: ReportType;
  data: Record<string, unknown>;
}) {
  // Generic table renderer for report data
  const rows: Record<string, unknown>[] = Array.isArray(data)
    ? data
    : ((data?.data as Record<string, unknown>[]) ?? []);

  // Show summary KPIs if present
  const summaryFields = [
    "total_sales",
    "total_revenue",
    "total_invoices",
    "total_amount",
    "gross_profit",
    "net_profit",
    "total_tax",
    "total_returns",
    "total_products",
  ];
  const summaryKpis = summaryFields
    .filter((f) => data[f] !== undefined)
    .map((f) => ({ key: f, value: data[f] }));

  return (
    <div className="space-y-4">
      {summaryKpis.length > 0 && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          {summaryKpis.map(({ key, value }) => (
            <div
              key={key}
              className="bg-gray-50 dark:bg-gray-700 rounded-lg p-3"
            >
              <p className="text-xs text-gray-500 capitalize">
                {key.replace(/_/g, " ")}
              </p>
              <p className="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                {typeof value === "number"
                  ? value.toLocaleString(undefined, {
                      minimumFractionDigits: 2,
                    })
                  : String(value)}
              </p>
            </div>
          ))}
        </div>
      )}

      {rows.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-gray-200 dark:border-gray-700">
                {Object.keys(rows[0]).map((col) => (
                  <th
                    key={col}
                    className="py-2 px-2 text-left font-semibold uppercase text-gray-400 whitespace-nowrap"
                  >
                    {col.replace(/_/g, " ")}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {rows.slice(0, 100).map((row, i) => (
                <tr
                  key={i}
                  className="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  {Object.values(row).map((val, j) => (
                    <td
                      key={j}
                      className="py-1.5 px-2 text-gray-700 dark:text-gray-300"
                    >
                      {typeof val === "number"
                        ? val.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          })
                        : String(val ?? "—")}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
          {rows.length > 100 && (
            <p className="text-xs text-gray-400 text-center mt-2">
              Showing first 100 of {rows.length} rows. Export CSV for full data.
            </p>
          )}
        </div>
      )}

      {rows.length === 0 && summaryKpis.length === 0 && (
        <div className="text-center text-gray-400 py-8">
          <BarChart2 className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p>No data for the selected period</p>
        </div>
      )}
    </div>
  );
}
