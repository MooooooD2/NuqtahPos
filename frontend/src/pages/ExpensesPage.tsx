import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Receipt, Plus, Pencil, Trash2, DollarSign, Hash, Calendar } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface ExpenseCategoryObj { id: number; name: string }
interface Expense {
  id: number
  title: string
  amount: string
  category?: ExpenseCategoryObj | null
  category_id?: number
  date: string
  payment_method: string
  reference_no?: string
  notes?: string
  created_by_name?: string
}
interface ExpenseCategory { id: number; name: string }

const paymentMethods = ['cash', 'card', 'transfer', 'wallet']
const methodBadge: Record<string, string> = { cash: 'badge-success', card: 'badge-info', transfer: 'badge-warning', wallet: 'badge-gray' }

const emptyForm = { title: '', amount: '', date: new Date().toISOString().slice(0, 10), category_id: '', payment_method: 'cash', reference_no: '', notes: '' }

export default function ExpensesPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [filterCategory, setFilterCategory] = useState('')
  const [filterMethod, setFilterMethod] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['expenses', page, filterCategory, filterMethod, dateFrom, dateTo],
    queryFn: () => apiGet<{ expenses: { data: Expense[]; total?: number } }>('/expenses', {
      page, per_page: 20,
      category_id: filterCategory || undefined,
      payment_method: filterMethod || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
    }),
    staleTime: 30_000,
  })
  const { data: catsData } = useQuery({
    queryKey: ['expense-categories'],
    queryFn: () => apiGet<{ categories: ExpenseCategory[] }>('/expense-categories'),
    staleTime: 120_000,
  })

  const expenses = data?.expenses?.data ?? []
  const categories = catsData?.categories ?? []
  const totalAmount = expenses.reduce((s, e) => s + parseFloat(e.amount ?? '0'), 0)
  const latestDate = expenses.reduce((d, e) => (e.date > d ? e.date : d), '')

  const canCreate = hasPermission('manage_expenses', 'view_pos')
  const canEdit   = hasPermission('manage_expenses', 'view_pos')
  const canDelete = hasPermission('manage_expenses', 'view_pos')

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
      setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (exp: Expense) => {
    setForm({
      title: exp.title,
      amount: exp.amount,
      date: exp.date?.slice(0, 10) ?? '',
      category_id: String(exp.category_id ?? ''),
      payment_method: exp.payment_method,
      reference_no: exp.reference_no ?? '',
      notes: exp.notes ?? '',
    })
    setEditId(exp.id)
    setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) =>
      editId ? apiPut(`/expenses/${editId}`, payload) : apiPost('/expenses', payload),
    onSuccess: () => {
      toast.success(editId ? t('updated_success') : t('created_success'))
      qc.invalidateQueries({ queryKey: ['expenses'] })
      setModal(null)
    },
    onError: () => toast.error(t('save_failed')),
  })
  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/expenses/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['expenses'] }); setDeleteId(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.title || !form.amount || !form.date) return toast.error(t('error'))
    saveMutation.mutate({
      title: form.title,
      amount: parseFloat(form.amount),
      expense_date: form.date,
      category_id: form.category_id ? parseInt(form.category_id) : undefined,
      payment_method: form.payment_method,
      reference: form.reference_no || undefined,
      notes: form.notes || undefined,
    })
  }

  const clearFilters = () => { setFilterCategory(''); setFilterMethod(''); setDateFrom(''); setDateTo(''); setPage(1) }
  const hasFilters = filterCategory || filterMethod || dateFrom || dateTo

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Receipt className="h-6 w-6 text-primary-500" /> {t('expenses')}
          {data?.expenses?.total !== undefined && <span className="text-sm font-normal text-gray-400">({data?.expenses?.total})</span>}
        </h1>
        {canCreate && (
          <button onClick={openAdd} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('new_expense')}
          </button>
        )}
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('total_expenses')}</p>
              <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{totalAmount.toFixed(2)}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-red-100 dark:bg-red-900/30">
              <DollarSign className="h-6 w-6 text-red-600 dark:text-red-400" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('quantity')}</p>
              <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{expenses.length}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-navy-100 dark:bg-navy-900/30">
              <Hash className="h-6 w-6 text-navy-600 dark:text-navy-400" />
            </div>
          </div>
        </div>
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500 dark:text-gray-400">{t('expense_date')}</p>
              <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{latestDate?.slice(0, 10) || '—'}</p>
            </div>
            <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-navy-100 dark:bg-navy-900/30">
              <Calendar className="h-6 w-6 text-navy-600 dark:text-navy-400" />
            </div>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-2">
        <select value={filterCategory} onChange={(e) => { setFilterCategory(e.target.value); setPage(1) }} className="input">
          <option value="">{t('all')} {t('expense_category')}</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <select value={filterMethod} onChange={(e) => { setFilterMethod(e.target.value); setPage(1) }} className="input">
          <option value="">{t('all')} {t('payment_method')}</option>
          {paymentMethods.map((m) => <option key={m} value={m} className="capitalize">{m}</option>)}
        </select>
        <input type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); setPage(1) }} className="input" />
        <input type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); setPage(1) }} className="input" />
        {hasFilters && <button onClick={clearFilters} className="btn btn-secondary text-sm">{t('filter')}</button>}
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
                    {['#', t('title'), t('category'), t('date'), t('amount'), t('payment_method'), t('created_by'), ''].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {expenses.length === 0 ? (
                    <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : expenses.map((exp, idx) => (
                    <tr key={exp.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 text-gray-400 text-xs">{(page - 1) * 20 + idx + 1}</td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{exp.title}</td>
                      <td className="px-4 py-3 text-gray-500">{exp.category?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{exp.date?.slice(0, 10)}</td>
                      <td className="px-4 py-3 font-semibold text-red-600">{parseFloat(exp.amount).toFixed(2)}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize', methodBadge[exp.payment_method] ?? 'badge-gray')}>{exp.payment_method}</span></td>
                      <td className="px-4 py-3 text-gray-500">{exp.created_by_name ?? '—'}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 justify-end">
                          {canEdit && <button onClick={() => openEdit(exp)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>}
                          {canDelete && <button onClick={() => setDeleteId(exp.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* ── Mobile cards ──────────────────────── <lg ── */}
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {expenses.length === 0 ? (
                <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
              ) : expenses.map((exp) => (
                <div key={exp.id} className="p-4 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <p className="font-semibold text-gray-900 dark:text-white text-sm leading-snug">{exp.title}</p>
                    <span className="font-bold text-red-600 shrink-0">{parseFloat(exp.amount).toFixed(2)}</span>
                  </div>
                  <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                    {exp.category?.name && <span className="badge badge-gray">{exp.category.name}</span>}
                    <span className={clsx('badge capitalize', methodBadge[exp.payment_method] ?? 'badge-gray')}>{exp.payment_method}</span>
                    <span>{exp.date?.slice(0, 10)}</span>
                    {exp.created_by_name && <span>· {exp.created_by_name}</span>}
                  </div>
                  {(canEdit || canDelete) && (
                    <div className="flex gap-2 pt-1">
                      {canEdit && (
                        <button onClick={() => openEdit(exp)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400 hover:bg-primary-100 transition-colors font-medium">
                          <Pencil className="h-3.5 w-3.5" />{isAr ? 'تعديل' : 'Edit'}
                        </button>
                      )}
                      {canDelete && (
                        <button onClick={() => setDeleteId(exp.id)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition-colors font-medium">
                          <Trash2 className="h-3.5 w-3.5" />{isAr ? 'حذف' : 'Delete'}
                        </button>
                      )}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </>
        )}
        {(data?.expenses?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">{t('page')} {page} · {data?.expenses?.total} {t('total')}</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={expenses.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      <Modal
        open={modal !== null}
        onClose={() => setModal(null)}
        title={modal === 'edit' ? t('edit') + ' ' + t('expenses') : t('new_expense')}
        size="lg"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button>
            <button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">
              {saveMutation.isPending ? t('loading') : modal === 'edit' ? t('update') : t('create')}
            </button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="label">{t('name')} *</label>
              <input {...f('title')} className="input w-full" placeholder="Expense title" required />
            </div>
            <div>
              <label className="label">{t('amount')} *</label>
              <input {...f('amount')} type="number" step="0.01" min="0" className="input w-full" placeholder="0.00" required />
            </div>
            <div>
              <label className="label">{t('expense_date')} *</label>
              <input {...f('date')} type="date" className="input w-full" required />
            </div>
            <div>
              <label className="label">{t('expense_category')}</label>
              <select {...f('category_id')} className="input w-full">
                <option value="">— {t('expense_category')} —</option>
                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </div>
            <div>
              <label className="label">{t('payment_method')}</label>
              <select {...f('payment_method')} className="input w-full">
                {paymentMethods.map((m) => <option key={m} value={m} className="capitalize">{m}</option>)}
              </select>
            </div>
            <div className="col-span-2">
              <label className="label">{t('reason')}</label>
              <input {...f('reference_no')} className="input w-full" placeholder="Optional reference" />
            </div>
            <div className="col-span-2">
              <label className="label">{t('notes')}</label>
              <textarea {...f('notes')} className="input w-full" rows={3} placeholder="Optional notes" />
            </div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title={t('delete') + ' ' + t('expenses')}
        message={t('confirm_delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        onCancel={() => setDeleteId(null)}
      />
    </div>
  )
}
