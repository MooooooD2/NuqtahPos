import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost, apiDelete } from '@/services/api'
import { api } from '@/services/api'
import { UserCog, Plus, X, Trash2, ToggleLeft, ToggleRight } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface User { id: number; username: string; full_name: string; role: string; is_active: boolean; created_at: string }
interface UsersResponse { success: boolean; users: User[] }
interface AddForm { username: string; full_name: string; password: string; role: string }

const roleClass: Record<string, string> = { admin: 'badge-danger', cashier: 'badge-info', warehouse: 'badge-warning' }

export default function UsersPage() {
  const qc = useQueryClient()
  const [showModal, setShowModal] = useState(false)

  const { data, isLoading } = useQuery({ queryKey: ['users'], queryFn: () => apiGet<UsersResponse>('/users'), staleTime: 60_000 })

  const { register, handleSubmit, reset, formState: { errors } } = useForm<AddForm>({ defaultValues: { username: '', full_name: '', password: '', role: 'cashier' } })

  const addMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/users', payload),
    onSuccess: () => { toast.success('User added'); qc.invalidateQueries({ queryKey: ['users'] }); setShowModal(false); reset() },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed'),
  })

  const toggleMutation = useMutation({
    mutationFn: (id: number) => api.patch(`/users/${id}/toggle-active`),
    onSuccess: () => { toast.success('Status updated'); qc.invalidateQueries({ queryKey: ['users'] }) },
    onError: () => toast.error('Failed to update status'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/users/${id}`),
    onSuccess: () => { toast.success('User deleted'); qc.invalidateQueries({ queryKey: ['users'] }) },
    onError: () => toast.error('Cannot delete user'),
  })

  const users = data?.users ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><UserCog className="h-6 w-6 text-primary-500" /> Users</h1>
        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> Add User</button>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Username', 'Full Name', 'Role', 'Status', 'Actions'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {users.map(u => (
                <tr key={u.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-mono text-sm font-medium text-gray-900 dark:text-white">{u.username}</td>
                  <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{u.full_name}</td>
                  <td className="px-4 py-3"><span className={`badge ${roleClass[u.role] ?? 'badge-gray'} capitalize`}>{u.role}</span></td>
                  <td className="px-4 py-3"><span className={`badge ${u.is_active ? 'badge-success' : 'badge-gray'}`}>{u.is_active ? 'Active' : 'Inactive'}</span></td>
                  <td className="px-4 py-3 flex items-center gap-3">
                    <button onClick={() => toggleMutation.mutate(u.id)} className="text-gray-400 hover:text-primary-600" title="Toggle active">
                      {u.is_active ? <ToggleRight className="h-5 w-5 text-green-500" /> : <ToggleLeft className="h-5 w-5" />}
                    </button>
                    <button onClick={() => { if (confirm(`Delete user ${u.username}?`)) deleteMutation.mutate(u.id) }} className="text-red-400 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-2xl">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 px-6 py-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Add User</h2>
              <button onClick={() => { setShowModal(false); reset() }} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit(d => addMutation.mutate(d))} className="p-6 space-y-4">
              <div><label className="label">Username *</label><input {...register('username', { required: 'Required' })} className="input" />{errors.username && <p className="mt-1 text-xs text-red-500">{errors.username.message}</p>}</div>
              <div><label className="label">Full Name *</label><input {...register('full_name', { required: 'Required' })} className="input" />{errors.full_name && <p className="mt-1 text-xs text-red-500">{errors.full_name.message}</p>}</div>
              <div><label className="label">Password *</label><input {...register('password', { required: 'Required', minLength: { value: 8, message: 'Min 8 characters' } })} type="password" className="input" />{errors.password && <p className="mt-1 text-xs text-red-500">{errors.password.message}</p>}</div>
              <div>
                <label className="label">Role *</label>
                <select {...register('role', { required: 'Required' })} className="input">
                  <option value="admin">Admin</option>
                  <option value="cashier">Cashier</option>
                  <option value="warehouse">Warehouse</option>
                </select>
              </div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowModal(false); reset() }} className="btn-secondary">Cancel</button>
                <button type="submit" disabled={addMutation.isPending} className="btn-primary">{addMutation.isPending ? 'Saving…' : 'Add User'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
