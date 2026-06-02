import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Warehouse, Plus, ArrowLeftRight, Lock, Unlock } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import ProductSelect from '@/components/common/ProductSelect'

interface WarehouseItem { id: number; name: string; code: string; address?: string; keeper_name?: string; type?: string; is_active?: boolean; is_locked?: boolean }
interface Transfer { id: number; from_warehouse?: { name: string }; to_warehouse?: { name: string }; status: string; created_at: string; items_count?: number }

const emptyWh = { name: '', code: '', address: '', keeper_name: '' }
const emptyTransfer = { from_warehouse_id: '', to_warehouse_id: '', notes: '', items: [{ product_id: '', product_name: '', quantity: '' }] }

export default function WarehousePage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [tab, setTab] = useState<'warehouses' | 'transfers'>('warehouses')
  const [modal, setModal] = useState<'add' | 'transfer' | null>(null)
  const [form, setForm] = useState({ ...emptyWh })
  const [transferForm, setTransferForm] = useState({ ...emptyTransfer })

  const { data: whData, isLoading: whLoading } = useQuery({
    queryKey: ['warehouses'],
    queryFn: () => apiGet<{ success: boolean; data: WarehouseItem[] }>('/warehouses'),
    staleTime: 60_000,
  })
  const { data: transferData, isLoading: transferLoading } = useQuery({
    queryKey: ['warehouse-transfers'],
    queryFn: () => apiGet<{ success: boolean; data: Transfer[] }>('/warehouse-transfers?per_page=30'),
    staleTime: 30_000,
    enabled: tab === 'transfers',
  })

  const warehouses = whData?.data ?? []
  const transfers = transferData?.data ?? []
  const canManage = hasPermission('manage_warehouses', 'view_warehouse')

  const createWh = useMutation({
    mutationFn: (payload: object) => apiPost('/warehouses', payload),
    onSuccess: () => { toast.success('Warehouse created'); qc.invalidateQueries({ queryKey: ['warehouses'] }); setModal(null) },
    onError: () => toast.error('Failed to create warehouse'),
  })
  const toggleLock = useMutation({
    mutationFn: ({ id }: { id: number; locked: boolean }) => apiPost(`/warehouses/${id}/toggle-lock`, {}),
    onSuccess: () => { toast.success('Warehouse status updated'); qc.invalidateQueries({ queryKey: ['warehouses'] }) },
    onError: () => toast.error('Action failed'),
  })
  const createTransfer = useMutation({
    mutationFn: (payload: object) => apiPost('/warehouse-transfers', payload),
    onSuccess: () => { toast.success('Transfer created'); qc.invalidateQueries({ queryKey: ['warehouse-transfers'] }); setModal(null) },
    onError: (err: any) => toast.error(err?.response?.data?.message ?? 'Failed to create transfer'),
  })

  const addTransferItem = () => setTransferForm((p) => ({ ...p, items: [...p.items, { product_id: '', product_name: '', quantity: '' }] }))

  const handleCreateWh = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name || !form.code) return toast.error('Name and code required')
    createWh.mutate({ name: form.name, code: form.code, address: form.address || undefined, keeper_name: form.keeper_name || undefined })
  }

  const handleCreateTransfer = (e: React.FormEvent) => {
    e.preventDefault()
    if (!transferForm.from_warehouse_id || !transferForm.to_warehouse_id) return toast.error('From and to warehouses required')
    if (transferForm.from_warehouse_id === transferForm.to_warehouse_id) return toast.error('Cannot transfer to same warehouse')
    createTransfer.mutate({
      from_warehouse_id: parseInt(transferForm.from_warehouse_id),
      to_warehouse_id: parseInt(transferForm.to_warehouse_id),
      notes: transferForm.notes || undefined,
      items: transferForm.items.filter((i) => i.product_id && i.quantity).map((i) => ({ product_id: parseInt(i.product_id), quantity: parseInt(i.quantity) })),
    })
  }

  const statusBadge: Record<string, string> = { pending: 'badge-warning', in_transit: 'badge-info', received: 'badge-success', cancelled: 'badge-danger' }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Warehouse className="h-6 w-6 text-primary-500" /> Warehouses</h1>
        {canManage && (
          <div className="flex gap-2">
            {tab === 'warehouses' && <button onClick={() => { setForm({ ...emptyWh }); setModal('add') }} className="btn btn-primary flex items-center gap-2 text-sm"><Plus className="h-4 w-4" /> Add Warehouse</button>}
            {tab === 'transfers' && <button onClick={() => { setTransferForm({ ...emptyTransfer }); setModal('transfer') }} className="btn btn-primary flex items-center gap-2 text-sm"><ArrowLeftRight className="h-4 w-4" /> New Transfer</button>}
          </div>
        )}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['warehouses', 'transfers'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === t ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{t}</button>
        ))}
      </div>

      {tab === 'warehouses' && (
        <div className="card overflow-hidden">
          {whLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700"><tr>{['Name', 'Code', 'Keeper', 'Address', 'Type', 'Status', ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {warehouses.length === 0 ? <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">No warehouses</td></tr>
                    : warehouses.map((w) => (
                      <tr key={w.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{w.name}</td>
                        <td className="px-4 py-3 font-mono text-xs text-gray-400">{w.code}</td>
                        <td className="px-4 py-3 text-gray-500">{w.keeper_name ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-500 max-w-32 truncate">{w.address ?? '—'}</td>
                        <td className="px-4 py-3"><span className="badge badge-gray capitalize">{w.type ?? 'default'}</span></td>
                        <td className="px-4 py-3">
                          {w.is_locked ? <span className="badge badge-danger flex items-center gap-1 w-fit"><Lock className="h-3 w-3" />Locked</span>
                            : <span className="badge badge-success flex items-center gap-1 w-fit"><Unlock className="h-3 w-3" />{w.is_active ? 'Active' : 'Inactive'}</span>}
                        </td>
                        <td className="px-4 py-3">
                          {canManage && (
                            <button onClick={() => toggleLock.mutate({ id: w.id, locked: !w.is_locked })}
                              className={clsx('p-1.5 rounded', w.is_locked ? 'text-amber-500 hover:bg-amber-50' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700')}>
                              {w.is_locked ? <Unlock className="h-4 w-4" /> : <Lock className="h-4 w-4" />}
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {tab === 'transfers' && (
        <div className="card overflow-hidden">
          {transferLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700"><tr>{['From', 'To', 'Items', 'Status', 'Date'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {transfers.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No transfers</td></tr>
                  : transfers.map((t) => (
                    <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{t.from_warehouse?.name ?? '—'}</td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{t.to_warehouse?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{t.items_count ?? '—'}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize', statusBadge[t.status] ?? 'badge-gray')}>{t.status}</span></td>
                      <td className="px-4 py-3 text-gray-400 text-xs">{t.created_at?.slice(0, 10)}</td>
                    </tr>
                  ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Add Warehouse Modal */}
      <Modal open={modal === 'add'} onClose={() => setModal(null)} title="Add Warehouse" size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={handleCreateWh} disabled={createWh.isPending} className="btn btn-primary">{createWh.isPending ? 'Creating…' : 'Create'}</button></>}>
        <form onSubmit={handleCreateWh} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div><label className="label">Name *</label><input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" required /></div>
            <div><label className="label">Code *</label><input value={form.code} onChange={(e) => setForm((p) => ({ ...p, code: e.target.value }))} className="input w-full" placeholder="WH001" required /></div>
            <div><label className="label">Keeper Name</label><input value={form.keeper_name} onChange={(e) => setForm((p) => ({ ...p, keeper_name: e.target.value }))} className="input w-full" /></div>
            <div className="col-span-2"><label className="label">Address</label><input value={form.address} onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))} className="input w-full" /></div>
          </div>
        </form>
      </Modal>

      {/* Transfer Modal */}
      <Modal open={modal === 'transfer'} onClose={() => setModal(null)} title="New Stock Transfer" size="lg"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={handleCreateTransfer} disabled={createTransfer.isPending} className="btn btn-primary">{createTransfer.isPending ? 'Creating…' : 'Create Transfer'}</button></>}>
        <form onSubmit={handleCreateTransfer} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">From Warehouse *</label>
              <select value={transferForm.from_warehouse_id} onChange={(e) => setTransferForm((p) => ({ ...p, from_warehouse_id: e.target.value }))} className="input w-full">
                <option value="">— Select —</option>
                {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </div>
            <div>
              <label className="label">To Warehouse *</label>
              <select value={transferForm.to_warehouse_id} onChange={(e) => setTransferForm((p) => ({ ...p, to_warehouse_id: e.target.value }))} className="input w-full">
                <option value="">— Select —</option>
                {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </div>
            <div className="col-span-2"><label className="label">Notes</label><input value={transferForm.notes} onChange={(e) => setTransferForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" /></div>
          </div>
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">Items</label>
              <button type="button" onClick={addTransferItem} className="text-xs text-primary-600 hover:underline flex items-center gap-1"><Plus className="h-3 w-3" />Add Item</button>
            </div>
            {transferForm.items.map((item, i) => (
              <div key={i} className="flex gap-2 mb-2">
                <ProductSelect
                    value={item.product_id}
                    onChange={(id, name) => setTransferForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, product_id: id, product_name: name } : x) }))}
                    className="flex-1"
                  />
                <input value={item.quantity} type="number" min="1" onChange={(e) => setTransferForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, quantity: e.target.value } : x) }))} className="input w-24" placeholder="Qty" />
              </div>
            ))}
          </div>
        </form>
      </Modal>
    </div>
  )
}
