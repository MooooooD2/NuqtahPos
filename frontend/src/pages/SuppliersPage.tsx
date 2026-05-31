import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost, apiDelete } from '@/services/api'
import { Truck, Plus, X, Trash2 } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface Supplier { id: number; name: string; phone: string | null; email: string | null; address: string | null; balance: string; created_at: string }
interface SuppliersResponse { success: boolean; suppliers: Supplier[] }
interface AddForm { name: string; phone: string; email: string; address: string }

export default function SuppliersPage() {
  const qc = useQueryClient()
  const [showModal, setShowModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['suppliers'],
    queryFn: () => apiGet<SuppliersResponse>('/suppliers'),
    staleTime: 60_000,
  })

  const { register, handleSubmit, reset, formState: { errors } } = useForm<AddForm>({ defaultValues: { name: '', phone: '', email: '', address: '' } })

  const addMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/suppliers', payload),
    onSuccess: () => { toast.success('Supplier added'); qc.invalidateQueries({ queryKey: ['suppliers'] }); setShowModal(false); reset() },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/suppliers/${id}`),
    onSuccess: () => { toast.success('Supplier deleted'); qc.invalidateQueries({ queryKey: ['suppliers'] }) },
    onError: () => toast.error('Cannot delete supplier'),
  })

  const suppliers = data?.suppliers ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Truck className="h-6 w-6 text-primary-500" /> Suppliers</h1>
        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> Add Supplier</button>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Name', 'Phone', 'Email', 'Address', 'Balance', ''].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {suppliers.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">No suppliers yet.</td></tr>
              ) : suppliers.map(s => (
                <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{s.name}</td>
                  <td className="px-4 py-3 text-gray-500">{s.phone ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{s.email ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{s.address ?? '—'}</td>
                  <td className="px-4 py-3 font-semibold text-primary-600">{parseFloat(s.balance ?? '0').toFixed(2)}</td>
                  <td className="px-4 py-3">
                    <button onClick={() => { if (confirm('Delete this supplier?')) deleteMutation.mutate(s.id) }} className="text-red-400 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
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
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Add Supplier</h2>
              <button onClick={() => { setShowModal(false); reset() }} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit(d => addMutation.mutate(d))} className="p-6 space-y-4">
              <div>
                <label className="label">Name *</label>
                <input {...register('name', { required: 'Required' })} className="input" />
                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div><label className="label">Phone</label><input {...register('phone')} className="input" /></div>
                <div><label className="label">Email</label><input {...register('email')} type="email" className="input" /></div>
              </div>
              <div><label className="label">Address</label><input {...register('address')} className="input" /></div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowModal(false); reset() }} className="btn-secondary">Cancel</button>
                <button type="submit" disabled={addMutation.isPending} className="btn-primary">{addMutation.isPending ? 'Saving…' : 'Add Supplier'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
