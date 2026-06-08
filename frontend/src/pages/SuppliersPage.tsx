import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Truck, Plus, Pencil, Trash2, Search, DollarSign } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Supplier { id: number; name: string; phone?: string; email?: string; address?: string; tax_number?: string; payment_terms?: number; outstanding_balance?: string; is_active?: boolean }

const emptyForm = { name: '', phone: '', email: '', address: '', tax_number: '', payment_terms: '30' }
const emptyPayment = { amount: '', notes: '', payment_date: new Date().toISOString().slice(0, 10), payment_method: 'cash' }

export default function SuppliersPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState<'add' | 'edit' | 'payment' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [paymentForm, setPaymentForm] = useState({ ...emptyPayment })
  const [paymentSupplierId, setPaymentSupplierId] = useState<number | null>(null)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['suppliers', page, search],
    queryFn: () => apiGet<{ success: boolean; data: Supplier[]; total?: number }>('/suppliers', { page, per_page: 20, search: search || undefined }),
    staleTime: 30_000,
  })

  const suppliers = data?.data ?? []
  const canCreate = hasPermission('create_suppliers', 'manage_suppliers')
  const canEdit   = hasPermission('edit_suppliers', 'manage_suppliers')
  const canDelete = hasPermission('delete_suppliers', 'manage_suppliers')
  const canPay    = hasPermission('create_supplier_payments', 'manage_suppliers')

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (s: Supplier) => {
    setForm({ name: s.name, phone: s.phone ?? '', email: s.email ?? '', address: s.address ?? '', tax_number: s.tax_number ?? '', payment_terms: String(s.payment_terms ?? 30) })
    setEditId(s.id); setModal('edit')
  }
  const openPayment = (id: number) => { setPaymentForm({ ...emptyPayment }); setPaymentSupplierId(id); setModal('payment') }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/suppliers/${editId}`, payload) : apiPost('/suppliers', payload),
    onSuccess: () => { toast.success(editId ? 'Supplier updated' : 'Supplier created'); qc.invalidateQueries({ queryKey: ['suppliers'] }); setModal(null) },
    onError: () => toast.error('Failed to save'),
  })
  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/suppliers/${id}`),
    onSuccess: () => { toast.success('Deleted'); qc.invalidateQueries({ queryKey: ['suppliers'] }); setDeleteId(null) },
    onError: () => toast.error('Failed to delete'),
  })
  const paymentMutation = useMutation({
    mutationFn: (payload: object) => apiPost(`/supplier-payments`, payload),
    onSuccess: () => { toast.success('Payment recorded'); qc.invalidateQueries({ queryKey: ['suppliers'] }); setModal(null) },
    onError: () => toast.error('Failed to record payment'),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error('Name required')
    saveMutation.mutate({ name: form.name, phone: form.phone || undefined, email: form.email || undefined, address: form.address || undefined, tax_number: form.tax_number || undefined, payment_terms: parseInt(form.payment_terms) || 30 })
  }

  const handlePayment = (e: React.FormEvent) => {
    e.preventDefault()
    if (!paymentForm.amount) return toast.error('Amount required')
    paymentMutation.mutate({ supplier_id: paymentSupplierId, amount: parseFloat(paymentForm.amount), payment_method: paymentForm.payment_method, notes: paymentForm.notes || undefined, payment_date: paymentForm.payment_date })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Truck className="h-6 w-6 text-primary-500" /> {t('suppliers')}</h1>
        {canCreate && <button onClick={openAdd} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> {t('add_supplier')}</button>}
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1) }} placeholder={t('search')} className="input pl-9 w-full" />
      </div>

      <div className="card overflow-hidden">
        {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{[t('name'), t('phone'), t('email'), 'Tax No.', 'Terms', t('balance'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {suppliers.length === 0 ? <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  : suppliers.map((s) => (
                    <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{s.name}</td>
                      <td className="px-4 py-3 text-gray-500">{s.phone ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{s.email ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-400 font-mono text-xs">{s.tax_number ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{s.payment_terms ?? 30} days</td>
                      <td className="px-4 py-3"><span className={clsx('font-semibold', parseFloat(s.outstanding_balance ?? '0') > 0 ? 'text-red-600' : 'text-gray-500')}>{parseFloat(s.outstanding_balance ?? '0').toFixed(2)}</span></td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 justify-end">
                          {canPay && <button onClick={() => openPayment(s.id)} title="Record Payment" className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded"><DollarSign className="h-4 w-4" /></button>}
                          {canEdit && <button onClick={() => openEdit(s)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>}
                          {canDelete && <button onClick={() => setDeleteId(s.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>}
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
            <span className="text-sm text-gray-500">{t('page')} {page}</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={suppliers.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      <Modal open={modal === 'add' || modal === 'edit'} onClose={() => setModal(null)} title={editId ? t('edit_supplier') : t('add_supplier')} size="lg"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">{saveMutation.isPending ? 'Saving…' : editId ? t('update') : t('create')}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2"><label className="label">{t('name')} *</label><input {...f('name')} className="input w-full" required /></div>
            <div><label className="label">{t('phone')}</label><input {...f('phone')} className="input w-full" /></div>
            <div><label className="label">{t('email')}</label><input {...f('email')} type="email" className="input w-full" /></div>
            <div><label className="label">{t('tax_number')}</label><input {...f('tax_number')} className="input w-full" /></div>
            <div><label className="label">Payment Terms (days)</label><input {...f('payment_terms')} type="number" min="0" className="input w-full" /></div>
            <div className="col-span-2"><label className="label">{t('address')}</label><input {...f('address')} className="input w-full" /></div>
          </div>
        </form>
      </Modal>

      <Modal open={modal === 'payment'} onClose={() => setModal(null)} title={t('supplier_payments')} size="sm"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handlePayment} disabled={paymentMutation.isPending} className="btn btn-primary">{paymentMutation.isPending ? 'Saving…' : t('add_payment')}</button></>}>
        <form onSubmit={handlePayment} className="space-y-4">
          <div><label className="label">{t('amount')} *</label><input value={paymentForm.amount} type="number" min="0" step="0.01" onChange={(e) => setPaymentForm((p) => ({ ...p, amount: e.target.value }))} className="input w-full" required /></div>
          <div><label className="label">{t('payment_method')} *</label><select value={paymentForm.payment_method} onChange={(e) => setPaymentForm((p) => ({ ...p, payment_method: e.target.value }))} className="input w-full"><option value="cash">{t('cash')}</option><option value="card">{t('card')}</option><option value="transfer">{t('transfer')}</option><option value="check">Check</option></select></div>
          <div><label className="label">{t('payment_date')}</label><input value={paymentForm.payment_date} type="date" onChange={(e) => setPaymentForm((p) => ({ ...p, payment_date: e.target.value }))} className="input w-full" /></div>
          <div><label className="label">{t('notes')}</label><input value={paymentForm.notes} onChange={(e) => setPaymentForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" placeholder="Optional notes" /></div>
        </form>
      </Modal>

      <ConfirmDialog open={deleteId !== null} title={t('delete_supplier')} message={t('confirm_delete')} loading={deleteMutation.isPending} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} onCancel={() => setDeleteId(null)} />
    </div>
  )
}
