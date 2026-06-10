import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { TrendingUp, Download, BarChart2, DollarSign, ShoppingCart, Percent } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface ProductTotals { total_revenue: string; total_cost: string; gross_profit: string; profit_margin: string; products_count: number }
interface DailyTotals { revenue: string; cost: string; profit: string }
interface ProductRow { product_name: string; category: string; total_qty: number; total_revenue: string; total_cost: string; gross_profit: string; profit_margin: string }
interface DailyRow { date: string; invoices_count: number; revenue: string; cost: string; profit: string; margin: string }

const today = new Date().toISOString().slice(0, 10)
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)

export default function ProfitReportsPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission()
  const [startDate, setStartDate] = useState(firstOfMonth)
  const [endDate, setEndDate] = useState(today)
  const [category, setCategory] = useState('')
  const [viewType, setViewType] = useState<'product' | 'daily'>('product')

  const productQuery = useQuery({
    queryKey: ['profit-product', startDate, endDate, category],
    queryFn: () => apiPost<{ success: boolean; products: ProductRow[]; totals: ProductTotals }>('/reports/profit-by-product', { start_date: startDate, end_date: endDate, category: category || undefined }),
    enabled: false,
    staleTime: 60_000,
  })

  const dailyQuery = useQuery({
    queryKey: ['profit-daily', startDate, endDate],
    queryFn: () => apiPost<{ success: boolean; daily: DailyRow[]; totals: DailyTotals }>('/reports/profit-daily', { start_date: startDate, end_date: endDate }),
    enabled: false,
    staleTime: 60_000,
  })

  const activeQuery = viewType === 'product' ? productQuery : dailyQuery
  const productRows = productQuery.data?.products ?? []
  const dailyRows = dailyQuery.data?.daily ?? []

  const displayTotals = viewType === 'product'
    ? productQuery.data?.totals
      ? { revenue: parseFloat(productQuery.data.totals.total_revenue), cost: parseFloat(productQuery.data.totals.total_cost), profit: parseFloat(productQuery.data.totals.gross_profit), margin: parseFloat(productQuery.data.totals.profit_margin) as number | null }
      : null
    : dailyQuery.data?.totals
      ? { revenue: parseFloat(dailyQuery.data.totals.revenue), cost: parseFloat(dailyQuery.data.totals.cost), profit: parseFloat(dailyQuery.data.totals.profit), margin: null as number | null }
      : null

  const handleLoad = () => {
    if (!startDate || !endDate) { toast.error('Select start and end date'); return }
    if (viewType === 'product') productQuery.refetch()
    else dailyQuery.refetch()
  }

  const handleExportCSV = () => {
    const rows = viewType === 'product' ? productRows : dailyRows
    if (rows.length === 0) { toast.error('No data to export'); return }
    const headers = Object.keys(rows[0])
    const csv = [headers.join(','), ...(rows as unknown as Record<string, unknown>[]).map((r) => headers.map((h) => JSON.stringify(r[h] ?? '')).join(','))].join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = `profit-${viewType}-report.csv`; a.click()
    URL.revokeObjectURL(url)
  }

  const marginColor = (v: number) => {
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
          <TrendingUp className="h-6 w-6 text-primary-500" /> {t('profit_reports')}
        </h1>
        <button onClick={handleExportCSV} className="btn btn-secondary flex items-center gap-2 text-sm">
          <Download className="h-4 w-4" /> Export CSV
        </button>
      </div>

      <div className="card p-4">
        <div className="flex flex-wrap gap-4 items-end">
          <div>
            <label className="label">{t('start_date')}</label>
            <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="input" />
          </div>
          <div>
            <label className="label">{t('end_date')}</label>
            <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="input" />
          </div>
          <div>
            <label className="label">{t('by_product')}</label>
            <input value={category} onChange={(e) => setCategory(e.target.value)} placeholder={t('all_categories')} className="input" />
          </div>
          <div>
            <label className="label">{t('profit_report')}</label>
            <select value={viewType} onChange={(e) => setViewType(e.target.value as 'product' | 'daily')} className="input">
              <option value="product">{t('by_product')}</option>
              <option value="daily">{t('daily')}</option>
            </select>
          </div>
          <button onClick={handleLoad} disabled={activeQuery.isFetching} className="btn btn-primary flex items-center gap-2">
            {activeQuery.isFetching ? <LoadingSpinner size="sm" /> : <BarChart2 className="h-4 w-4" />}
            {t('generate')}
          </button>
        </div>
      </div>

      {displayTotals && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="card p-4 border-l-4 border-blue-500">
            <div className="flex items-center gap-2 mb-1"><ShoppingCart className="h-4 w-4 text-blue-500" /><p className="text-xs text-gray-500 uppercase font-semibold">{t('total_revenue')}</p></div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">{displayTotals.revenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
          </div>
          <div className="card p-4 border-l-4 border-red-500">
            <div className="flex items-center gap-2 mb-1"><DollarSign className="h-4 w-4 text-red-500" /><p className="text-xs text-gray-500 uppercase font-semibold">{t('total_cost')}</p></div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">{displayTotals.cost.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
          </div>
          <div className="card p-4 border-l-4 border-green-500">
            <div className="flex items-center gap-2 mb-1"><TrendingUp className="h-4 w-4 text-green-500" /><p className="text-xs text-gray-500 uppercase font-semibold">{t('total_profit')}</p></div>
            <p className="text-2xl font-bold text-green-600 dark:text-green-400">{displayTotals.profit.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
          </div>
          <div className="card p-4 border-l-4 border-orange-500">
            <div className="flex items-center gap-2 mb-1"><Percent className="h-4 w-4 text-orange-500" /><p className="text-xs text-gray-500 uppercase font-semibold">{t('profit_margin')}</p></div>
            {displayTotals.margin !== null
              ? <p className={clsx('text-2xl font-bold', marginColor(displayTotals.margin))}>{displayTotals.margin.toFixed(2)}%</p>
              : <p className="text-2xl font-bold text-gray-400">—</p>}
          </div>
        </div>
      )}

      <div className="card overflow-hidden">
        {activeQuery.isFetching ? (
          <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : !activeQuery.data ? (
          <div className="text-center py-16 text-gray-400">
            <BarChart2 className="h-10 w-10 mx-auto mb-2 opacity-30" />
            <p>{t('no_data')}</p>
          </div>
        ) : viewType === 'product' ? (
          <>
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[650px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{['#', t('product'), 'Category', 'Qty', t('total_revenue'), t('total_cost'), t('gross_profit'), t('profit_margin')].map((h, i) => <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {productRows.length === 0 ? (
                    <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : productRows.map((row, i) => (
                    <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 text-gray-400 text-xs">{i + 1}</td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.product_name}</td>
                      <td className="px-4 py-3"><span className="badge badge-info">{row.category}</span></td>
                      <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{row.total_qty.toLocaleString()}</td>
                      <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{parseFloat(row.total_revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      <td className="px-4 py-3 text-red-600 dark:text-red-400">{parseFloat(row.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      <td className="px-4 py-3 font-bold text-green-600 dark:text-green-400">{parseFloat(row.gross_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      <td className={clsx('px-4 py-3 font-semibold', marginColor(parseFloat(row.profit_margin)))}>{parseFloat(row.profit_margin).toFixed(2)}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {productRows.length === 0 ? (
                <div className="px-4 py-12 text-center text-gray-400">{t('no_data')}</div>
              ) : productRows.map((row, i) => (
                <div key={i} className="p-4 space-y-1.5">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="font-medium text-gray-900 dark:text-white text-sm">{row.product_name}</p>
                      <span className="badge badge-info text-xs mt-0.5">{row.category}</span>
                    </div>
                    <span className={clsx('font-semibold text-sm', marginColor(parseFloat(row.profit_margin)))}>{parseFloat(row.profit_margin).toFixed(2)}%</span>
                  </div>
                  <div className="grid grid-cols-3 gap-1 text-xs">
                    <div><span className="text-gray-500">{t('total_revenue')}</span><p className="font-medium text-gray-700 dark:text-gray-300">{parseFloat(row.total_revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                    <div><span className="text-gray-500">{t('total_cost')}</span><p className="font-medium text-red-600 dark:text-red-400">{parseFloat(row.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                    <div><span className="text-gray-500">{t('gross_profit')}</span><p className="font-bold text-green-600 dark:text-green-400">{parseFloat(row.gross_profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                  </div>
                  <p className="text-xs text-gray-500">Qty: {row.total_qty.toLocaleString()}</p>
                </div>
              ))}
            </div>
          </>
        ) : (
          <>
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[650px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{[t('date'), 'Invoices', t('total_revenue'), t('total_cost'), t('net_profit'), t('profit_margin')].map((h, i) => <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {dailyRows.length === 0 ? (
                    <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : dailyRows.map((row, i) => (
                    <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.date}</td>
                      <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{row.invoices_count.toLocaleString()}</td>
                      <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{parseFloat(row.revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      <td className="px-4 py-3 text-red-600 dark:text-red-400">{parseFloat(row.cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      <td className="px-4 py-3 font-bold text-green-600 dark:text-green-400">{parseFloat(row.profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      <td className={clsx('px-4 py-3 font-semibold', marginColor(parseFloat(row.margin)))}>{parseFloat(row.margin).toFixed(2)}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {dailyRows.length === 0 ? (
                <div className="px-4 py-12 text-center text-gray-400">{t('no_data')}</div>
              ) : dailyRows.map((row, i) => (
                <div key={i} className="p-4 space-y-1.5">
                  <div className="flex items-center justify-between gap-2">
                    <p className="font-medium text-gray-900 dark:text-white text-sm">{row.date}</p>
                    <span className={clsx('font-semibold text-sm', marginColor(parseFloat(row.margin)))}>{parseFloat(row.margin).toFixed(2)}%</span>
                  </div>
                  <div className="grid grid-cols-3 gap-1 text-xs">
                    <div><span className="text-gray-500">{t('total_revenue')}</span><p className="font-medium text-gray-700 dark:text-gray-300">{parseFloat(row.revenue).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                    <div><span className="text-gray-500">{t('total_cost')}</span><p className="font-medium text-red-600 dark:text-red-400">{parseFloat(row.cost).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                    <div><span className="text-gray-500">{t('net_profit')}</span><p className="font-bold text-green-600 dark:text-green-400">{parseFloat(row.profit).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                  </div>
                  <p className="text-xs text-gray-500">{isAr ? 'الفواتير' : 'Invoices'}: {row.invoices_count.toLocaleString()}</p>
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  )
}
