import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Warehouse, Plus, ArrowLeftRight, Lock, Unlock, Trash2, AlertTriangle } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import ProductSelect from '@/components/common/ProductSelect'
import { useTranslation } from 'react-i18next'

interface WarehouseItem { id: number; name: string; code: string; address?: string; keeper_name?: string; type?: string; is_active?: boolean; is_locked?: boolean }
interface Transfer { id: number; from_warehouse?: { name: string }; to_warehouse?: { name: string }; status: string; created_at: string; items_count?: number }
interface WarehouseStockEntry { product_id: number; quantity: number; reserved_qty: number }

const emptyWh = { name: '', code: '', address: '', keeper_name: '' }
const emptyTransfer = { from_warehouse_id: '', to_warehouse_id: '', notes: '', items: [{ product_id: '', product_name: '', quantity: '' }] }

export default function WarehousePage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
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

  // Load stock of the selected source warehouse so we can show available qty per product
  const fromId = transferForm.from_warehouse_id
  const { data: fromStockData } = useQuery({
    queryKey: ['warehouse-stock', fromId],
    queryFn: () => apiGet<{ success: boolean; stock: WarehouseStockEntry[] }>(`/warehouses/${fromId}/stock`),
    enabled: !!fromId && modal === 'transfer',
    staleTime: 30_000,
  })
  // Build a map: product_id → available quantity (total - reserved)
  const availableMap: Record<number, number> = {}
  for (const s of fromStockData?.stock ?? []) {
    availableMap[s.product_id] = Math.max(0, (s.quantity ?? 0) - (s.reserved_qty ?? 0))
  }

  const warehouses = whData?.data ?? []
  const transfers = transferData?.data ?? []
  const canManage = hasPermission('manage_warehouses', 'view_warehouse')

  const createWh = useMutation({
    mutationFn: (payload: object) => apiPost('/warehouses', payload),
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['warehouses'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const toggleLock = useMutation({
    mutationFn: ({ id }: { id: number; locked: boolean }) => apiPost(`/warehouses/${id}/toggle-lock`, {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['warehouses'] }) },
    onError: () => toast.error(t('save_failed')),
  })
  const createTransfer = useMutation({
    mutationFn: (payload: object) => apiPost('/warehouse-transfers', payload),
    onSuccess: () => {
      toast.success(t('created_success'))
      qc.invalidateQueries({ queryKey: ['warehouse-transfers'] })
      qc.invalidateQueries({ queryKey: ['warehouse-stock', fromId] })
      setModal(null)
      setTransferForm({ ...emptyTransfer })
    },
    onError: (err: any) => {
      const msg = err?.response?.data?.message
        ?? Object.values(err?.response?.data?.errors ?? {}).flat().join(' ')
        ?? t('save_failed')
      toast.error(msg)
    },
  })

  const addTransferItem = () => setTransferForm((p) => ({ ...p, items: [...p.items, { product_id: '', product_name: '', quantity: '' }] }))
  const removeTransferItem = (idx: number) => setTransferForm((p) => ({ ...p, items: p.items.filter((_, i) => i !== idx) }))

  const handleCreateWh = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name || !form.code) return toast.error(t('error'))
    createWh.mutate({ name: form.name, code: form.code, address: form.address || undefined, keeper_name: form.keeper_name || undefined })
  }

  const handleCreateTransfer = () => {
    if (!transferForm.from_warehouse_id || !transferForm.to_warehouse_id) return toast.error(t('error'))
    if (transferForm.from_warehouse_id === transferForm.to_warehouse_id) return toast.error(t('error'))
    const validItems = transferForm.items.filter((i) => i.product_id && i.quantity && parseInt(i.quantity) > 0)
    if (validItems.length === 0) return toast.error(t('error'))
    createTransfer.mutate({
      from_warehouse_id: parseInt(transferForm.from_warehouse_id),
      to_warehouse_id: parseInt(transferForm.to_warehouse_id),
      notes: transferForm.notes || null,
      items: validItems.map((i) => ({ product_id: parseInt(i.product_id), quantity: parseInt(i.quantity) })),
    })
  }

  const statusBadge: Record<string, string> = { pending: 'badge-warning', in_transit: 'badge-info', received: 'badge-success', cancelled: 'badge-danger' }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Warehouse className="h-6 w-6 text-primary-500" /> {t('warehouses')}</h1>
        {canManage && (
          <div className="flex gap-2">
            {tab === 'warehouses' && <button onClick={() => { setForm({ ...emptyWh }); setModal('add') }} className="btn btn-primary flex items-center gap-2 text-sm"><Plus className="h-4 w-4" /> {t('new_warehouse')}</button>}
            {tab === 'transfers' && <button onClick={() => { setTransferForm({ ...emptyTransfer }); setModal('transfer') }} className="btn btn-primary flex items-center gap-2 text-sm"><ArrowLeftRight className="h-4 w-4" /> {t('new_transfer')}</button>}
          </div>
        )}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['warehouses', 'transfers'] as const).map((tab_key) => (
          <button key={tab_key} onClick={() => setTab(tab_key)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === tab_key ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{tab_key === 'warehouses' ? t('warehouses') : t('warehouse_transfers')}</button>
        ))}
      </div>

      {tab === 'warehouses' && (
        <div className="card overflow-hidden">
          {whLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[650px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700"><tr>{[t('name'), t('warehouse_code'), t('warehouse_keeper'), t('address'), t('type'), t('status'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {warehouses.length === 0 ? <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
                      : warehouses.map((w) => (
                        <tr key={w.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{w.name}</td>
                          <td className="px-4 py-3 font-mono text-xs text-gray-400">{w.code}</td>
                          <td className="px-4 py-3 text-gray-500">{w.keeper_name ?? '—'}</td>
                          <td className="px-4 py-3 text-gray-500 max-w-32 truncate">{w.address ?? '—'}</td>
                          <td className="px-4 py-3"><span className="badge badge-gray capitalize">{w.type ?? 'default'}</span></td>
                          <td className="px-4 py-3">
                            {w.is_locked ? <span className="badge badge-danger flex items-center gap-1 w-fit"><Lock className="h-3 w-3" />{t('locked')}</span>
                              : <span className="badge badge-success flex items-center gap-1 w-fit"><Unlock className="h-3 w-3" />{w.is_active ? t('active') : t('inactive')}</span>}
                          </td>
                          <td className="px-4 py-3">
                            {canManage && <button onClick={() => toggleLock.mutate({ id: w.id, locked: !w.is_locked })} className={clsx('p-1.5 rounded', w.is_locked ? 'text-amber-500 hover:bg-amber-50' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700')}>{w.is_locked ? <Unlock className="h-4 w-4" /> : <Lock className="h-4 w-4" />}</button>}
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {warehouses.length === 0 ? <p className="px-4 py-10 text-center text-gray-400">{t('no_data')}</p>
                  : warehouses.map((w) => (
                    <div key={w.id} className="p-4 space-y-2">
                      <div className="flex items-start justify-between gap-2">
                        <p className="font-semibold text-gray-900 dark:text-white text-sm">{w.name}</p>
                        {w.is_locked ? <span className="badge badge-danger flex items-center gap-1 shrink-0"><Lock className="h-3 w-3" />{t('locked')}</span>
                          : <span className="badge badge-success flex items-center gap-1 shrink-0"><Unlock className="h-3 w-3" />{w.is_active ? t('active') : t('inactive')}</span>}
                      </div>
                      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span className="font-mono">{w.code}</span>
                        <span className="badge badge-gray capitalize">{w.type ?? 'default'}</span>
                        {w.keeper_name && <span>{w.keeper_name}</span>}
                        {w.address && <span className="truncate max-w-48">{w.address}</span>}
                      </div>
                      {canManage && (
                        <button onClick={() => toggleLock.mutate({ id: w.id, locked: !w.is_locked })}
                          className={clsx('flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg font-medium transition-colors', w.is_locked ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200')}>
                          {w.is_locked ? <><Unlock className="h-3.5 w-3.5" />{isAr ? 'فتح القفل' : 'Unlock'}</> : <><Lock className="h-3.5 w-3.5" />{isAr ? 'قفل' : 'Lock'}</>}
                        </button>
                      )}
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}

      {tab === 'transfers' && (
        <div className="card overflow-hidden">
          {transferLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[650px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700"><tr>{[t('from_warehouse'), t('to_warehouse'), t('transfer_items'), t('status'), t('date')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {transfers.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
                      : transfers.map((tr) => (
                        <tr key={tr.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{tr.from_warehouse?.name ?? '—'}</td>
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{tr.to_warehouse?.name ?? '—'}</td>
                          <td className="px-4 py-3 text-gray-500">{tr.items_count ?? '—'}</td>
                          <td className="px-4 py-3"><span className={clsx('badge capitalize', statusBadge[tr.status] ?? 'badge-gray')}>{tr.status}</span></td>
                          <td className="px-4 py-3 text-gray-400 text-xs">{tr.created_at?.slice(0, 10)}</td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {transfers.length === 0 ? <p className="px-4 py-10 text-center text-gray-400">{t('no_data')}</p>
                  : transfers.map((tr) => (
                    <div key={tr.id} className="p-4 space-y-1.5">
                      <div className="flex items-center justify-between gap-2">
                        <p className="text-sm text-gray-900 dark:text-white">
                          <span className="font-semibold">{tr.from_warehouse?.name ?? '—'}</span>
                          <span className="text-gray-400 mx-1.5">→</span>
                          <span className="font-semibold">{tr.to_warehouse?.name ?? '—'}</span>
                        </p>
                        <span className={clsx('badge capitalize shrink-0', statusBadge[tr.status] ?? 'badge-gray')}>{tr.status}</span>
                      </div>
                      <div className="flex items-center gap-3 text-xs text-gray-500">
                        {tr.items_count !== undefined && <span>{tr.items_count} {isAr ? 'بند' : 'items'}</span>}
                        <span>{tr.created_at?.slice(0, 10)}</span>
                      </div>
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}

      {/* Add Warehouse Modal */}
      <Modal open={modal === 'add'} onClose={() => setModal(null)} title={t('new_warehouse')} size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleCreateWh} disabled={createWh.isPending} className="btn btn-primary">{createWh.isPending ? t('creating') : t('create')}</button></>}>
        <form onSubmit={handleCreateWh} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div><label className="label">{t('name')} *</label><input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" required /></div>
            <div><label className="label">{t('warehouse_code')} *</label><input value={form.code} onChange={(e) => setForm((p) => ({ ...p, code: e.target.value }))} className="input w-full" required /></div>
            <div><label className="label">{t('warehouse_keeper')}</label><input value={form.keeper_name} onChange={(e) => setForm((p) => ({ ...p, keeper_name: e.target.value }))} className="input w-full" /></div>
            <div className="col-span-2"><label className="label">{t('address')}</label><input value={form.address} onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))} className="input w-full" /></div>
          </div>
        </form>
      </Modal>

      {/* Transfer Modal */}
      <Modal open={modal === 'transfer'} onClose={() => setModal(null)} title={t('new_transfer')} size="lg"
        footer={<><button type="button" onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button type="button" onClick={handleCreateTransfer} disabled={createTransfer.isPending} className="btn btn-primary">{createTransfer.isPending ? t('creating') : t('create')}</button></>}>
        <form onSubmit={(e) => { e.preventDefault(); handleCreateTransfer() }} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('from_warehouse')} *</label>
              <select value={transferForm.from_warehouse_id} onChange={(e) => setTransferForm((p) => ({ ...p, from_warehouse_id: e.target.value }))} className="input w-full">
                <option value="">— {t('select')} —</option>
                {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </div>
            <div>
              <label className="label">{t('to_warehouse')} *</label>
              <select value={transferForm.to_warehouse_id} onChange={(e) => setTransferForm((p) => ({ ...p, to_warehouse_id: e.target.value }))} className="input w-full">
                <option value="">— {t('select')} —</option>
                {warehouses.filter((w) => String(w.id) !== transferForm.from_warehouse_id).map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </div>
            <div className="col-span-2"><label className="label">{t('notes')}</label><input value={transferForm.notes} onChange={(e) => setTransferForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" /></div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">{t('transfer_items')}</label>
              <button type="button" onClick={addTransferItem} className="text-xs text-primary-600 hover:underline flex items-center gap-1"><Plus className="h-3 w-3" />{t('add_item')}</button>
            </div>

            {!transferForm.from_warehouse_id && (
              <p className="text-xs text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                <AlertTriangle className="h-3.5 w-3.5" />
                {t('select_source_warehouse_hint')}
              </p>
            )}

            <div className="space-y-2">
              {transferForm.items.map((item, i) => {
                const pid = parseInt(item.product_id)
                const available = pid && fromStockData ? (availableMap[pid] ?? 0) : null
                const qty = parseInt(item.quantity) || 0
                const overStock = available !== null && qty > available

                return (
                  <div key={i} className="flex gap-2 items-start">
                    <div className="flex-1">
                      <ProductSelect
                        value={item.product_id}
                        onChange={(id, name) => setTransferForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, product_id: id, product_name: name } : x) }))}
                        className="w-full"
                      />
                      {available !== null && (
                        <p className={clsx('text-xs mt-0.5', available === 0 ? 'text-red-500' : 'text-gray-400')}>
                          {t('available')}: <span className="font-medium">{available}</span>
                        </p>
                      )}
                    </div>
                    <div>
                      <input
                        value={item.quantity}
                        type="number"
                        min="1"
                        onChange={(e) => setTransferForm((p) => ({ ...p, items: p.items.map((x, idx) => idx === i ? { ...x, quantity: e.target.value } : x) }))}
                        className={clsx('input w-24', overStock && 'border-red-400 focus:ring-red-400')}
                        placeholder={t('qty')}
                      />
                      {overStock && (
                        <p className="text-xs text-red-500 mt-0.5">{t('exceeds_available', { n: available })}</p>
                      )}
                    </div>
                    {transferForm.items.length > 1 && (
                      <button type="button" onClick={() => removeTransferItem(i)} className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded mt-0.5">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                )
              })}
            </div>
          </div>
        </form>
      </Modal>
    </div>
  )
}
