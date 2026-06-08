import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { MessageSquare, Send, ArrowUp, ArrowDown, BarChart2, List, Megaphone, Bell } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

interface WaStats { total_messages: number; sent_today: number; inbound_today: number; failed_today: number }
interface WaLog { id: number; to_phone: string; type: string; status: string; direction: string; created_at: string; error_message?: string }

function statusBadge(s: string): string {
  if (s === 'sent') return 'badge-success'
  if (s === 'delivered') return 'badge-info'
  if (s === 'read') return 'badge badge-info'
  if (s === 'failed') return 'badge-danger'
  if (s === 'queued') return 'badge-gray'
  return 'badge-gray'
}

function typeBadge(t: string): string {
  if (t === 'invoice') return 'badge-info'
  if (t === 'debt_reminder') return 'badge-warning'
  if (t === 'promotion') return 'badge-success'
  return 'badge-gray'
}

export default function WhatsAppPage() {
  const { hasPermission } = usePermission()
  const { t } = useTranslation('pos')
  const canManage = hasPermission('manage_roles')

  const [tab, setTab] = useState<'stats' | 'logs' | 'promotions' | 'debt_reminders'>('stats')

  const [logStatus, setLogStatus] = useState('')
  const [logType, setLogType] = useState('')
  const [logPhone, setLogPhone] = useState('')
  const [logPage, setLogPage] = useState(1)

  const [promoMessage, setPromoMessage] = useState('')
  const [vipOnly, setVipOnly] = useState(false)
  const [promoResult, setPromoResult] = useState<string | null>(null)

  const [bulkConfirm, setBulkConfirm] = useState(false)
  const [debtResult, setDebtResult] = useState<string | null>(null)

  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ['wa-stats'],
    queryFn: () => apiGet<{ success: boolean; data: WaStats }>('/whatsapp/stats'),
    staleTime: 60_000,
    enabled: tab === 'stats',
    retry: false,
  })
  const stats = statsData?.data

  const { data: logsData, isLoading: logsLoading } = useQuery({
    queryKey: ['wa-logs', logStatus, logType, logPhone, logPage],
    queryFn: () => {
      const params = new URLSearchParams({ page: String(logPage), per_page: '20' })
      if (logStatus) params.set('status', logStatus)
      if (logType) params.set('type', logType)
      if (logPhone) params.set('phone', logPhone)
      return apiGet<{ success: boolean; data: WaLog[]; total: number }>(`/whatsapp/logs?${params}`)
    },
    staleTime: 30_000,
    enabled: tab === 'logs',
    retry: false,
  })
  const logs = logsData?.data ?? []
  const logsTotal = logsData?.total ?? 0
  const totalPages = Math.ceil(logsTotal / 20) || 1

  const clearLogFilters = () => { setLogStatus(''); setLogType(''); setLogPhone(''); setLogPage(1) }

  const getLogTypeLabel = (type: string) => {
    const map: Record<string, string> = { invoice: t('invoice_msg_type'), debt_reminder: t('debt_reminder_type'), promotion: t('promotion_type') }
    return map[type] ?? type.replace('_', ' ')
  }

  const promoMutation = useMutation({
    mutationFn: (payload: object) => apiPost<{ success: boolean; data?: { count?: number }; message?: string }>('/whatsapp/promotions', payload),
    onSuccess: (res) => {
      const count = (res as any)?.data?.count
      toast.success(count != null ? t('promo_sent_n', { n: count }) : t('promo_sent'))
      setPromoResult(JSON.stringify(res, null, 2))
      setPromoMessage('')
      setVipOnly(false)
    },
    onError: () => toast.error(t('save_failed')),
  })

  const handlePromoSend = (e: React.FormEvent) => {
    e.preventDefault()
    if (!promoMessage.trim()) return toast.error(t('error'))
    promoMutation.mutate({ message: promoMessage.trim(), vip_only: vipOnly })
  }

  const debtMutation = useMutation({
    mutationFn: () => apiPost<{ success: boolean; data?: unknown; message?: string }>('/whatsapp/customers/bulk-reminders', {}),
    onSuccess: (res) => {
      toast.success(t('updated_success'))
      setDebtResult(JSON.stringify(res, null, 2))
      setBulkConfirm(false)
    },
    onError: () => { toast.error(t('save_failed')); setBulkConfirm(false) },
  })

  if (!canManage) {
    return (
      <div className="card p-8 text-center text-gray-400 space-y-3">
        <MessageSquare className="h-10 w-10 mx-auto opacity-40" />
        <p className="font-medium">{t('no_permission')}</p>
      </div>
    )
  }

  const tabs = [
    { key: 'stats', label: t('wa_stats'), icon: BarChart2 },
    { key: 'logs', label: t('wa_logs'), icon: List },
    { key: 'promotions', label: t('wa_promotions'), icon: Megaphone },
    { key: 'debt_reminders', label: t('wa_debt_reminders'), icon: Bell },
  ] as const

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <MessageSquare className="h-6 w-6 text-green-500" /> {t('whatsapp')}
        </h1>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit flex-wrap">
        {tabs.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={clsx('px-4 py-1.5 rounded-md text-sm font-medium transition-colors flex items-center gap-1.5',
              tab === key ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'
            )}
          >
            <Icon className="h-3.5 w-3.5" />
            {label}
          </button>
        ))}
      </div>

      {/* ── Stats Tab ── */}
      {tab === 'stats' && (
        <div className="space-y-4">
          {statsLoading ? (
            <div className="flex h-40 items-center justify-center"><LoadingSpinner size="lg" /></div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div className="card p-5 space-y-1">
                <p className="text-xs text-gray-500 uppercase font-semibold">{t('total_messages')}</p>
                <p className="text-3xl font-bold text-gray-900 dark:text-white">{stats?.total_messages?.toLocaleString() ?? '—'}</p>
              </div>
              <div className="card p-5 space-y-1">
                <p className="text-xs text-gray-500 uppercase font-semibold">{t('sent_today_wa')}</p>
                <p className="text-3xl font-bold text-green-600">{stats?.sent_today?.toLocaleString() ?? '—'}</p>
              </div>
              <div className="card p-5 space-y-1">
                <p className="text-xs text-gray-500 uppercase font-semibold">{t('inbound_today')}</p>
                <p className="text-3xl font-bold text-blue-600">{stats?.inbound_today?.toLocaleString() ?? '—'}</p>
              </div>
              <div className="card p-5 space-y-1">
                <p className="text-xs text-gray-500 uppercase font-semibold">{t('failed_today')}</p>
                <p className="text-3xl font-bold text-red-600">{stats?.failed_today?.toLocaleString() ?? '—'}</p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ── Logs Tab ── */}
      {tab === 'logs' && (
        <div className="space-y-4">
          <div className="card p-4 flex flex-wrap gap-3 items-end">
            <div>
              <label className="label">{t('status')}</label>
              <select value={logStatus} onChange={(e) => { setLogStatus(e.target.value); setLogPage(1) }} className="input">
                <option value="">{t('all')}</option>
                <option value="queued">{t('queued')}</option>
                <option value="sent">{t('sent_status')}</option>
                <option value="delivered">{t('delivered')}</option>
                <option value="read">{t('read_status')}</option>
                <option value="failed">{t('failed_status')}</option>
              </select>
            </div>
            <div>
              <label className="label">{t('type')}</label>
              <select value={logType} onChange={(e) => { setLogType(e.target.value); setLogPage(1) }} className="input">
                <option value="">{t('all')}</option>
                <option value="invoice">{t('invoice_msg_type')}</option>
                <option value="debt_reminder">{t('debt_reminder_type')}</option>
                <option value="promotion">{t('promotion_type')}</option>
              </select>
            </div>
            <div>
              <label className="label">{t('phone')}</label>
              <input
                type="text"
                value={logPhone}
                onChange={(e) => { setLogPhone(e.target.value); setLogPage(1) }}
                placeholder={t('search_placeholder')}
                className="input w-44"
              />
            </div>
            <button onClick={clearLogFilters} className="btn btn-secondary self-end">{t('clear')}</button>
          </div>

          <div className="card overflow-hidden">
            {logsLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>{[t('to'), t('type'), t('status'), t('direction'), t('date'), t('error_col')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {logs.length === 0
                        ? <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">{t('no_messages_found')}</td></tr>
                        : logs.map((log) => (
                          <tr key={log.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td className="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{log.to_phone}</td>
                            <td className="px-4 py-3"><span className={clsx('badge capitalize', typeBadge(log.type))}>{getLogTypeLabel(log.type)}</span></td>
                            <td className="px-4 py-3">
                              <span className={clsx('badge capitalize', log.status === 'read' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : statusBadge(log.status))}>
                                {log.status}
                              </span>
                            </td>
                            <td className="px-4 py-3">
                              {log.direction === 'outbound'
                                ? <span className="flex items-center gap-1 text-green-600 text-xs"><ArrowUp className="h-3.5 w-3.5" /> {t('direction_out')}</span>
                                : <span className="flex items-center gap-1 text-blue-600 text-xs"><ArrowDown className="h-3.5 w-3.5" /> {t('direction_in')}</span>
                              }
                            </td>
                            <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{log.created_at?.slice(0, 16)}</td>
                            <td className="px-4 py-3 text-red-400 text-xs max-w-[200px] truncate" title={log.error_message}>
                              {log.error_message ? log.error_message.slice(0, 40) + (log.error_message.length > 40 ? '…' : '') : '—'}
                            </td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
                {totalPages > 1 && (
                  <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                    <p className="text-sm text-gray-500">{t('page')} {logPage} / {totalPages} · {logsTotal} {t('total_records')}</p>
                    <div className="flex gap-2">
                      <button disabled={logPage <= 1} onClick={() => setLogPage((p) => p - 1)} className="btn btn-secondary text-xs py-1 px-3 disabled:opacity-40">{t('prev')}</button>
                      <button disabled={logPage >= totalPages} onClick={() => setLogPage((p) => p + 1)} className="btn btn-secondary text-xs py-1 px-3 disabled:opacity-40">{t('next')}</button>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      )}

      {/* ── Promotions Tab ── */}
      {tab === 'promotions' && (
        <div className="max-w-2xl space-y-4">
          <div className="card p-6 space-y-4">
            <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2"><Megaphone className="h-4 w-4 text-green-500" /> {t('send_promotion')}</h2>
            <form onSubmit={handlePromoSend} className="space-y-4">
              <div>
                <div className="flex justify-between">
                  <label className="label">{t('message')} *</label>
                  <span className={clsx('text-xs', promoMessage.length > 1000 ? 'text-red-500 font-semibold' : 'text-gray-400')}>
                    {promoMessage.length} / 1000
                  </span>
                </div>
                <textarea
                  value={promoMessage}
                  onChange={(e) => setPromoMessage(e.target.value)}
                  maxLength={1000}
                  rows={6}
                  className="input w-full resize-y min-h-[140px]"
                  placeholder={t('promo_message_ph')}
                  required
                />
              </div>
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" checked={vipOnly} onChange={(e) => setVipOnly(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-primary-600" />
                <span className="text-sm text-gray-700 dark:text-gray-300">{t('vip_only')}</span>
              </label>
              <button type="submit" disabled={promoMutation.isPending || promoMessage.length > 1000} className="btn btn-primary flex items-center gap-2">
                <Send className="h-4 w-4" />
                {promoMutation.isPending ? t('sending') : t('send_promotion')}
              </button>
            </form>
          </div>

          {promoResult && (
            <div className="card p-4 space-y-2">
              <p className="text-xs font-semibold text-gray-500 uppercase">{t('response')}</p>
              <pre className="text-xs bg-gray-50 dark:bg-gray-800 p-3 rounded overflow-x-auto text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{promoResult}</pre>
            </div>
          )}
        </div>
      )}

      {/* ── Debt Reminders Tab ── */}
      {tab === 'debt_reminders' && (
        <div className="max-w-xl space-y-4">
          <div className="card p-6 space-y-4">
            <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2"><Bell className="h-4 w-4 text-orange-500" /> {t('bulk_debt_reminders')}</h2>
            <button
              onClick={() => setBulkConfirm(true)}
              disabled={debtMutation.isPending}
              className="btn flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
            >
              <Bell className="h-4 w-4" />
              {debtMutation.isPending ? t('sending') : t('send_bulk_reminders')}
            </button>
          </div>

          {debtResult && (
            <div className="card p-4 space-y-2">
              <p className="text-xs font-semibold text-gray-500 uppercase">{t('response')}</p>
              <pre className="text-xs bg-gray-50 dark:bg-gray-800 p-3 rounded overflow-x-auto text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{debtResult}</pre>
            </div>
          )}
        </div>
      )}

      <ConfirmDialog
        open={bulkConfirm}
        title={t('bulk_debt_reminders')}
        message={t('bulk_confirm_msg')}
        loading={debtMutation.isPending}
        onConfirm={() => debtMutation.mutate()}
        onCancel={() => setBulkConfirm(false)}
      />
    </div>
  )
}
