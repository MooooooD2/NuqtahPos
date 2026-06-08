import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { UserCog, Plus, Pencil, Trash2, ToggleLeft, ToggleRight, Shield } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

interface AppUser { id: number; username: string; full_name?: string; email?: string; role: string; is_active: boolean; created_at: string }
interface PermissionObj { id: number; name: string; guard_name: string }
interface Role { id: number; name: string; permissions?: PermissionObj[] }

const emptyForm = { username: '', name: '', email: '', password: '', role: 'cashier', branch_id: '' }

export default function UsersPage() {
  const { t } = useTranslation('pos')
  const { hasPermission, isAdmin } = usePermission()
  const qc = useQueryClient()
  const [tab, setTab] = useState<'users' | 'roles'>('users')
  const [modal, setModal] = useState<'add' | 'edit' | 'permissions' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [selectedRole, setSelectedRole] = useState<Role | null>(null)

  const { data: usersData, isLoading: usersLoading } = useQuery({
    queryKey: ['users'],
    queryFn: () => apiGet<{ success: boolean; data: AppUser[] }>('/users'),
    staleTime: 60_000,
  })
  const { data: rolesData, isLoading: rolesLoading } = useQuery({
    queryKey: ['roles'],
    queryFn: () => apiGet<{ success: boolean; data: Role[] }>('/roles'),
    staleTime: 120_000,
  })
  const { data: permsData } = useQuery({
    queryKey: ['all-permissions'],
    queryFn: () => apiGet<{ success: boolean; data: PermissionObj[] }>('/permissions'),
    staleTime: 300_000,
    enabled: modal === 'permissions',
  })

  const users = usersData?.data ?? []
  const roles = rolesData?.data ?? []
  const allPerms = permsData?.data?.map((p) => p.name) ?? []

  const canManage = hasPermission('manage_roles') || isAdmin

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyForm }); setEditId(null); setModal('add') }
  const openEdit = (u: AppUser) => {
    setForm({ username: u.username, name: u.full_name ?? '', email: u.email ?? '', password: '', role: u.role, branch_id: '' })
    setEditId(u.id); setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/users/${editId}`, payload) : apiPost('/users', payload),
    onSuccess: () => { toast.success(editId ? t('updated_success') : t('created_success')); qc.invalidateQueries({ queryKey: ['users'] }); setModal(null) },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('save_failed')
      toast.error(msg)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/users/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['users'] }); setDeleteId(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const toggleActive = useMutation({
    mutationFn: (id: number) => apiPost(`/users/${id}/toggle-active`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['users'] }) },
    onError: () => toast.error(t('save_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!editId && (!form.username || !form.name)) return toast.error(t('error'))
    if (!editId && !form.password) return toast.error(t('error'))
    const payload: Record<string, unknown> = { full_name: form.name, role: form.role }
    if (!editId) payload.username = form.username
    if (form.password) payload.password = form.password
    saveMutation.mutate(payload)
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><UserCog className="h-6 w-6 text-primary-500" /> {t('users_roles')}</h1>
        {canManage && tab === 'users' && <button onClick={openAdd} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> {t('add_user')}</button>}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['users', 'roles'] as const).map((tabKey) => (
          <button key={tabKey} onClick={() => setTab(tabKey)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === tabKey ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{tabKey === 'users' ? t('users') : t('roles')}</button>
        ))}
      </div>

      {tab === 'users' && (
        <div className="card overflow-hidden">
          {usersLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{[t('username'), t('full_name'), t('role'), t('status'), t('created_at'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {users.length === 0 ? <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">{t('no_users')}</td></tr>
                    : users.map((u) => (
                      <tr key={u.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white font-mono text-sm">{u.username}</td>
                        <td className="px-4 py-3 text-gray-500">{u.full_name ?? '—'}</td>
                        <td className="px-4 py-3"><span className="badge badge-info capitalize">{u.role}</span></td>
                        <td className="px-4 py-3">
                          <button onClick={() => canManage && toggleActive.mutate(u.id)}
                            className={clsx('flex items-center gap-1.5 text-sm font-medium', u.is_active ? 'text-green-600' : 'text-gray-400', canManage && 'hover:opacity-70 cursor-pointer')}>
                            {u.is_active ? <ToggleRight className="h-5 w-5" /> : <ToggleLeft className="h-5 w-5" />}
                            {u.is_active ? t('active') : t('inactive')}
                          </button>
                        </td>
                        <td className="px-4 py-3 text-gray-400 text-xs">{u.created_at?.slice(0, 10)}</td>
                        <td className="px-4 py-3">
                          {canManage && (
                            <div className="flex gap-1 justify-end">
                              <button onClick={() => openEdit(u)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>
                              <button onClick={() => setDeleteId(u.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>
                            </div>
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

      {tab === 'roles' && (
        <div className="card overflow-hidden">
          {rolesLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700"><tr>{[t('role_name'), t('permissions_count'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {roles.length === 0 ? <tr><td colSpan={3} className="px-4 py-10 text-center text-gray-400">{t('no_roles')}</td></tr>
                  : roles.map((r) => (
                    <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white capitalize">{r.name}</td>
                      <td className="px-4 py-3 text-gray-500">{r.permissions?.length ?? '—'} {t('permissions')}</td>
                      <td className="px-4 py-3">
                        {canManage && (
                          <button onClick={() => { setSelectedRole(r); setModal('permissions') }} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded flex items-center gap-1 text-xs">
                            <Shield className="h-4 w-4" /> {t('permissions')}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Add/Edit User Modal */}
      <Modal open={modal === 'add' || modal === 'edit'} onClose={() => setModal(null)} title={editId ? t('edit_user') : t('add_user')} size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">{saveMutation.isPending ? t('saving') : editId ? t('update') : t('create')}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          {!editId && <div><label className="label">{t('username')} *</label><input {...f('username')} className="input w-full font-mono" required /></div>}
          <div><label className="label">{t('full_name')} *</label><input {...f('name')} className="input w-full" required /></div>
          <div><label className="label">{editId ? t('new_password_hint') : t('password')}</label><input {...f('password')} type="password" className="input w-full" /></div>
          <div>
            <label className="label">{t('role')}</label>
            <select {...f('role')} className="input w-full">
              <option value="cashier">{t('role_cashier')}</option>
              <option value="warehouse">{t('role_warehouse')}</option>
              <option value="accountant">{t('role_accountant')}</option>
              <option value="admin">{t('role_admin')}</option>
            </select>
          </div>
        </form>
      </Modal>

      {/* Permissions Modal */}
      <Modal open={modal === 'permissions'} onClose={() => setModal(null)} title={`${t('permissions')} — ${selectedRole?.name ?? ''}`} size="xl">
        <div className="grid grid-cols-2 gap-1.5">
          {allPerms.map((perm) => {
            const hasIt = selectedRole?.permissions?.some((p) => p.name === perm) ?? false
            return (
              <div key={perm} className={clsx('flex items-center gap-2 p-2 rounded text-xs', hasIt ? 'bg-primary-50 dark:bg-primary-900/20' : 'bg-gray-50 dark:bg-gray-700')}>
                <div className={clsx('h-2 w-2 rounded-full flex-shrink-0', hasIt ? 'bg-primary-500' : 'bg-gray-300')} />
                <span className={clsx('font-mono', hasIt ? 'text-primary-700 dark:text-primary-300' : 'text-gray-400')}>{perm}</span>
              </div>
            )
          })}
          {allPerms.length === 0 && <p className="col-span-2 text-center text-gray-400 py-4">{t('no_permissions')}</p>}
        </div>
      </Modal>

      <ConfirmDialog open={deleteId !== null} title={t('delete_user')} message={t('confirm_delete')} loading={deleteMutation.isPending} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} onCancel={() => setDeleteId(null)} />
    </div>
  )
}
