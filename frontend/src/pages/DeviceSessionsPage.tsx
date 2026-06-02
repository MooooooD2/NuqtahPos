import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiDelete } from '@/services/api'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import { Smartphone, Monitor, Tablet, Globe, Clock, ShieldOff } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface DeviceSession { id: number; ip_address?: string; user_agent?: string; last_active_at?: string; is_current?: boolean }

function getDeviceIcon(userAgent?: string) {
  if (!userAgent) return Monitor
  const ua = userAgent.toLowerCase()
  if (ua.includes('iphone') || ua.includes('android') || ua.includes('mobile')) return Smartphone
  if (ua.includes('ipad') || ua.includes('tablet')) return Tablet
  return Monitor
}

function timeAgo(dateStr?: string): string {
  if (!dateStr) return 'Unknown'
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function getBrowserInfo(userAgent?: string): string {
  if (!userAgent) return 'Unknown Browser'
  if (userAgent.includes('Chrome') && !userAgent.includes('Edg')) return 'Chrome'
  if (userAgent.includes('Firefox')) return 'Firefox'
  if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) return 'Safari'
  if (userAgent.includes('Edg')) return 'Edge'
  return 'Browser'
}

export default function DeviceSessionsPage() {
  const qc = useQueryClient()
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
    onSuccess: () => { toast.success('Session revoked'); qc.invalidateQueries({ queryKey: ['device-sessions'] }); setRevokeId(null) },
    onError: () => toast.error('Failed to revoke session'),
  })

  const revokeAllMutation = useMutation({
    mutationFn: () => apiDelete('/device-sessions/revoke-all'),
    onSuccess: () => { toast.success('All other sessions signed out'); qc.invalidateQueries({ queryKey: ['device-sessions'] }); setRevokeAll(false) },
    onError: () => toast.error('Failed to sign out other sessions'),
  })

  const otherSessions = sessions.filter((s) => !s.is_current)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Monitor className="h-6 w-6 text-primary-500" /> Device Sessions
        </h1>
        {otherSessions.length > 0 && (
          <button onClick={() => setRevokeAll(true)} className="btn bg-red-600 hover:bg-red-700 text-white flex items-center gap-2 text-sm">
            <ShieldOff className="h-4 w-4" /> Sign Out All Other Devices
          </button>
        )}
      </div>

      <p className="text-sm text-gray-500 dark:text-gray-400">
        Manage active sessions across all your devices. Revoke any session you don&apos;t recognize.
      </p>

      {isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : sessions.length === 0 ? (
        <div className="card p-12 text-center text-gray-400">
          <Monitor className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p>No active sessions found</p>
        </div>
      ) : (
        <div className="space-y-3">
          {sessions.map((session) => {
            const DeviceIcon = getDeviceIcon(session.user_agent)
            const browser = getBrowserInfo(session.user_agent)
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
                    <span className="font-semibold text-gray-900 dark:text-white text-sm">{browser}</span>
                    {session.is_current && <span className="badge badge-success text-xs">Current session</span>}
                  </div>
                  <div className="flex items-center gap-4 mt-1 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                    {session.ip_address && (
                      <span className="flex items-center gap-1">
                        <Globe className="h-3.5 w-3.5" /> {session.ip_address}
                      </span>
                    )}
                    <span className="flex items-center gap-1">
                      <Clock className="h-3.5 w-3.5" /> {timeAgo(session.last_active_at)}
                    </span>
                    {session.user_agent && (
                      <span className="hidden md:block font-mono text-gray-400 dark:text-gray-500 truncate max-w-xs">
                        {session.user_agent.slice(0, 60)}{session.user_agent.length > 60 ? '…' : ''}
                      </span>
                    )}
                  </div>
                </div>

                {!session.is_current && (
                  <button
                    onClick={() => setRevokeId(session.id)}
                    className="flex-shrink-0 flex items-center gap-1.5 text-xs text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-3 py-1.5 rounded-lg transition-colors border border-gray-200 dark:border-gray-600"
                  >
                    <ShieldOff className="h-3.5 w-3.5" /> Revoke
                  </button>
                )}
              </div>
            )
          })}
        </div>
      )}

      <ConfirmDialog
        open={revokeId !== null}
        title="Revoke Session"
        message="This device will be signed out immediately. Continue?"
        confirmLabel="Revoke"
        loading={revokeMutation.isPending}
        onConfirm={() => revokeId && revokeMutation.mutate(revokeId)}
        onCancel={() => setRevokeId(null)}
      />

      <ConfirmDialog
        open={revokeAll}
        title="Sign Out All Other Devices"
        message={`Sign out ${otherSessions.length} other session${otherSessions.length !== 1 ? 's' : ''}? Your current session will remain active.`}
        confirmLabel="Sign Out All"
        loading={revokeAllMutation.isPending}
        onConfirm={() => revokeAllMutation.mutate()}
        onCancel={() => setRevokeAll(false)}
      />
    </div>
  )
}
