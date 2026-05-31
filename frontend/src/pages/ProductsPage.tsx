import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost } from '@/services/api'
import { Package, X, Plus } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface Product {
  id: number
  name: string
  barcode: string | null
  category: string | null
  price: string
  quantity: number
  min_stock: number
  low_stock: boolean
  supplier: string | null
}

interface ProductsResponse {
  success: boolean
  products: Product[]
}

interface AddProductForm {
  name: string
  price: string
  cost_price: string
  category: string
  barcode: string
  min_stock: string
  initial_quantity: string
}

export default function ProductsPage() {
  const qc = useQueryClient()
  const [showModal, setShowModal] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['products'],
    queryFn: () => apiGet<ProductsResponse>('/products', { per_page: 50 }),
  })

  const { register, handleSubmit, reset, formState: { errors } } = useForm<AddProductForm>({
    defaultValues: { name: '', price: '', cost_price: '', category: '', barcode: '', min_stock: '0', initial_quantity: '0' },
  })

  const addMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/products', payload),
    onSuccess: () => {
      toast.success('Product added successfully')
      qc.invalidateQueries({ queryKey: ['products'] })
      setShowModal(false)
      reset()
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Failed to add product'
      toast.error(msg)
    },
  })

  const onSubmit = (form: AddProductForm) => {
    addMutation.mutate({
      name: form.name,
      price: parseFloat(form.price),
      cost_price: form.cost_price ? parseFloat(form.cost_price) : undefined,
      category: form.category || undefined,
      barcode: form.barcode || undefined,
      min_stock: parseInt(form.min_stock) || 0,
      initial_quantity: parseInt(form.initial_quantity) || 0,
    })
  }

  const products = data?.products ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Products</h1>
        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2">
          <Plus className="h-4 w-4" /> Add Product
        </button>
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>
                {['Barcode', 'Name', 'Category', 'Price', 'Stock', 'Status'].map(h => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {products.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-gray-400">
                    No products yet. Click "Add Product" to create one.
                  </td>
                </tr>
              ) : products.map(p => (
                <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-mono text-xs text-gray-500">{p.barcode ?? '—'}</td>
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                  <td className="px-4 py-3 text-gray-500">{p.category ?? '—'}</td>
                  <td className="px-4 py-3 font-semibold text-primary-600">{parseFloat(p.price).toFixed(2)}</td>
                  <td className="px-4 py-3">
                    <span className={p.low_stock ? 'font-semibold text-red-500' : 'text-gray-700 dark:text-gray-300'}>
                      {p.quantity}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={p.low_stock ? 'badge badge-warning' : 'badge badge-success'}>
                      {p.low_stock ? 'Low Stock' : 'In Stock'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Add Product Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-800 shadow-2xl">
            <div className="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 px-6 py-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <Package className="h-5 w-5 text-primary-500" /> Add Product
              </h2>
              <button onClick={() => { setShowModal(false); reset() }} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <X className="h-5 w-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                  <label className="label">Product Name *</label>
                  <input {...register('name', { required: 'Name is required' })} className="input" placeholder="e.g. Mineral Water 1.5L" />
                  {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
                </div>

                <div>
                  <label className="label">Selling Price *</label>
                  <input {...register('price', { required: 'Price is required' })} type="number" step="0.01" min="0" className="input" placeholder="0.00" />
                  {errors.price && <p className="mt-1 text-xs text-red-500">{errors.price.message}</p>}
                </div>

                <div>
                  <label className="label">Cost Price</label>
                  <input {...register('cost_price')} type="number" step="0.01" min="0" className="input" placeholder="0.00" />
                </div>

                <div>
                  <label className="label">Category</label>
                  <input {...register('category')} className="input" placeholder="e.g. Beverages" />
                </div>

                <div>
                  <label className="label">Barcode</label>
                  <input {...register('barcode')} className="input" placeholder="Scan or type barcode" />
                </div>

                <div>
                  <label className="label">Min Stock Alert</label>
                  <input {...register('min_stock')} type="number" min="0" className="input" placeholder="0" />
                </div>

                <div>
                  <label className="label">Opening Stock Qty</label>
                  <input {...register('initial_quantity')} type="number" min="0" className="input" placeholder="0" />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => { setShowModal(false); reset() }} className="btn-secondary">Cancel</button>
                <button type="submit" disabled={addMutation.isPending} className="btn-primary flex items-center gap-2">
                  {addMutation.isPending ? 'Saving…' : 'Add Product'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
