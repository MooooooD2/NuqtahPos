import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPost } from "@/services/api";
import { usePermission } from "@/hooks/usePermission";
import Modal from "@/components/common/Modal";
import LoadingSpinner from "@/components/common/LoadingSpinner";
import ProductSelect from "@/components/common/ProductSelect";
import {
  Package,
  AlertTriangle,
  XCircle,
  ArrowUpDown,
  Clock,
  Search,
} from "lucide-react";
import { clsx } from "clsx";
import toast from "react-hot-toast";

interface StockItem {
  id?: number;
  product_id?: number;
  name?: string;
  product_name?: string;
  quantity: number;
  min_stock: number;
  low_stock?: boolean;
  barcode?: string;
  category?: string;
}
interface StockHealth {
  total_products: number;
  in_stock: number;
  low_stock: number;
  out_of_stock: number;
}
interface BatchItem {
  id: number;
  product_name: string;
  quantity: number;
  expiry_date: string;
  batch_number: string;
  days_until_expiry: number;
}
interface AllProduct {
  id: number;
  name: string;
  quantity: number;
  min_stock: number;
  low_stock: boolean;
  barcode?: string;
  category?: string;
}

const adjForm = {
  product_id: "",
  product_name: "",
  quantity: "",
  reason: "adjustment",
  notes: "",
};

