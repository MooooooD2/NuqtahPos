import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost, apiDelete } from '@/services/api'
import { Users, Plus, X, Search, Trash2 } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface Customer { id: number; code: string; name: string; phone: string | null; email: string | null; balance: string; credit_limit: string; city: string | null; created_at: string }
interface CustomersResponse { success: boolean; data: Customer[]; total: number }
interface AddForm { name: string; phone: string; email: string; address: string; city: string; credit_limit: string }

export default function CustomersPage() {
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [showModal, setShowModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['customers', search],
    queryFn: () => apiGet<CustomersResponse>('/customers', { search: search || undefined, per_page: 50 }),
    staleTime: 30_000,
  })

  const { register, handleSubmit, reset, formState: { errors } } = useForm<AddForm>({ defaultValues: { name: '', phone: '', email: '', address: '', city: '', credit_limit: '0' } })

  const addMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/customers', payload),
    onSuccess: () => { toast.success('Customer added'); qc.invalidateQueries({ queryKey: ['customers'] }); setShowModal(false); reset() },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/customers/${id}`),
    onSuccess: () => { toast.success('Customer deleted'); qc.invalidateQueries({ queryKey: ['customers'] }) },
    onError: () => toast.error('Cannot delete customer'),
  })

  const customers = data?.data ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Users className="h-6 w-6 text-primary-500" /> Customers</h1>
        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> Add Customer</button>
      </div>

      <div className="card p-4">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input type="text" placeholder="Search by name or phone…" value={search} onChange={e => setSearch(e.target.value)} className="input pl-9" />
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Code', 'Name', 'Phone', 'Email', 'City', 'Balance', 'Credit Limit', ''].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {customers.length === 0 ? (
                <tr><td colSpan={8} className="px-4 py-10 text-center text-gray-400">No customers yet.</td></tr>
              ) : customers.map(c => (
                <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-mono text-xs text-gray-500">{c.code}</td>
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{c.name}</td>
                  <td className="px-4 py-3 text-gray-500">{c.phone ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{c.email ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{c.city ?? '—'}</td>
                  <td className="px-4 py-3 font-semibold text-primary-600">{parseFloat(c.balance).toFixed(2)}</td>
                  <td className="px-4 py-3 text-gray-500">{parseFloat(c.credit_limit).toFixed(2)}</td>
                  <td className="px-4 py-3">
                    <button onClick={() => { if (confirm('Delete this customer?')) deleteMutation.mutate(c.id) }} className="text-red-400 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-800 shadow-2xl">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 px-6 py-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Add Customer</h2>
              <button onClick={() => { setShowModal(false); reset() }} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit(d => addMutation.mutate(d))} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                  <label className="label">Name *</label>
                  <input {...register('name', { required: 'Required' })} className="input" />
                  {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
                </div>
                <div><label className="label">Phone</label><input {...register('phone')} className="input" /></div>
                <div><label className="label">Email</label><input {...register('email')} type="email" className="input" /></div>
                <div><label className="label">City</label><input {...register('city')} className="input" /></div>
                <div><label className="label">Credit Limit</label><input {...register('credit_limit')} type="number" min="0" step="0.01" className="input" /></div>
                <div className="col-span-2"><label className="label">Address</label><input {...register('address')} className="input" /></div>
              </div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowModal(false); reset() }} className="btn-secondary">Cancel</button>
                <button type="submit" disabled={addMutation.isPending} className="btn-primary">{addMutation.isPending ? 'Saving…' : 'Add Customer'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
