import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Users, Plus, Pencil, Trash2, Search, Star, CreditCard } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Customer { id: number; name: string; phone?: string; email?: string; address?: string; city?: string; credit_limit?: string; outstanding_balance?: string; loyalty_points?: number; customer_group_id?: number }
interface Group { id: number; name: string; discount_percent?: string }

const emptyForm = { name: '', phone: '', email: '', address: '', city: '', credit_limit: '0', customer_group_id: '' }

export default function CustomersPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [tab, setTab] = useState<'customers' | 'groups'>('customers')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState<'add' | 'edit' | 'group' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [groupForm, setGroupForm] = useState({ name: '', discount_percent: '0' })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['customers', page, search],
    queryFn: () => apiGet<{ success: boolean; data: Customer[]; total?: number }>('/customers', { page, per_page: 20, search: search || undefined }),
    staleTime: 30_000,
  })
  const { data: groupsData } = useQuery({
    queryKey: ['customer-groups'],
    queryFn: () => apiGet<{ success: boolean; data: Group[] }>('/customer-groups'),
    staleTime: 120_000,
  })

  const customers = data?.data ?? []
  const groups = groupsData?.data ?? []
  const canCreate = hasPermission('create_customers', 'manage_customers')
  const canEdit   = hasPermission('edit_customers', 'manage_customers')
  const canDelete = hasPermission('delete_customers', 'manage_customers')

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (c: Customer) => {
    setForm({ name: c.name, phone: c.phone ?? '', email: c.email ?? '', address: c.address ?? '', city: c.city ?? '', credit_limit: c.credit_limit ?? '0', customer_group_id: String(c.customer_group_id ?? '') })
    setEditId(c.id); setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/customers/${editId}`, payload) : apiPost('/customers', payload),
    onSuccess: () => { toast.success(editId ? t('updated_success') : t('created_success')); qc.invalidateQueries({ queryKey: ['customers'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/customers/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['customers'] }); setDeleteId(null) },
    onError: () => toast.error(t('delete_failed')),
  })
  const saveGroupMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/customer-groups', payload),
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['customer-groups'] }); setModal(null) },
    onError: () => toast.error(t('error')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error(t('error'))
    saveMutation.mutate({ name: form.name, phone: form.phone || undefined, email: form.email || undefined, address: form.address || undefined, city: form.city || undefined, credit_limit: parseFloat(form.credit_limit) || 0, customer_group_id: form.customer_group_id ? parseInt(form.customer_group_id) : undefined })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Users className="h-6 w-6 text-primary-500" /> {t('customers')}</h1>
        {canCreate && (
          <div className="flex gap-2">
            <button onClick={() => { setGroupForm({ name: '', discount_percent: '0' }); setModal('group') }} className="btn btn-secondary text-sm flex items-center gap-1"><Plus className="h-4 w-4" /> {t('customer_groups')}</button>
            <button onClick={openAdd} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> {t('add_customer')}</button>
          </div>
        )}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['customers', 'groups'] as const).map((tabKey) => (
          <button key={tabKey} onClick={() => setTab(tabKey)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === tabKey ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{tabKey === 'customers' ? t('customers') : t('customer_groups')}</button>
        ))}
      </div>

      {tab === 'customers' && (
        <>
          <div className="relative max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder={t('search')} className="input pl-9 w-full" />
          </div>
          <div className="card overflow-hidden">
            {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('name'), t('phone'), t('email'), t('customer_groups'), t('points'), t('balance'), t('credit_limit'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {customers.length === 0 ? <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_customers_found')}</td></tr>
                      : customers.map((c) => (
                        <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{c.name}</td>
                          <td className="px-4 py-3 text-gray-500">{c.phone ?? '—'}</td>
                          <td className="px-4 py-3 text-gray-500">{c.email ?? '—'}</td>
                          <td className="px-4 py-3">{c.customer_group_id ? <span className="badge badge-info text-xs">{groups.find((g) => g.id === c.customer_group_id)?.name ?? 'Group'}</span> : <span className="text-gray-400">—</span>}</td>
                          <td className="px-4 py-3">{(c.loyalty_points ?? 0) > 0 ? <span className="flex items-center gap-1 text-amber-600 font-semibold"><Star className="h-3.5 w-3.5" />{c.loyalty_points}</span> : <span className="text-gray-400">0</span>}</td>
                          <td className="px-4 py-3"><span className={clsx('font-semibold', parseFloat(c.outstanding_balance ?? '0') > 0 ? 'text-red-600' : 'text-gray-500')}>{parseFloat(c.outstanding_balance ?? '0').toFixed(2)}</span></td>
                          <td className="px-4 py-3 text-gray-500 flex items-center gap-1"><CreditCard className="h-3.5 w-3.5 text-gray-400" />{parseFloat(c.credit_limit ?? '0').toFixed(2)}</td>
                          <td className="px-4 py-3">
                            <div className="flex gap-1 justify-end">
                              {canEdit && <button onClick={() => openEdit(c)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>}
                              {canDelete && <button onClick={() => setDeleteId(c.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>}
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
                <span className="text-sm text-gray-500">{t('page')} {page} · {data?.total} total</span>
                <div className="flex gap-2">
                  <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
                  <button onClick={() => setPage((p) => p + 1)} disabled={customers.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
                </div>
              </div>
            )}
          </div>
        </>
      )}

      {tab === 'groups' && (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700"><tr>{[t('customer_groups'), t('discount') + ' %', ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {groups.length === 0 ? <tr><td colSpan={3} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                : groups.map((g) => (
                  <tr key={g.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{g.name}</td>
                    <td className="px-4 py-3 text-primary-600 font-semibold">{g.discount_percent ?? '0'}%</td>
                    <td className="px-4 py-3">{canDelete && <button onClick={() => apiDelete(`/customer-groups/${g.id}`).then(() => { qc.invalidateQueries({ queryKey: ['customer-groups'] }); toast.success(t('deleted_success')) })} className="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded"><Trash2 className="h-4 w-4" /></button>}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={modal === 'add' || modal === 'edit'} onClose={() => setModal(null)} title={editId ? t('edit_customer') : t('add_customer')} size="lg"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">{saveMutation.isPending ? t('saving') : editId ? t('update') : t('create')}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2"><label className="label">{t('name')} *</label><input {...f('name')} className="input w-full" required /></div>
            <div><label className="label">{t('phone')}</label><input {...f('phone')} className="input w-full" /></div>
            <div><label className="label">{t('email')}</label><input {...f('email')} type="email" className="input w-full" /></div>
            <div><label className="label">{t('city')}</label><input {...f('city')} className="input w-full" /></div>
            <div><label className="label">{t('credit_limit')}</label><input {...f('credit_limit')} type="number" min="0" step="0.01" className="input w-full" /></div>
            <div className="col-span-2"><label className="label">{t('address')}</label><input {...f('address')} className="input w-full" /></div>
            <div><label className="label">{t('customer_groups')}</label><select {...f('customer_group_id')} className="input w-full"><option value="">—</option>{groups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}</select></div>
          </div>
        </form>
      </Modal>

      <Modal open={modal === 'group'} onClose={() => setModal(null)} title={t('add_customer_group')} size="sm"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={() => { if (!groupForm.name) return toast.error(t('error')); saveGroupMutation.mutate({ name: groupForm.name, discount_percent: parseFloat(groupForm.discount_percent) || 0 }) }} disabled={saveGroupMutation.isPending} className="btn btn-primary">{saveGroupMutation.isPending ? t('saving') : t('create')}</button></>}>
        <div className="space-y-4">
          <div><label className="label">{t('name')} *</label><input value={groupForm.name} onChange={(e) => setGroupForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" /></div>
          <div><label className="label">{t('discount')} %</label><input value={groupForm.discount_percent} type="number" min="0" max="100" onChange={(e) => setGroupForm((p) => ({ ...p, discount_percent: e.target.value }))} className="input w-full" /></div>
        </div>
      </Modal>

      <ConfirmDialog open={deleteId !== null} title={t('delete_customer')} message={t('confirm_delete')} loading={deleteMutation.isPending} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} onCancel={() => setDeleteId(null)} />
    </div>
  )
}
