// Products page stub — extend with full CRUD
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import type { PaginatedResponse, Product } from '@/types'
import { Package } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

export default function ProductsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['products'],
    queryFn: () => apiGet<PaginatedResponse<Product>>('/api/products', { per_page: 20 }),
  })
  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Products</h1>
        <button className="btn-primary gap-2 flex items-center"><Package className="h-4 w-4" /> Add Product</button>
      </div>
      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-700">
            <tr>{['SKU','Name','Category','Price','Stock','Status'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {data?.data.map(p => (
              <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td className="px-4 py-3 font-mono text-xs text-gray-500">{p.sku}</td>
                <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                <td className="px-4 py-3 text-gray-500">{p.category?.name ?? '—'}</td>
                <td className="px-4 py-3 font-semibold text-primary-600">${p.price.toFixed(2)}</td>
                <td className="px-4 py-3"><span className={p.stock < p.low_stock_threshold ? 'text-red-500 font-semibold' : 'text-gray-700 dark:text-gray-300'}>{p.stock}</span></td>
                <td className="px-4 py-3"><span className={p.is_active ? 'badge-success' : 'badge-gray'}>{p.is_active ? 'Active' : 'Inactive'}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
