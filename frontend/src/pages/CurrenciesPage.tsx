import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api, apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Coins, Plus, Trash2, RefreshCw, ArrowRightLeft, Check } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Currency {
  code: string
  name: string
  symbol: string
  exchange_rate: string
  is_base: boolean
  is_active: boolean
  rate_updated_at?: string
}

const emptyForm = { code: '', symbol: '', name: '', exchange_rate: '', is_base: false }

export default function CurrenciesPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState({ ...emptyForm })
  const [deleteCode, setDeleteCode] = useState<string | null>(null)
  const [inlineRates, setInlineRates] = useState<Record<string, string>>({})
  const [savingRate, setSavingRate] = useState<string | null>(null)
  const [convAmount, setConvAmount] = useState('')
  const [convFrom, setConvFrom] = useState('')
  const [convTo, setConvTo] = useState('')
  const [convResult, setConvResult] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['currencies'],
    queryFn: () => apiGet<{ currencies: Currency[] }>('/currencies'),
    staleTime: 60_000,
  })

  const currencies = data?.currencies ?? []
  const canManage = hasPermission('manage_roles')

  useEffect(() => {
    if (currencies.length > 0) {
      if (!convFrom) setConvFrom(currencies[0].code)
      if (!convTo) setConvTo(currencies.length > 1 ? currencies[1].code : currencies[0].code)
    }
  }, [currencies.length])

  const openAdd = () => { setForm({ ...emptyForm }); setModal(true) }

  const createMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/currencies', payload),
    onSuccess: () => {
      toast.success(t('created_success'))
      qc.invalidateQueries({ queryKey: ['currencies'] })
      setModal(false)
    },
    onError: () => toast.error(t('save_failed')),
  })

  const toggleMutation = useMutation({
    mutationFn: ({ code, is_active }: { code: string; is_active: boolean }) =>
      api.patch(`/currencies/${code}/toggle`, { is_active }),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['currencies'] }) },
    onError: () => toast.error(t('save_failed')),
  })

  const deleteMutation = useMutation({
    mutationFn: (code: string) => apiDelete(`/currencies/${code}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['currencies'] }); setDeleteCode(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const syncMutation = useMutation({
    mutationFn: () => apiPost('/currencies/update-rates', {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['currencies'] }) },
    onError: () => toast.error(t('save_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.code || !form.symbol || !form.name || !form.exchange_rate) return toast.error(t('error'))
    if (parseFloat(form.exchange_rate) < 0.0001) return toast.error(t('error'))
    createMutation.mutate({
      code: form.code.toUpperCase(),
      symbol: form.symbol,
      name: form.name,
      exchange_rate: parseFloat(form.exchange_rate),
      is_base: form.is_base,
    })
  }

  const handleRateSave = async (code: string) => {
    const rate = inlineRates[code]
    if (!rate || parseFloat(rate) < 0.0001) { toast.error(t('error')); return }
    setSavingRate(code)
    try {
      await apiPut(`/currencies/${code}`, { exchange_rate: parseFloat(rate) })
      toast.success(t('updated_success'))
      qc.invalidateQueries({ queryKey: ['currencies'] })
      setInlineRates((p) => { const n = { ...p }; delete n[code]; return n })
    } catch {
      toast.error(t('save_failed'))
    } finally {
      setSavingRate(null)
    }
  }

  const handleConvert = () => {
    if (!convAmount || !convFrom || !convTo) { toast.error(t('error')); return }
    const fromCur = currencies.find((c) => c.code === convFrom)
    const toCur = currencies.find((c) => c.code === convTo)
    if (!fromCur || !toCur) { toast.error(t('error')); return }
    const result = parseFloat(convAmount) * parseFloat(toCur.exchange_rate) / parseFloat(fromCur.exchange_rate)
    setConvResult(result.toFixed(4))
  }

  const f = (field: keyof typeof emptyForm) => ({
    value: form[field] as string,
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Coins className="h-6 w-6 text-primary-500" /> {t('currencies')}
        </h1>
        <div className="flex gap-2">
          {canManage && (
            <button onClick={() => syncMutation.mutate()} disabled={syncMutation.isPending} className="btn btn-secondary flex items-center gap-2 text-sm">
              <RefreshCw className={clsx('h-4 w-4', syncMutation.isPending && 'animate-spin')} /> {t('sync_rates')}
            </button>
          )}
          {canManage && (
            <button onClick={openAdd} className="btn btn-primary flex items-center gap-2">
              <Plus className="h-4 w-4" /> {t('add_currency')}
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Left: currencies table */}
        <div className="lg:col-span-2 card overflow-hidden">
          {isLoading ? (
            <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[t('code'), t('name'), t('symbol'), t('exchange_rate'), t('rate_updated'), t('status'), ''].map((h, i) => (
                      <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {currencies.length === 0 ? (
                    <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  ) : currencies.map((cur) => (
                    <tr key={cur.code} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3">
                        <span className="font-mono font-bold text-gray-900 dark:text-white">{cur.code}</span>
                        {cur.is_base && <span className="ml-1 badge badge-info text-xs">{t('base')}</span>}
                      </td>
                      <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{cur.name}</td>
                      <td className="px-4 py-3 text-gray-500 font-mono">{cur.symbol}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1">
                          <input
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            value={inlineRates[cur.code] ?? cur.exchange_rate}
                            onChange={(e) => setInlineRates((p) => ({ ...p, [cur.code]: e.target.value }))}
                            className="input w-28 py-1 text-sm"
                            disabled={!canManage}
                          />
                          {canManage && inlineRates[cur.code] !== undefined && (
                            <button
                              onClick={() => handleRateSave(cur.code)}
                              disabled={savingRate === cur.code}
                              className="p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded"
                              title={t('save_rate')}
                            >
                              <Check className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </td>
                      <td className="px-4 py-3 text-gray-400 text-xs">{cur.rate_updated_at?.slice(0, 10) ?? '—'}</td>
                      <td className="px-4 py-3">
                        <span className={clsx('badge', cur.is_active ? 'badge-success' : 'badge-gray')}>
                          {cur.is_active ? t('active_status') : t('inactive_status')}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 justify-end">
                          {canManage && (
                            <button
                              onClick={() => toggleMutation.mutate({ code: cur.code, is_active: !cur.is_active })}
                              className={clsx('px-2 py-1 rounded text-xs font-medium transition-colors', cur.is_active ? 'text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20' : 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20')}
                            >
                              {cur.is_active ? t('disable') : t('enable')}
                            </button>
                          )}
                          {canManage && !cur.is_base && (
                            <button onClick={() => setDeleteCode(cur.code)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                              <Trash2 className="h-4 w-4" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Right: converter */}
        <div className="card p-5 space-y-4 self-start">
          <h2 className="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <ArrowRightLeft className="h-5 w-5 text-primary-500" /> {t('convert_currency')}
          </h2>
          <div>
            <label className="label">{t('amount')}</label>
            <input
              type="number"
              min="0"
              step="0.01"
              value={convAmount}
              onChange={(e) => { setConvAmount(e.target.value); setConvResult(null) }}
              className="input w-full"
              placeholder="0.00"
            />
          </div>
          <div>
            <label className="label">{t('from_currency')}</label>
            <select value={convFrom} onChange={(e) => { setConvFrom(e.target.value); setConvResult(null) }} className="input w-full">
              <option value="">— {t('select')} —</option>
              {currencies.map((c) => <option key={c.code} value={c.code}>{c.code} — {c.name}</option>)}
            </select>
          </div>
          <div>
            <label className="label">{t('to_currency')}</label>
            <select value={convTo} onChange={(e) => { setConvTo(e.target.value); setConvResult(null) }} className="input w-full">
              <option value="">— {t('select')} —</option>
              {currencies.map((c) => <option key={c.code} value={c.code}>{c.code} — {c.name}</option>)}
            </select>
          </div>
          <button onClick={handleConvert} className="btn btn-primary w-full flex items-center justify-center gap-2">
            <ArrowRightLeft className="h-4 w-4" /> {t('convert')}
          </button>
          {convResult !== null && (
            <div className="rounded-lg bg-primary-50 dark:bg-primary-900/20 p-4 text-center">
              <p className="text-sm text-gray-500 mb-1">{t('conversion_result')}: {convAmount} {convFrom} =</p>
              <p className="text-2xl font-bold text-primary-600 dark:text-primary-400">{convResult} {convTo}</p>
            </div>
          )}
        </div>
      </div>

      <Modal
        open={modal}
        onClose={() => setModal(false)}
        title={t('add_currency')}
        size="md"
        footer={
          <>
            <button onClick={() => setModal(false)} className="btn btn-secondary">{t('cancel')}</button>
            <button onClick={handleSubmit} disabled={createMutation.isPending} className="btn btn-primary">
              {createMutation.isPending ? '…' : t('save')}
            </button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('currency_code')} * <span className="text-xs text-gray-400">(3 chars)</span></label>
              <input {...f('code')} maxLength={3} className="input w-full uppercase" placeholder="USD" required />
            </div>
            <div>
              <label className="label">{t('symbol')} * <span className="text-xs text-gray-400">(max 5)</span></label>
              <input {...f('symbol')} maxLength={5} className="input w-full" placeholder="$" required />
            </div>
            <div className="col-span-2">
              <label className="label">{t('name')} *</label>
              <input {...f('name')} className="input w-full" placeholder="US Dollar" required />
            </div>
            <div className="col-span-2">
              <label className="label">{t('exchange_rate')} *</label>
              <input {...f('exchange_rate')} type="number" step="0.0001" min="0.0001" className="input w-full" placeholder="1.0000" required />
            </div>
            <div className="col-span-2 flex items-center gap-3">
              <input
                id="is_base"
                type="checkbox"
                checked={form.is_base}
                onChange={(e) => setForm((p) => ({ ...p, is_base: e.target.checked }))}
                className="h-4 w-4 rounded border-gray-300 text-primary-600"
              />
              <label htmlFor="is_base" className="label mb-0 cursor-pointer">{t('mark_base')}</label>
            </div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteCode !== null}
        title={t('delete_currency')}
        message={t('confirm_delete_currency', { n: deleteCode ?? '' })}
        loading={deleteMutation.isPending}
        onConfirm={() => deleteCode && deleteMutation.mutate(deleteCode)}
        onCancel={() => setDeleteCode(null)}
      />
    </div>
  )
}
