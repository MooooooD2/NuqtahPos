import { useState, useMemo, useEffect, useRef } from "react";
import { useTranslation } from "react-i18next";
import JsBarcode from "jsbarcode";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { apiGet, apiPost, apiPut, apiDelete, apiUpload } from "@/services/api";
import { usePermission } from "@/hooks/usePermission";
import Modal from "@/components/common/Modal";
import ConfirmDialog from "@/components/common/ConfirmDialog";
import BarcodeScanner from "@/components/common/BarcodeScanner";
import LoadingSpinner from "@/components/common/LoadingSpinner";
import {
  Package,
  Plus,
  Pencil,
  Trash2,
  Search,
  AlertTriangle,
  ScanLine,
  ImagePlus,
} from "lucide-react";
import { clsx } from "clsx";
import toast from "react-hot-toast";

interface ApiProduct {
  id: number;
  name: string;
  price: string;
  cost_price?: string;
  barcode: string | null;
  category: string | null;
  quantity: number;
  min_stock: number;
  low_stock: boolean;
  unit_name?: string | null;
  image_url?: string | null;
}

const emptyForm = {
  name: "",
  price: "",
  cost_price: "",
  category: "",
  barcode: "",
  min_stock: "5",
  initial_quantity: "0",
  quantity: "",
};

const makeEan13 = () => {
  const digits = Array.from({ length: 12 }, () =>
    Math.floor(Math.random() * 10),
  );
  const sum = digits.reduce(
    (acc, digit, idx) => acc + digit * (idx % 2 === 0 ? 1 : 3),
    0,
  );
  const check = (10 - (sum % 10)) % 10;
  return [...digits, check].join("");
};

