import { useState } from 'react'
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
interface POItem { id: number; product_id: number; product?: { name: string }; quantity: number; unit_cost: string; received_quantity: number }
interface Supplier { id: number; name: string }

const statusBadge: Record<string, string> = { draft: 'badge-gray', sent: 'badge-info', received: 'badge-success', partial: 'badge-warning', cancelled: 'badge-danger', approved: 'badge-success' }

const emptyPO = { supplier_id: '', expected_date: '', notes: '', items: [{ product_id: '', product_name: '', quantity: '1', unit_cost: '' }] }

export default function PurchasesPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState<'add' | 'view' | null>(null)
  const [selectedPO, setSelectedPO] = useState<PO | null>(null)
  const [form, setForm] = useState({ ...emptyPO })

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
    onSuccess: () => { toast.success('Purchase order created'); qc.invalidateQueries({ queryKey: ['purchase-orders'] }); setModal(null) },
    onError: () => toast.error('Failed to create PO'),
  })
  const actionMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: string }) => apiPost(`/purchase-orders/${id}/${action}`, {}),
    onSuccess: (_, vars) => { toast.success(`PO ${vars.action}d`); qc.invalidateQueries({ queryKey: ['purchase-orders'] }) },
    onError: () => toast.error('Action failed'),
  })

  const addItem = () => setForm((p) => ({ ...p, items: [...p.items, { product_id: '', product_name: '', quantity: '1', unit_cost: '' }] }))
  const removeItem = (i: number) => setForm((p) => ({ ...p, items: p.items.filter((_, idx) => idx !== i) }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.supplier_id) return toast.error('Supplier required')
    createMutation.mutate({
      supplier_id: parseInt(form.supplier_id),
      expected_date: form.expected_date || undefined,
      notes: form.notes || undefined,
      items: form.items.filter((item) => item.product_id && item.quantity).map((item) => ({
        product_id: parseInt(item.product_id),
        quantity: parseInt(item.quantity),
        unit_cost: parseFloat(item.unit_cost) || 0,
      })),
    })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><ShoppingBag className="h-6 w-6 text-primary-500" /> Purchase Orders</h1>
        {canCreate && <button onClick={() => { setForm({ ...emptyPO }); setModal('add') }} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> New PO</button>}
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder="Search POs…" className="input pl-9 w-full" />
      </div>

      <div className="card overflow-hidden">
        {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['PO #', 'Supplier', 'Status', 'Total', 'Expected', 'Date', 'Actions'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {orders.length === 0 ? <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">No purchase orders found</td></tr>
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
                          {canCreate && po.status === 'draft' && (
                            <button onClick={() => actionMutation.mutate({ id: po.id, action: 'submit' })} title="Submit" className="p-1 text-gray-400 hover:text-blue-600 rounded"><Send className="h-4 w-4" /></button>
                          )}
                          {canApprove && po.status === 'sent' && (
                            <>
                              <button onClick={() => actionMutation.mutate({ id: po.id, action: 'approve' })} title="Approve" className="p-1 text-gray-400 hover:text-green-600 rounded"><CheckCircle className="h-4 w-4" /></button>
                              <button onClick={() => actionMutation.mutate({ id: po.id, action: 'reject' })} title="Reject" className="p-1 text-gray-400 hover:text-red-600 rounded"><XCircle className="h-4 w-4" /></button>
                            </>
                          )}
                          {canReceive && (po.status === 'approved' || po.status === 'partial') && (
                            <button onClick={() => actionMutation.mutate({ id: po.id, action: 'receive' })} title="Mark Received" className="p-1 text-gray-400 hover:text-green-600 rounded"><Package className="h-4 w-4" /></button>
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
            <span className="text-sm text-gray-500">Page {page}</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Prev</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={orders.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Next</button>
            </div>
          </div>
        )}
      </div>

      {/* Create PO Modal */}
      <Modal open={modal === 'add'} onClose={() => setModal(null)} title="New Purchase Order" size="xl"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={handleSubmit} disabled={createMutation.isPending} className="btn btn-primary">{createMutation.isPending ? 'Creating…' : 'Create PO'}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">Supplier *</label>
              <select value={form.supplier_id} onChange={(e) => setForm((p) => ({ ...p, supplier_id: e.target.value }))} className="input w-full">
                <option value="">— Select supplier —</option>
                {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div><label className="label">Expected Date</label><input value={form.expected_date} type="date" onChange={(e) => setForm((p) => ({ ...p, expected_date: e.target.value }))} className="input w-full" /></div>
            <div className="col-span-2"><label className="label">Notes</label><input value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" /></div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">Items</label>
              <button type="button" onClick={addItem} className="text-xs text-primary-600 hover:underline flex items-center gap-1"><Plus className="h-3 w-3" /> Add Item</button>
            </div>
            <div className="space-y-2">
              {form.items.map((item, i) => (
                <div key={i} className="flex gap-2 items-center">
                  <ProductSelect
                    value={item.product_id}
                    onChange={(id, name) => setForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, product_id: id, product_name: name } : x) }))}
                    className="flex-1"
                  />
                  <input value={item.quantity} onChange={(e) => setForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, quantity: e.target.value } : x) }))} className="input w-20" placeholder="Qty" type="number" min="1" />
                  <input value={item.unit_cost} onChange={(e) => setForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, unit_cost: e.target.value } : x) }))} className="input w-28" placeholder="Unit Cost" type="number" step="0.01" />
                  {form.items.length > 1 && <button type="button" onClick={() => removeItem(i)} className="text-red-400 hover:text-red-600"><XCircle className="h-4 w-4" /></button>}
                </div>
              ))}
            </div>
          </div>
        </form>
      </Modal>

      {/* View PO Modal */}
      <Modal open={modal === 'view'} onClose={() => setModal(null)} title={`PO #${selectedPO?.po_number ?? ''}`} size="lg">
        {selectedPO && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">Supplier:</span> <span className="font-medium">{selectedPO.supplier?.name}</span></div>
              <div><span className="text-gray-500">Status:</span> <span className={clsx('badge ml-1 capitalize', statusBadge[selectedPO.status] ?? 'badge-gray')}>{selectedPO.status}</span></div>
              <div><span className="text-gray-500">Expected:</span> <span>{selectedPO.expected_date?.slice(0, 10) ?? '—'}</span></div>
              <div><span className="text-gray-500">Total:</span> <span className="font-bold text-primary-600">{parseFloat(selectedPO.total ?? '0').toFixed(2)}</span></div>
            </div>
            {selectedPO.notes && <p className="text-sm text-gray-500 bg-gray-50 dark:bg-gray-700 p-3 rounded">{selectedPO.notes}</p>}
          </div>
        )}
      </Modal>
    </div>
  )
}
