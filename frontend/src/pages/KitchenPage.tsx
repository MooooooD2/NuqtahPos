import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { ChefHat, Plus, Clock, Maximize2, Minimize2, Trash2 } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface KitchenItem {
  id: number
  product_name: string
  quantity: number
  notes?: string
  status?: string
}
interface Order {
  id: number
  order_number: string
  table_number?: string
  order_type: string
  status: string
  notes?: string
  created_at: string
  items: KitchenItem[]
}
interface KitchenStats {
  pending: number
  preparing: number
  ready: number
  served_today: number
  avg_prep_min: number
}
interface KitchenResponse {
  orders: Order[]
  stats: KitchenStats
}

interface NewItemRow { name: string; quantity: number; notes: string }
const emptyItem = (): NewItemRow => ({ name: '', quantity: 1, notes: '' })
const emptyForm = { table_no: '', order_type: 'dine_in', notes: '' }

const statusTabs = ['all', 'pending', 'preparing', 'ready', 'served'] as const
type StatusTab = typeof statusTabs[number]

const typeBadge: Record<string, string> = {
  dine_in: 'badge-info',
  takeaway: 'badge-warning',
  delivery: 'badge badge-gray',
}
const typeLabel: Record<string, string> = {
  dine_in: 'dine_in',
  takeaway: 'takeaway',
  delivery: 'delivery',
}

function elapsed(created_at: string): string {
  const diff = Math.floor((Date.now() - new Date(created_at).getTime()) / 1000)
  const m = Math.floor(diff / 60)
  const s = diff % 60
  return m > 0 ? `${m}m ${s}s` : `${s}s`
}

