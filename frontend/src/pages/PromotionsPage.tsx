import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Tag, Plus, Pencil, Trash2, Search } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

interface Promotion {
  id: number
  name: string
  type: 'percentage' | 'fixed' | 'buy_x_get_y'
  value?: string
  buy_quantity?: number
  get_quantity?: number
  product_id?: number
  category?: string
  min_order_amount?: string
  starts_at?: string
  ends_at?: string
  description?: string
  is_active: boolean
}

const emptyForm = {
  name: '',
  type: 'percentage' as Promotion['type'],
  value: '',
  buy_quantity: '',
  get_quantity: '',
  product_id: '',
  category: '',
  min_order_amount: '',
  starts_at: '',
  ends_at: '',
  description: '',
  is_active: true,
}

const typeBadge: Record<string, string> = {
  percentage: 'badge-info',
  fixed: 'badge-success',
  buy_x_get_y: 'badge badge-warning',
}


export default function PromotionsPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['promotions', page, search],
    queryFn: () => apiGet<{ success: boolean; data: Promotion[]; total?: number }>('/promotions', { page, per_page: 20, search: search || undefined }),
    staleTime: 30_000,
  })

  const promotions = data?.data ?? []
  const canManage = hasPermission('view_reports')

  const getTypeLabel = (type: string) => ({ percentage: t('promo_type_percentage'), fixed: t('promo_type_fixed'), buy_x_get_y: t('promo_type_bxgy') }[type] ?? type)
  const formatValue = (p: Promotion) => {
    if (p.type === 'percentage') return `${p.value ?? 0}%`
    if (p.type === 'fixed') return `$${p.value ?? 0}`
    return `${t('buy_qty')} ${p.buy_quantity ?? 0} ${t('get_qty')} ${p.get_quantity ?? 0}`
  }

  const set = (field: keyof typeof emptyForm) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const val = e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value
    setForm((p) => ({ ...p, [field]: val }))
  }

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (p: Promotion) => {
    setForm({
      name: p.name,
      type: p.type,
      value: p.value ?? '',
      buy_quantity: String(p.buy_quantity ?? ''),
      get_quantity: String(p.get_quantity ?? ''),
      product_id: String(p.product_id ?? ''),
      category: p.category ?? '',
      min_order_amount: p.min_order_amount ?? '',
      starts_at: p.starts_at?.slice(0, 10) ?? '',
      ends_at: p.ends_at?.slice(0, 10) ?? '',
      description: p.description ?? '',
      is_active: p.is_active,
    })
    setEditId(p.id)
    setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/promotions/${editId}`, payload) : apiPost('/promotions', payload),
    onSuccess: () => { toast.success(editId ? t('updated_success') : t('created_success')); qc.invalidateQueries({ queryKey: ['promotions'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/promotions/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['promotions'] }); setDeleteId(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error(t('error'))
    const payload: Record<string, unknown> = {
      name: form.name,
      type: form.type,
      product_id: form.product_id ? parseInt(form.product_id) : undefined,
      category: form.category || undefined,
      min_order_amount: form.min_order_amount ? parseFloat(form.min_order_amount) : undefined,
      starts_at: form.starts_at || undefined,
      ends_at: form.ends_at || undefined,
      description: form.description || undefined,
      is_active: form.is_active,
    }
    if (form.type === 'buy_x_get_y') {
      payload.buy_qty = parseInt(form.buy_quantity) || 1
      payload.get_qty = parseInt(form.get_quantity) || 1
    } else {
      payload.value = parseFloat(form.value) || 0
    }
    saveMutation.mutate(payload)
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Tag className="h-6 w-6 text-primary-500" /> {t('promotions')}
        </h1>
        {canManage && (
          <button onClick={openAdd} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('new_promotion')}
          </button>
        )}
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder={t('search_placeholder')} className="input pl-9 w-full" />
      </div>

      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : (
          <>
            {/* Desktop table */}
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[650px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[t('name'), t('type'), t('price'), t('scope'), t('validity'), t('status'), ''].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {promotions.length === 0 ? (
                    <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : promotions.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                      <td className="px-4 py-3">
                        <span className={clsx('badge capitalize', typeBadge[p.type] ?? 'badge-gray')}>{getTypeLabel(p.type)}</span>
                      </td>
                      <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{formatValue(p)}</td>
                      <td className="px-4 py-3 text-gray-500">
                        {p.product_id ? t('product_scope', { n: p.product_id }) : p.category ? p.category : t('all')}
                      </td>
                      <td className="px-4 py-3 text-gray-400 text-xs">
                        {p.starts_at || p.ends_at
                          ? `${p.starts_at?.slice(0, 10) ?? '∞'} – ${p.ends_at?.slice(0, 10) ?? '∞'}`
                          : t('always_label')}
                      </td>
                      <td className="px-4 py-3">
                        <span className={clsx('badge', p.is_active ? 'badge-success' : 'badge-gray')}>
                          {p.is_active ? t('active_status') : t('inactive_status')}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 justify-end">
                          {canManage && (
                            <>
                              <button onClick={() => openEdit(p)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded">
                                <Pencil className="h-4 w-4" />
                              </button>
                              <button onClick={() => setDeleteId(p.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
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
            {/* Mobile cards */}
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {promotions.length === 0 ? (
                <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
              ) : promotions.map((p) => (
                <div key={p.id} className="p-4 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <span className="font-semibold text-gray-900 dark:text-white">{p.name}</span>
                    <span className={clsx('badge capitalize shrink-0', typeBadge[p.type] ?? 'badge-gray')}>{getTypeLabel(p.type)}</span>
                  </div>
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-semibold text-primary-600 dark:text-primary-400">{formatValue(p)}</span>
                    <span className={clsx('badge', p.is_active ? 'badge-success' : 'badge-gray')}>
                      {p.is_active ? t('active_status') : t('inactive_status')}
                    </span>
                  </div>
                  <div className="text-xs text-gray-500 space-y-0.5">
                    <div>{t('scope')}: {p.product_id ? t('product_scope', { n: p.product_id }) : p.category ? p.category : t('all')}</div>
                    <div>{t('validity')}: {p.starts_at || p.ends_at ? `${p.starts_at?.slice(0, 10) ?? '∞'} – ${p.ends_at?.slice(0, 10) ?? '∞'}` : t('always_label')}</div>
                  </div>
                  {canManage && (
                    <div className="flex gap-2 pt-1">
                      <button onClick={() => openEdit(p)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400 hover:bg-primary-100 transition-colors font-medium">
                        <Pencil className="h-3.5 w-3.5" />{isAr ? 'تعديل' : 'Edit'}
                      </button>
                      <button onClick={() => setDeleteId(p.id)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition-colors font-medium">
                        <Trash2 className="h-3.5 w-3.5" />{isAr ? 'حذف' : 'Delete'}
                      </button>
                    </div>
                  )}
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
              <button onClick={() => setPage((p) => p + 1)} disabled={promotions.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      <Modal
        open={modal === 'add' || modal === 'edit'}
        onClose={() => setModal(null)}
        title={editId ? t('edit_promotion') : t('new_promotion')}
        size="lg"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button>
            <button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">
              {saveMutation.isPending ? t('loading') : editId ? t('update') : t('create')}
            </button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="label">{t('name')} *</label>
              <input value={form.name} onChange={set('name')} className="input w-full" required />
            </div>
            <div>
              <label className="label">{t('type')} *</label>
              <select value={form.type} onChange={set('type')} className="input w-full">
                <option value="percentage">{t('promo_type_percentage')}</option>
                <option value="fixed">{t('promo_type_fixed')}</option>
                <option value="buy_x_get_y">{t('promo_type_bxgy')}</option>
              </select>
            </div>
            {form.type !== 'buy_x_get_y' && (
              <div>
                <label className="label">{t('promo_value_label')} {form.type === 'percentage' ? '(%)' : '($)'} *</label>
                <input value={form.value} onChange={set('value')} type="number" min="0" step="0.01" className="input w-full" />
              </div>
            )}
            {form.type === 'buy_x_get_y' && (
              <>
                <div>
                  <label className="label">{t('buy_qty')} *</label>
                  <input value={form.buy_quantity} onChange={set('buy_quantity')} type="number" min="1" className="input w-full" />
                </div>
                <div>
                  <label className="label">{t('get_qty')} *</label>
                  <input value={form.get_quantity} onChange={set('get_quantity')} type="number" min="1" className="input w-full" />
                </div>
              </>
            )}
            <div>
              <label className="label">{t('product_id_opt')}</label>
              <input value={form.product_id} onChange={set('product_id')} type="number" min="1" className="input w-full" />
            </div>
            <div>
              <label className="label">{t('category_opt')}</label>
              <input value={form.category} onChange={set('category')} className="input w-full" placeholder="e.g. Electronics" />
            </div>
            <div>
              <label className="label">{t('min_order_opt')}</label>
              <input value={form.min_order_amount} onChange={set('min_order_amount')} type="number" min="0" step="0.01" className="input w-full" />
            </div>
            <div>
              <label className="label">{t('starts_at_opt')}</label>
              <input value={form.starts_at} onChange={set('starts_at')} type="date" className="input w-full" />
            </div>
            <div>
              <label className="label">{t('ends_at_opt')}</label>
              <input value={form.ends_at} onChange={set('ends_at')} type="date" className="input w-full" />
            </div>
            <div className="col-span-2">
              <label className="label">{t('description_opt')}</label>
              <textarea value={form.description} onChange={set('description')} rows={2} className="input w-full resize-none" />
            </div>
            <div className="col-span-2 flex items-center gap-2">
              <input id="promo-active" type="checkbox" checked={form.is_active} onChange={set('is_active')} className="h-4 w-4 rounded border-gray-300 text-primary-600" />
              <label htmlFor="promo-active" className="label mb-0">{t('active_status')}</label>
            </div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title={t('delete_promotion')}
        message={t('confirm_delete_promotion')}
        loading={deleteMutation.isPending}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        onCancel={() => setDeleteId(null)}
      />
    </div>
  )
}
