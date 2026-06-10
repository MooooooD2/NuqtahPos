import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import ProductSelect from '@/components/common/ProductSelect'
import { Trash2, Search, ClipboardList } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

interface WasteRecord {
  id: number
  product_name: string
  quantity: number
  reason: string
  waste_value?: string
  notes?: string
  created_by_name?: string
  created_at: string
}

const reasons = ['expired', 'damaged', 'theft', 'other'] as const
type Reason = typeof reasons[number]

const reasonBadge: Record<Reason, string> = {
  expired: 'badge-warning',
  damaged: 'badge-danger',
  theft: 'badge-danger',
  other: 'badge-gray',
}

const emptyForm = { product_id: '', product_name: '', quantity: '', reason: 'expired' as Reason, notes: '' }

export default function WastePage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [form, setForm] = useState({ ...emptyForm })
  const [page, setPage] = useState(1)
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [searchFrom, setSearchFrom] = useState('')
  const [searchTo, setSearchTo] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['waste', page, searchFrom, searchTo],
    queryFn: () => apiGet<{ success: boolean; data: WasteRecord[]; total?: number }>('/waste', {
      start_date: searchFrom || undefined,
      end_date: searchTo || undefined,
      page,
      per_page: 20,
    }),
    staleTime: 30_000,
  })

  const records = data?.data ?? []
  const canManage = hasPermission('add_stock', 'manage_roles')

  const recordMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/waste', payload),
    onSuccess: () => {
      toast.success(t('record_success'))
      qc.invalidateQueries({ queryKey: ['waste'] })
      setForm({ ...emptyForm })
    },
    onError: () => toast.error(t('record_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.product_id) return toast.error(t('error'))
    if (!form.quantity || parseInt(form.quantity) < 1) return toast.error(t('error'))
    if (!form.reason) return toast.error(t('error'))
    recordMutation.mutate({
      product_id: parseInt(form.product_id),
      quantity: parseInt(form.quantity),
      reason: form.reason,
      notes: form.notes || undefined,
    })
  }

  const handleSearch = () => {
    setSearchFrom(dateFrom)
    setSearchTo(dateTo)
    setPage(1)
  }

  const formatValue = (val?: string) => {
    if (!val) return '—'
    return parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2 })
  }

  if (!canManage) {
    return (
      <div className="card p-8 text-center text-gray-400">
        <Trash2 className="h-12 w-12 mx-auto mb-3 opacity-40" />
        <p>{t('access_denied_msg')}</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Trash2 className="h-6 w-6 text-primary-500" /> {t('waste_management')}
          {data?.total !== undefined && <span className="text-sm font-normal text-gray-400">({data.total})</span>}
        </h1>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        {/* Left: Record Waste form */}
        <div className="card p-5 space-y-4 sticky top-4">
          <h2 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <ClipboardList className="h-5 w-5 text-primary-500" /> {t('waste_recording')}
          </h2>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="label">{t('product')} *</label>
              <ProductSelect
                value={form.product_id}
                onChange={(id, name) => setForm((p) => ({ ...p, product_id: id, product_name: name }))}
              />
            </div>
            <div>
              <label className="label">{t('waste_quantity')} *</label>
              <input
                type="number"
                min="1"
                value={form.quantity}
                onChange={(e) => setForm((p) => ({ ...p, quantity: e.target.value }))}
                className="input w-full"
                placeholder="1"
                required
              />
            </div>
            <div>
              <label className="label">{t('waste_reason')} *</label>
              <select
                value={form.reason}
                onChange={(e) => setForm((p) => ({ ...p, reason: e.target.value as Reason }))}
                className="input w-full"
                required
              >
                {reasons.map((r) => (
                  <option key={r} value={r} className="capitalize">{t(`waste_reason_${r}` as any)}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="label">{t('notes')}</label>
              <textarea
                value={form.notes}
                onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))}
                className="input w-full"
                rows={3}
                placeholder="Optional notes…"
              />
            </div>
            <button type="submit" disabled={recordMutation.isPending} className="btn btn-primary w-full flex items-center justify-center gap-2">
              {recordMutation.isPending ? <LoadingSpinner size="sm" /> : <Trash2 className="h-4 w-4" />}
              {recordMutation.isPending ? t('saving') : t('record_waste')}
            </button>
          </form>
        </div>

        {/* Right: History */}
        <div className="lg:col-span-2 space-y-3">
          <div className="flex flex-wrap gap-2 items-end">
            <div>
              <label className="label">{t('date_from')}</label>
              <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="input" />
            </div>
            <div>
              <label className="label">{t('date_to')}</label>
              <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="input" />
            </div>
            <button onClick={handleSearch} className="btn btn-secondary flex items-center gap-2">
              <Search className="h-4 w-4" /> {t('search')}
            </button>
            {(searchFrom || searchTo) && (
              <button onClick={() => { setDateFrom(''); setDateTo(''); setSearchFrom(''); setSearchTo(''); setPage(1) }} className="btn btn-secondary text-sm">{t('clear')}</button>
            )}
          </div>

          <div className="card overflow-hidden">
            {isLoading ? (
              <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
            ) : (
              <>
                {/* Desktop table */}
                <div className="hidden lg:block overflow-x-auto">
                  <table className="w-full min-w-[550px] text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        {[t('date'), t('product'), t('quantity'), t('waste_reason'), t('waste_value'), t('recorded_by')].map((h) => (
                          <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {records.length === 0 ? (
                        <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                      ) : records.map((rec) => (
                        <tr key={rec.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 text-gray-500 text-xs">{rec.created_at?.slice(0, 10)}</td>
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{rec.product_name}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{rec.quantity.toLocaleString()}</td>
                          <td className="px-4 py-3">
                            <span className={clsx('badge capitalize', reasonBadge[rec.reason as Reason] ?? 'badge-gray')}>{rec.reason}</span>
                          </td>
                          <td className="px-4 py-3 font-semibold text-red-600 dark:text-red-400">{formatValue(rec.waste_value)}</td>
                          <td className="px-4 py-3 text-gray-500">{rec.created_by_name ?? '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {/* Mobile cards */}
                <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                  {records.length === 0 ? (
                    <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
                  ) : records.map((rec) => (
                    <div key={rec.id} className="p-4 space-y-2">
                      <div className="flex items-start justify-between gap-2">
                        <span className="font-semibold text-gray-900 dark:text-white">{rec.product_name}</span>
                        <span className={clsx('badge capitalize shrink-0', reasonBadge[rec.reason as Reason] ?? 'badge-gray')}>{rec.reason}</span>
                      </div>
                      <div className="flex items-center gap-3 flex-wrap text-sm">
                        <span className="text-gray-600 dark:text-gray-300">{t('quantity')}: <strong>{rec.quantity.toLocaleString()}</strong></span>
                        <span className="font-semibold text-red-600 dark:text-red-400">{formatValue(rec.waste_value)}</span>
                      </div>
                      <div className="text-xs text-gray-500 space-y-0.5">
                        <div>{t('date')}: {rec.created_at?.slice(0, 10)}</div>
                        <div>{t('recorded_by')}: {rec.created_by_name ?? '—'}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            )}
            {(data?.total ?? 0) > 20 && (
              <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
                <span className="text-sm text-gray-500">{t('page')} {page} · {data?.total} total</span>
                <div className="flex gap-2">
                  <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
                  <button onClick={() => setPage((p) => p + 1)} disabled={records.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
