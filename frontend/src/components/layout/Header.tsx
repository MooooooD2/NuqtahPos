import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { useOfflineStore } from '@/stores/offlineStore'
import { usePermission } from '@/hooks/usePermission'
import { api, apiGet, apiPost } from '@/services/api'
import {
  Menu, Search, Moon, Sun, WifiOff, Wifi, Bell, LogOut,
  ChevronDown, User, Check, CheckCheck, Trash2,
  AlertTriangle, Package, ShoppingCart, DollarSign, UserCheck,
  Info, TrendingDown, Clock, RefreshCw, Zap,
  RotateCcw, ShoppingBag, Calendar,
} from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

export interface AppNotification {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

function notifIcon(type: string, data?: Record<string, unknown>) {
  const t = type.split('\\').pop()?.toLowerCase() ?? ''
  // Check data fields too for general notifications
  const msg = String(data?.message ?? '').toLowerCase()

  if (t.includes('lowstock') || t.includes('stock') || t.includes('inventory') || msg.includes('low stock') || msg.includes('out of stock'))
    return <Package className="h-4 w-4 text-amber-500" />
  if (t.includes('newinvoice') || t.includes('sale') || msg.startsWith('new sale'))
    return <ShoppingCart className="h-4 w-4 text-green-500" />
  if (t.includes('return') || msg.includes('return'))
    return <RotateCcw className="h-4 w-4 text-orange-500" />
  if (t.includes('purchase') || t.includes('purchaseorder') || msg.startsWith('new po'))
    return <ShoppingBag className="h-4 w-4 text-indigo-500" />
  if (t.includes('expense') || msg.startsWith('expense'))
    return <DollarSign className="h-4 w-4 text-red-500" />
  if (t.includes('payment'))
    return <DollarSign className="h-4 w-4 text-blue-500" />
  if (t.includes('leave') || msg.includes('leave request'))
    return <Calendar className="h-4 w-4 text-purple-500" />
  if (t.includes('user') || t.includes('employee'))
    return <UserCheck className="h-4 w-4 text-purple-500" />
  if (t.includes('expir') || t.includes('waste'))
    return <TrendingDown className="h-4 w-4 text-red-500" />
  if (t.includes('alert') || t.includes('warn'))
    return <AlertTriangle className="h-4 w-4 text-orange-500" />
  if (t.includes('shift') || t.includes('clock'))
    return <Clock className="h-4 w-4 text-teal-500" />
  if (t.includes('sync') || t.includes('backup'))
    return <RefreshCw className="h-4 w-4 text-gray-500" />
  return <Info className="h-4 w-4 text-primary-500" />
}

function getNotifLink(type: string, data: Record<string, unknown>): string | null {
  const t = type.split('\\').pop()?.toLowerCase() ?? ''
  const msg = String(data?.message ?? '').toLowerCase()

  if (t === 'lowstocknotification' || msg.includes('low stock') || msg.includes('out of stock'))
    return '/inventory'

  if (t === 'newinvoicenotification' || msg.startsWith('new sale')) {
    const inv = data.invoice_number as string | undefined
    return inv ? `/invoices?search=${encodeURIComponent(inv)}` : '/invoices'
  }

  if (t === 'returnprocessednotification' || msg.startsWith('return processed')) {
    const ret = data.invoice_number as string | undefined
    return ret ? `/invoices?search=${encodeURIComponent(ret)}` : '/returns'
  }

  if (t.includes('purchaseorder') || msg.startsWith('new po')) {
    const po = data.po_number as string | undefined
    return po ? `/purchases?search=${encodeURIComponent(po)}` : '/purchases'
  }

  if (msg.startsWith('expense'))
    return '/expenses'

  if (t === 'leaverequestnotification' || msg.startsWith('leave request'))
    return '/hr?tab=leaves'

  if (t.includes('stock') || t.includes('inventory'))
    return '/inventory'

  if (t.includes('invoice') || t.includes('sale'))
    return '/invoices'

  if (t.includes('return'))
    return '/returns'

  if (t.includes('payment') || t.includes('expense'))
    return '/expenses'

  return null
}

function notifLabel(n: AppNotification): string {
  const d = n.data
  return (
    (d.message as string) ??
    (d.title as string) ??
    (d.body as string) ??
    (d.subject as string) ??
    n.type.split('\\').pop()?.replace(/([A-Z])/g, ' $1').trim() ??
    'Notification'
  )
}

function notifSub(n: AppNotification): string | null {
  const d = n.data
  return (d.subtitle as string) ?? (d.detail as string) ?? null
}

function timeAgo(dateStr: string, tFn: (key: string, opts?: Record<string, unknown>) => string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1) return tFn('just_now')
  if (m < 60) return tFn('minutes_ago', { m })
  const h = Math.floor(m / 60)
  if (h < 24) return tFn('hours_ago', { h })
  return tFn('days_ago', { d: Math.floor(h / 24) })
}

