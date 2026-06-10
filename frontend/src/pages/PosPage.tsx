import { useState, useEffect, useRef, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { useCartStore } from '@/stores/cartStore'
import { useOfflineStore } from '@/stores/offlineStore'
import { usePermission } from '@/hooks/usePermission'
import BarcodeScanner, { useKeyboardScanner } from '@/components/common/BarcodeScanner'
import { useSyncQueue } from '@/hooks/useSyncQueue'
import InvoicePrintModal, { type PrintableInvoice } from '@/components/common/InvoicePrintModal'
import { invokeTauri } from '@/lib/tauri'
import type { Product, Customer } from '@/types'
import {
  Search, Plus, Minus, Trash2, ShoppingCart, User, Pause, Play,
  X, ScanLine, DollarSign, CreditCard, Wallet, Tag, AlertCircle,
  PackagePlus, Globe, Gift, Percent, Package,
} from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface ApiProduct {
  id: number; name: string; price: string; barcode: string | null
  category: string | null; quantity: number; min_stock: number; low_stock: boolean
  supplier: string | null; unit_name: string | null; tax_rate?: string
  // Dynamic pricing (populated when with_pricing=true or happy hour is active)
  effective_price?: number; discount_pct?: number; has_discount?: boolean
  price_rule?: { id: number; name: string; type: string } | null
}
interface ProductsApiResponse { success: boolean; products: ApiProduct[] }
interface CustomerSearchRes { success: boolean; customers: Customer[] }

// Response from GET /api/products/by-barcode
interface BarcodeRes {
  success: boolean
  found: boolean
  product?: ApiProduct
  external?: { name: string; brand?: string } | null
}

function toCartProduct(p: ApiProduct): Product {
  // Prefer effective_price (after pricing rules) over the base price
  const price = p.effective_price !== undefined ? p.effective_price : parseFloat(p.price)
  return {
    id: p.id, name: p.name, sku: p.barcode ?? String(p.id),
    barcode: p.barcode ?? undefined, category_id: 0, unit_id: 0,
    price, cost: 0,
    tax_rate: parseFloat(p.tax_rate ?? '0'),
    stock: p.quantity, low_stock_threshold: p.min_stock,
    is_active: true, has_variants: false, created_at: '',
  }
}

const openCashDrawer = () => invokeTauri('open_cash_drawer')

export default function PosPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState<string | null>(null)
  const [payModal, setPayModal] = useState(false)
  const [tenderedAmount, setTenderedAmount] = useState('')
  const [showScanner, setShowScanner] = useState(false)
  const [showHeld, setShowHeld] = useState(false)
  const [customerSearch, setCustomerSearch] = useState('')
  const [showCustomerDrop, setShowCustomerDrop] = useState(false)
  const [discountInput, setDiscountInput] = useState('')
  // New-product-from-barcode dialog state
  const [newProductModal, setNewProductModal] = useState<{
    barcode: string; name: string; brand?: string; price: string
  } | null>(null)
  const [creatingProduct, setCreatingProduct] = useState(false)
  const [printInvoice, setPrintInvoice] = useState<PrintableInvoice | null>(null)
  const [cashbackBalance, setCashbackBalance] = useState(0)
  const [activeRate, setActiveRate] = useState(0)
  const [cashbackToUse, setCashbackToUse] = useState(0)
  const [newCustModal, setNewCustModal] = useState(false)
  const [newCustForm, setNewCustForm] = useState({ name: '', phone: '' })
  const [creatingCust, setCreatingCust] = useState(false)
  const [mobileView, setMobileView] = useState<'products' | 'cart'>('products')
  const searchRef = useRef<HTMLInputElement>(null)
  const cart = useCartStore()
  const { isOnline, enqueue } = useOfflineStore()
  useSyncQueue()

  const { data: productsData, isLoading: prodLoading } = useQuery({
    queryKey: ['pos-products', search, category],
    queryFn: () => apiGet<ProductsApiResponse>('/products', { search: search || undefined, category: category || undefined, per_page: 60, with_pricing: true }),
    staleTime: 30_000,
    networkMode: 'offlineFirst',
    retry: isOnline ? 3 : false,
  })

  // Fetch cashback balance + active rate whenever customer changes
  useEffect(() => {
    if (!cart.customer) { setCashbackBalance(0); setActiveRate(0); setCashbackToUse(0); return }
    apiGet<{ balance: number; active_rate: number }>(`/cashback/customer/${cart.customer.id}`)
      .then((r) => { setCashbackBalance(Number(r.balance ?? 0)); setActiveRate(Number(r.active_rate ?? 0)) })
      .catch(() => {})
  }, [cart.customer?.id])

  const { data: customerData } = useQuery({
    queryKey: ['pos-customers', customerSearch],
    queryFn: () => apiGet<CustomerSearchRes>('/customers/search', { q: customerSearch, per_page: 8 }),
    enabled: customerSearch.length >= 2,
    staleTime: 10_000,
  })

  const products = productsData?.products ?? []
  const customers = customerData?.customers ?? []

  const categories = useMemo(() => {
    const cats = new Set<string>()
    products.forEach((p) => { if (p.category) cats.add(p.category) })
    return Array.from(cats).sort()
  }, [products])

  const handleBarcodeScanned = useCallback(async (code: string) => {
    setShowScanner(false)

    // 1. Check local cache first (works offline)
    const local = products.find((p) => p.barcode === code)
    if (local) { cart.addItem(toCartProduct(local)); toast.success(`✓ ${local.name}`); return }

    // 2. Query backend — searches DB + external databases (UPCitemdb, Open Food Facts)
    const loadingId = `barcode-${code}`
    toast.loading(`Looking up barcode ${code}…`, { id: loadingId })
    try {
      const res = await apiGet<BarcodeRes>('/products/by-barcode', { barcode: code })
      toast.dismiss(loadingId)

      if (res.found && res.product) {
        // Found in local DB
        cart.addItem(toCartProduct(res.product))
        toast.success(`✓ ${res.product.name}`)
      } else if (res.external?.name) {
        // Found in external database — prompt to create
        const fullName = res.external.brand
          ? `${res.external.name} — ${res.external.brand}`
          : res.external.name
        setNewProductModal({ barcode: code, name: fullName, brand: res.external.brand, price: '0.00' })
      } else {
        // Not found anywhere — still offer to create manually
        setNewProductModal({ barcode: code, name: '', brand: undefined, price: '0.00' })
        toast.error(t('product_not_found'))
      }
    } catch {
      toast.dismiss(loadingId)
      toast.error(t('error'))
    }
  }, [products, cart])

  const handleCreateAndAdd = async () => {
    if (!newProductModal?.name.trim()) return toast.error(t('product_name'))
    setCreatingProduct(true)
    try {
      const res = await apiPost<{ success: boolean; product: ApiProduct }>('/products', {
        name: newProductModal.name.trim(),
        barcode: newProductModal.barcode,
        price: parseFloat(newProductModal.price) || 0,
        cost_price: 0,
        initial_quantity: 0,
        min_stock: 5,
      })
      if (res?.product) {
        cart.addItem(toCartProduct(res.product))
        toast.success(t('created_success'))
        qc.invalidateQueries({ queryKey: ['pos-products'] })
        qc.invalidateQueries({ queryKey: ['products'] })
        setNewProductModal(null)
      }
    } catch {
      toast.error(t('save_failed'))
    } finally {
      setCreatingProduct(false)
    }
  }

  const handleCreateCustomer = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newCustForm.name.trim()) return toast.error(t('error'))
    setCreatingCust(true)
    try {
      const res = await apiPost<{ customer: Customer }>('/customers/quick', {
        name: newCustForm.name.trim(),
        phone: newCustForm.phone.trim() || undefined,
      })
      if (res?.customer) {
        cart.setCustomer(res.customer)
        setCustomerSearch('')
        setShowCustomerDrop(false)
        setNewCustModal(false)
        setNewCustForm({ name: '', phone: '' })
        toast.success(t('created_success'))
        qc.invalidateQueries({ queryKey: ['pos-customers'] })
      }
    } catch {
      toast.error(t('save_failed'))
    } finally {
      setCreatingCust(false)
    }
  }

  useKeyboardScanner(handleBarcodeScanned, !payModal)

  const checkoutMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/invoices', payload),
    onSuccess: async (res: unknown) => {
      const data = res as { invoice?: PrintableInvoice }
      const inv = data.invoice
      const customerId = cart.customer?.id
      const cbUsed = cashbackToUse
      // Capture cash values before clearing state
      const isCashPayment = cart.payment_method === 'cash'
      const capturedTendered = tendered
      const capturedChange = Math.max(0, change)
      toast.success(`${t('complete_sale')} #${inv?.invoice_number ?? '—'}`)
      await openCashDrawer()
      cart.clearCart(); setPayModal(false); setTenderedAmount(''); setCashbackToUse(0); setCashbackBalance(0)
      qc.invalidateQueries({ queryKey: ['dashboard'] })
      setTimeout(() => qc.invalidateQueries({ queryKey: ['notifications'] }), 800)
      if (inv) setPrintInvoice({
        ...inv,
        cash_received: isCashPayment && capturedTendered > 0 ? capturedTendered : inv.cash_received,
        change_amount: isCashPayment && capturedTendered > 0 ? capturedChange : inv.change_amount,
      })
      // Redeem cashback points after invoice is created
      if (cbUsed > 0 && customerId && inv?.id) {
        apiPost('/cashback/redeem', { customer_id: customerId, amount: cbUsed, invoice_id: inv.id })
          .catch(() => toast.error(t('error')))
      }
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('error')
      toast.error(msg)
    },
  })

  const handleCheckout = () => {
    if (cart.items.length === 0) return
    const isCash = cart.payment_method === 'cash'
    const payload = {
      customer_id: cart.customer?.id ?? null,
      items: cart.items.map((i) => ({ product_id: i.product_id, quantity: i.quantity, unit_price: i.unit_price, discount_amount: i.discount_amount })),
      payment_method: cart.payment_method,
      discount_amount: cart.discount_amount + cashbackToUse,
      discount_percent: cart.discount_percent,
      notes: cart.notes || undefined,
      cash_received: isCash && tendered > 0 ? tendered : undefined,
      change_amount: isCash && tendered > 0 ? Math.max(0, change) : undefined,
    }
    if (!isOnline) { enqueue('invoice', payload); cart.clearCart(); setPayModal(false); toast.success('Saved offline'); return }
    checkoutMutation.mutate(payload)
  }

  const total = cart.total()
  const finalTotal = Math.max(0, total - cashbackToUse)
  const tendered = parseFloat(tenderedAmount) || 0
  const change = tendered - finalTotal
  const willEarnCashback = activeRate > 0 && !!cart.customer ? finalTotal * (activeRate / 100) : 0

  if (!hasPermission('view_pos')) {
    return (
      <div className="flex h-full items-center justify-center">
        <div className="text-center"><AlertCircle className="h-12 w-12 text-red-400 mx-auto mb-3" />
          <p className="text-lg font-medium text-gray-700 dark:text-gray-300">{t('access_denied')}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="flex h-[calc(100vh-8rem)] md:h-[calc(100vh-4rem)] gap-0 -m-4 md:-m-6 overflow-hidden">
      {/* LEFT: Products */}
      <div className={clsx('flex flex-col bg-gray-50 dark:bg-gray-900 overflow-hidden', mobileView === 'products' ? 'flex flex-1' : 'hidden md:flex md:flex-1')}>
        <div className="p-3 space-y-2 bg-white dark:bg-gray-800 border-b dark:border-gray-700">
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input ref={searchRef} value={search} onChange={(e) => setSearch(e.target.value)}
                placeholder={t('search_product')} className="input pl-9 w-full" />
              {search && <button className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400" onClick={() => setSearch('')}><X className="h-4 w-4" /></button>}
            </div>
            <button onClick={() => setShowScanner(!showScanner)}
              className={clsx('btn btn-secondary flex items-center gap-1.5', showScanner && 'ring-2 ring-primary-400')}>
              <ScanLine className="h-4 w-4" /><span className="hidden sm:inline">{t('scan_barcode')}</span>
            </button>
          </div>

          {showScanner && (
            <div className="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
              <BarcodeScanner onScan={handleBarcodeScanned} onClose={() => setShowScanner(false)} />
            </div>
          )}

          <div className="flex gap-2 overflow-x-auto pb-1">
            <button onClick={() => setCategory(null)}
              className={clsx('flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium', !category ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300')}>
              {t('all')}
            </button>
            {categories.map((cat) => (
              <button key={cat} onClick={() => setCategory(cat === category ? null : cat)}
                className={clsx('flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium', category === cat ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300')}>
                {cat}
              </button>
            ))}
          </div>
        </div>

        <div className="flex-1 overflow-y-auto p-3 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 content-start">
          {prodLoading ? (
            <div className="col-span-full flex justify-center py-16">
              <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
            </div>
          ) : products.map((apiProd) => {
            const product = toCartProduct(apiProd)
            const inCart = cart.items.find((i) => i.product_id === product.id)
            return (
              <button key={product.id} onClick={() => cart.addItem(product)} disabled={product.stock <= 0}
                className={clsx('card p-3 text-left transition-all hover:shadow-md hover:border-primary-300 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed', inCart && 'ring-2 ring-primary-400')}>
                <div className="w-full h-20 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 mb-2 flex items-center justify-center text-2xl">
                  {apiProd.category ? apiProd.category.charAt(0) : '📦'}
                </div>
                <p className="text-xs font-semibold text-gray-900 dark:text-white line-clamp-2">{product.name}</p>
                <p className="mt-0.5 text-xs text-gray-400 truncate">{apiProd.barcode ?? apiProd.category ?? ''}</p>
                <div className="mt-1.5 flex items-center justify-between gap-1">
                  <div className="flex flex-col min-w-0">
                    {apiProd.has_discount && (
                      <span className="text-[10px] text-gray-400 line-through leading-none">{parseFloat(apiProd.price).toFixed(2)}</span>
                    )}
                    <div className="flex items-center gap-1">
                      <span className="text-sm font-bold text-primary-600 dark:text-primary-400">{product.price.toFixed(2)}</span>
                      {apiProd.discount_pct && apiProd.discount_pct > 0 && (
                        <span className="text-[9px] font-bold text-white bg-green-500 rounded px-1 py-0.5 flex items-center gap-0.5">
                          <Percent className="h-2 w-2" />{Math.round(apiProd.discount_pct)}
                        </span>
                      )}
                    </div>
                  </div>
                  <span className={clsx('text-xs flex-shrink-0', product.stock <= 0 ? 'text-red-500 font-medium' : product.stock <= 5 ? 'text-amber-500' : 'text-gray-400')}>
                    {product.stock <= 0 ? 'Out' : product.stock}
                  </span>
                </div>
                {inCart && <div className="mt-1 text-center text-xs font-semibold text-primary-600 bg-primary-50 dark:bg-primary-900/20 rounded py-0.5">× {inCart.quantity}</div>}
              </button>
            )
          })}
          {!prodLoading && products.length === 0 && (
            <div className="col-span-full py-16 text-center text-gray-400">
              <ShoppingCart className="mx-auto h-12 w-12 opacity-30 mb-2" /><p>{t('no_products_found')}</p>
            </div>
          )}
        </div>
      </div>

      {/* RIGHT: Cart */}
      <div className={clsx('flex flex-col bg-white dark:bg-gray-800 border-l dark:border-gray-700 flex-shrink-0', mobileView === 'cart' ? 'flex flex-1 w-full' : 'hidden md:flex md:w-80 lg:md:w-96')}>
        <div className="px-4 py-3 border-b dark:border-gray-700 space-y-2">
          <div className="flex items-center justify-between">
            <span className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              <ShoppingCart className="h-5 w-5" /> {t('cart')}
              {cart.items.length > 0 && <span className="bg-primary-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">{cart.items.reduce((s, i) => s + i.quantity, 0)}</span>}
            </span>
            <div className="flex gap-1">
              {cart.held_orders.length > 0 && (
                <button onClick={() => setShowHeld(!showHeld)} className="p-1.5 rounded-md text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20 text-xs flex items-center gap-1">
                  <Play className="h-3.5 w-3.5" />{cart.held_orders.length}
                </button>
              )}
              <button onClick={cart.holdOrder} disabled={cart.items.length === 0} title="Hold order"
                className="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40">
                <Pause className="h-4 w-4" />
              </button>
              <button onClick={cart.clearCart} disabled={cart.items.length === 0} title="Clear"
                className="p-1.5 rounded-md text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-40">
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          </div>

          {showHeld && cart.held_orders.length > 0 && (
            <div className="border border-amber-200 dark:border-amber-700 rounded-lg overflow-hidden text-xs">
              {cart.held_orders.map((order) => (
                <div key={order.id} className="flex items-center justify-between px-3 py-2 bg-amber-50 dark:bg-amber-900/10 border-b last:border-b-0 border-amber-100 dark:border-amber-800">
                  <span className="text-amber-700 dark:text-amber-400">{order.items.length} items · {new Date(order.timestamp).toLocaleTimeString()}</span>
                  <div className="flex gap-2">
                    <button onClick={() => { cart.resumeOrder(order.id); setShowHeld(false) }} className="text-primary-600 hover:underline">{t('resume_invoice')}</button>
                    <button onClick={() => cart.deleteHeldOrder(order.id)} className="text-red-500 hover:underline">{t('delete')}</button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Customer selection */}
          <div className="relative">
            <div className="flex items-center gap-2">
              <User className="h-4 w-4 text-gray-400 flex-shrink-0" />
              {cart.customer ? (
                <div className="flex-1 flex items-center justify-between bg-primary-50 dark:bg-primary-900/20 rounded-md px-2 py-1">
                  <div>
                    <span className="text-xs font-medium text-primary-700 dark:text-primary-300">{cart.customer.name}</span>
                    {cashbackBalance > 0 && (
                      <span className="ml-2 text-[10px] font-semibold text-amber-600 dark:text-amber-400 inline-flex items-center gap-0.5">
                        <Gift className="h-2.5 w-2.5" />{cashbackBalance.toFixed(2)}
                      </span>
                    )}
                  </div>
                  <button onClick={() => cart.setCustomer(null)} className="text-gray-400 hover:text-gray-600"><X className="h-3.5 w-3.5" /></button>
                </div>
              ) : (
                <>
                  <input value={customerSearch} onChange={(e) => { setCustomerSearch(e.target.value); setShowCustomerDrop(true) }}
                    onFocus={() => setShowCustomerDrop(true)} placeholder={t('customer')} className="input flex-1 text-xs py-1" />
                  <button
                    onClick={() => { setNewCustForm({ name: customerSearch, phone: '' }); setNewCustModal(true); setShowCustomerDrop(false) }}
                    title="Add new customer"
                    className="flex-shrink-0 p-1.5 rounded-md bg-primary-600 text-white hover:bg-primary-700"
                  >
                    <Plus className="h-3.5 w-3.5" />
                  </button>
                </>
              )}
            </div>
            {showCustomerDrop && !cart.customer && (
              <div className="absolute top-full left-0 right-0 z-10 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
                {customers.length > 0 ? customers.map((c) => (
                  <button key={c.id} onClick={() => { cart.setCustomer(c); setCustomerSearch(''); setShowCustomerDrop(false) }}
                    className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 border-b last:border-b-0 border-gray-100 dark:border-gray-700">
                    <span className="font-medium text-gray-900 dark:text-white">{c.name}</span>
                    {c.phone && <span className="text-gray-400 ml-2 text-xs">{c.phone}</span>}
                  </button>
                )) : customerSearch.length >= 2 ? (
                  <div className="px-3 py-2 text-xs text-gray-400">{t('no_data')}</div>
                ) : null}
                {customerSearch.length >= 1 && (
                  <button
                    onClick={() => { setNewCustForm({ name: customerSearch, phone: '' }); setNewCustModal(true); setShowCustomerDrop(false) }}
                    className="w-full text-left px-3 py-2 text-sm text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 flex items-center gap-2 border-t border-gray-100 dark:border-gray-700"
                  >
                    <Plus className="h-3.5 w-3.5" /> {t('new_customer')} "{customerSearch}"
                  </button>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Items */}
        <div className="flex-1 overflow-y-auto">
          {cart.items.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-gray-300">
              <ShoppingCart className="h-16 w-16 mb-3 opacity-30" />
              <p className="text-sm">{t('cart_empty')}</p>
              <p className="text-xs mt-1 opacity-70">{t('scan_barcode')}</p>
            </div>
          ) : (
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
              {cart.items.map((item) => (
                <div key={item.product_id} className="px-4 py-3">
                  <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium text-gray-900 dark:text-white leading-tight flex-1">{item.product.name}</p>
                    <button onClick={() => cart.removeItem(item.product_id)} className="text-red-400 hover:text-red-600 flex-shrink-0">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                  <div className="mt-2 flex items-center justify-between">
                    <div className="flex items-center border border-gray-200 dark:border-gray-600 rounded-lg">
                      <button onClick={() => cart.updateQuantity(item.product_id, item.quantity - 1)}
                        className="h-7 w-7 flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-l-lg">
                        <Minus className="h-3.5 w-3.5" />
                      </button>
                      <span className="w-8 text-center text-sm font-semibold">{item.quantity}</span>
                      <button onClick={() => cart.updateQuantity(item.product_id, item.quantity + 1)}
                        className="h-7 w-7 flex items-center justify-center text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-r-lg">
                        <Plus className="h-3.5 w-3.5" />
                      </button>
                    </div>
                    <span className="text-xs text-gray-400">{item.unit_price.toFixed(2)} × {item.quantity}</span>
                    <span className="text-sm font-bold text-gray-900 dark:text-white">{item.subtotal.toFixed(2)}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Totals */}
        <div className="border-t dark:border-gray-700 px-4 py-3 space-y-2">
          <div className="flex items-center gap-2">
            <Tag className="h-4 w-4 text-gray-400" />
            <input value={discountInput} onChange={(e) => setDiscountInput(e.target.value)}
              onBlur={() => {
                const v = parseFloat(discountInput)
                if (!isNaN(v)) { if (discountInput.includes('%')) cart.setDiscount(Math.min(v, 100), 'percent'); else cart.setDiscount(Math.max(0, v), 'amount') }
              }}
              placeholder={t('discount')} className="input text-xs flex-1 py-1" />
          </div>

          <div className="space-y-1 text-sm">
            <div className="flex justify-between text-gray-500"><span>{t('subtotal')}</span><span>{cart.subtotal().toFixed(2)}</span></div>
            {cart.discount_amount > 0 && <div className="flex justify-between text-green-600"><span>{t('discount')}</span><span>- {cart.discount_amount.toFixed(2)}</span></div>}
            {cart.discount_percent > 0 && <div className="flex justify-between text-green-600"><span>{t('discount')} ({cart.discount_percent}%)</span><span>- {(cart.subtotal() * cart.discount_percent / 100).toFixed(2)}</span></div>}
            <div className="flex justify-between text-gray-500"><span>{t('tax_amount')}</span><span>{cart.tax_total().toFixed(2)}</span></div>
            {cashbackToUse > 0 && (
              <div className="flex justify-between text-amber-600 dark:text-amber-400 text-sm">
                <span className="flex items-center gap-1"><Gift className="h-3.5 w-3.5" />{t('cashback')}</span>
                <span>- {cashbackToUse.toFixed(2)}</span>
              </div>
            )}
            <div className="flex justify-between font-bold text-lg text-gray-900 dark:text-white border-t pt-1 dark:border-gray-700">
              <span>{t('total')}</span><span>{finalTotal.toFixed(2)}</span>
            </div>
            {willEarnCashback > 0 && (
              <p className="text-[10px] text-amber-500 text-right">+{willEarnCashback.toFixed(2)} cashback after sale</p>
            )}
          </div>

          {!isOnline && <p className="text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded px-2 py-1">Offline — will sync when reconnected</p>}

          <button onClick={() => setPayModal(true)} disabled={cart.items.length === 0}
            className="btn btn-primary w-full py-3 text-base font-semibold disabled:opacity-50">
            {t('complete_sale')} · {finalTotal.toFixed(2)}
          </button>
        </div>
      </div>

      {/* Mobile bottom tab bar */}
      <div className="fixed bottom-0 left-0 right-0 z-40 md:hidden flex h-16 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
        <button
          onClick={() => setMobileView('products')}
          className={clsx('flex-1 flex flex-col items-center justify-center gap-1 text-xs font-medium transition-colors', mobileView === 'products' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400')}
        >
          <Package className="h-5 w-5" />
          {t('products')}
        </button>
        <button
          onClick={() => setMobileView('cart')}
          className={clsx('relative flex-1 flex flex-col items-center justify-center gap-1 text-xs font-medium transition-colors', mobileView === 'cart' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400')}
        >
          {cart.items.length > 0 && (
            <span className="absolute top-2 start-[calc(50%+8px)] flex h-4 w-4 items-center justify-center rounded-full bg-primary-600 text-[10px] text-white font-bold">
              {cart.items.reduce((s, i) => s + i.quantity, 0)}
            </span>
          )}
          <ShoppingCart className="h-5 w-5" />
          <span>{t('cart')}{finalTotal > 0 ? ` · ${finalTotal.toFixed(2)}` : ''}</span>
        </button>
      </div>

      {/* Payment Modal */}
      {payModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white">{t('payment_title')}</h2>
              <button onClick={() => setPayModal(false)} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>

            <div className="text-center py-2">
              {cashbackToUse > 0 && (
                <p className="text-sm text-gray-400 line-through">{total.toFixed(2)}</p>
              )}
              <p className="text-3xl font-bold text-primary-600">{finalTotal.toFixed(2)}</p>
              <p className="text-sm text-gray-400 mt-1">{t('total')}</p>
            </div>

            {/* Cashback redemption */}
            {cashbackBalance > 0 && cart.customer && (
              <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 space-y-2">
                <div className="flex items-center justify-between text-sm">
                  <span className="flex items-center gap-1.5 text-amber-700 dark:text-amber-300 font-medium">
                    <Gift className="h-4 w-4" /> {t('cashback')}
                  </span>
                  <span className="font-bold text-amber-700 dark:text-amber-300">{cashbackBalance.toFixed(2)}</span>
                </div>
                <div className="flex items-center gap-2">
                  <input
                    type="number" min="0" step="0.01"
                    max={Math.min(cashbackBalance, total)}
                    value={cashbackToUse || ''}
                    onChange={(e) => setCashbackToUse(Math.min(parseFloat(e.target.value) || 0, cashbackBalance, total))}
                    placeholder="0.00"
                    className="input flex-1 text-sm py-1 text-center"
                  />
                  <button onClick={() => setCashbackToUse(Math.min(cashbackBalance, total))} className="btn btn-secondary text-xs py-1 px-2 whitespace-nowrap">{t('use_all')}</button>
                  {cashbackToUse > 0 && (
                    <button onClick={() => setCashbackToUse(0)} className="text-xs text-gray-400 hover:text-red-500 whitespace-nowrap">{t('clear')}</button>
                  )}
                </div>
              </div>
            )}

            <div className="grid grid-cols-3 gap-2">
              {([{ key: 'cash', label: t('cash'), icon: DollarSign }, { key: 'card', label: t('card'), icon: CreditCard }, { key: 'wallet', label: t('wallet'), icon: Wallet }] as const).map(({ key, label, icon: Icon }) => (
                <button key={key} onClick={() => cart.setPaymentMethod(key)}
                  className={clsx('flex flex-col items-center gap-1 py-3 rounded-xl border-2 transition-all text-sm font-medium',
                    cart.payment_method === key ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400')}>
                  <Icon className="h-5 w-5" />{label}
                </button>
              ))}
            </div>

            {cart.payment_method === 'cash' && (
              <div className="space-y-3">
                <div>
                  <label className="text-sm font-medium text-gray-700 dark:text-gray-300">{t('cash_received')}</label>
                  <input
                    type="number" value={tenderedAmount}
                    onChange={(e) => setTenderedAmount(e.target.value)}
                    className="input w-full text-2xl font-bold text-center mt-1" placeholder="0.00" autoFocus
                  />
                </div>

                {/* Quick amount buttons — exact + round-ups */}
                <div className="grid grid-cols-4 gap-1.5">
                  <button
                    onClick={() => setTenderedAmount(finalTotal.toFixed(2))}
                    className="col-span-4 btn btn-secondary text-sm py-1.5 font-semibold text-primary-600 dark:text-primary-400 border-primary-200 dark:border-primary-700"
                  >
                    {t('exact_amount')} · {finalTotal.toFixed(2)}
                  </button>
                  {[
                    Math.ceil(finalTotal / 10) * 10,
                    Math.ceil(finalTotal / 50) * 50,
                    Math.ceil(finalTotal / 100) * 100,
                    Math.ceil(finalTotal / 500) * 500,
                  ]
                    .filter((v, i, a) => v !== finalTotal && a.indexOf(v) === i)
                    .slice(0, 4)
                    .map((amt) => (
                      <button key={amt} onClick={() => setTenderedAmount(String(amt))} className="btn btn-secondary text-sm py-1.5">{amt}</button>
                    ))
                  }
                </div>

                {/* Change display */}
                {tenderedAmount && (
                  <div className={clsx(
                    'rounded-xl p-3 text-center border-2',
                    change >= 0
                      ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700'
                      : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700',
                  )}>
                    <p className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">{t('change_due')}</p>
                    <p className={clsx('text-3xl font-bold', change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')}>
                      {change >= 0 ? change.toFixed(2) : `${t('short_by')} ${Math.abs(change).toFixed(2)}`}
                    </p>
                  </div>
                )}
              </div>
            )}

            <button onClick={handleCheckout}
              disabled={checkoutMutation.isPending || (cart.payment_method === 'cash' && !!tenderedAmount && tendered < finalTotal)}
              className="btn btn-primary w-full py-3 text-base font-semibold">
              {checkoutMutation.isPending ? t('processing') : `${t('complete_sale')} · ${finalTotal.toFixed(2)}`}
            </button>
          </div>
        </div>
      )}

      {/* New Product from Barcode Modal */}
      {newProductModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                {newProductModal.name ? <Globe className="h-5 w-5 text-green-500" /> : <PackagePlus className="h-5 w-5 text-primary-500" />}
                {newProductModal.name ? t('add_product') : t('add_product')}
              </h2>
              <button onClick={() => setNewProductModal(null)} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>

            {newProductModal.name ? (
              <div className="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-sm text-green-700 dark:text-green-300">
                Found in external database — review and save to your catalog
              </div>
            ) : (
              <div className="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3 text-sm text-amber-700 dark:text-amber-300">
                Barcode not found in any database — enter the product name manually
              </div>
            )}

            <div className="space-y-3">
              <div>
                <label className="label">{t('barcode')}</label>
                <input value={newProductModal.barcode} readOnly
                  className="input w-full bg-gray-50 dark:bg-gray-700 font-mono text-gray-500 cursor-not-allowed" />
              </div>
              <div>
                <label className="label">{t('product_name')} *</label>
                <input
                  value={newProductModal.name}
                  onChange={(e) => setNewProductModal((p) => p ? { ...p, name: e.target.value } : null)}
                  className="input w-full"
                  placeholder={t('product_name')}
                  autoFocus
                />
              </div>
              <div>
                <label className="label">{t('selling_price')}</label>
                <input
                  type="number" step="0.01" min="0"
                  value={newProductModal.price}
                  onChange={(e) => setNewProductModal((p) => p ? { ...p, price: e.target.value } : null)}
                  className="input w-full"
                  placeholder="0.00"
                />
                <p className="text-xs text-gray-400 mt-1">You can update the price later in the Products page</p>
              </div>
            </div>

            <div className="flex gap-2">
              <button onClick={() => setNewProductModal(null)} className="btn btn-secondary flex-1">
                {t('cancel')}
              </button>
              <button
                onClick={handleCreateAndAdd}
                disabled={creatingProduct || !newProductModal.name.trim()}
                className="btn btn-primary flex-1 flex items-center justify-center gap-2"
              >
                <PackagePlus className="h-4 w-4" />
                {creatingProduct ? t('saving') : t('add_product')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── New Customer Quick-Create Modal ──────────────────────────── */}
      {newCustModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <User className="h-5 w-5 text-primary-500" /> {t('new_customer')}
              </h2>
              <button onClick={() => setNewCustModal(false)} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleCreateCustomer} className="space-y-3">
              <div>
                <label className="label">{t('name')} *</label>
                <input
                  value={newCustForm.name}
                  onChange={(e) => setNewCustForm((p) => ({ ...p, name: e.target.value }))}
                  className="input w-full"
                  placeholder={t('name')}
                  autoFocus
                  required
                />
              </div>
              <div>
                <label className="label">{t('phone')}</label>
                <input
                  value={newCustForm.phone}
                  onChange={(e) => setNewCustForm((p) => ({ ...p, phone: e.target.value }))}
                  className="input w-full"
                  placeholder={t('optional_notes')}
                />
              </div>
              <div className="flex gap-2 pt-1">
                <button type="button" onClick={() => setNewCustModal(false)} className="btn btn-secondary flex-1">{t('cancel')}</button>
                <button type="submit" disabled={creatingCust || !newCustForm.name.trim()} className="btn btn-primary flex-1">
                  {creatingCust ? t('saving') : t('new_customer')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ── Invoice Print Modal (shown after successful sale) ─────────── */}
      {printInvoice && (
        <InvoicePrintModal
          invoice={printInvoice}
          onClose={() => setPrintInvoice(null)}
          title={`Receipt — #${printInvoice.invoice_number}`}
        />
      )}
    </div>
  )
}
