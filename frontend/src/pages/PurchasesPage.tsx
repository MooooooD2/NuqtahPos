import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { ShoppingBag, Plus, Search, CheckCircle, XCircle, Package, Send, Eye } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import ProductSelect from '@/components/common/ProductSelect'

interface PO {
  id: number; po_number: string; supplier_id: number; supplier?: { name: string }
  status: string; total: string; expected_date?: string; notes?: string; created_at: string
  items?: POItem[]
}
interface POItem { id: number; product_id: number; product_name?: string; product?: { name: string }; quantity: number; unit_cost?: string; cost_price?: string; received_quantity: number }
interface Supplier { id: number; name: string }

const statusBadge: Record<string, string> = { draft: 'badge-gray', pending: 'badge-info', sent: 'badge-info', received: 'badge-success', partial: 'badge-warning', cancelled: 'badge-danger', approved: 'badge-success', rejected: 'badge-danger' }

const emptyPO = { supplier_id: '', expected_date: '', notes: '', items: [{ product_id: '', product_name: '', quantity: '1', unit_cost: '' }] }

export default function PurchasesPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [searchParams] = useSearchParams()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState(() => searchParams.get('search') ?? '')

  useEffect(() => {
    const s = searchParams.get('search')
    if (s) setSearch(s)
  }, [searchParams])
  const [modal, setModal] = useState<'add' | 'view' | null>(null)
  const [selectedPO, setSelectedPO] = useState<PO | null>(null)
  const [form, setForm] = useState({ ...emptyPO })
  const [receiveModal, setReceiveModal] = useState(false)
  const [receivingPO, setReceivingPO] = useState<PO | null>(null)
  const [receiveQtys, setReceiveQtys] = useState<Record<number, string>>({})

  const { data, isLoading } = useQuery({
    queryKey: ['purchase-orders', page, search],
    queryFn: () => apiGet<{ success: boolean; data: PO[]; total?: number }>('/purchase-orders', { page, per_page: 20, search: search || undefined }),
    staleTime: 30_000,
  })
  const { data: suppData } = useQuery({
    queryKey: ['suppliers-list'],
    queryFn: () => apiGet<{ success: boolean; data: Supplier[] }>('/suppliers', { per_page: 100 }),
    staleTime: 120_000,
  })

  const orders = data?.data ?? []
  const suppliers = suppData?.data ?? []
  const canCreate = hasPermission('create_purchase_orders', 'manage_purchases')
  const canApprove = hasPermission('approve_purchase_orders', 'manage_purchases')
  const canReceive = hasPermission('receive_purchase_orders', 'manage_purchases')

  const createMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/purchase-orders', payload),
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['purchase-orders'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const actionMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: string }) => apiPost(`/purchase-orders/${id}/${action}`, {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['purchase-orders'] }) },
    onError: () => toast.error(t('save_failed')),
  })
  const receiveMutation = useMutation({
    mutationFn: ({ id, items }: { id: number; items: object[] }) => apiPost(`/purchase-orders/${id}/receive`, { items }),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['purchase-orders'] }); setReceiveModal(false) },
    onError: () => toast.error(t('save_failed')),
  })

  const openReceiveModal = (po: PO) => {
    const qtys: Record<number, string> = {}
    po.items?.forEach((item) => { qtys[item.id] = String(Math.max(0, item.quantity - (item.received_quantity ?? 0))) })
    setReceiveQtys(qtys)
    setReceivingPO(po)
    setReceiveModal(true)
  }

  const handleReceive = () => {
    if (!receivingPO) return
    const items = (receivingPO.items ?? [])
      .map((item) => ({ item_id: item.id, received_quantity: parseInt(receiveQtys[item.id] ?? '0') }))
      .filter((i) => i.received_quantity > 0)
    if (items.length === 0) return toast.error(t('error'))
    receiveMutation.mutate({ id: receivingPO.id, items })
  }

  const addItem = () => setForm((p) => ({ ...p, items: [...p.items, { product_id: '', product_name: '', quantity: '1', unit_cost: '' }] }))
  const removeItem = (i: number) => setForm((p) => ({ ...p, items: p.items.filter((_, idx) => idx !== i) }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.supplier_id) return toast.error(t('error'))
    const validItems = form.items.filter((item) => item.product_id && item.quantity)
    if (validItems.length === 0) return toast.error(t('error'))
    createMutation.mutate({
      supplier_id: parseInt(form.supplier_id),
      order_date: new Date().toISOString().slice(0, 10),
      expected_date: form.expected_date || undefined,
      notes: form.notes || undefined,
      items: validItems.map((item) => ({
        product_id: parseInt(item.product_id),
        product_name: item.product_name || '',
        quantity: parseInt(item.quantity),
        cost_price: parseFloat(item.unit_cost) || 0,
      })),
    })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><ShoppingBag className="h-6 w-6 text-primary-500" /> {t('purchase_orders')}</h1>
        {canCreate && <button onClick={() => { setForm({ ...emptyPO }); setModal('add') }} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> {t('create_po')}</button>}
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder={t('search')} className="input pl-9 w-full" />
      </div>

      <div className="card overflow-hidden">
        {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
          <>
            {/* ── Desktop table ─────────────────────── lg+ ── */}
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[650px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{[t('po_number'), t('select_supplier'), t('status'), t('total'), t('expected_date'), t('date'), t('actions')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {orders.length === 0 ? <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                    : orders.map((po) => (
                      <tr key={po.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-mono text-xs text-primary-600">{po.po_number}</td>
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{po.supplier?.name ?? `Supplier #${po.supplier_id}`}</td>
                        <td className="px-4 py-3"><span className={clsx('badge capitalize', statusBadge[po.status] ?? 'badge-gray')}>{po.status}</span></td>
                        <td className="px-4 py-3 font-semibold">{parseFloat(po.total ?? '0').toFixed(2)}</td>
                        <td className="px-4 py-3 text-gray-400">{po.expected_date?.slice(0, 10) ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-400 text-xs">{po.created_at?.slice(0, 10)}</td>
                        <td className="px-4 py-3">
                          <div className="flex gap-1">
                            <button onClick={() => { setSelectedPO(po); setModal('view') }} className="p-1 text-gray-400 hover:text-primary-600 rounded"><Eye className="h-4 w-4" /></button>
                            {canCreate && po.status === 'draft' && <button onClick={() => actionMutation.mutate({ id: po.id, action: 'submit' })} title="Submit" className="p-1 text-gray-400 hover:text-navy-600 rounded"><Send className="h-4 w-4" /></button>}
                            {canApprove && po.status === 'pending' && <>
                              <button onClick={() => actionMutation.mutate({ id: po.id, action: 'approve' })} title="Approve" className="p-1 text-gray-400 hover:text-green-600 rounded"><CheckCircle className="h-4 w-4" /></button>
                              <button onClick={() => actionMutation.mutate({ id: po.id, action: 'reject' })} title="Reject" className="p-1 text-gray-400 hover:text-red-600 rounded"><XCircle className="h-4 w-4" /></button>
                            </>}
                            {canReceive && (po.status === 'approved' || po.status === 'partial') && <button onClick={() => openReceiveModal(po)} title="Mark Received" className="p-1 text-gray-400 hover:text-green-600 rounded"><Package className="h-4 w-4" /></button>}
                          </div>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>

            {/* ── Mobile cards ──────────────────────── <lg ── */}
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {orders.length === 0 ? (
                <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
              ) : orders.map((po) => (
                <div key={po.id} className="p-4 space-y-2">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-mono text-xs font-semibold text-primary-600">{po.po_number}</span>
                    <span className={clsx('badge capitalize shrink-0', statusBadge[po.status] ?? 'badge-gray')}>{po.status}</span>
                  </div>
                  <p className="font-semibold text-gray-900 dark:text-white text-sm">{po.supplier?.name ?? `Supplier #${po.supplier_id}`}</p>
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                    <span className="font-bold text-gray-900 dark:text-white">{parseFloat(po.total ?? '0').toFixed(2)}</span>
                    {po.expected_date && <span>{isAr ? 'متوقع:' : 'Exp:'} {po.expected_date.slice(0, 10)}</span>}
                    <span>{po.created_at?.slice(0, 10)}</span>
                  </div>
                  <div className="flex flex-wrap gap-2 pt-1">
                    <button onClick={() => { setSelectedPO(po); setModal('view') }} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400 hover:bg-primary-100 transition-colors font-medium">
                      <Eye className="h-3.5 w-3.5" />{isAr ? 'عرض' : 'View'}
                    </button>
                    {canCreate && po.status === 'draft' && (
                      <button onClick={() => actionMutation.mutate({ id: po.id, action: 'submit' })} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-navy-50 text-navy-700 dark:bg-navy-900/20 dark:text-navy-400 hover:bg-navy-100 transition-colors font-medium">
                        <Send className="h-3.5 w-3.5" />{isAr ? 'إرسال' : 'Submit'}
                      </button>
                    )}
                    {canApprove && po.status === 'pending' && <>
                      <button onClick={() => actionMutation.mutate({ id: po.id, action: 'approve' })} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 hover:bg-green-100 transition-colors font-medium">
                        <CheckCircle className="h-3.5 w-3.5" />{isAr ? 'قبول' : 'Approve'}
                      </button>
                      <button onClick={() => actionMutation.mutate({ id: po.id, action: 'reject' })} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition-colors font-medium">
                        <XCircle className="h-3.5 w-3.5" />{isAr ? 'رفض' : 'Reject'}
                      </button>
                    </>}
                    {canReceive && (po.status === 'approved' || po.status === 'partial') && (
                      <button onClick={() => openReceiveModal(po)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 hover:bg-green-100 transition-colors font-medium">
                        <Package className="h-3.5 w-3.5" />{isAr ? 'استلام' : 'Receive'}
                      </button>
                    )}
                  </div>
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
              <button onClick={() => setPage((p) => p + 1)} disabled={orders.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      {/* Create PO Modal */}
      <Modal open={modal === 'add'} onClose={() => setModal(null)} title={t('create_po')} size="xl"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleSubmit} disabled={createMutation.isPending} className="btn btn-primary">{createMutation.isPending ? t('loading') : t('create_po')}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('select_supplier')} *</label>
              <select value={form.supplier_id} onChange={(e) => setForm((p) => ({ ...p, supplier_id: e.target.value }))} className="input w-full">
                <option value="">— {t('select_supplier')} —</option>
                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div><label className="label">{t('expected_date')}</label><input value={form.expected_date} type="date" onChange={(e) => setForm((p) => ({ ...p, expected_date: e.target.value }))} className="input w-full" /></div>
            <div className="col-span-2"><label className="label">{t('notes')}</label><input value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" /></div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">{t('items')}</label>
              <button type="button" onClick={addItem} className="text-xs text-primary-600 hover:underline flex items-center gap-1"><Plus className="h-3 w-3" /> {t('add_item')}</button>
            </div>
            <div className="space-y-2">
              {form.items.map((item, i) => (
                <div key={i} className="flex gap-2 items-center">
                  <ProductSelect
                    value={item.product_id}
                    onChange={(id, name) => setForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, product_id: id, product_name: name } : x) }))}
                    className="flex-1"
                  />
                  <input value={item.quantity} onChange={(e) => setForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, quantity: e.target.value } : x) }))} className="input w-20" placeholder={t('quantity')} type="number" min="1" />
                  <input value={item.unit_cost} onChange={(e) => setForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, unit_cost: e.target.value } : x) }))} className="input w-28" placeholder={t('unit_price')} type="number" step="0.01" />
                  {form.items.length > 1 && <button type="button" onClick={() => removeItem(i)} className="text-red-400 hover:text-red-600"><XCircle className="h-4 w-4" /></button>}
                </div>
              ))}
            </div>
          </div>
        </form>
      </Modal>

      {/* Receive PO Modal */}
      <Modal open={receiveModal} onClose={() => setReceiveModal(false)} title={`${t('receive_po')} #${receivingPO?.po_number ?? ''}`} size="lg"
        footer={<><button onClick={() => setReceiveModal(false)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleReceive} disabled={receiveMutation.isPending} className="btn btn-primary">{receiveMutation.isPending ? t('loading') : t('receive_po')}</button></>}>
        {receivingPO && (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[650px] text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{[t('name'), t('quantity'), t('po_status_received'), t('receive_po')].map((h) => <th key={h} className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {(receivingPO.items ?? []).length === 0
                  ? <tr><td colSpan={4} className="px-3 py-8 text-center text-gray-400">{t('no_data')}</td></tr>
                  : (receivingPO.items ?? []).map((item) => {
                    const remaining = item.quantity - (item.received_quantity ?? 0)
                    return (
                      <tr key={item.id}>
                        <td className="px-3 py-2 font-medium text-gray-900 dark:text-white">{item.product_name ?? item.product?.name ?? `#${item.product_id}`}</td>
                        <td className="px-3 py-2 text-gray-500">{item.quantity}</td>
                        <td className="px-3 py-2 text-gray-500">{item.received_quantity ?? 0}</td>
                        <td className="px-3 py-2">
                          <input type="number" min="0" max={remaining} value={receiveQtys[item.id] ?? '0'}
                            onChange={(e) => setReceiveQtys((p) => ({ ...p, [item.id]: e.target.value }))}
                            className="input w-20" />
                          {remaining > 0 && <span className="ml-2 text-xs text-gray-400">{t('of')} {remaining}</span>}
                        </td>
                      </tr>
                    )
                  })}
              </tbody>
            </table>
          </div>
        )}
      </Modal>

      {/* View PO Modal */}
      <Modal open={modal === 'view'} onClose={() => setModal(null)} title={`PO #${selectedPO?.po_number ?? ''}`} size="lg">
        {selectedPO && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('select_supplier')}:</span> <span className="font-medium">{selectedPO.supplier?.name}</span></div>
              <div><span className="text-gray-500">{t('status')}:</span> <span className={clsx('badge ml-1 capitalize', statusBadge[selectedPO.status] ?? 'badge-gray')}>{selectedPO.status}</span></div>
              <div><span className="text-gray-500">{t('expected_date')}:</span> <span>{selectedPO.expected_date?.slice(0, 10) ?? '—'}</span></div>
              <div><span className="text-gray-500">{t('total')}:</span> <span className="font-bold text-primary-600">{parseFloat(selectedPO.total ?? '0').toFixed(2)}</span></div>
            </div>
            {selectedPO.notes && <p className="text-sm text-gray-500 bg-gray-50 dark:bg-gray-700 p-3 rounded">{selectedPO.notes}</p>}
          </div>
        )}
      </Modal>
    </div>
  )
}
