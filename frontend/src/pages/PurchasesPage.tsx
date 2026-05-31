import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost } from '@/services/api'
import { ShoppingBag, Plus, X } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface PurchaseOrder { id: number; order_number: string; supplier: { name: string } | null; total: string; status: string; created_at: string }
interface PurchasesResponse { success: boolean; purchase_orders: { data: PurchaseOrder[]; total: number } }
interface Supplier { id: number; name: string }
interface AddForm { supplier_id: string; expected_date: string; notes: string }

const statusClass: Record<string, string> = { pending: 'badge-warning', approved: 'badge-info', received: 'badge-success', partial: 'badge-warning', cancelled: 'badge-danger' }

export default function PurchasesPage() {
  const qc = useQueryClient()
  const [showModal, setShowModal] = useState(false)

  const { data, isLoading } = useQuery({ queryKey: ['purchases'], queryFn: () => apiGet<PurchasesResponse>('/purchase-orders?per_page=20'), staleTime: 30_000 })
  const { data: suppData } = useQuery({ queryKey: ['suppliers'], queryFn: () => apiGet<{ success: boolean; suppliers: Supplier[] }>('/suppliers'), staleTime: 300_000 })

  const { register, handleSubmit, reset, formState: { errors } } = useForm<AddForm>({ defaultValues: { supplier_id: '', expected_date: '', notes: '' } })

  const addMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/purchase-orders', payload),
    onSuccess: () => { toast.success('Purchase order created'); qc.invalidateQueries({ queryKey: ['purchases'] }); setShowModal(false); reset() },
    onError: (err: unknown) => toast.error((err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed'),
  })

  const orders = data?.purchase_orders?.data ?? []
  const suppliers = suppData?.suppliers ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><ShoppingBag className="h-6 w-6 text-primary-500" /> Purchase Orders</h1>
        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> New Order</button>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Order #', 'Supplier', 'Total', 'Status', 'Date'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {orders.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No purchase orders yet.</td></tr>
              ) : orders.map(o => (
                <tr key={o.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-mono text-xs font-semibold text-primary-600">{o.order_number}</td>
                  <td className="px-4 py-3 text-gray-900 dark:text-white">{o.supplier?.name ?? '—'}</td>
                  <td className="px-4 py-3 font-semibold">{parseFloat(o.total ?? '0').toFixed(2)}</td>
                  <td className="px-4 py-3"><span className={`badge ${statusClass[o.status] ?? 'badge-gray'} capitalize`}>{o.status}</span></td>
                  <td className="px-4 py-3 text-gray-400 text-xs">{o.created_at?.slice(0, 10)}</td>
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
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">New Purchase Order</h2>
              <button onClick={() => { setShowModal(false); reset() }} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit(d => addMutation.mutate({ ...d, supplier_id: parseInt(d.supplier_id) }))} className="p-6 space-y-4">
              <div>
                <label className="label">Supplier *</label>
                <select {...register('supplier_id', { required: 'Required' })} className="input">
                  <option value="">Select supplier…</option>
                  {suppliers.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
                {errors.supplier_id && <p className="mt-1 text-xs text-red-500">{errors.supplier_id.message}</p>}
              </div>
              <div><label className="label">Expected Date</label><input {...register('expected_date')} type="date" className="input" /></div>
              <div><label className="label">Notes</label><textarea {...register('notes')} className="input h-20 resize-none" /></div>
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowModal(false); reset() }} className="btn-secondary">Cancel</button>
                <button type="submit" disabled={addMutation.isPending} className="btn-primary">{addMutation.isPending ? 'Creating…' : 'Create Order'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
