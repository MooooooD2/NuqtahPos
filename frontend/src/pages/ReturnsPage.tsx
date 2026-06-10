import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { RotateCcw, Plus, Search, Eye, ArrowRight } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface ReturnItem { id: number; product_name: string; quantity: number; unit_price: string }
interface Return {
  id: number
  return_number: string
  invoice_number: string
  customer_name?: string
  refund_method: string
  total_amount: string
  status: string
  created_at: string
  items?: ReturnItem[]
}
interface ReturnableItem { id: number; product_id: number; product_name: string; quantity: number; returnable_quantity: number; unit_price: string }
interface InvoiceSearchResult { id: number; invoice_number: string; customer_name?: string; final_total: string; created_at: string }

const refundBadge: Record<string, string> = { cash: 'badge-success', store_credit: 'badge-info', exchange: 'badge-warning' }
const statusBadge: Record<string, string> = { completed: 'badge-success', pending: 'badge-warning' }
const refundMethods = ['cash', 'store_credit', 'exchange']

export default function ReturnsPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [viewReturn, setViewReturn] = useState<Return | null>(null)
  const [wizardStep, setWizardStep] = useState<1 | 2>(1)
  const [wizardOpen, setWizardOpen] = useState(false)
  const [invoiceSearch, setInvoiceSearch] = useState('')
  const [foundInvoice, setFoundInvoice] = useState<InvoiceSearchResult | null>(null)
  const [returnableItems, setReturnableItems] = useState<ReturnableItem[]>([])
  const [itemQtys, setItemQtys] = useState<Record<number, number>>({})
  const [refundMethod, setRefundMethod] = useState('cash')
  const [reason, setReason] = useState('')
  const [searchingInvoice, setSearchingInvoice] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['returns', page, search],
    queryFn: () => apiGet<{ returns: Return[]; total?: number }>('/returns', {
      page, per_page: 20, search: search || undefined,
    }),
    staleTime: 30_000,
  })

  const returns = data?.returns ?? []
  const canProcess = hasPermission('create_returns', 'view_returns')

  const processMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/returns', payload),
    onSuccess: () => {
      toast.success(t('record_success'))
      qc.invalidateQueries({ queryKey: ['returns'] })
      closeWizard()
    },
    onError: () => toast.error(t('record_failed')),
  })

  const closeWizard = () => {
    setWizardOpen(false)
    setWizardStep(1)
    setInvoiceSearch('')
    setFoundInvoice(null)
    setReturnableItems([])
    setItemQtys({})
    setRefundMethod('cash')
    setReason('')
  }

  const searchInvoice = async () => {
    if (!invoiceSearch.trim()) return toast.error(t('error'))
    setSearchingInvoice(true)
    try {
      const res = await apiGet<{ success: boolean; data?: InvoiceSearchResult; invoice?: InvoiceSearchResult }>(
        '/invoices/search', { number: invoiceSearch.trim() }
      )
      const inv = res.data ?? res.invoice ?? null
      if (!inv) { toast.error(t('error')); return }
      setFoundInvoice(inv)
      const itemsRes = await apiGet<{ success: boolean; items: ReturnableItem[] }>(`/invoices/${inv.id}/returnable-items`)
      setReturnableItems(itemsRes.items ?? [])
      setWizardStep(2)
    } catch {
      toast.error(t('error'))
    } finally {
      setSearchingInvoice(false)
    }
  }

  const handleProcessReturn = () => {
    if (!foundInvoice) return
    const items = returnableItems
      .filter((item) => (itemQtys[item.id] ?? 0) > 0)
      .map((item) => ({ product_id: item.product_id, quantity: itemQtys[item.id] }))
    if (items.length === 0) return toast.error(t('error'))
    processMutation.mutate({
      invoice_id: foundInvoice.id,
      items,
      refund_method: refundMethod,
      reason: reason || undefined,
    })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <RotateCcw className="h-6 w-6 text-primary-500" /> {t('returns')}
          {data?.total !== undefined && <span className="text-sm font-normal text-gray-400">({data.total})</span>}
        </h1>
        {canProcess && (
          <button onClick={() => { setWizardOpen(true); setWizardStep(1) }} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('process_return')}
          </button>
        )}
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder={t('search')} className="input pl-9 w-full" />
      </div>

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : (
          <>
            {/* ── Desktop table ─────────────────────── lg+ ── */}
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[700px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[t('return_number'), t('invoice_number'), t('name'), t('refund_method'), t('amount'), t('status'), t('date'), ''].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {returns.length === 0 ? (
                    <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : returns.map((r) => (
                    <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-mono text-xs text-primary-600">{r.return_number}</td>
                      <td className="px-4 py-3 font-mono text-xs text-gray-500">{r.invoice_number}</td>
                      <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{r.customer_name ?? t('walk_in')}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize', refundBadge[r.refund_method] ?? 'badge-gray')}>{r.refund_method.replace('_', ' ')}</span></td>
                      <td className="px-4 py-3 font-semibold text-red-600">{parseFloat(r.total_amount).toFixed(2)}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize', statusBadge[r.status] ?? 'badge-gray')}>{r.status}</span></td>
                      <td className="px-4 py-3 text-gray-400 text-xs">{r.created_at?.slice(0, 10)}</td>
                      <td className="px-4 py-3"><button onClick={() => setViewReturn(r)} className="p-1 text-gray-400 hover:text-primary-600 rounded"><Eye className="h-4 w-4" /></button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* ── Mobile cards ──────────────────────── <lg ── */}
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {returns.length === 0 ? (
                <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
              ) : returns.map((r) => (
                <div key={r.id} className="p-4 space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-mono text-xs font-semibold text-primary-600">{r.return_number}</span>
                    <span className={clsx('badge capitalize shrink-0', statusBadge[r.status] ?? 'badge-gray')}>{r.status}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-gray-900 dark:text-white">{r.customer_name ?? t('walk_in')}</span>
                    <span className="font-bold text-red-600">{parseFloat(r.total_amount).toFixed(2)}</span>
                  </div>
                  <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                    <span className="font-mono">{r.invoice_number}</span>
                    <span>·</span>
                    <span className={clsx('badge capitalize', refundBadge[r.refund_method] ?? 'badge-gray')}>{r.refund_method.replace('_', ' ')}</span>
                    <span>·</span>
                    <span>{r.created_at?.slice(0, 10)}</span>
                  </div>
                  <div className="pt-1">
                    <button onClick={() => setViewReturn(r)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400 hover:bg-primary-100 transition-colors font-medium">
                      <Eye className="h-3.5 w-3.5" />{isAr ? 'عرض' : 'View'}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
        {(data?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">{t('page')} {page} · {data?.total} total</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={returns.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      {/* View Return Modal */}
      <Modal open={!!viewReturn} onClose={() => setViewReturn(null)} title={`Return #${viewReturn?.return_number ?? ''}`} size="lg">
        {viewReturn && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div><span className="text-gray-500">{t('invoice_number')}:</span> <span className="font-mono font-medium">{viewReturn.invoice_number}</span></div>
              <div><span className="text-gray-500">{t('name')}:</span> <span className="font-medium">{viewReturn.customer_name ?? t('walk_in')}</span></div>
              <div><span className="text-gray-500">{t('refund_method')}:</span> <span className={clsx('badge ml-1 capitalize', refundBadge[viewReturn.refund_method] ?? 'badge-gray')}>{viewReturn.refund_method.replace('_', ' ')}</span></div>
              <div><span className="text-gray-500">{t('status')}:</span> <span className={clsx('badge ml-1 capitalize', statusBadge[viewReturn.status] ?? 'badge-gray')}>{viewReturn.status}</span></div>
              <div><span className="text-gray-500">{t('amount')}:</span> <span className="font-bold text-red-600">{parseFloat(viewReturn.total_amount).toFixed(2)}</span></div>
              <div><span className="text-gray-500">{t('date')}:</span> <span>{viewReturn.created_at?.slice(0, 10)}</span></div>
            </div>
            {viewReturn.items && viewReturn.items.length > 0 && (
              <div>
                <p className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{t('items')}</p>
                <div className="space-y-2">
                  {viewReturn.items.map((item) => (
                    <div key={item.id} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
                      <span className="font-medium text-gray-900 dark:text-white">{item.product_name}</span>
                      <div className="flex gap-4 text-gray-500">
                        <span>{t('qty')}: {item.quantity}</span>
                        <span>{parseFloat(item.unit_price).toFixed(2)}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>

      {/* Process Return Wizard */}
      <Modal
        open={wizardOpen}
        onClose={closeWizard}
        title={wizardStep === 1 ? t('process_return') + ' — 1' : t('process_return') + ' — 2'}
        size="lg"
        footer={
          wizardStep === 1 ? (
            <>
              <button onClick={closeWizard} className="btn btn-secondary">{t('cancel')}</button>
              <button onClick={searchInvoice} disabled={searchingInvoice} className="btn btn-primary flex items-center gap-2">
                {searchingInvoice ? t('loading') : <><ArrowRight className="h-4 w-4" /> {t('search')}</>}
              </button>
            </>
          ) : (
            <>
              <button onClick={() => setWizardStep(1)} className="btn btn-secondary">{t('prev')}</button>
              <button onClick={handleProcessReturn} disabled={processMutation.isPending} className="btn btn-primary">
                {processMutation.isPending ? t('loading') : t('process_return')}
              </button>
            </>
          )
        }
      >
        {wizardStep === 1 && (
          <div className="space-y-4">
            <p className="text-sm text-gray-500">{t('invoice_search_hint')}</p>
            <div>
              <label className="label">{t('invoice_number')}</label>
              <input
                value={invoiceSearch}
                onChange={(e) => setInvoiceSearch(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && searchInvoice()}
                className="input w-full"
                placeholder="e.g. INV-0001"
              />
            </div>
          </div>
        )}
        {wizardStep === 2 && foundInvoice && (
          <div className="space-y-4">
            <div className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm grid grid-cols-2 gap-2">
              <div><span className="text-gray-500">{t('invoice_number')}:</span> <span className="font-mono font-medium">{foundInvoice.invoice_number}</span></div>
              <div><span className="text-gray-500">{t('name')}:</span> <span className="font-medium">{foundInvoice.customer_name ?? t('walk_in')}</span></div>
              <div><span className="text-gray-500">{t('total')}:</span> <span className="font-bold">{parseFloat(foundInvoice.final_total).toFixed(2)}</span></div>
              <div><span className="text-gray-500">{t('date')}:</span> <span>{foundInvoice.created_at?.slice(0, 10)}</span></div>
            </div>
            <div>
              <p className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{t('returns')}</p>
              {returnableItems.length === 0 ? (
                <p className="text-center text-gray-400 py-4">{t('no_data')}</p>
              ) : (
                <div className="space-y-2">
                  {returnableItems.map((item) => (
                    <div key={item.id} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                      <div>
                        <p className="font-medium text-gray-900 dark:text-white text-sm">{item.product_name}</p>
                        <p className="text-xs text-gray-400">{t('max')}: {item.returnable_quantity} · {t('selling_price')}: {parseFloat(item.unit_price).toFixed(2)}</p>
                      </div>
                      <input
                        type="number" min="0" max={item.returnable_quantity}
                        value={itemQtys[item.id] ?? 0}
                        onChange={(e) => setItemQtys((p) => ({ ...p, [item.id]: Math.min(parseInt(e.target.value) || 0, item.returnable_quantity) }))}
                        className="input w-20 text-center"
                      />
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div>
              <label className="label">{t('refund_method')}</label>
              <div className="flex gap-3">
                {refundMethods.map((m) => (
                  <label key={m} className="flex items-center gap-1.5 cursor-pointer text-sm capitalize">
                    <input type="radio" name="refund_method" value={m} checked={refundMethod === m} onChange={() => setRefundMethod(m)} className="accent-primary-600" />
                    {m.replace('_', ' ')}
                  </label>
                ))}
              </div>
            </div>
            <div>
              <label className="label">{t('return_reason')}</label>
              <input value={reason} onChange={(e) => setReason(e.target.value)} className="input w-full" placeholder={t('optional_notes')} />
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
