import { useState, useEffect } from 'react'
import { Monitor, Apple, Terminal, Download, X, ChevronDown, AlertCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { isTauriApp } from '@/lib/tauri'

const isTauri = isTauriApp()
const webBase = (import.meta.env.BASE_URL ?? '/').replace(/\/$/, '')
// In dev, Vite intercepts /pos/... before proxy — call Laravel directly
const apiBase = import.meta.env.DEV ? 'http://localhost:8000/api' : `${webBase}/api`

function detectOS(): Platform {
  const ua = navigator.userAgent.toLowerCase()
  if (ua.includes('win')) return 'windows'
  if (ua.includes('mac')) return 'mac'
  return 'linux'
}

const META = {
  windows: { label: 'Windows', ext: '.exe',      icon: Monitor  },
  mac:     { label: 'macOS',   ext: '.dmg',       icon: Apple    },
  linux:   { label: 'Linux',   ext: '.AppImage',  icon: Terminal },
} as const

type Platform = keyof typeof META

interface PlatformInfo { available: boolean; file: string; size: number | null }
type CheckResult = Record<Platform, PlatformInfo>

function formatBytes(bytes: number): string {
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export default function DesktopDownloadBanner({ forceShow = false }: { forceShow?: boolean }) {
  const { t } = useTranslation('pos')
  const [dismissed, setDismissed] = useState(() => !forceShow && sessionStorage.getItem('dl-banner-dismissed') === '1')
  const [showAll, setShowAll]     = useState(false)
  const [check, setCheck] = useState<CheckResult | null>(null)

  useEffect(() => {
    if (isTauri) return
    fetch(`${apiBase}/desktop-app/check`)
      .then((r) => r.json())
      .then((d) => setCheck(d))
      .catch(() => setCheck(null))
  }, [])

  if (isTauri || dismissed) return null

  const primary = detectOS()
  const others  = (Object.keys(META) as Platform[]).filter((p) => p !== primary)

  const handleDismiss = () => {
    sessionStorage.setItem('dl-banner-dismissed', '1')
    setDismissed(true)
  }

  const handleDownload = (platform: Platform) => {
    const info = check?.[platform]
    if (!info?.available) {
      toast.error(t('download_not_available'))
      return
    }
    const a = document.createElement('a')
    a.href = `${apiBase}/desktop-app/download/${platform}`
    a.download = `Nuqtah-POS-Setup.${info.file.split('.').pop()}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
  }

  const DownloadBtn = ({ platform, variant = 'primary' }: { platform: Platform; variant?: 'primary' | 'secondary' }) => {
    const { label, ext, icon: Icon } = META[platform]
    const info      = check?.[platform]
    const available = info?.available ?? false

    return (
      <button
        onClick={() => handleDownload(platform)}
        disabled={!available}
        title={!available ? t('coming_soon') : undefined}
        className={clsx(
          'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors',
          variant === 'primary'
            ? available
              ? 'bg-primary-600 text-white hover:bg-primary-700 shadow-sm'
              : 'bg-white/10 text-white/50 cursor-not-allowed border border-white/10'
            : available
              ? 'bg-white/10 text-white hover:bg-white/20 border border-white/20'
              : 'bg-white/5 text-white/30 cursor-not-allowed border border-white/10',
        )}
      >
        {!available
          ? <AlertCircle className="h-4 w-4 flex-shrink-0 opacity-50" />
          : <Icon className="h-4 w-4 flex-shrink-0" />}
        <span>{label}</span>
        {available && info?.size ? (
          <span className="opacity-60 text-xs">{formatBytes(info.size)}</span>
        ) : (
          <span className="opacity-50 text-xs">{available ? ext : t('coming_soon')}</span>
        )}
        {available && <Download className="h-3.5 w-3.5 opacity-70" />}
      </button>
    )
  }

  const anyAvailable = check && (Object.keys(META) as Platform[]).some((p) => check[p]?.available)

  return (
    <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-slate-800 to-slate-700 dark:from-slate-900 dark:to-slate-800 p-5 text-white shadow-lg">
      <div className="pointer-events-none absolute inset-0 opacity-5">
        <Monitor className="absolute -right-6 -top-6 h-40 w-40" />
      </div>

      {!forceShow && (
        <button
          onClick={handleDismiss}
          className="absolute top-3 end-3 rounded-md p-1 text-white/50 hover:text-white hover:bg-white/10 transition-colors"
          aria-label="dismiss"
        >
          <X className="h-4 w-4" />
        </button>
      )}

      <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div className="flex-1 min-w-0">
          <p className="text-base font-bold">{t('download_desktop_title')}</p>
          <p className="mt-0.5 text-sm text-white/70">
            {anyAvailable ? t('download_desktop_desc') : t('download_desktop_coming_soon')}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2 flex-shrink-0">
          {check === null ? (
            <div className="flex items-center gap-2 text-white/50 text-sm">
              <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white inline-block" />
              <span>{t('checking_availability')}</span>
            </div>
          ) : (
            <>
              <DownloadBtn platform={primary} variant="primary" />

              <button
                onClick={() => setShowAll((v) => !v)}
                className="inline-flex items-center gap-1 text-xs text-white/60 hover:text-white transition-colors"
              >
                {t('other_platforms')}
                <ChevronDown className={clsx('h-3.5 w-3.5 transition-transform', showAll && 'rotate-180')} />
              </button>

              {showAll && others.map((p) => (
                <DownloadBtn key={p} platform={p} variant="secondary" />
              ))}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
