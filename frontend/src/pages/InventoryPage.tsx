import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { Package, AlertTriangle, XCircle } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

interface StockItem { id: number; name: string; quantity: number; min_stock: number; category: string | null; barcode: string | null }
interface StockHealth { total: number; in_stock: number; low_stock: number; out_of_stock: number }

export default function InventoryPage() {
  const [tab, setTab] = useState<'low' | 'out'>('low')

  const { data: health } = useQuery({ queryKey: ['stock-health'], queryFn: () => apiGet<{ success: boolean; health: StockHealth }>('/stock/health'), staleTime: 60_000 })
  const { data: lowData, isLoading: lowLoading } = useQuery({ queryKey: ['stock-low'], queryFn: () => apiGet<{ success: boolean; items: StockItem[] }>('/stock/low-stock'), staleTime: 60_000 })
  const { data: outData, isLoading: outLoading } = useQuery({ queryKey: ['stock-out'], queryFn: () => apiGet<{ success: boolean; items: StockItem[] }>('/stock/out-of-stock'), staleTime: 60_000 })

  const h = health?.health
  const items = tab === 'low' ? (lowData?.items ?? []) : (outData?.items ?? [])
  const loading = tab === 'low' ? lowLoading : outLoading

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Package className="h-6 w-6 text-primary-500" /> Inventory</h1>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {[
          { label: 'Total Products', value: h?.total ?? '—', color: 'blue' },
          { label: 'In Stock', value: h?.in_stock ?? '—', color: 'green' },
          { label: 'Low Stock', value: h?.low_stock ?? '—', color: 'yellow' },
          { label: 'Out of Stock', value: h?.out_of_stock ?? '—', color: 'red' },
        ].map(card => (
          <div key={card.label} className="card p-4">
            <p className="text-xs font-semibold uppercase text-gray-500">{card.label}</p>
            <p className={`mt-1 text-2xl font-bold text-${card.color}-600 dark:text-${card.color}-400`}>{card.value}</p>
          </div>
        ))}
      </div>

      <div className="flex gap-2">
        <button onClick={() => setTab('low')} className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors ${tab === 'low' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'}`}>
          <AlertTriangle className="h-4 w-4" /> Low Stock
        </button>
        <button onClick={() => setTab('out')} className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors ${tab === 'out' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'}`}>
          <XCircle className="h-4 w-4" /> Out of Stock
        </button>
      </div>

      {loading ? (
        <div className="flex h-48 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Name', 'Category', 'Barcode', 'Stock', 'Min Stock', 'Status'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {items.length === 0 ? (
                <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">No items — great news!</td></tr>
              ) : items.map(item => (
                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{item.name}</td>
                  <td className="px-4 py-3 text-gray-500">{item.category ?? '—'}</td>
                  <td className="px-4 py-3 font-mono text-xs text-gray-400">{item.barcode ?? '—'}</td>
                  <td className="px-4 py-3 font-semibold text-red-600">{item.quantity}</td>
                  <td className="px-4 py-3 text-gray-500">{item.min_stock}</td>
                  <td className="px-4 py-3"><span className={`badge ${tab === 'out' ? 'badge-danger' : 'badge-warning'}`}>{tab === 'out' ? 'Out of Stock' : 'Low Stock'}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
