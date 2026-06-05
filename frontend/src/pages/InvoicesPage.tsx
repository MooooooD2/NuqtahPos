import { useState, useEffect, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import InvoicePrintModal, { type PrintableInvoice } from '@/components/common/InvoicePrintModal'
import { FileText, Search, Eye, RotateCcw, Printer } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Invoice { id: number; invoice_number: string; customer_name?: string; cashier_name?: string; payment_method?: string; final_total?: string; status?: string; created_at: string; type?: string }
interface ReturnableItem { id: number; product_id: number; product_name: string; quantity: number; returnable_quantity: number; unit_price: string }
interface InvoiceDetail extends Invoice { items?: ReturnableItem[] }

const statusBadge: Record<string, string> = { paid: 'badge-success', completed: 'badge-success', partial: 'badge-warning', cancelled: 'badge-danger', refunded: 'badge-info', draft: 'badge-gray' }

export default function InvoicesPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [searchParams] = useSearchParams()
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState(() => searchParams.get('search') ?? '')
  const [search, setSearch] = useState(() => searchParams.get('search') ?? '')
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  // When navigated from a notification link with ?search=, apply it
  useEffect(() => {
    const s = searchParams.get('search')
    if (s) { setSearchInput(s); setSearch(s) }
  }, [searchParams])

  const handleSearchChange = (value: string) => {
    setSearchInput(value)
    setPage(1)
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => setSearch(value), 400)
  }
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [viewInvoice, setViewInvoice] = useState<InvoiceDetail | null>(null)
  const [returnModal, setReturnModal] = useState(false)
  const [returnItems, setReturnItems] = useState<Array<{ item_id: number; quantity: number }>>([])
  const [refundMethod, setRefundMethod] = useState<'cash' | 'store_credit' | 'exchange'>('cash')
  const [printInvoiceNumber, setPrintInvoiceNumber] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', page, search, dateFrom, dateTo],
    queryFn: () => apiGet<{ success: boolean; data: Invoice[]; total?: number }>('/invoices', {
      page, per_page: 20, search: search || undefined, date_from: dateFrom || undefined, date_to: dateTo || undefined,
    }),
    staleTime: 30_000,
  })

  const { data: returnableData } = useQuery({
    queryKey: ['returnable', viewInvoice?.id],
    queryFn: () => apiGet<{ success: boolean; items: ReturnableItem[] }>(`/invoices/${viewInvoice!.id}/returnable-items`),
    enabled: !!viewInvoice?.id,
  })

  const { data: printData, isLoading: printLoading } = useQuery({
    queryKey: ['invoice-print', printInvoiceNumber],
    queryFn: () => apiGet<{ success: boolean; invoice?: PrintableInvoice }>('/invoices/search', { number: printInvoiceNumber }),
    enabled: !!printInvoiceNumber,
    staleTime: 0,
  })

  const invoices = data?.data ?? []
  const returnableItems = returnableData?.items ?? []
  const printableInvoice = printData?.invoice ?? null
  const canReturn = hasPermission('create_returns', 'view_pos')

  const returnMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/returns', payload),
    onSuccess: () => { toast.success('Return processed'); qc.invalidateQueries({ queryKey: ['invoices'] }); setReturnModal(false); setViewInvoice(null) },
    onError: () => toast.error('Failed to process return'),
  })

  const handleReturn = () => {
    const filtered = returnItems.filter((i) => i.quantity > 0)
    if (filtered.length === 0) return toast.error('Select items to return')
    const items = filtered.map((ri) => {
      const item = returnableItems.find((x) => x.id === ri.item_id)
      return { product_id: item?.product_id, quantity: ri.quantity }
    }).filter((i) => i.product_id != null)
    returnMutation.mutate({ invoice_id: viewInvoice?.id, items, refund_method: refundMethod })
  }

  const openView = (inv: Invoice) => {
    setViewInvoice(inv)
    setReturnItems([])
    setRefundMethod('cash')
  }

  const handlePrint = (inv: Invoice) => setPrintInvoiceNumber(inv.invoice_number)

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><FileText className="h-6 w-6 text-primary-500" /> Invoices</h1>

      {/* Filters */}
      <div className="flex flex-wrap gap-2">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input value={searchInput} onChange={(e) => handleSearchChange(e.target.value)} placeholder="Invoice # or customer…" className="input pl-9 w-56" />
        </div>
        <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="input" placeholder="From date" />
        <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="input" placeholder="To date" />
        {(dateFrom || dateTo || searchInput) && (
          <button onClick={() => { setSearchInput(''); setSearch(''); setDateFrom(''); setDateTo(''); }} className="btn btn-secondary text-sm">Clear</button>
        )}
      </div>

      <div className="card overflow-hidden">
        {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['Invoice #', 'Customer', 'Cashier', 'Method', 'Total', 'Status', 'Date', ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {invoices.length === 0 ? <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">No invoices found</td></tr>
                  : invoices.map((inv) => (
                    <tr key={inv.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-mono text-xs text-primary-600">{inv.invoice_number}</td>
                      <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{inv.customer_name ?? 'Walk-in'}</td>
                      <td className="px-4 py-3 text-gray-500">{inv.cashier_name ?? '—'}</td>
                      <td className="px-4 py-3"><span className="badge badge-gray capitalize">{inv.payment_method ?? '—'}</span></td>
                      <td className="px-4 py-3 font-semibold">{parseFloat(inv.final_total ?? '0').toFixed(2)}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize', statusBadge[inv.status ?? ''] ?? 'badge-gray')}>{inv.status ?? 'paid'}</span></td>
                      <td className="px-4 py-3 text-gray-400 text-xs">{inv.created_at?.slice(0, 10)}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1">
                          <button onClick={() => openView(inv)} className="p-1 text-gray-400 hover:text-primary-600 rounded"><Eye className="h-4 w-4" /></button>
                          <button onClick={() => handlePrint(inv)} className="p-1 text-gray-400 hover:text-gray-600 rounded" title="Print invoice"><Printer className="h-4 w-4" /></button>
                          {canReturn && (inv.status === 'paid' || inv.status === 'completed') && (
                            <button onClick={() => { openView(inv); setReturnModal(true) }} title="Process Return" className="p-1 text-gray-400 hover:text-orange-600 rounded"><RotateCcw className="h-4 w-4" /></button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        )}
        {(data?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">Page {page} · {data?.total} total</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Prev</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={invoices.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Next</button>
            </div>
          </div>
        )}
      </div>

      {/* View Invoice Modal */}
      <Modal open={!!viewInvoice && !returnModal} onClose={() => setViewInvoice(null)} title={`Invoice #${viewInvoice?.invoice_number ?? ''}`} size="lg"
        footer={<>
          <button onClick={() => setViewInvoice(null)} className="btn btn-secondary">Close</button>
          {viewInvoice && <button onClick={() => handlePrint(viewInvoice)} className="btn btn-secondary flex items-center gap-2"><Printer className="h-4 w-4" />Print</button>}
          {canReturn && (viewInvoice?.status === 'paid' || viewInvoice?.status === 'completed') && (
            <button onClick={() => setReturnModal(true)} className="btn bg-orange-500 hover:bg-orange-600 text-white flex items-center gap-2"><RotateCcw className="h-4 w-4" />Return</button>
          )}
        </>}>
        {viewInvoice && (
          <div className="space-y-3 text-sm">
            <div className="grid grid-cols-2 gap-3">
              <div><span className="text-gray-500">Customer:</span> <span className="font-medium">{viewInvoice.customer_name ?? 'Walk-in'}</span></div>
              <div><span className="text-gray-500">Status:</span> <span className={clsx('badge ml-1 capitalize', statusBadge[viewInvoice.status ?? ''] ?? 'badge-gray')}>{viewInvoice.status}</span></div>
              <div><span className="text-gray-500">Payment:</span> <span className="capitalize">{viewInvoice.payment_method}</span></div>
              <div><span className="text-gray-500">Total:</span> <span className="font-bold text-primary-600">{parseFloat(viewInvoice.final_total ?? '0').toFixed(2)}</span></div>
              <div><span className="text-gray-500">Date:</span> <span>{viewInvoice.created_at?.slice(0, 16)}</span></div>
            </div>
          </div>
        )}
      </Modal>

      {/* Return Modal */}
      <Modal open={returnModal && !!viewInvoice} onClose={() => { setReturnModal(false) }} title={`Return — Invoice #${viewInvoice?.invoice_number ?? ''}`} size="lg"
        footer={<>
          <button onClick={() => setReturnModal(false)} className="btn btn-secondary">Cancel</button>
          <button onClick={handleReturn} disabled={returnMutation.isPending} className="btn bg-orange-500 hover:bg-orange-600 text-white">{returnMutation.isPending ? 'Processing…' : 'Process Return'}</button>
        </>}>
        <div className="space-y-3">
          <div className="flex items-center gap-3 mb-1">
            <label className="text-sm text-gray-500 font-medium">Refund Method:</label>
            <select value={refundMethod} onChange={(e) => setRefundMethod(e.target.value as typeof refundMethod)} className="input py-1 text-sm">
              <option value="cash">Cash</option>
              <option value="store_credit">Store Credit</option>
              <option value="exchange">Exchange</option>
            </select>
          </div>
          <p className="text-sm text-gray-500">Select quantities to return:</p>
          {returnableItems.length === 0 ? <p className="text-center text-gray-400 py-4">No returnable items</p>
            : returnableItems.map((item) => {
              const ri = returnItems.find((x) => x.item_id === item.id)
              const qty = ri?.quantity ?? 0
              return (
                <div key={item.id} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <div>
                    <p className="font-medium text-gray-900 dark:text-white text-sm">{item.product_name}</p>
                    <p className="text-xs text-gray-400">Max returnable: {item.returnable_quantity} · Price: {parseFloat(item.unit_price).toFixed(2)}</p>
                  </div>
                  <input type="number" min="0" max={item.returnable_quantity} value={qty}
                    onChange={(e) => {
                      const v = Math.min(parseInt(e.target.value) || 0, item.returnable_quantity)
                      setReturnItems((prev) => {
                        const exists = prev.find((x) => x.item_id === item.id)
                        if (exists) return prev.map((x) => x.item_id === item.id ? { ...x, quantity: v } : x)
                        return [...prev, { item_id: item.id, quantity: v }]
                      })
                    }}
                    className="input w-20 text-center"
                  />
                </div>
              )
            })}
        </div>
      </Modal>

      {/* ── Invoice Print Modal ───────────────────────────────────────── */}
      {printInvoiceNumber && (
        printLoading ? (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="bg-white dark:bg-gray-800 rounded-xl p-6 flex items-center gap-3">
              <div className="h-5 w-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
              <span className="text-sm text-gray-600 dark:text-gray-300">Loading invoice…</span>
            </div>
          </div>
        ) : (
          <InvoicePrintModal
            invoice={printableInvoice}
            onClose={() => setPrintInvoiceNumber(null)}
          />
        )
      )}
    </div>
  )
}
