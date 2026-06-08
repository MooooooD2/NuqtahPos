import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiDelete } from '@/services/api'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import { Smartphone, Monitor, Tablet, Globe, Clock, ShieldOff } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

interface DeviceSession {
  id: number
  device_name?: string
  device_type?: string
  browser?: string
  os?: string
  ip_address?: string
  last_active_at?: string
  is_current?: boolean
}

function getDeviceIcon(deviceType?: string) {
  if (!deviceType) return Monitor
  const dtype = deviceType.toLowerCase()
  if (dtype === 'mobile') return Smartphone
  if (dtype === 'tablet') return Tablet
  return Monitor
}

export default function DeviceSessionsPage() {
  const qc = useQueryClient()
  const { t } = useTranslation('pos')
  const [revokeId, setRevokeId] = useState<number | null>(null)
  const [revokeAll, setRevokeAll] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['device-sessions'],
    queryFn: () => apiGet<{ success: boolean; data: DeviceSession[] }>('/device-sessions'),
    staleTime: 30_000,
  })

  const sessions = data?.data ?? []

  const revokeMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/device-sessions/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['device-sessions'] }); setRevokeId(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const revokeAllMutation = useMutation({
    mutationFn: () => apiDelete('/device-sessions/revoke-all'),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['device-sessions'] }); setRevokeAll(false) },
    onError: () => toast.error(t('save_failed')),
  })

  const otherSessions = sessions.filter((s) => !s.is_current)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Monitor className="h-6 w-6 text-primary-500" /> {t('device_sessions')}
        </h1>
        {otherSessions.length > 0 && (
          <button onClick={() => setRevokeAll(true)} className="btn bg-red-600 hover:bg-red-700 text-white flex items-center gap-2 text-sm">
            <ShieldOff className="h-4 w-4" /> {t('sign_out_all_other')}
          </button>
        )}
      </div>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : sessions.length === 0 ? (
        <div className="card p-12 text-center text-gray-400">
          <Monitor className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p>{t('no_active_sessions')}</p>
        </div>
      ) : (
        <div className="space-y-3">
          {sessions.map((session) => {
            const DeviceIcon = getDeviceIcon(session.device_type)
            return (
              <div
                key={session.id}
                className={clsx(
                  'card p-4 flex items-center gap-4',
                  session.is_current && 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900'
                )}
              >
                <div className={clsx('flex-shrink-0 h-12 w-12 rounded-xl flex items-center justify-center', session.is_current ? 'bg-primary-100 dark:bg-primary-900/30' : 'bg-gray-100 dark:bg-gray-700')}>
                  <DeviceIcon className={clsx('h-6 w-6', session.is_current ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400')} />
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-semibold text-gray-900 dark:text-white text-sm">
                      {session.browser ?? t('unknown_browser')}{session.os ? ` ${t('on')} ${session.os}` : ''}
                    </span>
                    {session.is_current && <span className="badge badge-success text-xs">{t('current_session_badge')}</span>}
                  </div>
                  <div className="flex items-center gap-4 mt-1 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                    {session.ip_address && (
                      <span className="flex items-center gap-1">
                        <Globe className="h-3.5 w-3.5" /> {session.ip_address}
                      </span>
                    )}
                    <span className="flex items-center gap-1">
                      <Clock className="h-3.5 w-3.5" /> {session.last_active_at ?? t('unknown')}
                    </span>
                    {session.device_name && (
                      <span className="hidden md:block text-gray-400 dark:text-gray-500 truncate max-w-xs">
                        {session.device_name}
                      </span>
                    )}
                  </div>
                </div>

                {!session.is_current && (
                  <button
                    onClick={() => setRevokeId(session.id)}
                    className="flex-shrink-0 flex items-center gap-1.5 text-xs text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-3 py-1.5 rounded-lg transition-colors border border-gray-200 dark:border-gray-600"
                  >
                    <ShieldOff className="h-3.5 w-3.5" /> {t('revoke')}
                  </button>
                )}
              </div>
            )
          })}
        </div>
      )}

      <ConfirmDialog
        open={revokeId !== null}
        title={t('revoke_session')}
        message={t('confirm_revoke_session')}
        confirmLabel={t('revoke')}
        loading={revokeMutation.isPending}
        onConfirm={() => revokeId && revokeMutation.mutate(revokeId)}
        onCancel={() => setRevokeId(null)}
      />

      <ConfirmDialog
        open={revokeAll}
        title={t('sign_out_all_other')}
        message={t('sign_out_others_confirm', { n: otherSessions.length })}
        confirmLabel={t('sign_out_all_other')}
        loading={revokeAllMutation.isPending}
        onConfirm={() => revokeAllMutation.mutate()}
        onCancel={() => setRevokeAll(false)}
      />
    </div>
  )
}
