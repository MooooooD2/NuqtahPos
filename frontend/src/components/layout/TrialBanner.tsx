import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { X, Clock, AlertTriangle, Zap } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { api } from '@/services/api'

interface Subscription {
  status: string
  plan: string | null
  trial_ends_at: string | null
  days_left: number | null
  is_expired: boolean
}

export default function TrialBanner() {
  const { t } = useTranslation('pos')
  const [sub, setSub] = useState<Subscription | null>(null)
  const [dismissed, setDismissed] = useState(false)

  // Don't show for the master tenant
  const companyCode = localStorage.getItem('pos-company-code') ?? ''
  const isMaster = companyCode === 'main'

  useEffect(() => {
    if (isMaster) return
    api.get<Subscription>('/subscription')
      .then((r) => setSub(r.data))
      .catch(() => {})
  }, [isMaster])

  // Session-only: hides until next page load, no localStorage persistence
  const handleDismiss = () => setDismissed(true)

  if (isMaster || dismissed || !sub) return null
  if (sub.status !== 'trial') return null

  const daysLeft  = sub.days_left ?? 0
  const isUrgent  = daysLeft <= 3
  const isExpired = sub.is_expired || daysLeft === 0

  // Color scheme
  const bg      = isExpired || isUrgent ? 'bg-red-600'    : 'bg-amber-500'
  const border  = isExpired || isUrgent ? 'border-red-700': 'border-amber-600'
  const btnCls  = isExpired || isUrgent
    ? 'bg-white text-red-700 hover:bg-red-50'
    : 'bg-amber-800/30 border border-amber-300/40 text-white hover:bg-amber-800/50'

  let message: string
  if (isExpired) {
    message = t('trial_expired')
  } else if (daysLeft === 0) {
    message = t('trial_expires_today')
  } else if (daysLeft === 1) {
    message = t('trial_expires_tomorrow')
  } else {
    message = daysLeft > 2
      ? t('trial_expires_days_plural', { count: daysLeft })
      : t('trial_expires_days', { count: daysLeft })
  }

  const Icon = isUrgent || isExpired ? AlertTriangle : Clock

  return (
    <div className={`relative flex items-center justify-between gap-3 px-4 py-2.5 text-sm text-white ${bg} border-b ${border}`}>
      {/* Left: icon + message */}
      <div className="flex items-center gap-2 min-w-0">
        <Icon className="h-4 w-4 shrink-0" />
        <span className="font-medium">{message}</span>
        {sub.plan && (
          <span className="hidden sm:inline text-white/70">
            · {t('trial_plan', { plan: sub.plan.charAt(0).toUpperCase() + sub.plan.slice(1) })}
          </span>
        )}
      </div>

      {/* Right: CTA + dismiss */}
      <div className="flex shrink-0 items-center gap-2">
        <Link
          to="/payment"
          target="_blank"
          rel="noopener noreferrer"
          className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-colors ${btnCls}`}
        >
          <Zap className="h-3.5 w-3.5" />
          {t('trial_activate_now')}
        </Link>

        {!isExpired && (
          <button
            onClick={handleDismiss}
            className="rounded p-1 text-white/70 hover:text-white transition-colors"
            title={t('trial_dismiss')}
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
    </div>
  )
}
