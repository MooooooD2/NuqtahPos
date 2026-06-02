import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { CreditCard, Plus, Printer, DollarSign, Calendar, Hash } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Supplier { id: number; name: string; phone?: string; outstanding_balance?: string }
interface SupplierPayment { id: number; payment_number?: string; supplier?: { name: string; phone?: string }; amount: string; payment_method: string; payment_date: string; notes?: string }

const methodClass: Record<string, string> = { cash: 'badge-success', card: 'badge-info', transfer: 'badge-warning', check: 'badge-gray' }
const emptyForm = { supplier_id: '', amount: '', payment_method: 'cash', payment_date: new Date().toISOString().slice(0, 10), notes: '' }

export default function SupplierPaymentsPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState<'new' | 'print' | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [printRow, setPrintRow] = useState<SupplierPayment | null>(null)
  const [filterSupplier, setFilterSupplier] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)

  const { data: suppliersData } = useQuery({
    queryKey: ['suppliers-all'],
    queryFn: () => apiGet<{ success: boolean; data: Supplier[] }>('/suppliers', { all: 1 }),
    staleTime: 120_000,
  })
  const { data, isLoading } = useQuery({
    queryKey: ['supplier-payments', filterSupplier, dateFrom, dateTo, page],
    queryFn: () => apiGet<{ success: boolean; data: SupplierPayment[]; total?: number }>('/supplier-payments', {
      supplier_id: filterSupplier || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      page,
      per_page: 20,
    }),
    staleTime: 30_000,
  })

  const suppliers = suppliersData?.data ?? []
  const payments = data?.data ?? []
  const canView = hasPermission('view_warehouse')

  const selectedSupplier = suppliers.find((s) => String(s.id) === form.supplier_id)

  const totalPaid = payments.reduce((s, p) => s + parseFloat(p.amount || '0'), 0)
  const latestDate = payments.length > 0 ? payments[0].payment_date?.slice(0, 10) : '—'

  const createPayment = useMutation({
    mutationFn: (payload: object) => apiPost('/supplier-payments', payload),
    onSuccess: () => { toast.success('Payment recorded'); qc.invalidateQueries({ queryKey: ['supplier-payments'] }); setModal(null) },
    onError: () => toast.error('Failed to record payment'),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.supplier_id) return toast.error('Supplier required')
    if (!form.amount || parseFloat(form.amount) <= 0) return toast.error('Amount must be > 0')
    if (!form.payment_date) return toast.error('Payment date required')
    createPayment.mutate({
      supplier_id: parseInt(form.supplier_id),
      amount: parseFloat(form.amount),
      payment_method: form.payment_method,
      payment_date: form.payment_date,
      notes: form.notes || undefined,
    })
  }

  const openPrint = (p: SupplierPayment) => { setPrintRow(p); setModal('print') }

  const clearFilters = () => { setFilterSupplier(''); setDateFrom(''); setDateTo(''); setPage(1) }

  if (!canView) return <div className="card p-8 text-center text-gray-400">No permission to view supplier payments.</div>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><CreditCard className="h-6 w-6 text-primary-500" /> Supplier Payments</h1>
        <button onClick={() => { setForm({ ...emptyForm }); setModal('new') }} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> New Payment</button>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-green-100 dark:bg-green-900/30">
            <DollarSign className="h-5 w-5 text-green-600 dark:text-green-400" />
          </div>
          <div><p className="text-xs text-gray-500">Total Paid Amount</p><p className="text-xl font-bold text-gray-900 dark:text-white">{totalPaid.toFixed(2)}</p></div>
        </div>
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-blue-100 dark:bg-blue-900/30">
            <Hash className="h-5 w-5 text-blue-600 dark:text-blue-400" />
          </div>
          <div><p className="text-xs text-gray-500">Total Payment Count</p><p className="text-xl font-bold text-gray-900 dark:text-white">{data?.total ?? payments.length}</p></div>
        </div>
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-amber-100 dark:bg-amber-900/30">
            <Calendar className="h-5 w-5 text-amber-600 dark:text-amber-400" />
          </div>
          <div><p className="text-xs text-gray-500">Latest Payment Date</p><p className="text-xl font-bold text-gray-900 dark:text-white">{latestDate}</p></div>
        </div>
      </div>

      <div className="card p-3 flex flex-wrap gap-3 items-end">
        <div>
          <label className="label text-xs">Supplier</label>
          <select value={filterSupplier} onChange={(e) => { setFilterSupplier(e.target.value); setPage(1) }} className="input text-sm">
            <option value="">All Suppliers</option>
            {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </div>
        <div>
          <label className="label text-xs">Date From</label>
          <input value={dateFrom} type="date" onChange={(e) => { setDateFrom(e.target.value); setPage(1) }} className="input text-sm" />
        </div>
        <div>
          <label className="label text-xs">Date To</label>
          <input value={dateTo} type="date" onChange={(e) => { setDateTo(e.target.value); setPage(1) }} className="input text-sm" />
        </div>
        <button onClick={clearFilters} className="btn btn-secondary text-sm">Clear</button>
      </div>

      <div className="card overflow-hidden">
        {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['Payment #', 'Supplier', 'Phone', 'Amount', 'Method', 'Date', 'Notes', ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {payments.length === 0 ? <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">No payments found</td></tr>
                  : payments.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-mono text-xs text-primary-600">{p.payment_number ?? `#${p.id}`}</td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.supplier?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{p.supplier?.phone ?? '—'}</td>
                      <td className="px-4 py-3 font-semibold text-green-600">{parseFloat(p.amount).toFixed(2)}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', methodClass[p.payment_method] ?? 'badge-gray')}>{p.payment_method}</span></td>
                      <td className="px-4 py-3 text-gray-500">{p.payment_date?.slice(0, 10)}</td>
                      <td className="px-4 py-3 text-gray-400 max-w-40 truncate">{p.notes ?? '—'}</td>
                      <td className="px-4 py-3">
                        <button onClick={() => openPrint(p)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded" title="Print"><Printer className="h-4 w-4" /></button>
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        )}
        {(data?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">Page {page} · {data?.total} total</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Prev</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={payments.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Next</button>
            </div>
          </div>
        )}
      </div>

      <Modal open={modal === 'new'} onClose={() => setModal(null)} title="New Supplier Payment" size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={handleSubmit} disabled={createPayment.isPending} className="btn btn-primary">{createPayment.isPending ? 'Saving…' : 'Record Payment'}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="label">Supplier *</label>
            <select value={form.supplier_id} onChange={(e) => setForm((p) => ({ ...p, supplier_id: e.target.value }))} className="input w-full" required>
              <option value="">— Select supplier —</option>
              {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
            {selectedSupplier && (
              <p className="mt-1 text-xs text-gray-500">Current balance: <span className={clsx('font-semibold', parseFloat(selectedSupplier.outstanding_balance ?? '0') > 0 ? 'text-red-600' : 'text-green-600')}>{parseFloat(selectedSupplier.outstanding_balance ?? '0').toFixed(2)}</span></p>
            )}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">Amount *</label>
              <input value={form.amount} type="number" min="0.01" step="0.01" onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))} className="input w-full" required />
            </div>
            <div>
              <label className="label">Payment Method</label>
              <select value={form.payment_method} onChange={(e) => setForm((p) => ({ ...p, payment_method: e.target.value }))} className="input w-full">
                {['cash', 'card', 'transfer', 'check'].map((m) => <option key={m} value={m} className="capitalize">{m}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="label">Payment Date *</label>
            <input value={form.payment_date} type="date" onChange={(e) => setForm((p) => ({ ...p, payment_date: e.target.value }))} className="input w-full" required />
          </div>
          <div>
            <label className="label">Notes</label>
            <textarea value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full h-20 resize-none" placeholder="Optional notes" />
          </div>
        </form>
      </Modal>

      <Modal open={modal === 'print'} onClose={() => setModal(null)} title="Payment Receipt" size="sm"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Close</button><button onClick={() => window.print()} className="btn btn-primary flex items-center gap-2"><Printer className="h-4 w-4" />Print</button></>}>
        {printRow && (
          <div className="space-y-3 text-sm">
            <div className="flex justify-between"><span className="text-gray-500">Payment #</span><span className="font-mono font-semibold">{printRow.payment_number ?? `#${printRow.id}`}</span></div>
            <div className="flex justify-between"><span className="text-gray-500">Supplier</span><span className="font-medium">{printRow.supplier?.name ?? '—'}</span></div>
            {printRow.supplier?.phone && <div className="flex justify-between"><span className="text-gray-500">Phone</span><span>{printRow.supplier.phone}</span></div>}
            <div className="flex justify-between"><span className="text-gray-500">Amount</span><span className="font-bold text-green-600">{parseFloat(printRow.amount).toFixed(2)}</span></div>
            <div className="flex justify-between"><span className="text-gray-500">Method</span><span className="capitalize">{printRow.payment_method}</span></div>
            <div className="flex justify-between"><span className="text-gray-500">Date</span><span>{printRow.payment_date?.slice(0, 10)}</span></div>
            {printRow.notes && <div className="flex justify-between"><span className="text-gray-500">Notes</span><span className="max-w-40 text-right">{printRow.notes}</span></div>}
          </div>
        )}
      </Modal>
    </div>
  )
}
