import { useEffect } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { apiGet, apiPost } from '@/services/api'
import { Settings, Save } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'

interface SettingsData {
  store_name?: string; store_phone?: string; store_address?: string; currency?: string
  allow_negative_stock?: boolean; allow_cashier_price_change?: boolean; auto_print?: boolean
  tax_rate?: number; receipt_footer?: string
}

export default function SettingsPage() {
  const { data, isLoading } = useQuery({ queryKey: ['settings'], queryFn: () => apiGet<SettingsData>('/settings'), staleTime: 300_000 })

  const { register, handleSubmit, reset } = useForm<SettingsData>()

  useEffect(() => { if (data) reset(data) }, [data, reset])

  const saveMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/settings', payload),
    onSuccess: () => toast.success('Settings saved'),
    onError: () => toast.error('Failed to save settings'),
  })

  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Settings className="h-6 w-6 text-primary-500" /> Settings</h1>

      <form onSubmit={handleSubmit(d => saveMutation.mutate(d))} className="space-y-6">
        <div className="card p-6 space-y-4">
          <h2 className="text-base font-semibold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2">Store Information</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label className="label">Store Name</label><input {...register('store_name')} className="input" /></div>
            <div><label className="label">Phone</label><input {...register('store_phone')} className="input" /></div>
            <div><label className="label">Currency</label><input {...register('currency')} className="input" placeholder="EGP" /></div>
          </div>
          <div><label className="label">Address</label><input {...register('store_address')} className="input" /></div>
          <div><label className="label">Receipt Footer</label><input {...register('receipt_footer')} className="input" /></div>
        </div>

        <div className="card p-6 space-y-4">
          <h2 className="text-base font-semibold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2">Sales Settings</h2>
          <div className="space-y-3">
            {[
              { key: 'allow_negative_stock' as const, label: 'Allow selling when stock reaches zero' },
              { key: 'allow_cashier_price_change' as const, label: 'Allow cashier to change item price' },
              { key: 'auto_print' as const, label: 'Auto-print receipt after sale' },
            ].map(({ key, label }) => (
              <label key={key} className="flex items-center gap-3 cursor-pointer">
                <input {...register(key)} type="checkbox" className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                <span className="text-sm text-gray-700 dark:text-gray-300">{label}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="card p-6 space-y-4">
          <h2 className="text-base font-semibold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2">Tax</h2>
          <div className="max-w-xs">
            <label className="label">Default Tax Rate (%)</label>
            <input {...register('tax_rate', { valueAsNumber: true })} type="number" step="0.01" min="0" max="100" className="input" />
          </div>
        </div>

        <div className="flex justify-end">
          <button type="submit" disabled={saveMutation.isPending} className="btn-primary flex items-center gap-2">
            <Save className="h-4 w-4" />{saveMutation.isPending ? 'Saving…' : 'Save Settings'}
          </button>
        </div>
      </form>
    </div>
  )
}
