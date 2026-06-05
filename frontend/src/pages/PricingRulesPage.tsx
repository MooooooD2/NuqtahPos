import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api, apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Percent, Plus, Pencil, Trash2, ToggleLeft, ToggleRight, AlertCircle } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Rule {
  id: number
  name: string
  description?: string
  priority: number
  rule_type: string
  discount_type: string
  discount_value: string
  time_start?: string
  time_end?: string
  days_of_week?: number[]
  valid_from?: string
  valid_until?: string
  min_quantity?: number
  is_active: boolean
  active_now?: boolean
}

const emptyForm = {
  name: '',
  description: '',
  priority: '50',
  rule_type: 'happy_hour',
  discount_type: 'percentage',
  discount_value: '',
  start_time: '',
  end_time: '',
  days_of_week: [] as number[],
  valid_from: '',
  valid_until: '',
  min_quantity: '',
  is_active: true,
}

const typeIcon: Record<string, string> = {
  happy_hour: '⏰',
  bulk_discount: '📦',
  day_of_week: '📅',
  category: '🏷',
  flat_price: '💲',
}

const typeBadge: Record<string, string> = {
  happy_hour: 'badge-info',
  bulk_discount: 'badge-warning',
  day_of_week: 'badge-info',
  category: 'badge-gray',
  flat_price: 'badge-success',
}

const DAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function formatDiscount(rule: Rule) {
  if (rule.discount_type === 'percentage') return `${rule.discount_value}% off`
  if (rule.discount_type === 'fixed_amount') return `$${rule.discount_value} off`
  return `Set to $${rule.discount_value}`
}