export default function ProductsPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission();
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [modal, setModal] = useState<"add" | "edit" | null>(null);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState({ ...emptyForm });
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [showScanner, setShowScanner] = useState(false);
  const barcodeSvgRef = useRef<SVGSVGElement | null>(null);
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
  };

  useEffect(() => {
    if (!barcodeSvgRef.current || !form.barcode) return;
    barcodeSvgRef.current.innerHTML = "";
    try {
      JsBarcode(barcodeSvgRef.current, form.barcode, {
        format: form.barcode.length === 13 ? "ean13" : "code128",
        displayValue: true,
        fontSize: 14,
        height: 80,
        width: 2,
        margin: 10,
      });
    } catch (error) {
      // Ignore invalid barcode rendering until a valid value is entered.
    }
  }, [form.barcode]);

  const generateBarcode = () => {
    const code = makeEan13();
    setForm((prev) => ({ ...prev, barcode: code }));
    toast.success("Generated barcode for product");
  };

  const downloadBarcode = () => {
    if (!form.barcode) {
      return toast.error("Enter or generate a barcode before downloading");
    }
    if (!barcodeSvgRef.current) return;

    const svgData = new XMLSerializer().serializeToString(
      barcodeSvgRef.current,
    );
    const blob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `barcode-${form.barcode}.svg`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  };

  const { data, isLoading } = useQuery({
    queryKey: ["products", page, search],
    queryFn: () =>
      apiGet<{ success: boolean; products: ApiProduct[]; total?: number }>(
        "/products",
        { page, per_page: 20, search: search || undefined },
      ),
    staleTime: 30_000,
  });

  const products = data?.products ?? [];

  // Derive categories from loaded products — no separate API call needed
  const categories = useMemo(() => {
    const cats = new Set<string>();
    products.forEach((p) => {
      if (p.category) cats.add(p.category);
    });
    return Array.from(cats).sort();
  }, [products]);
  const canCreate = hasPermission("create_products", "manage_products");
  const canEdit = hasPermission("edit_products", "manage_products");
  const canDelete = hasPermission("delete_products", "manage_products");

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm((p) => ({ ...p, [field]: e.target.value })),
  });

  const openAdd = () => {
    setForm({ ...emptyForm });
    setEditId(null);
    setImageFile(null);
    setImagePreview(null);
    setModal("add");
  };
  const openEdit = (p: ApiProduct) => {
    setForm({
      name: p.name,
      price: p.price,
      cost_price: p.cost_price ?? "",
      category: p.category ?? "",
      barcode: p.barcode ?? "",
      min_stock: String(p.min_stock),
      initial_quantity: "",
      quantity: String(p.quantity),
    });
    setEditId(p.id);
    setImageFile(null);
    setImagePreview(p.image_url ?? null);
    setModal("edit");
  };

  const saveMutation = useMutation({
    mutationFn: async (payload: object) => {
      const res = await (modal === "edit" && editId
        ? apiPut<{ product: ApiProduct }>(`/products/${editId}`, payload)
        : apiPost<{ product: ApiProduct }>("/products", payload));
      const productId = modal === "edit" ? editId : res.product?.id;
      if (imageFile && productId) {
        await apiUpload(`/products/${productId}/image`, imageFile);
      }
      return res;
    },
    onSuccess: () => {
      toast.success(editId ? "Product updated" : "Product created");
      qc.invalidateQueries({ queryKey: ["products"] });
      setModal(null);
    },
    onError: () => toast.error("Failed to save product"),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/products/${id}`),
    onSuccess: () => {
      toast.success("Product deleted");
      qc.invalidateQueries({ queryKey: ["products"] });
      setDeleteId(null);
    },
    onError: () => toast.error("Failed to delete product"),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name || !form.price)
      return toast.error("Name and price are required");
    const payload: Record<string, unknown> = {
      name: form.name,
      price: form.price,
      cost_price: form.cost_price || "0",
      category: form.category || undefined,
      barcode: form.barcode || undefined,
      min_stock: parseInt(form.min_stock) || 5,
    };
    if (modal === "add") {
      payload.initial_quantity = parseInt(form.initial_quantity) || 0;
    } else {
      payload.quantity = parseInt(form.quantity) || 0;
    }
    saveMutation.mutate(payload);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Package className="h-6 w-6 text-primary-500" /> {t('products')}
          {data?.total !== undefined && (
            <span className="text-sm font-normal text-gray-400">
              ({data.total})
            </span>
          )}
        </h1>
        {canCreate && (
          <button
            onClick={openAdd}
            className="btn btn-primary flex items-center gap-2"
          >
            <Plus className="h-4 w-4" /> {t('add_product')}
          </button>
        )}
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
          placeholder={t('search') + '…'}
          className="input pl-9 w-full"
        />
      </div>

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="flex h-64 items-center justify-center">
            <LoadingSpinner size="lg" />
          </div>
        ) : (
          <>
            {/* ── Desktop table ─────────────────────── lg+ ── */}
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[700px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {["", t('name'), t('barcode'), t('category'), t('selling_price'), t('cost_price'), t('current_stock'), t('status'), ""].map((h, i) => (
                      <th key={`${h}-${i}`} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {products.length === 0 ? (
                    <tr><td colSpan={9} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : products.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3">
                        <div className="h-10 w-10 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden flex items-center justify-center shrink-0">
                          {p.image_url ? (
                            <img src={p.image_url} alt={p.name} className="h-full w-full object-cover" />
                          ) : (
                            <Package className="h-4 w-4 text-gray-300" />
                          )}
                        </div>
                      </td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                      <td className="px-4 py-3 text-gray-400 font-mono text-xs">{p.barcode ?? "—"}</td>
                      <td className="px-4 py-3 text-gray-500">{p.category ?? "—"}</td>
                      <td className="px-4 py-3 font-semibold text-primary-600">{parseFloat(p.price).toFixed(2)}</td>
                      <td className="px-4 py-3 text-gray-500">{p.cost_price ? parseFloat(p.cost_price).toFixed(2) : "—"}</td>
                      <td className="px-4 py-3">
                        <span className={clsx("font-semibold", p.low_stock ? "text-red-600" : "text-gray-900 dark:text-white")}>{p.quantity}</span>
                        {p.low_stock && <AlertTriangle className="h-3.5 w-3.5 text-red-500 inline ml-1" />}
                      </td>
                      <td className="px-4 py-3">
                        <span className={clsx("badge", p.quantity > 0 ? "badge-success" : "badge-danger")}>{p.quantity > 0 ? t('active_status') : t('out_of_stock')}</span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 justify-end">
                          {canEdit && <button onClick={() => openEdit(p)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>}
                          {canDelete && <button onClick={() => setDeleteId(p.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* ── Mobile cards ──────────────────────── <lg ── */}
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {products.length === 0 ? (
                <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
              ) : products.map((p) => (
                <div key={p.id} className="p-4 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2 min-w-0">
                      <div className="h-10 w-10 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden flex items-center justify-center shrink-0">
                        {p.image_url ? (
                          <img src={p.image_url} alt={p.name} className="h-full w-full object-cover" />
                        ) : (
                          <Package className="h-4 w-4 text-gray-300" />
                        )}
                      </div>
                      <p className="font-semibold text-gray-900 dark:text-white text-sm leading-snug">{p.name}</p>
                    </div>
                    <span className={clsx("badge shrink-0", p.quantity > 0 ? "badge-success" : "badge-danger")}>{p.quantity > 0 ? t('active_status') : t('out_of_stock')}</span>
                  </div>
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                    <span className="font-bold text-primary-600 text-sm">{parseFloat(p.price).toFixed(2)}</span>
                    {p.category && <span className="badge badge-gray">{p.category}</span>}
                    <span className={clsx("flex items-center gap-1", p.low_stock ? "text-red-600 font-semibold" : "")}>
                      {isAr ? 'المخزون:' : 'Stock:'} {p.quantity}
                      {p.low_stock && <AlertTriangle className="h-3 w-3" />}
                    </span>
                  </div>
                  {p.barcode && <p className="font-mono text-xs text-gray-400">{p.barcode}</p>}
                  {(canEdit || canDelete) && (
                    <div className="flex gap-2 pt-1">
                      {canEdit && (
                        <button onClick={() => openEdit(p)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400 hover:bg-primary-100 transition-colors font-medium">
                          <Pencil className="h-3.5 w-3.5" />{isAr ? 'تعديل' : 'Edit'}
                        </button>
                      )}
                      {canDelete && (
                        <button onClick={() => setDeleteId(p.id)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition-colors font-medium">
                          <Trash2 className="h-3.5 w-3.5" />{isAr ? 'حذف' : 'Delete'}
                        </button>
                      )}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </>
        )}
        {(data?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">{t('page')} {page}</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={products.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      <Modal
        open={modal !== null}
        onClose={() => setModal(null)}
        title={modal === "edit" ? t('edit_product') : t('add_product')}
        size="lg"
        footer={
          <>
            <button
              onClick={() => setModal(null)}
              className="btn btn-secondary"
            >
              {t('cancel')}
            </button>
            <button
              onClick={handleSubmit}
              disabled={saveMutation.isPending}
              className="btn btn-primary"
            >
              {saveMutation.isPending
                ? "Saving…"
                : modal === "edit"
                  ? t('update')
                  : t('create')}
            </button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="label">{t('image')}</label>
              <div className="flex items-center gap-3">
                <div className="h-16 w-16 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 flex items-center justify-center overflow-hidden shrink-0">
                  {imagePreview ? (
                    <img src={imagePreview} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <ImagePlus className="h-6 w-6 text-gray-300" />
                  )}
                </div>
                <input
                  type="file"
                  accept="image/jpeg,image/jpg,image/png,image/webp"
                  onChange={handleImageChange}
                  className="input flex-1 text-sm"
                />
              </div>
            </div>
            <div className="col-span-2">
              <label className="label">{t('name')} *</label>
              <input
                {...f("name")}
                className="input w-full"
                placeholder="Product name"
                required
              />
            </div>
            <div>
              <label className="label">{t('selling_price')} *</label>
              <input
                {...f("price")}
                type="number"
                step="0.01"
                min="0"
                className="input w-full"
                placeholder="0.00"
                required
              />
            </div>
            <div>
              <label className="label">{t('cost_price')}</label>
              <input
                {...f("cost_price")}
                type="number"
                step="0.01"
                min="0"
                className="input w-full"
                placeholder="0.00"
              />
            </div>
            <div>
              <label className="label">{t('category')}</label>
              <select {...f("category")} className="input w-full">
                <option value="">— Select category —</option>
                {categories.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">{t('barcode')}</label>
              <div className="flex gap-2">
                <input
                  {...f("barcode")}
                  className="input flex-1"
                  placeholder="EAN / SKU"
                />
                <button
                  type="button"
                  onClick={() => setShowScanner(!showScanner)}
                  className={clsx(
                    "btn btn-secondary px-2",
                    showScanner && "ring-2 ring-primary-400",
                  )}
                >
                  <ScanLine className="h-4 w-4" />
                </button>
              </div>
              <div className="mt-2 flex flex-wrap gap-2 items-center">
                <button
                  type="button"
                  onClick={generateBarcode}
                  className="btn btn-secondary"
                >
                  Generate Barcode
                </button>
                <button
                  type="button"
                  onClick={downloadBarcode}
                  disabled={!form.barcode}
                  className="btn btn-primary"
                >
                  Download Barcode
                </button>
              </div>
              {form.barcode && (
                <div className="mt-4">
                  <svg
                    ref={barcodeSvgRef}
                    className="h-28 w-full max-w-sm"
                    aria-label={`Barcode preview for ${form.barcode}`}
                  />
                </div>
              )}
              {showScanner && (
                <div className="mt-2">
                  <BarcodeScanner
                    onScan={async (code) => {
                      setShowScanner(false);
                      setForm((p) => ({ ...p, barcode: code }));
                      // Auto-fetch product name from external database
                      const res = await apiGet<{
                        success: boolean;
                        found: boolean;
                        product?: { name: string };
                        external?: { name: string; brand?: string } | null;
                      }>("/products/by-barcode", { barcode: code }).catch(
                        () => null,
                      );
                      if (res?.found && res.product?.name) {
                        setForm((p) => ({ ...p, name: res.product!.name }));
                        toast.success(
                          `Found in database: ${res.product!.name}`,
                        );
                      } else if (res?.external?.name) {
                        const name = res.external.brand
                          ? `${res.external.name} — ${res.external.brand}`
                          : res.external.name;
                        setForm((p) => ({ ...p, name: p.name || name }));
                        toast.success(`Found online: ${name}`);
                      } else {
                        toast("Barcode scanned — enter product name manually", {
                          icon: "📦",
                        });
                      }
                    }}
                    onClose={() => setShowScanner(false)}
                  />
                </div>
              )}
            </div>
            <div>
              <label className="label">{t('min_stock')}</label>
              <input
                {...f("min_stock")}
                type="number"
                min="0"
                className="input w-full"
              />
            </div>
            {modal === "add" ? (
              <div>
                <label className="label">{t('quantity')}</label>
                <input
                  {...f("initial_quantity")}
                  type="number"
                  min="0"
                  className="input w-full"
                />
              </div>
            ) : (
              <div>
                <label className="label">{t('current_stock')}</label>
                <input
                  {...f("quantity")}
                  type="number"
                  min="0"
                  className="input w-full"
                />
              </div>
            )}
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title={t('delete_product')}
        message="Are you sure? This cannot be undone."
        loading={deleteMutation.isPending}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        onCancel={() => setDeleteId(null)}
      />
    </div>
  );
}
