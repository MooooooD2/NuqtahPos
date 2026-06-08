import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { GitBranch, Plus, Pencil, Trash2, MapPin, Phone, Star } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Branch { id: number; name: string; code: string; address?: string; phone?: string; is_default: boolean; is_active: boolean }

const emptyForm = { name: '', code: '', address: '', phone: '', is_default: false, is_active: true }

export default function BranchesPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['branches'],
    queryFn: () => apiGet<{ success: boolean; data: Branch[] }>('/branches'),
    staleTime: 60_000,
  })

  const branches = data?.data ?? []
  const canManage = hasPermission('manage_roles')

  const sf = (field: keyof typeof emptyForm) => ({
    value: form[field] as string,
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (b: Branch) => {
    setForm({ name: b.name, code: b.code, address: b.address ?? '', phone: b.phone ?? '', is_default: b.is_default, is_active: b.is_active })
    setEditId(b.id); setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/branches/${editId}`, payload) : apiPost('/branches', payload),
    onSuccess: () => { toast.success(editId ? t('updated_success') : t('created_success')); qc.invalidateQueries({ queryKey: ['branches'] }); setModal(null) },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('save_failed')
      toast.error(msg)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/branches/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['branches'] }); setDeleteId(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error(t('error'))
    if (!form.code) return toast.error(t('error'))
    saveMutation.mutate({ name: form.name, code: form.code, address: form.address || undefined, phone: form.phone || undefined, is_default: form.is_default, is_active: form.is_active })
  }

  const deletingBranch = branches.find((b) => b.id === deleteId)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <GitBranch className="h-6 w-6 text-primary-500" /> {t('branches')}
        </h1>
        {canManage && (
          <button onClick={openAdd} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('new_branch')}
          </button>
        )}
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /><span className="ml-2 text-gray-400">{t('loading')}</span></div>
      ) : branches.length === 0 ? (
        <div className="card p-12 text-center text-gray-400">
          <GitBranch className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p>{t('no_data')}</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {branches.map((branch) => (
            <div key={branch.id} className="card p-5 flex flex-col gap-3">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <h3 className="text-lg font-bold text-gray-900 dark:text-white truncate">{branch.name}</h3>
                  <div className="flex items-center gap-1.5 mt-1 flex-wrap">
                    <span className="badge badge-gray font-mono text-xs">{branch.code}</span>
                    {branch.is_default && (
                      <span className="badge badge-warning flex items-center gap-0.5"><Star className="h-3 w-3" /> {t('main_branch')}</span>
                    )}
                    <span className={clsx('badge', branch.is_active ? 'badge-success' : 'badge-gray')}>
                      {branch.is_active ? t('active') : t('inactive')}
                    </span>
                  </div>
                </div>
              </div>

              {(branch.address || branch.phone) && (
                <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                  {branch.address && (
                    <div className="flex items-start gap-1.5">
                      <MapPin className="h-4 w-4 flex-shrink-0 mt-0.5 text-gray-400" />
                      <span className="line-clamp-2">{branch.address}</span>
                    </div>
                  )}
                  {branch.phone && (
                    <div className="flex items-center gap-1.5">
                      <Phone className="h-4 w-4 flex-shrink-0 text-gray-400" />
                      <span>{branch.phone}</span>
                    </div>
                  )}
                </div>
              )}

              {canManage && (
                <div className="flex gap-2 pt-1 border-t border-gray-100 dark:border-gray-700 mt-auto">
                  <button onClick={() => openEdit(branch)} className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 px-2 py-1.5 rounded transition-colors">
                    <Pencil className="h-3.5 w-3.5" /> {t('edit')}
                  </button>
                  <button
                    onClick={() => !branch.is_default && setDeleteId(branch.id)}
                    disabled={branch.is_default}
                    title={branch.is_default ? t('cannot_delete_default_branch') : t('delete_branch')}
                    className={clsx('flex items-center gap-1.5 text-xs px-2 py-1.5 rounded transition-colors', branch.is_default ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed' : 'text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20')}
                  >
                    <Trash2 className="h-3.5 w-3.5" /> {t('delete')}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <Modal
        open={modal === 'add' || modal === 'edit'}
        onClose={() => setModal(null)}
        title={editId ? t('edit') : t('new_branch')}
        size="md"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button>
            <button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">
              {saveMutation.isPending ? t('loading') : t('save')}
            </button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="label">{t('name')} *</label>
              <input {...sf('name')} className="input w-full" placeholder="e.g. Main Branch" required />
            </div>
            <div className="col-span-2">
              <label className="label">{t('branch_code')} *</label>
              <input {...sf('code')} className="input w-full font-mono" placeholder="e.g. BR001" required />
            </div>
            <div className="col-span-2">
              <label className="label">{t('address')}</label>
              <input {...sf('address')} className="input w-full" placeholder="Street, City" />
            </div>
            <div className="col-span-2">
              <label className="label">{t('phone')}</label>
              <input {...sf('phone')} className="input w-full" placeholder="+1 555 000 0000" />
            </div>
            <div className="flex items-center gap-2">
              <input
                id="is_default"
                type="checkbox"
                checked={form.is_default}
                onChange={(e) => setForm((p) => ({ ...p, is_default: e.target.checked }))}
                className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <label htmlFor="is_default" className="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{t('set_as_default_branch')}</label>
            </div>
            <div className="flex items-center gap-2">
              <input
                id="is_active"
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm((p) => ({ ...p, is_active: e.target.checked }))}
                className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <label htmlFor="is_active" className="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">{t('active')}</label>
            </div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title={t('delete_branch')}
        message={t('confirm_delete_branch', { n: deletingBranch?.name ?? '' })}
        loading={deleteMutation.isPending}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        onCancel={() => setDeleteId(null)}
      />
    </div>
  )
}
