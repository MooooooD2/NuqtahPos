import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { useCartStore } from '@/stores/cartStore'
import { useOfflineStore } from '@/stores/offlineStore'
import type { Product, Customer, PaginatedResponse } from '@/types'
import {
  Search, Plus, Minus, Trash2, ShoppingCart, User, Pause,
  Play, RotateCcw, Printer, ChevronRight, X, Barcode,
} from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

export default function PosPage() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [categoryId, setCategoryId] = useState<number | null>(null)
  const [payModal, setPayModal] = useState(false)
  const [tenderedAmount, setTenderedAmount] = useState('')
  const searchRef = useRef<HTMLInputElement>(null)

  const cart = useCartStore()
  const { isOnline, enqueue } = useOfflineStore()

  const { data: productsData } = useQuery({
    queryKey: ['pos-products', search, categoryId],
    queryFn: () => apiGet<PaginatedResponse<Product>>('/api/products', {
      search, category_id: categoryId, per_page: 24, is_active: true,
    }),
    staleTime: 30_000,
  })

  const { data: categories } = useQuery({
    queryKey: ['categories'],
    queryFn: () => apiGet<{ data: Array<{ id: number; name: string }> }>('/api/categories'),
    staleTime: 300_000,
  })

  const checkoutMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/api/invoices', payload),
    onSuccess: (res: unknown) => {
      const data = res as { data: { invoice_number: string } }
      toast.success(`Sale #${data.data.invoice_number} completed!`)
      cart.clearCart()
      setPayModal(false)
      setTenderedAmount('')
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: () => toast.error('Sale failed. Please retry.'),
  })

  const handleCheckout = () => {
    const payload = {
      customer_id: cart.customer?.id,
      items: cart.items.map((i) => ({
        product_id: i.product_id,
        quantity: i.quantity,
        unit_price: i.unit_price,
        discount_amount: i.discount_amount,
      })),
      payment_method: cart.payment_method,
      discount_amount: cart.discount_amount,
      discount_percent: cart.discount_percent,
      notes: cart.notes,
    }

    if (!isOnline) {
      enqueue('invoice', payload)
      cart.clearCart()
      setPayModal(false)
      toast.success('Sale saved offline — will sync when connected')
      return
    }

    checkoutMutation.mutate(payload)
  }

  const products = productsData?.data ?? []
  const total = cart.total()
  const tendered = parseFloat(tenderedAmount) || 0
  const change = tendered - total

  return (
    <div className="flex h-[calc(100vh-4rem)] gap-4 -m-4 md:-m-6 p-0 overflow-hidden">
      {/* ── LEFT: Product grid ─────────────────────────────────────────── */}
      <div className="flex flex-1 flex-col bg-gray-50 dark:bg-gray-900 overflow-hidden">
        {/* Search + filter bar */}
        <div className="p-4 space-y-3 bg-white dark:bg-gray-800 border-b dark:border-gray-700">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              ref={searchRef}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search products or scan barcode…"
              className="input pl-9 w-full"
            />
            {search && (
              <button
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                onClick={() => setSearch('')}
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
          {/* Category pills */}
          <div className="flex gap-2 overflow-x-auto pb-1">
            <button
              onClick={() => setCategoryId(null)}
              className={clsx(
                'flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                !categoryId ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300',
              )}
            >
              All
            </button>
            {(categories?.data ?? []).map((cat) => (
              <button
                key={cat.id}
                onClick={() => setCategoryId(cat.id)}
                className={clsx(
                  'flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                  categoryId === cat.id ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300',
                )}
              >
                {cat.name}
              </button>
            ))}
          </div>
        </div>

        {/* Product grid */}
        <div className="flex-1 overflow-y-auto p-4 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 content-start">
          {products.map((product) => (
            <button
              key={product.id}
              onClick={() => cart.addItem(product)}
              disabled={product.stock <= 0}
              className={clsx(
                'card p-3 text-left transition-all hover:shadow-md hover:border-primary-300 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed',
                cart.items.find((i) => i.product_id === product.id) && 'ring-2 ring-primary-400',
              )}
            >
              {product.image ? (
                <img src={product.image} alt={product.name} className="w-full h-24 object-cover rounded-lg mb-2" />
              ) : (
                <div className="w-full h-24 rounded-lg bg-gray-100 dark:bg-gray-700 mb-2 flex items-center justify-center">
                  <Barcode className="h-8 w-8 text-gray-300 dark:text-gray-600" />
                </div>
              )}
              <p className="text-xs font-semibold text-gray-900 dark:text-white line-clamp-2">{product.name}</p>
              <p className="mt-0.5 text-xs text-gray-400">{product.sku}</p>
              <div className="mt-1.5 flex items-center justify-between">
                <span className="text-sm font-bold text-primary-600 dark:text-primary-400">
                  ${product.price.toFixed(2)}
                </span>
                <span className={clsx('text-xs', product.stock <= 5 ? 'text-red-500' : 'text-gray-400')}>
                  {product.stock} left
                </span>
              </div>
            </button>
          ))}
          {products.length === 0 && (
            <div className="col-span-full py-16 text-center text-gray-400">
              <ShoppingCart className="mx-auto h-12 w-12 opacity-30 mb-2" />
              <p>No products found</p>
            </div>
          )}
        </div>
      </div>

      {/* ── RIGHT: Cart ────────────────────────────────────────────────── */}
      <div className="w-full max-w-xs lg:max-w-sm flex flex-col bg-white dark:bg-gray-800 border-l dark:border-gray-700">
        {/* Cart header */}
        <div className="p-4 border-b dark:border-gray-700 flex items-center justify-between">
          <span className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <ShoppingCart className="h-5 w-5" />
            Cart ({cart.items.length})
          </span>
          <div className="flex gap-1">
            <button
              title="Hold order"
              onClick={cart.holdOrder}
              disabled={cart.items.length === 0}
              className="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40"
            >
              <Pause className="h-4 w-4" />
            </button>
            <button
              title="Clear cart"
              onClick={cart.clearCart}
              disabled={cart.items.length === 0}
              className="p-1.5 rounded-md text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-40"
            >
              <RotateCcw className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Customer */}
        <div className="px-4 py-2 border-b dark:border-gray-700">
          <button className="flex items-center gap-2 w-full text-left text-sm text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
            <User className="h-4 w-4" />
            {cart.customer ? cart.customer.name : 'Walk-in Customer'}
            <ChevronRight className="h-4 w-4 ml-auto" />
          </button>
        </div>

        {/* Cart items */}
        <div className="flex-1 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700">
          {cart.items.map((item) => (
            <div key={item.product_id} className="px-4 py-3 flex items-center gap-3">
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{item.product.name}</p>
                <p className="text-xs text-gray-400">${item.unit_price.toFixed(2)} each</p>
              </div>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => cart.updateQuantity(item.product_id, item.quantity - 1)}
                  className="h-6 w-6 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200"
                >
                  <Minus className="h-3 w-3" />
                </button>
                <span className="w-7 text-center text-sm font-semibold text-gray-900 dark:text-white">{item.quantity}</span>
                <button
                  onClick={() => cart.updateQuantity(item.product_id, item.quantity + 1)}
                  className="h-6 w-6 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 hover:bg-gray-200"
                >
                  <Plus className="h-3 w-3" />
                </button>
              </div>
              <span className="text-sm font-bold text-gray-900 dark:text-white w-14 text-right">
                ${(item.unit_price * item.quantity).toFixed(2)}
              </span>
              <button
                onClick={() => cart.removeItem(item.product_id)}
                className="text-red-400 hover:text-red-600"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))}
          {cart.items.length === 0 && (
            <div className="py-16 text-center text-gray-300 dark:text-gray-600">
              <ShoppingCart className="mx-auto h-10 w-10 mb-2" />
              <p className="text-sm">Cart is empty</p>
            </div>
          )}
        </div>

        {/* Totals */}
        <div className="p-4 border-t dark:border-gray-700 space-y-1.5 text-sm">
          <div className="flex justify-between text-gray-500"><span>Subtotal</span><span>${cart.subtotal().toFixed(2)}</span></div>
          <div className="flex justify-between text-gray-500"><span>Tax</span><span>${cart.tax_total().toFixed(2)}</span></div>
          {cart.discount_amount > 0 && (
            <div className="flex justify-between text-green-600"><span>Discount</span><span>-${cart.discount_amount.toFixed(2)}</span></div>
          )}
          <div className="flex justify-between border-t dark:border-gray-600 pt-2 text-lg font-bold text-gray-900 dark:text-white">
            <span>Total</span>
            <span>${total.toFixed(2)}</span>
          </div>
        </div>

        {/* Checkout button */}
        <div className="p-4 pt-0">
          <button
            onClick={() => setPayModal(true)}
            disabled={cart.items.length === 0}
            className="w-full btn-primary py-3 text-base font-semibold disabled:opacity-50"
          >
            Checkout ${total.toFixed(2)}
          </button>
        </div>
      </div>

      {/* ── Payment Modal ──────────────────────────────────────────────── */}
      {payModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-2xl">
            <div className="flex items-center justify-between p-5 border-b dark:border-gray-700">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Complete Payment</h2>
              <button onClick={() => setPayModal(false)} className="text-gray-400 hover:text-gray-600">
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="p-5 space-y-4">
              {/* Payment method */}
              <div className="grid grid-cols-3 gap-2">
                {(['cash', 'card', 'wallet'] as const).map((m) => (
                  <button
                    key={m}
                    onClick={() => cart.setPaymentMethod(m)}
                    className={clsx(
                      'rounded-lg py-2.5 text-sm font-medium capitalize transition-colors',
                      cart.payment_method === m
                        ? 'bg-primary-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300',
                    )}
                  >
                    {m}
                  </button>
                ))}
              </div>

              <div className="text-center text-3xl font-bold text-gray-900 dark:text-white">
                ${total.toFixed(2)}
              </div>

              {cart.payment_method === 'cash' && (
                <>
                  <div>
                    <label className="label">Tendered Amount</label>
                    <input
                      type="number"
                      value={tenderedAmount}
                      onChange={(e) => setTenderedAmount(e.target.value)}
                      placeholder="0.00"
                      className="input text-lg text-center font-semibold"
                      autoFocus
                    />
                  </div>
                  {tendered > 0 && (
                    <div className={clsx(
                      'rounded-lg p-3 text-center font-semibold',
                      change >= 0 ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                  : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
                    )}>
                      Change: ${Math.abs(change).toFixed(2)}
                      {change < 0 && ' (short)'}
                    </div>
                  )}
                  {/* Quick amount buttons */}
                  <div className="grid grid-cols-4 gap-2">
                    {[total, Math.ceil(total / 10) * 10, Math.ceil(total / 50) * 50, Math.ceil(total / 100) * 100].map((amt, i) => (
                      <button
                        key={i}
                        onClick={() => setTenderedAmount(amt.toFixed(2))}
                        className="rounded-lg bg-gray-100 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                      >
                        ${amt.toFixed(0)}
                      </button>
                    ))}
                  </div>
                </>
              )}

              <button
                onClick={handleCheckout}
                disabled={
                  checkoutMutation.isPending ||
                  (cart.payment_method === 'cash' && tendered < total)
                }
                className="w-full btn-primary py-3 text-base disabled:opacity-50"
              >
                {checkoutMutation.isPending ? 'Processing…' : 'Confirm Payment'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
