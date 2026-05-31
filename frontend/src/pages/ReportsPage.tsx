import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { BarChart2, TrendingUp, Package, Truck } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

interface DashboardData {
  today_sales_count: number; today_sales_total: number; yesterday_sales_total: number
  growth_percentage: number; low_stock_count: number; out_of_stock_count: number
  total_products: number; total_suppliers: number; total_revenue: number
  recent_invoices: Array<{ invoice_number: string; customer_name: string | null; final_total: string; created_at: string }>
  top_products: Array<{ product: { name: string } | null; quantity: number; revenue: number }>
}

export default function ReportsPage() {
  const { data, isLoading } = useQuery({ queryKey: ['dashboard'], queryFn: () => apiGet<DashboardData>('/dashboard-data'), staleTime: 60_000 })

  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>

  const d = data ?? { today_sales_count: 0, today_sales_total: 0, growth_percentage: 0, total_products: 0, total_suppliers: 0, total_revenue: 0, recent_invoices: [], top_products: [] }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><BarChart2 className="h-6 w-6 text-primary-500" /> Reports</h1>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: "Today's Revenue", value: `${(d.today_sales_total ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`, icon: TrendingUp, color: 'green' },
          { label: "Today's Sales", value: d.today_sales_count ?? 0, icon: BarChart2, color: 'blue' },
          { label: 'Total Products', value: d.total_products ?? 0, icon: Package, color: 'purple' },
          { label: 'Suppliers', value: d.total_suppliers ?? 0, icon: Truck, color: 'yellow' },
        ].map(card => (
          <div key={card.label} className="card p-5 flex items-center gap-4">
            <div className={`h-10 w-10 flex items-center justify-center rounded-xl bg-${card.color}-100 dark:bg-${card.color}-900/30`}>
              <card.icon className={`h-5 w-5 text-${card.color}-600 dark:text-${card.color}-400`} />
            </div>
            <div>
              <p className="text-xs text-gray-500">{card.label}</p>
              <p className="text-xl font-bold text-gray-900 dark:text-white">{card.value}</p>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card p-5">
          <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Top Selling Products</h3>
          <table className="w-full text-sm">
            <thead><tr>{['Product', 'Qty', 'Revenue'].map(h => <th key={h} className="pb-2 text-left text-xs font-semibold uppercase text-gray-400">{h}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
              {(d.top_products ?? []).slice(0, 8).map((row, i) => (
                <tr key={i}>
                  <td className="py-2 text-gray-900 dark:text-white">{(row as { name?: string; product?: { name: string } }).name ?? (row as { product?: { name: string } }).product?.name ?? '—'}</td>
                  <td className="py-2 text-right text-gray-500">{(row as { total_quantity?: number | string; quantity?: number }).total_quantity ?? (row as { quantity?: number }).quantity ?? 0}</td>
                  <td className="py-2 text-right font-medium text-primary-600">{parseFloat(String((row as { total_sales?: number | string; revenue?: number }).total_sales ?? (row as { revenue?: number }).revenue ?? 0)).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                </tr>
              ))}
              {(d.top_products ?? []).length === 0 && <tr><td colSpan={3} className="py-6 text-center text-gray-400">No sales yet</td></tr>}
            </tbody>
          </table>
        </div>

        <div className="card p-5">
          <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Recent Invoices</h3>
          <table className="w-full text-sm">
            <thead><tr>{['Invoice', 'Customer', 'Total', 'Date'].map(h => <th key={h} className="pb-2 text-left text-xs font-semibold uppercase text-gray-400">{h}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
              {(d.recent_invoices ?? []).slice(0, 8).map((inv, i) => (
                <tr key={i}>
                  <td className="py-2 font-mono text-xs text-primary-600">{inv.invoice_number}</td>
                  <td className="py-2 text-gray-600 dark:text-gray-400">{inv.customer_name ?? '—'}</td>
                  <td className="py-2 font-medium">{parseFloat(inv.final_total).toFixed(2)}</td>
                  <td className="py-2 text-gray-400 text-xs">{inv.created_at?.slice(0, 10)}</td>
                </tr>
              ))}
              {(d.recent_invoices ?? []).length === 0 && <tr><td colSpan={4} className="py-6 text-center text-gray-400">No invoices yet</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