export default function KitchenPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [displayMode, setDisplayMode] = useState(false)
  const [filterTab, setFilterTab] = useState<StatusTab>('all')
  const [addModal, setAddModal] = useState(false)
  const [form, setForm] = useState({ ...emptyForm })
  const [items, setItems] = useState<NewItemRow[]>([emptyItem()])
  const [cancelId, setCancelId] = useState<number | null>(null)
  const [now, setNow] = useState(new Date())

  useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 1000)
    return () => clearInterval(t)
  }, [])

  const refetchInterval = displayMode ? 8_000 : 30_000

  const { data, isLoading } = useQuery({
    queryKey: ['kitchen', filterTab],
    queryFn: () =>
      apiGet<KitchenResponse>('/kitchen', {
        status: filterTab === 'all' ? undefined : filterTab,
      }),
    refetchInterval,
    staleTime: 5_000,
  })

  const orders = data?.orders ?? []
  const stats = data?.stats ?? { pending: 0, preparing: 0, ready: 0, served_today: 0, avg_prep_min: 0 }

  const canView = hasPermission('view_kitchen')

  const actionMutation = useMutation({
    mutationFn: ({ id, action }: { id: number; action: string }) =>
      apiPost(`/kitchen/${id}/${action}`, {}),
    onSuccess: (_d, { action }) => {
      toast.success(t('updated_success'))
      qc.invalidateQueries({ queryKey: ['kitchen'] })
      if (action === 'cancel') setCancelId(null)
    },
    onError: () => toast.error(t('save_failed')),
  })

  const createMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/kitchen', payload),
    onSuccess: () => {
      toast.success(t('created_success'))
      qc.invalidateQueries({ queryKey: ['kitchen'] })
      setAddModal(false)
      setForm({ ...emptyForm })
      setItems([emptyItem()])
    },
    onError: () => toast.error(t('save_failed')),
  })

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault()
    const validItems = items.filter((i) => i.name.trim())
    if (validItems.length === 0) return toast.error(t('error'))
    createMutation.mutate({
      table_number: form.table_no || undefined,
      order_type: form.order_type,
      notes: form.notes || undefined,
      items: validItems.map((i) => ({ product_name: i.name, quantity: i.quantity, notes: i.notes || undefined })),
    })
  }

  const updateItem = (idx: number, field: keyof NewItemRow, value: string | number) =>
    setItems((prev) => prev.map((it, i) => (i === idx ? { ...it, [field]: value } : it)))

  const displayTabs = ['all', 'pending', 'preparing', 'ready'] as const

  if (!canView && !displayMode) {
    return (
      <div className="card p-8 text-center text-gray-400 space-y-3">
        <ChefHat className="h-10 w-10 mx-auto opacity-40" />
        <p className="font-medium">{t('kitchen_display')}</p>
        <p className="text-sm">{t('no_permission')}</p>
      </div>
    )
  }

  if (displayMode) {
    return (
      <div className="fixed inset-0 z-50 bg-gray-900 flex flex-col overflow-hidden">
        <div className="flex items-center justify-between px-6 py-3 bg-gray-800 border-b border-gray-700 flex-shrink-0">
          <div className="flex items-center gap-3">
            <ChefHat className="h-6 w-6 text-orange-400" />
            <span className="text-white font-bold text-lg">{t('kitchen_display')}</span>
          </div>
          <div className="flex items-center gap-4">
            <span className="text-gray-300 text-sm font-mono">
              {now.toLocaleTimeString()}
            </span>
            <div className="flex gap-2">
              <span className="badge badge-warning">{stats.pending} {t('kitchen_pending')}</span>
              <span className="badge badge-info">{stats.preparing} {t('kitchen_preparing')}</span>
              <span className="badge badge-success">{stats.ready} {t('kitchen_ready')}</span>
            </div>
            <button onClick={() => setDisplayMode(false)} className="btn btn-secondary flex items-center gap-1 text-sm">
              <Minimize2 className="h-4 w-4" /> {t('exit')}
            </button>
          </div>
        </div>

        <div className="flex gap-1 px-4 py-2 bg-gray-800 border-b border-gray-700 flex-shrink-0">
          {displayTabs.map((tab) => (
            <button
              key={tab}
              onClick={() => setFilterTab(tab as StatusTab)}
              className={clsx(
                'px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors',
                filterTab === tab ? 'bg-orange-500 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700',
              )}
            >
              {tab === 'all' ? t('kitchen_all') : tab === 'pending' ? t('kitchen_pending') : tab === 'preparing' ? t('kitchen_preparing') : t('kitchen_ready')}
            </button>
          ))}
        </div>

        <div className="flex-1 overflow-y-auto p-4">
          {isLoading ? (
            <div className="flex h-full items-center justify-center"><LoadingSpinner size="lg" /></div>
          ) : orders.length === 0 ? (
            <div className="flex h-full items-center justify-center text-gray-500">
              <div className="text-center space-y-2">
                <ChefHat className="h-16 w-16 mx-auto opacity-20" />
                <p className="text-xl">{t('no_orders')}</p>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {orders.map((order: Order) => (
                <OrderCard
                  key={order.id}
                  order={order}
                  dark
                  onAction={(action) => {
                    if (action === 'cancel') { setCancelId(order.id); return }
                    actionMutation.mutate({ id: order.id, action })
                  }}
                  loading={actionMutation.isPending}
                />
              ))}
            </div>
          )}
        </div>

        <ConfirmDialog
          open={cancelId !== null}
          title={t('kitchen_cancel')}
          message={t('confirm_cancel_kitchen_order')}
          confirmLabel={t('kitchen_cancel')}
          loading={actionMutation.isPending}
          onConfirm={() => cancelId && actionMutation.mutate({ id: cancelId, action: 'cancel' })}
          onCancel={() => setCancelId(null)}
        />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <ChefHat className="h-6 w-6 text-primary-500" /> {t('kitchen')}
        </h1>
        <div className="flex gap-2">
          <button onClick={() => setDisplayMode(true)} className="btn btn-secondary flex items-center gap-2">
            <Maximize2 className="h-4 w-4" /> {t('display_mode')}
          </button>
          <button onClick={() => setAddModal(true)} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('new_order')}
          </button>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('kitchen_pending')}</p>
              <p className="mt-1 text-2xl font-bold text-yellow-600">{stats.pending}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-yellow-100 dark:bg-yellow-900/30">
              <Clock className="h-6 w-6 text-yellow-600" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('kitchen_preparing')}</p>
              <p className="mt-1 text-2xl font-bold text-orange-600">{stats.preparing}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-orange-100 dark:bg-orange-900/30">
              <ChefHat className="h-6 w-6 text-orange-600" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('kitchen_ready')}</p>
              <p className="mt-1 text-2xl font-bold text-green-600">{stats.ready}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-green-100 dark:bg-green-900/30">
              <ChefHat className="h-6 w-6 text-green-600" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('avg_prep_time')}</p>
              <p className="mt-1 text-2xl font-bold text-blue-600">{stats.avg_prep_min ?? 0}<span className="text-sm font-normal ml-1">{t('min')}</span></p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-blue-100 dark:bg-blue-900/30">
              <Clock className="h-6 w-6 text-blue-600" />
            </div>
          </div>
        </div>
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit flex-wrap">
        {statusTabs.map((tabKey) => (
          <button
            key={tabKey}
            onClick={() => setFilterTab(tabKey)}
            className={clsx(
              'px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors',
              filterTab === tabKey ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700',
            )}
          >
            {tabKey === 'all' ? t('kitchen_all') : tabKey === 'pending' ? t('kitchen_pending') : tabKey === 'preparing' ? t('kitchen_preparing') : tabKey === 'ready' ? t('kitchen_ready') : t('kitchen_served')}
          </button>
        ))}
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : orders.length === 0 ? (
        <div className="card p-12 text-center text-gray-400 space-y-2">
          <ChefHat className="h-10 w-10 mx-auto opacity-40" />
          <p>{t('no_orders')}</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {orders.map((order) => (
            <OrderCard
              key={order.id}
              order={order}
              dark={false}
              onAction={(action) => {
                if (action === 'cancel') { setCancelId(order.id); return }
                actionMutation.mutate({ id: order.id, action })
              }}
              loading={actionMutation.isPending}
            />
          ))}
        </div>
      )}

      <Modal
        open={addModal}
        onClose={() => setAddModal(false)}
        title={t('kitchen_new_order')}
        size="xl"
        footer={
          <>
            <button type="button" onClick={() => setAddModal(false)} className="btn btn-secondary">{t('cancel')}</button>
            <button type="button" onClick={handleCreate} disabled={createMutation.isPending} className="btn btn-primary">
              {createMutation.isPending ? t('creating') : t('create_order')}
            </button>
          </>
        }
      >
        <form onSubmit={handleCreate} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('table_no')}</label>
              <input
                value={form.table_no}
                onChange={(e) => setForm((p) => ({ ...p, table_no: e.target.value }))}
                className="input w-full"
              />
            </div>
            <div>
              <label className="label">{t('order_type')}</label>
              <select
                value={form.order_type}
                onChange={(e) => setForm((p) => ({ ...p, order_type: e.target.value }))}
                className="input w-full"
              >
                <option value="dine_in">{t('dine_in')}</option>
                <option value="takeaway">{t('takeaway')}</option>
                <option value="delivery">{t('delivery')}</option>
              </select>
            </div>
            <div className="col-span-2">
              <label className="label">{t('notes')}</label>
              <input
                value={form.notes}
                onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))}
                className="input w-full"
              />
            </div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">{t('items')}</label>
              <button type="button" onClick={() => setItems((p) => [...p, emptyItem()])} className="btn btn-secondary text-xs py-1 flex items-center gap-1">
                <Plus className="h-3 w-3" /> {t('add_item')}
              </button>
            </div>
            <div className="space-y-2">
              {items.map((it, idx) => (
                <div key={idx} className="grid grid-cols-12 gap-2 items-center">
                  <input
                    value={it.name}
                    onChange={(e) => updateItem(idx, 'name', e.target.value)}
                    className="input col-span-5"
                    placeholder={t('item_name')}
                  />
                  <input
                    type="number"
                    min={1}
                    value={it.quantity}
                    onChange={(e) => updateItem(idx, 'quantity', parseInt(e.target.value) || 1)}
                    className="input col-span-2"
                    placeholder={t('qty')}
                  />
                  <input
                    value={it.notes}
                    onChange={(e) => updateItem(idx, 'notes', e.target.value)}
                    className="input col-span-4"
                    placeholder={t('notes')}
                  />
                  <button
                    type="button"
                    onClick={() => setItems((p) => p.filter((_, i) => i !== idx))}
                    disabled={items.length === 1}
                    className="col-span-1 p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded disabled:opacity-30"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={cancelId !== null}
        title={t('kitchen_cancel')}
        message={t('confirm_cancel_kitchen_order')}
        confirmLabel={t('kitchen_cancel')}
        loading={actionMutation.isPending}
        onConfirm={() => cancelId && actionMutation.mutate({ id: cancelId, action: 'cancel' })}
        onCancel={() => setCancelId(null)}
      />
    </div>
  )
}

interface OrderCardProps {
  order: Order
  dark: boolean
  onAction: (action: string) => void
  loading: boolean
}

function OrderCard({ order, dark, onAction, loading }: OrderCardProps) {
  const { t } = useTranslation('pos')
  const statusColor: Record<string, string> = {
    pending: dark ? 'border-yellow-500' : 'border-yellow-400',
    preparing: dark ? 'border-orange-500' : 'border-orange-400',
    ready: dark ? 'border-green-500' : 'border-green-400',
    served: dark ? 'border-gray-600' : 'border-gray-300',
  }

  return (
    <div
      className={clsx(
        'rounded-xl border-l-4 p-4 space-y-3',
        dark ? 'bg-gray-800 text-white' : 'card',
        statusColor[order.status] ?? (dark ? 'border-gray-600' : 'border-gray-300'),
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className={clsx('font-bold text-lg', dark ? 'text-white' : 'text-gray-900 dark:text-white')}>
            #{order.order_number}
          </p>
          {order.table_number && (
            <p className={clsx('text-xs', dark ? 'text-gray-400' : 'text-gray-500')}>{t('table')} {order.table_number}</p>
          )}
        </div>
        <div className="flex flex-col items-end gap-1">
          <span className={clsx('badge text-xs', typeBadge[order.order_type] ?? 'badge-gray')}>
            {typeLabel[order.order_type] ?? order.order_type}
          </span>
          <span className={clsx('text-xs flex items-center gap-1', dark ? 'text-gray-400' : 'text-gray-400')}>
            <Clock className="h-3 w-3" /> {elapsed(order.created_at)}
          </span>
        </div>
      </div>

      <ul className="space-y-1">
        {order.items.map((item) => (
          <li key={item.id} className={clsx('flex items-start gap-2 text-sm', dark ? 'text-gray-300' : 'text-gray-700 dark:text-gray-300')}>
            <span className="font-semibold text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded px-1.5 py-0.5 mt-0.5">
              x{item.quantity}
            </span>
            <div>
              <span>{item.product_name}</span>
              {item.notes && (
                <p className={clsx('text-xs italic', dark ? 'text-gray-500' : 'text-gray-400')}>{item.notes}</p>
              )}
            </div>
          </li>
        ))}
      </ul>

      {order.notes && (
        <p className={clsx('text-xs italic border-t pt-2', dark ? 'text-gray-400 border-gray-700' : 'text-gray-400 border-gray-100 dark:border-gray-700')}>
          {order.notes}
        </p>
      )}

      <div className="flex gap-2 pt-1">
        {order.status === 'pending' && (
          <button
            onClick={() => onAction('accept')}
            disabled={loading}
            className="btn btn-primary text-xs py-1 flex-1"
          >
            {t('kitchen_accept')}
          </button>
        )}
        {order.status === 'preparing' && (
          <button
            onClick={() => onAction('ready')}
            disabled={loading}
            className="btn btn-primary text-xs py-1 flex-1 bg-green-600 hover:bg-green-700"
          >
            {t('kitchen_mark_ready')}
          </button>
        )}
        {order.status === 'ready' && (
          <button
            onClick={() => onAction('served')}
            disabled={loading}
            className="btn btn-primary text-xs py-1 flex-1"
          >
            {t('kitchen_mark_served')}
          </button>
        )}
        {order.status !== 'served' && order.status !== 'cancelled' && (
          <button
            onClick={() => onAction('cancel')}
            disabled={loading}
            className="btn text-xs py-1 bg-red-600 hover:bg-red-700 text-white disabled:opacity-50"
          >
            {t('kitchen_cancel')}
          </button>
        )}
      </div>
    </div>
  )
}