export default function Header() {
  const { theme, setTheme, setSidebarMobileOpen } = useUIStore()
  const { user, logout } = useAuthStore()
  const { isOnline, syncQueue, clearQueue, setOffline } = useOfflineStore()
  const { isAdmin } = usePermission()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { t } = useTranslation('pos')

  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [notifOpen, setNotifOpen] = useState(false)
  const notifRef = useRef<HTMLDivElement>(null)
  const userRef = useRef<HTMLDivElement>(null)
  const prevUnreadRef = useRef(0)

  const pendingSync = syncQueue.filter((i) => i.status === 'pending').length

  // ── Real connectivity check (ping backend every 30s) ─────────────────────
  useEffect(() => {
    const check = () => {
      if (!navigator.onLine) { setOffline(true); return }
      api.get('/settings', { timeout: 5000 })
        .then(() => setOffline(false))
        .catch(() => { if (!navigator.onLine) setOffline(true) })
    }
    check()
    const id = setInterval(check, 30_000)
    return () => clearInterval(id)
  }, [setOffline])

  // ── Notifications — always poll every 60s so the badge updates ────────────
  const { data: notifData, isLoading: notifLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => apiGet<AppNotification[]>('/notifications'),
    staleTime: 15_000,
    refetchInterval: 30_000,
    retry: false,
  })

  const notifications: AppNotification[] = Array.isArray(notifData) ? notifData : []
  const unreadCount = notifications.filter((n) => !n.read_at).length

  // Toast when new unread notifications arrive while dropdown is closed
  useEffect(() => {
    const prev = prevUnreadRef.current
    if (!notifOpen && unreadCount > prev && prev >= 0) {
      const diff = unreadCount - prev
      toast(diff === 1 ? t('new_notif_1') : t('new_notif_n', { n: diff }), {
        icon: '🔔',
        duration: 3000,
        id: 'new-notif',
      })
    }
    prevUnreadRef.current = unreadCount
  }, [unreadCount, notifOpen])

  const markAllRead = useMutation({
    mutationFn: () => apiPost('/notifications/read-all', {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  })

  const markOneRead = useMutation({
    mutationFn: (id: string) => apiPost(`/notifications/read/${id}`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  })

  const testNotif = useMutation({
    mutationFn: (type: string) => apiPost('/dev/test-notification', { type }),
    onSuccess: (_, type) => {
      toast.success(`Test [${type}] notification sent!`)
      setTimeout(() => qc.invalidateQueries({ queryKey: ['notifications'] }), 500)
    },
    onError: () => toast.error('Test notification failed — check backend logs'),
  })

  // Close dropdowns on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) setNotifOpen(false)
      if (userRef.current && !userRef.current.contains(e.target as Node)) setUserMenuOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const handleLogout = async () => {
    try { await api.post('/logout') } catch {}
    logout()
    navigate('/login')
    toast.success(t('logout_success'))
  }

  return (
    <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800 no-print">
      {/* Left: Mobile menu + Search */}
      <div className="flex items-center gap-4">
        <button
          className="lg:hidden p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
          onClick={() => setSidebarMobileOpen(true)}
        >
          <Menu className="h-5 w-5" />
        </button>
        <div className="relative hidden sm:block">
          <Search className="absolute left-3 rtl:left-auto rtl:right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="search"
            placeholder={t('search_placeholder')}
            className="w-72 rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-4 rtl:pl-4 rtl:pr-9 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          />
        </div>
      </div>

      {/* Right: actions */}
      <div className="flex items-center gap-2">

        {/* ── Online/Offline indicator ─────────────────────────────────── */}
        <div className={clsx(
          'flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium cursor-default',
          isOnline
            ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'
            : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        )}>
          {isOnline ? <Wifi className="h-3.5 w-3.5" /> : <WifiOff className="h-3.5 w-3.5" />}
          {isOnline ? t('online') : t('offline')}
          {pendingSync > 0 && (
            <button
              title={t('pending_sync_title', { n: pendingSync })}
              onClick={() => { clearQueue(); toast.success(t('sync_queue_cleared')) }}
              className="ml-1 flex items-center gap-0.5 underline hover:no-underline"
            >
              ({pendingSync}) <Trash2 className="h-3 w-3" />
            </button>
          )}
        </div>

        {/* ── Theme toggle ─────────────────────────────────────────────── */}
        <button
          onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
          className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400"
        >
          {theme === 'dark' ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
        </button>

        {/* ── Notifications ────────────────────────────────────────────── */}
        <div ref={notifRef} className="relative">
          <button
            onClick={() => setNotifOpen((v) => !v)}
            className="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400"
            aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ''}`}
          >
            <Bell className="h-5 w-5" />
            {unreadCount > 0 ? (
              <span className="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white animate-pulse">
                {unreadCount > 9 ? '9+' : unreadCount}
              </span>
            ) : (
              <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600" />
            )}
          </button>

          {notifOpen && (
            <div className="absolute right-0 rtl:right-auto rtl:left-0 top-11 z-50 w-96 rounded-xl border border-gray-100 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
              {/* Header */}
              <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                <span className="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <Bell className="h-4 w-4 text-primary-500" />
                  {t('notifications')}
                  {unreadCount > 0 && (
                    <span className="rounded-full bg-red-100 px-1.5 py-0.5 text-xs text-red-600 dark:bg-red-900/40 dark:text-red-400">
                      {unreadCount} {t('new_label')}
                    </span>
                  )}
                </span>
                <div className="flex items-center gap-2">
                  {unreadCount > 0 && (
                    <button
                      onClick={() => markAllRead.mutate()}
                      disabled={markAllRead.isPending}
                      className="flex items-center gap-1 text-xs text-primary-600 hover:underline dark:text-primary-400"
                    >
                      <CheckCheck className="h-3.5 w-3.5" />
                      {t('mark_all_read')}
                    </button>
                  )}
                </div>
              </div>

              {/* List */}
              <div className="max-h-96 overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700/50">
                {notifLoading ? (
                  <div className="flex h-24 items-center justify-center gap-2 text-sm text-gray-400">
                    <div className="h-4 w-4 border-2 border-primary-400 border-t-transparent rounded-full animate-spin" />
                    {t('loading')}
                  </div>
                ) : notifications.length === 0 ? (
                  <div className="flex h-24 flex-col items-center justify-center gap-2">
                    <Bell className="h-8 w-8 text-gray-200 dark:text-gray-600" />
                    <p className="text-xs text-gray-400">{t('no_notifications')}</p>
                  </div>
                ) : notifications.map((n) => {
                  const label = notifLabel(n)
                  const sub = notifSub(n)
                  const isUnread = !n.read_at
                  const link = getNotifLink(n.type, n.data)

                  const handleClick = () => {
                    if (isUnread) markOneRead.mutate(n.id)
                    if (link) {
                      setNotifOpen(false)
                      navigate(link)
                    }
                  }

                  return (
                    <div
                      key={n.id}
                      onClick={handleClick}
                      role={link ? 'button' : undefined}
                      tabIndex={link ? 0 : undefined}
                      onKeyDown={link ? (e) => e.key === 'Enter' && handleClick() : undefined}
                      className={clsx(
                        'flex items-start gap-3 px-4 py-3 transition-colors',
                        link ? 'cursor-pointer' : 'cursor-default',
                        isUnread
                          ? 'bg-primary-50/60 dark:bg-primary-900/10 hover:bg-primary-50 dark:hover:bg-primary-900/20'
                          : 'hover:bg-gray-50 dark:hover:bg-gray-700/30',
                      )}
                    >
                      {/* Icon */}
                      <div className={clsx(
                        'mt-0.5 flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0',
                        isUnread
                          ? 'bg-primary-100 dark:bg-primary-900/40'
                          : 'bg-gray-100 dark:bg-gray-700',
                      )}>
                        {notifIcon(n.type, n.data)}
                      </div>

                      {/* Content */}
                      <div className="flex-1 min-w-0">
                        <p className={clsx(
                          'text-sm leading-snug',
                          isUnread ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400',
                        )}>
                          {label}
                        </p>
                        {sub && (
                          <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500 truncate">{sub}</p>
                        )}
                        <div className="mt-1 flex items-center gap-2">
                          <p className="text-xs text-gray-400 dark:text-gray-500">{timeAgo(n.created_at, t)}</p>
                          {link && (
                            <span className="text-[10px] text-primary-500 dark:text-primary-400 font-medium flex items-center gap-0.5">
                              {t('view_link')} →
                            </span>
                          )}
                        </div>
                      </div>

                      {/* Mark read button — stop propagation so it doesn't trigger navigation */}
                      {isUnread && (
                        <button
                          onClick={(e) => { e.stopPropagation(); markOneRead.mutate(n.id) }}
                          title={t('mark_as_read')}
                          className="flex-shrink-0 rounded-full p-1 text-gray-300 hover:bg-primary-100 hover:text-primary-600 dark:hover:bg-primary-900/40 dark:text-gray-500 dark:hover:text-primary-400"
                        >
                          <Check className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>
                  )
                })}
              </div>

              {/* Footer */}
              <div className="border-t border-gray-100 px-4 py-2 dark:border-gray-700 space-y-2">
                <div className="flex items-center justify-between">
                  <p className="text-xs text-gray-400">
                    {notifications.length > 0
                      ? `${notifications.length} ${t('notifications').toLowerCase()}${unreadCount > 0 ? ` · ${unreadCount} ${t('unread_label')}` : ''}`
                      : t('no_notifications')}
                  </p>
                  <button
                    onClick={() => qc.invalidateQueries({ queryKey: ['notifications'] })}
                    className="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 flex items-center gap-1"
                  >
                    <RefreshCw className="h-3 w-3" /> {t('refresh')}
                  </button>
                </div>

                {/* Admin-only: test notification buttons */}
                {isAdmin && (
                  <div className="flex flex-wrap gap-1">
                    <p className="w-full text-[10px] text-gray-400 mb-0.5 flex items-center gap-1">
                      <Zap className="h-3 w-3" /> {t('test_notifications')}
                    </p>
                    {(['low_stock', 'new_invoice', 'leave', 'custom'] as const).map((type) => (
                      <button
                        key={type}
                        onClick={() => testNotif.mutate(type)}
                        disabled={testNotif.isPending}
                        className="px-2 py-0.5 rounded text-[10px] bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-primary-100 hover:text-primary-700 dark:hover:bg-primary-900/30 dark:hover:text-primary-400 capitalize transition-colors disabled:opacity-40"
                      >
                        {type.replace('_', ' ')}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* ── User menu ────────────────────────────────────────────────── */}
        <div ref={userRef} className="relative">
          <button
            onClick={() => setUserMenuOpen((v) => !v)}
            className="flex items-center gap-2 rounded-lg p-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <div className="h-7 w-7 rounded-full bg-primary-500 flex items-center justify-center text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </div>
            <span className="hidden md:block text-gray-700 dark:text-gray-300">{user?.name}</span>
            <ChevronDown className="h-4 w-4 text-gray-400" />
          </button>

          {userMenuOpen && (
            <div className="absolute right-0 rtl:right-auto rtl:left-0 top-10 z-20 w-48 rounded-xl border border-gray-100 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
              <div className="p-2">
                <div className="mb-1 px-3 py-2">
                  <p className="text-xs font-semibold text-gray-900 dark:text-white truncate">{user?.name}</p>
                  <p className="text-xs text-gray-400 capitalize">{user?.role}</p>
                </div>
                <div className="border-t border-gray-100 dark:border-gray-700 mb-1" />
                <button className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                  <User className="h-4 w-4" /> {t('profile')}
                </button>
                <button
                  onClick={handleLogout}
                  className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                >
                  <LogOut className="h-4 w-4" /> {t('logout')}
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
