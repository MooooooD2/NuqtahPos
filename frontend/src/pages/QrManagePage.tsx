import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { QrCode, Plus, Copy, Download, ToggleLeft, ToggleRight, Users, ShoppingBag } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface QrTable {
  id: number
  table_name: string
  token: string
  capacity: number
  is_active: boolean
  today_orders?: number
  order_url?: string
}

const emptyForm = { table_name: '', capacity: '' }

export default function QrManagePage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [addModal, setAddModal] = useState(false)
  const [form, setForm] = useState({ ...emptyForm })

  const { data, isLoading } = useQuery({
    queryKey: ['qr-tables'],
    queryFn: () => apiGet<{ success: boolean; data: QrTable[] }>('/qr-tables'),
    staleTime: 60_000,
  })

  const tables = data?.data ?? []
  const canManage = hasPermission('manage_qr_orders')

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
      setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const createMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/qr-tables', payload),
    onSuccess: () => {
      toast.success('QR table created')
      qc.invalidateQueries({ queryKey: ['qr-tables'] })
      setAddModal(false)
      setForm({ ...emptyForm })
    },
    onError: () => toast.error('Failed to create table'),
  })

  const toggleMutation = useMutation({
    mutationFn: (id: number) => apiPost(`/qr-tables/${id}/toggle`, {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['qr-tables'] })
    },
    onError: () => toast.error('Failed to toggle table status'),
  })

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.table_name.trim()) return toast.error('Table name is required')
    const cap = parseInt(form.capacity)
    if (!cap || cap < 1) return toast.error('Capacity must be at least 1')
    createMutation.mutate({ table_name: form.table_name, capacity: cap })
  }

  const getUrl = (table: QrTable) =>
    table.order_url ?? `${window.location.origin}/qr/${table.token}/products`

  const copyLink = (table: QrTable) => {
    navigator.clipboard.writeText(getUrl(table)).then(() =>
      toast.success('Link copied')
    ).catch(() => toast.error('Failed to copy link'))
  }

  if (!canManage) {
    return (
      <div className="card p-8 text-center text-gray-400 space-y-3">
        <QrCode className="h-10 w-10 mx-auto opacity-40" />
        <p className="font-medium">QR Management not accessible</p>
        <p className="text-sm">Requires manage_qr_orders permission</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <QrCode className="h-6 w-6 text-primary-500" /> QR Table Management
          {tables.length > 0 && (
            <span className="text-sm font-normal text-gray-400">({tables.length})</span>
          )}
        </h1>
        <button onClick={() => setAddModal(true)} className="btn btn-primary flex items-center gap-2">
          <Plus className="h-4 w-4" /> Add Table
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">Total Tables</p>
              <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{tables.length}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-blue-100 dark:bg-blue-900/30">
              <QrCode className="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">Active Tables</p>
              <p className="mt-1 text-2xl font-bold text-green-600">{tables.filter((t) => t.is_active).length}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-green-100 dark:bg-green-900/30">
              <ToggleRight className="h-6 w-6 text-green-600" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">Today Orders</p>
              <p className="mt-1 text-2xl font-bold text-primary-600">
                {tables.reduce((s, t) => s + (t.today_orders ?? 0), 0)}
              </p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-purple-100 dark:bg-purple-900/30">
              <ShoppingBag className="h-6 w-6 text-purple-600 dark:text-purple-400" />
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : tables.length === 0 ? (
        <div className="card p-12 text-center text-gray-400 space-y-2">
          <QrCode className="h-10 w-10 mx-auto opacity-40" />
          <p>No QR tables yet. Click "Add Table" to get started.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {tables.map((table) => (
            <div key={table.id} className="card p-5 space-y-4">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-bold text-gray-900 dark:text-white text-lg">{table.table_name}</p>
                  <div className="flex items-center gap-1 text-sm text-gray-500 mt-0.5">
                    <Users className="h-3.5 w-3.5" />
                    <span>{table.capacity} seats</span>
                  </div>
                </div>
                <span className={clsx('badge', table.is_active ? 'badge-success' : 'badge-danger')}>
                  {table.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>

              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Today orders</span>
                <span className="font-semibold text-primary-600">{table.today_orders ?? 0}</span>
              </div>

              <div className="flex flex-col items-center justify-center py-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg gap-2">
                <div className="p-4 bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600">
                  <QrCode className="h-20 w-20 text-gray-700 dark:text-gray-300" />
                </div>
                <p className="text-xs text-gray-400 font-mono text-center break-all px-2 max-w-full">
                  {table.token}
                </p>
              </div>

              <div className="text-xs text-gray-400 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2 break-all font-mono">
                {getUrl(table)}
              </div>

              <div className="flex gap-2">
                <button
                  onClick={() => copyLink(table)}
                  className="btn btn-secondary flex-1 flex items-center justify-center gap-1.5 text-sm py-1.5"
                >
                  <Copy className="h-3.5 w-3.5" /> Copy Link
                </button>
                <button
                  disabled
                  title="Download requires a QR library"
                  className="btn btn-secondary flex items-center justify-center gap-1.5 text-sm py-1.5 px-3 opacity-40 cursor-not-allowed"
                >
                  <Download className="h-3.5 w-3.5" />
                </button>
                <button
                  onClick={() => toggleMutation.mutate(table.id)}
                  disabled={toggleMutation.isPending}
                  className={clsx(
                    'btn flex items-center justify-center gap-1.5 text-sm py-1.5 px-3',
                    table.is_active
                      ? 'bg-red-50 dark:bg-red-900/20 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/40'
                      : 'bg-green-50 dark:bg-green-900/20 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/40',
                  )}
                >
                  {table.is_active ? <ToggleLeft className="h-4 w-4" /> : <ToggleRight className="h-4 w-4" />}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Modal
        open={addModal}
        onClose={() => setAddModal(false)}
        title="Add QR Table"
        size="sm"
        footer={
          <>
            <button onClick={() => setAddModal(false)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleCreate} disabled={createMutation.isPending} className="btn btn-primary">
              {createMutation.isPending ? 'Creating…' : 'Create'}
            </button>
          </>
        }
      >
        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="label">Table Name *</label>
            <input {...f('table_name')} className="input w-full" placeholder="e.g. Table 1" required />
          </div>
          <div>
            <label className="label">Capacity *</label>
            <input
              {...f('capacity')}
              type="number"
              min="1"
              className="input w-full"
              placeholder="Number of seats"
              required
            />
          </div>
        </form>
      </Modal>
    </div>
  )
}
