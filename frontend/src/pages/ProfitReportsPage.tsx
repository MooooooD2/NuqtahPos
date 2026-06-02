import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { TrendingUp, Download, BarChart2, DollarSign, ShoppingCart, Percent } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Totals { total_revenue: string; total_cost: string; total_profit: string; profit_margin: string }
interface ProductRow { name: string; category: string; quantity: number; revenue: string; cost: string; profit: string; margin: string }
interface DailyRow { date: string; invoice_count: number; revenue: string; cost: string; profit: string; margin: string }

const today = new Date().toISOString().slice(0, 10)
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)

export default function ProfitReportsPage() {
  const { hasPermission } = usePermission()
  const [startDate, setStartDate] = useState(firstOfMonth)
  const [endDate, setEndDate] = useState(today)
  const [category, setCategory] = useState('')
  const [viewType, setViewType] = useState<'product' | 'daily'>('product')

  const productQuery = useQuery({
    queryKey: ['profit-product', startDate, endDate, category],
    queryFn: () => apiPost<{ success: boolean; rows: ProductRow[]; totals: Totals }>('/reports/profit-by-product', { start_date: startDate, end_date: endDate, category: category || undefined }),
    enabled: false,
    staleTime: 60_000,
  })

  const dailyQuery = useQuery({
    queryKey: ['profit-daily', startDate, endDate],
    queryFn: () => apiPost<{ success: boolean; rows: DailyRow[]; totals: Totals }>('/reports/profit-daily', { start_date: startDate, end_date: endDate }),
    enabled: false,
    staleTime: 60_000,
  })

  const activeQuery = viewType === 'product' ? productQuery : dailyQuery
  const totals = activeQuery.data?.totals
  const productRows = productQuery.data?.rows ?? []
  const dailyRows = dailyQuery.data?.rows ?? []

  const handleLoad = () => {
    if (!startDate || !endDate) { toast.error('Select start and end date'); return }
    if (viewType === 'product') productQuery.refetch()
    else dailyQuery.refetch()
  }

  const handleExportCSV = () => {
    const rows = viewType === 'product' ? productRows : dailyRows
    if (rows.length === 0) { toast.error('No data to export'); return }
    const headers = Object.keys(rows[0])
    const csv = [headers.join(','), ...rows.map((r: Record<string, unknown>) => headers.map((h) => JSON.stringify(r[h] ?? '')).join(','))].join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = `profit-${viewType}-report.csv`; a.click()
    URL.revokeObjectURL(url)
  }

  const marginColor = (m: string) => {
    const v = parseFloat(m)
    if (v >= 30) return 'text-green-600 dark:text-green-400'
    if (v >= 10) return 'text-yellow-600 dark:text-yellow-400'
    return 'text-red-600 dark:text-red-400'
  }

  if (!hasPermission('view_reports')) {
    return (
      <div className="card p-8 text-center text-gray-400">
        <BarChart2 className="h-12 w-12 mx-auto mb-3 opacity-40" />
        <p>Access to reports requires view_reports permission</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <TrendingUp className="h-6 w-6 text-primary-500" /> Profit Reports
        </h1>
        <button onClick={handleExportCSV} className="btn btn-secondary flex items-center gap-2 text-sm">
          <Download className="h-4 w-4" /> Export CSV
        </button>
      </div>

      <div className="card p-4">
        <div className="flex flex-wrap gap-4 items-end">
          <div>
            <label className="label">Start Date</label>
            <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="input" />
          </div>
          <div>
            <label className="label">End Date</label>
            <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="input" />
          </div>
          <div>
            <label className="label">Category</label>
            <input value={category} onChange={(e) => setCategory(e.target.value)} placeholder="All categories" className="input" />
          </div>
          <div>
            <label className="label">View Type</label>
            <select value={viewType} onChange={(e) => setViewType(e.target.value as 'product' | 'daily')} className="input">
              <option value="product">By Product</option>
              <option value="daily">Daily</option>
            </select>
          </div>
          <button onClick={handleLoad} disabled={activeQuery.isFetching} className="btn btn-primary flex items-center gap-2">
            {activeQuery.isFetching ? <LoadingSpinner size="sm" /> : <BarChart2 className="h-4 w-4" />}
            Load Report
          </button>
        </div>
      </div>

      {totals && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="card p-4 border-l-4 border-blue-500">
            <div className="flex items-center gap-2 mb-1"><ShoppingCart className="h-4 w-4 text-blue-500" /><p className="text-xs text-gray-500 uppercase font-semibold">Total Revenue</p></div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">{parseFloat(totals.total_revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
          </div>
          <div className="card p-4 border-l-4 border-red-500">
            <div className="flex items-center gap-2 mb-1"><DollarSign className="h-4 w-4 text-red-500" /><p className="text-xs text-gray-500 uppercase font-semibold">Total Cost</p></div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">{parseFloat(totals.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
          </div>
          <div className="card p-4 border-l-4 border-green-500">
            <div className="flex items-center gap-2 mb-1"><TrendingUp className="h-4 w-4 text-green-500" /><p className="text-xs text-gray-500 uppercase font-semibold">Total Profit</p></div>
            <p className="text-2xl font-bold text-green-600 dark:text-green-400">{parseFloat(totals.total_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
          </div>
          <div className="card p-4 border-l-4 border-orange-500">
            <div className="flex items-center gap-2 mb-1"><Percent className="h-4 w-4 text-orange-500" /><p className="text-xs text-gray-500 uppercase font-semibold">Profit Margin</p></div>
            <p className={clsx('text-2xl font-bold', marginColor(totals.profit_margin))}>{parseFloat(totals.profit_margin).toFixed(2)}%</p>
          </div>
        </div>
      )}

      <div className="card overflow-hidden">
        {activeQuery.isFetching ? (
          <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : !activeQuery.data ? (
          <div className="text-center py-16 text-gray-400">
            <BarChart2 className="h-10 w-10 mx-auto mb-2 opacity-30" />
            <p>Set filters and click Load Report</p>
          </div>
        ) : viewType === 'product' ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['#', 'Name', 'Category', 'Qty', 'Revenue', 'Cost', 'Profit', 'Margin'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {productRows.length === 0 ? (
                  <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">No data for the selected period</td></tr>
                ) : productRows.map((row, i) => (
                  <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 text-gray-400 text-xs">{i + 1}</td>
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.name}</td>
                    <td className="px-4 py-3"><span className="badge badge-info">{row.category}</span></td>
                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{row.quantity.toLocaleString()}</td>
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{parseFloat(row.revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className="px-4 py-3 text-red-600 dark:text-red-400">{parseFloat(row.cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className="px-4 py-3 font-bold text-green-600 dark:text-green-400">{parseFloat(row.profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className={clsx('px-4 py-3 font-semibold', marginColor(row.margin))}>{parseFloat(row.margin).toFixed(2)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['Date', 'Invoices', 'Revenue', 'Cost', 'Profit', 'Margin %'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {dailyRows.length === 0 ? (
                  <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">No data for the selected period</td></tr>
                ) : dailyRows.map((row, i) => (
                  <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.date}</td>
                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{row.invoice_count.toLocaleString()}</td>
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{parseFloat(row.revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className="px-4 py-3 text-red-600 dark:text-red-400">{parseFloat(row.cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className="px-4 py-3 font-bold text-green-600 dark:text-green-400">{parseFloat(row.profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className={clsx('px-4 py-3 font-semibold', marginColor(row.margin))}>{parseFloat(row.margin).toFixed(2)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
