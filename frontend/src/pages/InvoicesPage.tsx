import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { Search, FileText } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

interface Invoice {
  id: number
  invoice_number: string
  customer_name: string | null
  final_total: string
  payment_method: string
  status: string
  created_at: string
  user?: { username: string }
}
interface InvoicesResponse { success: boolean; invoices: Invoice[]; total: number; pages: number; page: number }

const statusClass: Record<string, string> = { completed: 'badge-success', cancelled: 'badge-danger', refunded: 'badge-warning', partial_refund: 'badge-warning' }

export default function InvoicesPage() {
  const [search, setSearch] = useState('')
  const [dateFrom, setDateFrom] = useState(() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10))
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', search, dateFrom, dateTo, page],
    queryFn: () => apiGet<InvoicesResponse>('/invoices', { search: search || undefined, date_from: dateFrom, date_to: dateTo, per_page: 20, page }),
    staleTime: 30_000,
  })

  const invoices = data?.invoices ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><FileText className="h-6 w-6 text-primary-500" /> Invoices</h1>
        <span className="text-sm text-gray-500">{data?.total ?? 0} total</span>
      </div>

      <div className="card p-4 flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-48">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input type="text" placeholder="Search invoice # or customer…" value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} className="input pl-9" />
        </div>
        <div className="flex items-center gap-2">
          <span className="text-sm text-gray-500">From</span>
          <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1) }} className="input w-36" />
        </div>
        <div className="flex items-center gap-2">
          <span className="text-sm text-gray-500">To</span>
          <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1) }} className="input w-36" />
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Invoice #', 'Customer', 'Cashier', 'Method', 'Total', 'Status', 'Date'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {invoices.length === 0 ? (
                <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">No invoices found for this period.</td></tr>
              ) : invoices.map(inv => (
                <tr key={inv.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-mono text-xs font-semibold text-primary-600">{inv.invoice_number}</td>
                  <td className="px-4 py-3 text-gray-900 dark:text-white">{inv.customer_name ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500 text-xs">{inv.user?.username ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500 capitalize">{inv.payment_method}</td>
                  <td className="px-4 py-3 font-semibold">{parseFloat(inv.final_total).toFixed(2)}</td>
                  <td className="px-4 py-3"><span className={`badge ${statusClass[inv.status] ?? 'badge-gray'} capitalize`}>{inv.status.replace('_', ' ')}</span></td>
                  <td className="px-4 py-3 text-gray-400 text-xs">{inv.created_at?.slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {(data?.pages ?? 1) > 1 && (
            <div className="flex items-center justify-between border-t border-gray-100 dark:border-gray-700 px-4 py-3">
              <span className="text-sm text-gray-500">Page {data?.page} of {data?.pages}</span>
              <div className="flex gap-2">
                <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="btn-secondary py-1 px-3 text-xs disabled:opacity-40">Prev</button>
                <button onClick={() => setPage(p => p + 1)} disabled={page === (data?.pages ?? 1)} className="btn-secondary py-1 px-3 text-xs disabled:opacity-40">Next</button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
