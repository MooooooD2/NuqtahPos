import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import type { DashboardStats } from '@/types'
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend,
} from 'recharts'
import { ShoppingCart, TrendingUp, Users, AlertTriangle } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

const COLORS = ['#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b']

export default function DashboardPage() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => apiGet<DashboardStats>('/api/dashboard-data'),
    refetchInterval: 60_000,
  })

  if (isLoading) return (
    <div className="flex h-64 items-center justify-center">
      <LoadingSpinner size="lg" />
    </div>
  )

  const s = stats ?? {
    today_sales: 0, today_invoices: 0, today_customers: 0,
    low_stock_count: 0, pending_orders: 0,
    top_products: [], sales_trend: [], payment_breakdown: [],
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
        <p className="text-sm text-gray-500 dark:text-gray-400">Welcome back — here's what's happening today</p>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {[
          { label: "Today's Sales",    value: `$${s.today_sales.toLocaleString()}`, icon: TrendingUp,   color: 'blue' },
          { label: 'Invoices',         value: s.today_invoices,                      icon: ShoppingCart, color: 'green' },
          { label: 'New Customers',    value: s.today_customers,                     icon: Users,        color: 'purple' },
          { label: 'Low Stock Items',  value: s.low_stock_count,                     icon: AlertTriangle,color: 'red' },
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
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Sales trend */}
        <div className="card p-5 lg:col-span-2">
          <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Sales Trend (30 days)</h3>
          <ResponsiveContainer width="100%" height={220}>
            <AreaChart data={s.sales_trend}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} />
              <Tooltip />
              <Area type="monotone" dataKey="amount" stroke="#0ea5e9" fill="#e0f2fe" />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Payment methods */}
        <div className="card p-5">
          <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Payment Methods</h3>
          <ResponsiveContainer width="100%" height={220}>
            <PieChart>
              <Pie data={s.payment_breakdown} dataKey="amount" nameKey="method" cx="50%" cy="50%" outerRadius={80}>
                {s.payment_breakdown.map((_, i) => (
                  <Cell key={i} fill={COLORS[i % COLORS.length]} />
                ))}
              </Pie>
              <Legend />
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Top products */}
      <div className="card p-5">
        <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Top Selling Products</h3>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <th className="pb-2 text-left text-xs font-semibold uppercase text-gray-500">Product</th>
                <th className="pb-2 text-right text-xs font-semibold uppercase text-gray-500">Qty Sold</th>
                <th className="pb-2 text-right text-xs font-semibold uppercase text-gray-500">Revenue</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
              {s.top_products.slice(0, 8).map((row, i) => (
                <tr key={i}>
                  <td className="py-2.5 text-gray-900 dark:text-white">{row.product?.name ?? '—'}</td>
                  <td className="py-2.5 text-right text-gray-600 dark:text-gray-400">{row.quantity}</td>
                  <td className="py-2.5 text-right font-medium text-gray-900 dark:text-white">${row.revenue.toLocaleString()}</td>
                </tr>
              ))}
              {s.top_products.length === 0 && (
                <tr><td colSpan={3} className="py-8 text-center text-gray-400">No data yet</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
