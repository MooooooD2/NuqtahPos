import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { RotateCcw, Plus, Eye, Search, ArrowLeft } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface PurchaseReturn {
  id: number
  return_number: string
  supplier?: { name: string }
  return_date: string
  total: string
  refund_method: string
  status: string
  notes?: string
  items?: ReturnItem[]
  recorded_by?: string
}

interface ReturnItem {
  id: number
  product_name: string
  quantity: number
  unit_cost: string
}

interface PO {
  id: number
  po_number: string
  supplier?: { name: string }
}

interface ReturnableItem {
  id: number
  product_id: number
  product_name: string
  quantity: number
  returnable_quantity: number
  unit_cost: string
}

const refundBadge: Record<string, string> = { credit_note: 'badge-info', cash: 'badge-success' }
const statusBadge: Record<string, string> = { pending: 'badge-warning', approved: 'badge-success', rejected: 'badge-danger', completed: 'badge-success' }

export default function PurchaseReturnsPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [modal, setModal] = useState<'new-step1' | 'new-step2' | 'view' | null>(null)
  const [poSearch, setPoSearch] = useState('')
  const [selectedPO, setSelectedPO] = useState<PO | null>(null)
  const [returnableItems, setReturnableItems] = useState<ReturnableItem[]>([])
  const [returnQtys, setReturnQtys] = useState<Record<number, string>>({})
  const [refundMethod, setRefundMethod] = useState<'credit_note' | 'cash'>('credit_note')
  const [reason, setReason] = useState('')
  const [viewReturn, setViewReturn] = useState<PurchaseReturn | null>(null)

  const canManage = hasPermission('view_warehouse')

  const { data, isLoading } = useQuery({
    queryKey: ['purchase-returns', page],
    queryFn: () => apiGet<{ purchase_returns: { data: PurchaseReturn[]; total?: number } }>('/purchase-returns', { page, per_page: 20 }),
    staleTime: 30_000,
  })

  const { data: poData, isLoading: poLoading } = useQuery({
    queryKey: ['po-search', poSearch],
    queryFn: () => apiGet<{ data: PO[] }>('/purchase-orders', { search: poSearch, per_page: 10 }),
    enabled: modal === 'new-step1' && poSearch.length >= 2,
    staleTime: 10_000,
  })

  const returnableQuery = useQuery({
    queryKey: ['returnable-items', selectedPO?.id],
    queryFn: () => apiGet<{ success: boolean; items: ReturnableItem[] }>(`/purchase-orders/${selectedPO!.id}/returnable-items`),
    enabled: !!selectedPO,
    staleTime: 10_000,
  })

  const returns = data?.purchase_returns?.data ?? []
  const pos = poData?.data ?? []

  const openNew = () => { setPoSearch(''); setSelectedPO(null); setReturnableItems([]); setReturnQtys({}); setRefundMethod('credit_note'); setReason(''); setModal('new-step1') }

  const selectPO = async (po: PO) => {
    setSelectedPO(po)
    setModal('new-step2')
  }

  const handleStep2Load = () => {
    const items = returnableQuery.data?.items ?? []
    if (items.length && returnableItems.length === 0) {
      setReturnableItems(items)
      const qtys: Record<number, string> = {}
      items.forEach((i) => { qtys[i.id] = '0' })
      setReturnQtys(qtys)
    }
  }

  if (modal === 'new-step2' && returnableQuery.isSuccess && returnableItems.length === 0) {
    handleStep2Load()
  }

  const createMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/purchase-returns', payload),
    onSuccess: () => { toast.success('Return recorded'); qc.invalidateQueries({ queryKey: ['purchase-returns'] }); setModal(null) },
    onError: () => toast.error('Failed to record return'),
  })

  const handleSubmit = () => {
    if (!selectedPO) return toast.error('No PO selected')
    const items = returnableItems
      .filter((i) => parseInt(returnQtys[i.id] ?? '0') > 0)
      .map((i) => ({ product_id: i.product_id, quantity: parseInt(returnQtys[i.id]) }))
    if (items.length === 0) return toast.error('Select at least one item to return')
    createMutation.mutate({ purchase_order_id: selectedPO.id, items, refund_method: refundMethod, reason: reason || undefined })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <RotateCcw className="h-6 w-6 text-primary-500" /> Purchase Returns
        </h1>
        {canManage && (
          <button onClick={openNew} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> New Return
          </button>
        )}
      </div>

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  {['Return #', 'Supplier', 'Return Date', 'Total', 'Refund Method', 'Status', 'Recorded By', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {returns.length === 0 ? (
                  <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">No purchase returns found</td></tr>
                ) : returns.map((r) => (
                  <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 font-mono text-xs text-primary-600">{r.return_number}</td>
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{r.supplier?.name ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-400">{r.return_date?.slice(0, 10)}</td>
                    <td className="px-4 py-3 font-semibold">{parseFloat(r.total ?? '0').toFixed(2)}</td>
                    <td className="px-4 py-3">
                      <span className={clsx('badge capitalize', refundBadge[r.refund_method] ?? 'badge-gray')}>
                        {r.refund_method?.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={clsx('badge capitalize', statusBadge[r.status] ?? 'badge-gray')}>{r.status}</span>
                    </td>
                    <td className="px-4 py-3 text-gray-500">{r.recorded_by ?? '—'}</td>
                    <td className="px-4 py-3">
                      <button onClick={() => { setViewReturn(r); setModal('view') }} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded">
                        <Eye className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {(data?.purchase_returns?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">Page {page}</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Prev</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={returns.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Next</button>
            </div>
          </div>
        )}
      </div>

      {/* Step 1: Search PO */}
      <Modal
        open={modal === 'new-step1'}
        onClose={() => setModal(null)}
        title="New Purchase Return — Step 1: Find PO"
        size="md"
      >
        <div className="space-y-4">
          <div>
            <label className="label">Search PO Number</label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                value={poSearch}
                onChange={(e) => setPoSearch(e.target.value)}
                placeholder="e.g. PO-0001…"
                className="input pl-9 w-full"
              />
            </div>
          </div>
          {poLoading && <div className="flex justify-center py-4"><LoadingSpinner size="sm" /></div>}
          {!poLoading && pos.length > 0 && (
            <ul className="divide-y divide-gray-100 dark:divide-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
              {pos.map((po) => (
                <li key={po.id}>
                  <button
                    onClick={() => selectPO(po)}
                    className="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors"
                  >
                    <span className="font-mono text-sm text-primary-600">{po.po_number}</span>
                    <span className="text-gray-500 text-sm">{po.supplier?.name ?? '—'}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
          {!poLoading && poSearch.length >= 2 && pos.length === 0 && (
            <p className="text-center text-gray-400 py-4">No purchase orders found</p>
          )}
        </div>
      </Modal>

      {/* Step 2: Select items & submit */}
      <Modal
        open={modal === 'new-step2'}
        onClose={() => setModal(null)}
        title={`New Return — PO ${selectedPO?.po_number ?? ''}`}
        size="xl"
        footer={
          <>
            <button onClick={() => setModal('new-step1')} className="btn btn-secondary flex items-center gap-1">
              <ArrowLeft className="h-4 w-4" /> Back
            </button>
            <button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleSubmit} disabled={createMutation.isPending} className="btn btn-primary">
              {createMutation.isPending ? 'Submitting…' : 'Submit Return'}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          {returnableQuery.isLoading ? (
            <div className="flex justify-center py-8"><LoadingSpinner size="lg" /></div>
          ) : (
            <>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>
                      {['Product', 'Ordered', 'Returnable', 'Return Qty', 'Unit Cost'].map((h) => (
                        <th key={h} className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {returnableItems.length === 0 ? (
                      <tr><td colSpan={5} className="px-3 py-8 text-center text-gray-400">No returnable items</td></tr>
                    ) : returnableItems.map((item) => (
                      <tr key={item.id}>
                        <td className="px-3 py-2 font-medium text-gray-900 dark:text-white">{item.product_name}</td>
                        <td className="px-3 py-2 text-gray-500">{item.quantity}</td>
                        <td className="px-3 py-2 text-gray-500">{item.returnable_quantity}</td>
                        <td className="px-3 py-2">
                          <input
                            type="number"
                            min="0"
                            max={item.returnable_quantity}
                            value={returnQtys[item.id] ?? '0'}
                            onChange={(e) => setReturnQtys((p) => ({ ...p, [item.id]: e.target.value }))}
                            className="input w-20"
                          />
                        </td>
                        <td className="px-3 py-2 text-gray-500">{parseFloat(item.unit_cost).toFixed(2)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="label">Refund Method *</label>
                  <select value={refundMethod} onChange={(e) => setRefundMethod(e.target.value as 'credit_note' | 'cash')} className="input w-full">
                    <option value="credit_note">Credit Note</option>
                    <option value="cash">Cash</option>
                  </select>
                </div>
                <div>
                  <label className="label">Reason</label>
                  <input value={reason} onChange={(e) => setReason(e.target.value)} className="input w-full" placeholder="Optional reason" />
                </div>
              </div>
            </>
          )}
        </div>
      </Modal>

      {/* View detail modal */}
      <Modal
        open={modal === 'view'}
        onClose={() => setModal(null)}
        title={`Return #${viewReturn?.return_number ?? ''}`}
        size="lg"
      >
        {viewReturn && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">Supplier:</span> <span className="font-medium">{viewReturn.supplier?.name ?? '—'}</span></div>
              <div><span className="text-gray-500">Date:</span> <span>{viewReturn.return_date?.slice(0, 10)}</span></div>
              <div>
                <span className="text-gray-500">Refund Method:</span>{' '}
                <span className={clsx('badge ml-1 capitalize', refundBadge[viewReturn.refund_method] ?? 'badge-gray')}>
                  {viewReturn.refund_method?.replace('_', ' ')}
                </span>
              </div>
              <div>
                <span className="text-gray-500">Status:</span>{' '}
                <span className={clsx('badge ml-1 capitalize', statusBadge[viewReturn.status] ?? 'badge-gray')}>{viewReturn.status}</span>
              </div>
              <div><span className="text-gray-500">Total:</span> <span className="font-bold text-primary-600">{parseFloat(viewReturn.total ?? '0').toFixed(2)}</span></div>
              {viewReturn.notes && <div className="col-span-2"><span className="text-gray-500">Notes:</span> <span>{viewReturn.notes}</span></div>}
            </div>
            {viewReturn.items && viewReturn.items.length > 0 && (
              <div>
                <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Returned Items</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        {['Product', 'Qty', 'Unit Cost'].map((h) => (
                          <th key={h} className="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {viewReturn.items.map((item: ReturnItem, i) => (
                        <tr key={i}>
                          <td className="px-3 py-2 text-gray-900 dark:text-white">{item.product_name}</td>
                          <td className="px-3 py-2 text-gray-500">{item.quantity}</td>
                          <td className="px-3 py-2 text-gray-500">{parseFloat(item.unit_cost ?? '0').toFixed(2)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
