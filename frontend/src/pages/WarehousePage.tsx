import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost } from '@/services/api'
import { Warehouse, Plus, X } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface WarehouseItem { id: number; name: string; code: string | null; address: string | null; keeper_name: string | null; is_default: boolean; is_locked: boolean; is_active: boolean }
interface WarehousesResponse { success: boolean; data: WarehouseItem[] }
interface AddForm { name: string; code: string; address: string; keeper_name: string }

export default function WarehousePage() {
  const qc = useQueryClient()
  const [showModal, setShowModal] = useState(false)

  const { data, isLoading } = useQuery({ queryKey: ['warehouses'], queryFn: () => apiGet<WarehousesResponse>('/warehouses'), staleTime: 60_000 })
  const { register, handleSubmit, reset, formState: { errors } } = useForm<AddForm>({ defaultValues: { name: '', code: '', address: '', keeper_name: '' } })

  const addMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/warehouses', payload),
    onSuccess: () => { toast.success('Warehouse added'); qc.invalidateQueries({ queryKey: ['warehouses'] }); setShowModal(false); reset() },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed'),
  })

  const warehouses = data?.data ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Warehouse className="h-6 w-6 text-primary-500" /> Warehouses</h1>
        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> Add Warehouse</button>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Code', 'Name', 'Keeper', 'Address', 'Type', 'Status'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {warehouses.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">No warehouses yet.</td></tr>
              ) : warehouses.map(w => (
                <tr key={w.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-mono text-xs text-gray-500">{w.code ?? '—'}</td>
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{w.name}</td>
                  <td className="px-4 py-3 text-gray-500">{w.keeper_name ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{w.address ?? '—'}</td>
                  <td className="px-4 py-3">
                    {w.is_default ? <span className="badge badge-success">Default</span> : <span className="badge badge-gray">Branch</span>}
                  </td>
                  <td className="px-4 py-3 flex items-center gap-2">
                    {w.is_active ? <span className="badge badge-success">Active</span> : <span className="badge badge-gray">Inactive</span>}
                    {w.is_locked && <span className="badge badge-danger">Locked</span>}
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
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Add Warehouse</h2>
              <button onClick={() => { setShowModal(false); reset() }} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit(d => addMutation.mutate(d))} className="p-6 space-y-4">
              <div>
                <label className="label">Name *</label>
                <input {...register('name', { required: 'Required' })} className="input" />
                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div><label className="label">Code</label><input {...register('code')} className="input" placeholder="WH-001" /></div>
                <div><label className="label">Keeper Name</label><input {...register('keeper_name')} className="input" /></div>
              </div>
              <div><label className="label">Address</label><input {...register('address')} className="input" /></div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowModal(false); reset() }} className="btn-secondary">Cancel</button>
                <button type="submit" disabled={addMutation.isPending} className="btn-primary">{addMutation.isPending ? 'Saving…' : 'Add Warehouse'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