export default function InventoryPage() {
  const { t } = useTranslation('pos');
  const { hasPermission } = usePermission();
  const qc = useQueryClient();
  const [tab, setTab] = useState<"all" | "low" | "out" | "expiry" | "movements">("all");
  const [allSearch, setAllSearch] = useState("");
  const [allPage, setAllPage] = useState(1);
  const [adjModal, setAdjModal] = useState(false);
  const [form, setForm] = useState({ ...adjForm });

  const {
    data: healthData,
    isLoading: healthLoading,
    error: healthError,
  } = useQuery({
    queryKey: ["stock-health"],
    queryFn: () =>
      apiGet<{ success: boolean; data: StockHealth }>("/stock/health"),
    staleTime: 60_000,
  });
  const {
    data: lowData,
    isLoading: lowLoading,
    error: lowError,
  } = useQuery({
    queryKey: ["stock-low"],
    queryFn: () =>
      apiGet<{ success: boolean; data: StockItem[] }>("/stock/low-stock"),
    staleTime: 60_000,
    enabled: tab === "low",
  });
  const {
    data: outData,
    isLoading: outLoading,
    error: outError,
  } = useQuery({
    queryKey: ["stock-out"],
    queryFn: () =>
      apiGet<{ success: boolean; data: StockItem[] }>("/stock/out-of-stock"),
    staleTime: 60_000,
    enabled: tab === "out",
  });
  const {
    data: expiryData,
    isLoading: expiryLoading,
    error: expiryError,
  } = useQuery({
    queryKey: ["stock-expiry"],
    queryFn: () =>
      apiGet<{ success: boolean; data: BatchItem[] }>(
        "/stock/near-expiry?days=30",
      ),
    staleTime: 60_000,
    enabled: tab === "expiry",
  });

  const {
    data: allData,
    isLoading: allLoading,
    error: allError,
  } = useQuery({
    queryKey: ["stock-all", allPage, allSearch],
    queryFn: () =>
      apiGet<{ success: boolean; data: AllProduct[]; total?: number; pages?: number }>(
        "/stock/all-products",
        { page: allPage, per_page: 50, search: allSearch || undefined },
      ),
    staleTime: 30_000,
    enabled: tab === "all",
  });

  const health = healthData?.data;
  const lowItems = lowData?.data ?? [];
  const outItems = outData?.data ?? [];
  const expiryItems = expiryData?.data ?? [];
  const allItems = allData?.data ?? [];
  const allTotal = allData?.total ?? 0;
  const allPages = allData?.pages ?? 1;

  const canAdjust = hasPermission("add_stock", "manage_roles");

  const adjMutation = useMutation({
    mutationFn: (payload: object) => apiPost("/stock/adjustment", payload),
    onSuccess: () => {
      toast.success(t('stock_adjusted'));
      qc.invalidateQueries({ queryKey: ["stock-low"] });
      qc.invalidateQueries({ queryKey: ["stock-health"] });
      setAdjModal(false);
    },
    onError: () => toast.error(t('save_failed')),
  });

  const kpis = [
    {
      label: t('total_products'),
      value: health?.total_products ?? 0,
      icon: Package,
      color: "blue",
    },
    {
      label: t('in'),
      value: health?.in_stock ?? 0,
      icon: Package,
      color: "green",
    },
    {
      label: t('low_stock'),
      value: health?.low_stock ?? 0,
      icon: AlertTriangle,
      color: "amber",
    },
    {
      label: t('out_of_stock'),
      value: health?.out_of_stock ?? 0,
      icon: XCircle,
      color: "red",
    },
  ];

  const stockTable = (
    items: StockItem[],
    loading: boolean,
    emptyMsg: string,
  ) =>
    loading ? (
      <div className="flex h-40 items-center justify-center">
        <LoadingSpinner />
      </div>
    ) : (
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-700">
            <tr>
              {[
                t('product'),
                t('category'),
                t('barcode'),
                t('current_stock'),
                t('min_stock'),
              ].map((h) => (
                <th
                  key={h}
                  className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500"
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {items.length === 0 ? (
              <tr>
                <td
                  colSpan={5}
                  className="px-4 py-10 text-center text-gray-400"
                >
                  {emptyMsg}
                </td>
              </tr>
            ) : (
              items.map((p) => (
                <tr
                  key={p.id ?? p.product_id}
                  className="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                    {p.name ?? p.product_name ?? "—"}
                  </td>
                  <td className="px-4 py-3 text-gray-500">
                    {p.category ?? "—"}
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-gray-400">
                    {p.barcode ?? "—"}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={clsx(
                        "font-bold",
                        p.quantity <= 0
                          ? "text-red-600"
                          : p.low_stock
                            ? "text-amber-600"
                            : "text-green-600",
                      )}
                    >
                      {p.quantity}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500">{p.min_stock}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    );

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Package className="h-6 w-6 text-primary-500" /> {t('inventory')}
        </h1>
        {canAdjust && (
          <button
            onClick={() => {
              setForm({ ...adjForm });
              setAdjModal(true);
            }}
            className="btn btn-secondary flex items-center gap-2"
          >
            <ArrowUpDown className="h-4 w-4" /> {t('adjust_stock')}
          </button>
        )}
      </div>

      {/* KPI Cards */}
      {healthLoading ? (
        <div className="h-24 flex items-center justify-center">
          <LoadingSpinner />
        </div>
      ) : healthError ? (
        <div className="card p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <p className="text-red-700 dark:text-red-300 text-sm">
            <strong>Error loading stock health:</strong> {String(healthError)}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((kpi) => (
            <div key={kpi.label} className="card p-4 flex items-center gap-4">
              <div
                className={`h-10 w-10 rounded-xl flex items-center justify-center bg-${kpi.color}-100 dark:bg-${kpi.color}-900/30`}
              >
                <kpi.icon
                  className={`h-5 w-5 text-${kpi.color}-600 dark:text-${kpi.color}-400`}
                />
              </div>
              <div>
                <p className="text-xs text-gray-500">{kpi.label}</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                  {kpi.value}
                </p>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(
          [
            { key: "all", label: t('all_products') },
            { key: "low", label: t('low_stock_items'), count: health?.low_stock },
            { key: "out", label: t('out_of_stock_items'), count: health?.out_of_stock },
            { key: "expiry", label: t('near_expiry'), icon: Clock },
            { key: "movements", label: t('movements') },
          ] as const
        ).map((tb) => (
          <button
            key={tb.key}
            onClick={() => setTab(tb.key)}
            className={clsx(
              "px-3 py-1.5 rounded-md text-sm font-medium transition-colors flex items-center gap-1.5",
              tab === tb.key
                ? "bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm"
                : "text-gray-500 hover:text-gray-700",
            )}
          >
            {tb.label}
            {tb.count !== undefined && tb.count > 0 && (
              <span
                className={clsx(
                  "text-xs rounded-full px-1.5 py-0.5 font-semibold",
                  tb.key === "low"
                    ? "bg-amber-100 text-amber-700"
                    : "bg-red-100 text-red-700",
                )}
              >
                {tb.count}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* All Products search bar */}
      {tab === "all" && (
        <div className="relative w-64">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            value={allSearch}
            onChange={(e) => { setAllSearch(e.target.value); setAllPage(1); }}
            placeholder={t('search')}
            className="input pl-9 w-full"
          />
        </div>
      )}

      <div className="card overflow-hidden">
        {tab === "all" && (
          allError ? (
            <div className="p-4 text-red-600 dark:text-red-400 text-sm">
              <strong>Error loading products:</strong> {String(allError)}
            </div>
          ) : allLoading ? (
            <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      {[t('product'), t('category'), t('barcode'), t('current_stock'), t('min_stock'), t('status')].map((h) => (
                        <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {allItems.length === 0 ? (
                      <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
                    ) : allItems.map((p) => (
                      <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                        <td className="px-4 py-3 text-gray-500">{p.category ?? "—"}</td>
                        <td className="px-4 py-3 font-mono text-xs text-gray-400">{p.barcode ?? "—"}</td>
                        <td className="px-4 py-3">
                          <span className={clsx("font-bold text-lg",
                            p.quantity <= 0 ? "text-red-600" : p.low_stock ? "text-amber-600" : "text-green-600"
                          )}>
                            {p.quantity}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-gray-500">{p.min_stock}</td>
                        <td className="px-4 py-3">
                          {p.quantity <= 0
                            ? <span className="badge badge-danger">{t('out_of_stock')}</span>
                            : p.low_stock
                              ? <span className="badge badge-warning">{t('low_stock')}</span>
                              : <span className="badge badge-success">{t('in')}</span>}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {allTotal > 50 && (
                <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
                  <span className="text-sm text-gray-500">{t('page')} {allPage} / {allPages} · {allTotal}</span>
                  <div className="flex gap-2">
                    <button onClick={() => setAllPage((p) => Math.max(1, p - 1))} disabled={allPage === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
                    <button onClick={() => setAllPage((p) => p + 1)} disabled={allPage >= allPages} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
                  </div>
                </div>
              )}
            </>
          )
        )}
        {tab === "low" &&
          (lowError ? (
            <div className="p-4 text-red-600 dark:text-red-400 text-sm">
              <strong>Error loading low stock:</strong> {String(lowError)}
            </div>
          ) : (
            stockTable(lowItems, lowLoading, t('stock_healthy'))
          ))}
        {tab === "out" &&
          (outError ? (
            <div className="p-4 text-red-600 dark:text-red-400 text-sm">
              <strong>Error loading out of stock:</strong> {String(outError)}
            </div>
          ) : (
            stockTable(outItems, outLoading, t('no_out_of_stock_items'))
          ))}
        {tab === "expiry" &&
          (expiryError ? (
            <div className="p-4 text-red-600 dark:text-red-400 text-sm">
              <strong>Error loading expiry:</strong> {String(expiryError)}
            </div>
          ) : expiryLoading ? (
            <div className="flex h-40 items-center justify-center">
              <LoadingSpinner />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[
                      t('product'),
                      t('batch_number'),
                      t('quantity'),
                      t('expiry_date'),
                      t('date'),
                    ].map((h) => (
                      <th
                        key={h}
                        className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500"
                      >
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {expiryItems.length === 0 ? (
                    <tr>
                      <td
                        colSpan={5}
                        className="px-4 py-10 text-center text-gray-400"
                      >
                        {t('no_data')}
                      </td>
                    </tr>
                  ) : (
                    expiryItems.map((b) => (
                      <tr
                        key={b.id}
                        className="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                      >
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                          {b.product_name}
                        </td>
                        <td className="px-4 py-3 font-mono text-xs text-gray-400">
                          {b.batch_number}
                        </td>
                        <td className="px-4 py-3 text-gray-900 dark:text-white">
                          {b.quantity}
                        </td>
                        <td className="px-4 py-3 text-gray-500">
                          {b.expiry_date?.slice(0, 10)}
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={clsx(
                              "badge",
                              b.days_until_expiry <= 7
                                ? "badge-danger"
                                : b.days_until_expiry <= 14
                                  ? "badge-warning"
                                  : "badge-warning",
                            )}
                          >
                            {t('days_count', { n: b.days_until_expiry })}
                          </span>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          ))}
        {tab === "movements" && (
          <div className="p-6 text-center text-gray-400">
            <ArrowUpDown className="h-10 w-10 mx-auto mb-3 opacity-40" />
            <p>{t('no_movements_found')}</p>
          </div>
        )}
      </div>

      {/* Stock Adjustment Modal */}
      <Modal
        open={adjModal}
        onClose={() => setAdjModal(false)}
        title={t('stock_adjustment')}
        size="md"
        footer={
          <>
            <button
              onClick={() => setAdjModal(false)}
              className="btn btn-secondary"
            >
              {t('cancel')}
            </button>
            <button
              onClick={() => {
                if (!form.product_id || !form.quantity)
                  return toast.error(t('error'));
                adjMutation.mutate({
                  product_id: parseInt(form.product_id),
                  quantity: parseInt(form.quantity),
                  reason: form.reason,
                  notes: form.notes || undefined,
                });
              }}
              disabled={adjMutation.isPending}
              className="btn btn-primary"
            >
              {adjMutation.isPending ? t('loading') : t('adjust_stock')}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="label">{t('product')}</label>
            <ProductSelect
              value={form.product_id}
              onChange={(id, name) =>
                setForm((p) => ({ ...p, product_id: id, product_name: name }))
              }
              className="w-full"
            />
          </div>
          <div>
            <label className="label">{t('adjustment_qty')}</label>
            <input
              value={form.quantity}
              onChange={(e) =>
                setForm((p) => ({ ...p, quantity: e.target.value }))
              }
              type="number"
              className="input w-full"
              placeholder="e.g. 10 or -5"
            />
          </div>
          <div>
            <label className="label">{t('reason')}</label>
            <select
              value={form.reason}
              onChange={(e) =>
                setForm((p) => ({ ...p, reason: e.target.value }))
              }
              className="input w-full"
            >
              <option value="adjustment">{t('adjustment')}</option>
              <option value="damage">{t('damage_loss')}</option>
              <option value="count">{t('physical_count')}</option>
              <option value="return">{t('return_to_stock_type')}</option>
            </select>
          </div>
          <div>
            <label className="label">{t('notes')}</label>
            <input
              value={form.notes}
              onChange={(e) =>
                setForm((p) => ({ ...p, notes: e.target.value }))
              }
              className="input w-full"
              placeholder="Optional"
            />
          </div>
        </div>
      </Modal>
    </div>
  );
}