export default function PricingRulesPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const canManage = hasPermission('view_reports')

  const { data, isLoading } = useQuery({
    queryKey: ['pricing-rules'],
    queryFn: () => apiGet<{ success: boolean; data: Rule[] }>('/pricing-rules/'),
    staleTime: 30_000,
  })

  const rules = data?.data ?? []
  const hasActiveHappyHour = rules.some((r) => r.rule_type === 'happy_hour' && r.active_now)

  const set = (field: keyof typeof emptyForm) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const val = e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value
    setForm((p) => ({ ...p, [field]: val }))
  }

  const toggleDay = (day: number) => {
    setForm((p) => ({
      ...p,
      days_of_week: p.days_of_week.includes(day) ? p.days_of_week.filter((d) => d !== day) : [...p.days_of_week, day],
    }))
  }

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (r: Rule) => {
    setForm({
      name: r.name,
      description: r.description ?? '',
      priority: String(r.priority),
      rule_type: r.rule_type,
      discount_type: r.discount_type,
      discount_value: r.discount_value,
      start_time: r.time_start ?? '',
      end_time: r.time_end ?? '',
      days_of_week: r.days_of_week ?? [],
      valid_from: r.valid_from?.slice(0, 10) ?? '',
      valid_until: r.valid_until?.slice(0, 10) ?? '',
      min_quantity: String(r.min_quantity ?? ''),
      is_active: r.is_active,
    })
    setEditId(r.id)
    setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/pricing-rules/${editId}`, payload) : apiPost('/pricing-rules/', payload),
    onSuccess: () => { toast.success(editId ? 'Rule updated' : 'Rule created'); qc.invalidateQueries({ queryKey: ['pricing-rules'] }); setModal(null) },
    onError: () => toast.error('Failed to save'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/pricing-rules/${id}`),
    onSuccess: () => { toast.success('Deleted'); qc.invalidateQueries({ queryKey: ['pricing-rules'] }); setDeleteId(null) },
    onError: () => toast.error('Failed to delete'),
  })

  const toggleMutation = useMutation({
    mutationFn: (id: number) => api.patch(`/pricing-rules/${id}/toggle`).then((r) => r.data),
    onSuccess: () => { toast.success('Toggled'); qc.invalidateQueries({ queryKey: ['pricing-rules'] }) },
    onError: () => toast.error('Failed to toggle'),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error('Name required')
    if (!form.discount_value) return toast.error('Discount value required')
    const payload: Record<string, unknown> = {
      name: form.name,
      description: form.description || undefined,
      priority: parseInt(form.priority) || 50,
      rule_type: form.rule_type,
      discount_type: form.discount_type,
      discount_value: parseFloat(form.discount_value),
      valid_from: form.valid_from || undefined,
      valid_until: form.valid_until || undefined,
      is_active: form.is_active,
    }
    if (form.rule_type === 'happy_hour' || form.rule_type === 'day_of_week') {
      payload.time_start = form.start_time || undefined
      payload.time_end = form.end_time || undefined
    }
    if (form.rule_type === 'day_of_week') {
      payload.days_of_week = form.days_of_week
    }
    if (form.rule_type === 'bulk_discount') {
      payload.min_quantity = parseInt(form.min_quantity) || undefined
    }
    saveMutation.mutate(payload)
  }

  const showTimePicker = form.rule_type === 'happy_hour' || form.rule_type === 'day_of_week'
  const showDays = form.rule_type === 'day_of_week'
  const showMinQty = form.rule_type === 'bulk_discount'

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Percent className="h-6 w-6 text-primary-500" /> Pricing Rules
        </h1>
        {canManage && (
          <button onClick={openAdd} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> New Rule
          </button>
        )}
      </div>

      {hasActiveHappyHour && (
        <div className="flex items-center gap-2 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-amber-700 dark:text-amber-300 text-sm font-medium">
          <AlertCircle className="h-4 w-4 shrink-0" />
          Happy Hour is currently active — discounted pricing is in effect.
        </div>
      )}

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  {['Name', 'Type', 'Discount', 'Priority', 'Status', 'Active Now', ''].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {rules.length === 0 ? (
                  <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">No pricing rules found</td></tr>
                ) : rules.map((r) => (
                  <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3">
                      <div className="font-medium text-gray-900 dark:text-white">{r.name}</div>
                      {r.description && <div className="text-xs text-gray-400 mt-0.5">{r.description}</div>}
                    </td>
                    <td className="px-4 py-3">
                      <span className={clsx('badge capitalize', typeBadge[r.rule_type] ?? 'badge-gray')}>
                        {typeIcon[r.rule_type] ?? ''} {r.rule_type.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-600 dark:text-gray-300 font-medium">{formatDiscount(r)}</td>
                    <td className="px-4 py-3">
                      <span className="badge badge-gray font-mono">{r.priority}</span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={clsx('badge', r.is_active ? 'badge-success' : 'badge-gray')}>
                        {r.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      {r.active_now ? (
                        <span className="badge badge-success">Yes</span>
                      ) : (
                        <span className="text-gray-400 text-xs">No</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-1 justify-end">
                        {canManage && (
                          <>
                            <button
                              onClick={() => toggleMutation.mutate(r.id)}
                              title={r.is_active ? 'Deactivate' : 'Activate'}
                              className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded"
                            >
                              {r.is_active ? <ToggleRight className="h-4 w-4" /> : <ToggleLeft className="h-4 w-4" />}
                            </button>
                            <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded">
                              <Pencil className="h-4 w-4" />
                            </button>
                            <button onClick={() => setDeleteId(r.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <Modal
        open={modal === 'add' || modal === 'edit'}
        onClose={() => setModal(null)}
        title={editId ? 'Edit Pricing Rule' : 'New Pricing Rule'}
        size="lg"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">
              {saveMutation.isPending ? 'Saving…' : editId ? 'Update' : 'Create'}
            </button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="label">Name *</label>
              <input value={form.name} onChange={set('name')} className="input w-full" required />
            </div>
            <div>
              <label className="label">Rule Type *</label>
              <select value={form.rule_type} onChange={set('rule_type')} className="input w-full">
                <option value="happy_hour">Happy Hour</option>
                <option value="bulk_discount">Bulk Discount</option>
                <option value="day_of_week">Day of Week</option>
                <option value="category">Category</option>
                <option value="flat_price">Flat Price</option>
              </select>
            </div>
            <div>
              <label className="label">Priority (1–100)</label>
              <input value={form.priority} onChange={set('priority')} type="number" min="1" max="100" className="input w-full" />
            </div>
            <div>
              <label className="label">Discount Type *</label>
              <select value={form.discount_type} onChange={set('discount_type')} className="input w-full">
                <option value="percentage">Percentage</option>
                <option value="fixed_amount">Fixed Amount</option>
                <option value="new_price">New Price</option>
              </select>
            </div>
            <div>
              <label className="label">Discount Value *</label>
              <input value={form.discount_value} onChange={set('discount_value')} type="number" min="0" step="0.01" className="input w-full" />
            </div>

            {showTimePicker && (
              <>
                <div>
                  <label className="label">Start Time</label>
                  <input value={form.start_time} onChange={set('start_time')} type="time" className="input w-full" />
                </div>
                <div>
                  <label className="label">End Time</label>
                  <input value={form.end_time} onChange={set('end_time')} type="time" className="input w-full" />
                </div>
              </>
            )}

            {showDays && (
              <div className="col-span-2">
                <label className="label">Days of Week</label>
                <div className="flex flex-wrap gap-2 mt-1">
                  {DAY_LABELS.map((label, i) => (
                    <button
                      key={i}
                      type="button"
                      onClick={() => toggleDay(i + 1)}
                      className={clsx(
                        'px-3 py-1 rounded-full text-sm font-medium border transition-colors',
                        form.days_of_week.includes(i + 1)
                          ? 'bg-primary-600 text-white border-primary-600'
                          : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'
                      )}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {showMinQty && (
              <div>
                <label className="label">Min Quantity</label>
                <input value={form.min_quantity} onChange={set('min_quantity')} type="number" min="1" className="input w-full" />
              </div>
            )}

            <div>
              <label className="label">Valid From (optional)</label>
              <input value={form.valid_from} onChange={set('valid_from')} type="date" className="input w-full" />
            </div>
            <div>
              <label className="label">Valid Until (optional)</label>
              <input value={form.valid_until} onChange={set('valid_until')} type="date" className="input w-full" />
            </div>
            <div className="col-span-2">
              <label className="label">Description (optional)</label>
              <input value={form.description} onChange={set('description')} className="input w-full" />
            </div>
            <div className="col-span-2 flex items-center gap-2">
              <input id="rule-active" type="checkbox" checked={form.is_active} onChange={set('is_active')} className="h-4 w-4 rounded border-gray-300 text-primary-600" />
              <label htmlFor="rule-active" className="label mb-0">Active</label>
            </div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title="Delete Pricing Rule"
        message="Delete this pricing rule? This cannot be undone."
        loading={deleteMutation.isPending}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        onCancel={() => setDeleteId(null)}
      />
    </div>
  )
}
