import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend,
} from 'recharts'
import { ShoppingCart, TrendingUp, Users, AlertTriangle } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import DesktopDownloadBanner from '@/components/common/DesktopDownloadBanner'
import { useTranslation } from 'react-i18next'

const COLORS = ['#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b']

interface TopProduct { name: string; total_quantity: string | number; total_sales: string | number }
interface RecentInvoice { invoice_number: string; final_total: string | number; payment_method: string; cashier_name: string; created_at: string }
interface DashData {
  today_sales_count: number; today_sales_total: string | number; total_revenue: number
  low_stock_count: number; out_of_stock_count: number; total_products: number; total_suppliers: number
  top_products: TopProduct[]; recent_invoices: RecentInvoice[]
  sales_trend?: Array<{ date: string; amount: number }>
  payment_breakdown?: Array<{ method: string; amount: number }>
}

export default function DashboardPage() {
  const { t } = useTranslation('pos')
  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => apiGet<DashData>('/dashboard-data'),
    refetchInterval: 60_000,
  })

  if (isLoading || !stats) return (
    <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
  )

  const todaySales = parseFloat(String(stats.today_sales_total ?? 0))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{t('dashboard')}</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">{t('welcome')}</p>
      </div>

      <DesktopDownloadBanner />

      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {[
          { label: t('today_sales'),    value: `${todaySales.toLocaleString(undefined, { minimumFractionDigits: 2 })}`, icon: TrendingUp,    color: 'blue' },
          { label: t('recent_invoices'), value: stats.today_sales_count ?? 0,                                           icon: ShoppingCart,  color: 'green' },
          { label: t('total_products'), value: stats.total_products ?? 0,                                               icon: Users,         color: 'purple' },
          { label: t('low_stock'),      value: stats.low_stock_count ?? 0,                                              icon: AlertTriangle, color: 'red' },
        ].map((card) => (
          <div key={card.label} className="card p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">{card.label}</p>
                <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{card.value}</p>
              </div>
              <div className={`h-12 w-12 rounded-xl flex items-center justify-center bg-${card.color}-100 dark:bg-${card.color}-900/30`}>
                <card.icon className={`h-6 w-6 text-${card.color}-600 dark:text-${card.color}-400`} />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Charts row */}
      {((stats.sales_trend?.length ?? 0) > 0 || (stats.payment_breakdown?.length ?? 0) > 0) && (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="card p-5 lg:col-span-2">
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{t('sales_trend_30')}</h3>
            <ResponsiveContainer width="100%" height={220}>
              <AreaChart data={stats.sales_trend ?? []}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                <YAxis tick={{ fontSize: 11 }} />
                <Tooltip />
                <Area type="monotone" dataKey="amount" stroke="#0ea5e9" fill="#e0f2fe" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
          <div className="card p-5">
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{t('payment_method')}</h3>
            <ResponsiveContainer width="100%" height={220}>
              <PieChart>
                <Pie data={stats.payment_breakdown ?? []} dataKey="amount" nameKey="method" cx="50%" cy="50%" outerRadius={80}>
                  {(stats.payment_breakdown ?? []).map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Legend />
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>
      )}

      {/* Bottom row */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Top products */}
        <div className="card p-5">
          <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{t('top_products')}</h3>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <th className="pb-2 text-left text-xs font-semibold uppercase text-gray-500">{t('product')}</th>
                <th className="pb-2 text-right text-xs font-semibold uppercase text-gray-500">{t('qty')}</th>
                <th className="pb-2 text-right text-xs font-semibold uppercase text-gray-500">{t('sales')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
              {(stats.top_products ?? []).slice(0, 8).map((row, i) => (
                <tr key={i}>
                  <td className="py-2.5 text-gray-900 dark:text-white">{row.name ?? '—'}</td>
                  <td className="py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_quantity ?? 0}</td>
                  <td className="py-2.5 text-right font-medium text-primary-600">
                    {parseFloat(String(row.total_sales ?? 0)).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </td>
                </tr>
              ))}
              {(stats.top_products ?? []).length === 0 && (
                <tr><td colSpan={3} className="py-8 text-center text-gray-400">{t('no_sales_today')}</td></tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Recent invoices */}
        <div className="card p-5">
          <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{t('recent_invoices')}</h3>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <th className="pb-2 text-left text-xs font-semibold uppercase text-gray-500">{t('invoice_number')}</th>
                <th className="pb-2 text-left text-xs font-semibold uppercase text-gray-500">{t('cashier_col')}</th>
                <th className="pb-2 text-right text-xs font-semibold uppercase text-gray-500">{t('total')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
              {(stats.recent_invoices ?? []).slice(0, 8).map((inv, i) => (
                <tr key={i}>
                  <td className="py-2.5 font-mono text-xs text-primary-600">{inv.invoice_number}</td>
                  <td className="py-2.5 text-gray-500 text-xs">{inv.cashier_name ?? '—'}</td>
                  <td className="py-2.5 text-right font-medium text-gray-900 dark:text-white">
                    {parseFloat(String(inv.final_total ?? 0)).toFixed(2)}
                  </td>
                </tr>
              ))}
              {(stats.recent_invoices ?? []).length === 0 && (
                <tr><td colSpan={3} className="py-8 text-center text-gray-400">{t('no_invoices_yet')}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
