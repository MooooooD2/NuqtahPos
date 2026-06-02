import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { useOfflineStore } from '@/stores/offlineStore'
import { api, apiGet, apiPost } from '@/services/api'
import {
  Menu, Search, Moon, Sun, WifiOff, Wifi, Bell, LogOut,
  ChevronDown, User, Check, CheckCheck, Trash2,
} from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface AppNotification {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export default function Header() {
  const { theme, setTheme, setSidebarMobileOpen } = useUIStore()
  const { user, logout } = useAuthStore()
  const { isOnline, syncQueue, clearQueue, setOffline } = useOfflineStore()
  const navigate = useNavigate()
  const qc = useQueryClient()

  const [userMenuOpen, setUserMenuOpen] = useState(false)
  const [notifOpen, setNotifOpen] = useState(false)
  const notifRef = useRef<HTMLDivElement>(null)
  const userRef = useRef<HTMLDivElement>(null)

  const pendingSync = syncQueue.filter((i) => i.status === 'pending').length

  // ── Real connectivity check (ping backend every 30s) ─────────────────────
  useEffect(() => {
    const check = () => {
      if (!navigator.onLine) { setOffline(true); return }
      api.get('/settings', { timeout: 5000 })
        .then(() => setOffline(false))
        .catch(() => {
          // Backend unreachable but browser says online → keep online state
          // Only set offline if browser reports offline
          if (!navigator.onLine) setOffline(true)
        })
    }
    check()
    const id = setInterval(check, 30_000)
    return () => clearInterval(id)
  }, [setOffline])

  // ── Notifications ─────────────────────────────────────────────────────────
  const { data: notifData, isLoading: notifLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => apiGet<AppNotification[]>('/notifications'),
    staleTime: 30_000,
    enabled: notifOpen,
    retry: false,
  })

  const notifications = Array.isArray(notifData) ? notifData : []
  const unreadCount = notifications.filter((n) => !n.read_at).length

  const markAllRead = useMutation({
    mutationFn: () => apiPost('/notifications/read-all', {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  })

  const markOneRead = useMutation({
    mutationFn: (id: string) => apiPost(`/notifications/read/${id}`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
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
    toast.success('Logged out successfully')
  }

  const notifLabel = (n: AppNotification) => {
    const d = n.data
    return (d.message as string) ?? (d.title as string) ?? (d.body as string) ?? n.type.split('\\').pop() ?? 'Notification'
  }

  const timeAgo = (dateStr: string) => {
    const diff = Date.now() - new Date(dateStr).getTime()
    const m = Math.floor(diff / 60000)
    if (m < 1) return 'Just now'
    if (m < 60) return `${m}m ago`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}h ago`
    return `${Math.floor(h / 24)}d ago`
  }

  return (
    <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800">
      {/* Left: Mobile menu + Search */}
      <div className="flex items-center gap-4">
        <button
          className="lg:hidden p-1.5 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
          onClick={() => setSidebarMobileOpen(true)}
        >
          <Menu className="h-5 w-5" />
        </button>
        <div className="relative hidden sm:block">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="search"
            placeholder="Search..."
            className="w-72 rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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
          {isOnline ? 'Online' : 'Offline'}
          {pendingSync > 0 && (
            <button
              title={`${pendingSync} pending sync item(s) — click to clear`}
              onClick={() => { clearQueue(); toast.success('Sync queue cleared') }}
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
          >
            <Bell className="h-5 w-5" />
            {unreadCount > 0 && (
              <span className="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                {unreadCount > 9 ? '9+' : unreadCount}
              </span>
            )}
            {unreadCount === 0 && (
              <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600" />
            )}
          </button>

          {notifOpen && (
            <div className="absolute right-0 top-11 z-50 w-80 rounded-xl border border-gray-100 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
              {/* Header */}
              <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                <span className="text-sm font-semibold text-gray-900 dark:text-white">
                  Notifications {unreadCount > 0 && <span className="ml-1 rounded-full bg-red-100 px-1.5 py-0.5 text-xs text-red-600 dark:bg-red-900/40 dark:text-red-400">{unreadCount} new</span>}
                </span>
                {unreadCount > 0 && (
                  <button
                    onClick={() => markAllRead.mutate()}
                    disabled={markAllRead.isPending}
                    className="flex items-center gap-1 text-xs text-primary-600 hover:underline dark:text-primary-400"
                  >
                    <CheckCheck className="h-3.5 w-3.5" /> Mark all read
                  </button>
                )}
              </div>

              {/* List */}
              <div className="max-h-80 overflow-y-auto">
                {notifLoading ? (
                  <div className="flex h-20 items-center justify-center text-sm text-gray-400">Loading…</div>
                ) : notifications.length === 0 ? (
                  <div className="flex h-20 flex-col items-center justify-center gap-1">
                    <Bell className="h-6 w-6 text-gray-300" />
                    <p className="text-xs text-gray-400">No notifications</p>
                  </div>
                ) : notifications.map((n) => (
                  <div
                    key={n.id}
                    className={clsx(
                      'flex items-start gap-3 px-4 py-3 border-b border-gray-50 dark:border-gray-700/50 last:border-0 transition-colors',
                      !n.read_at ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30',
                    )}
                  >
                    {/* Unread dot */}
                    <div className="mt-1.5 flex-shrink-0">
                      {!n.read_at
                        ? <span className="h-2 w-2 rounded-full bg-primary-500 block" />
                        : <span className="h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600 block" />
                      }
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className={clsx('text-sm truncate', !n.read_at ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400')}>
                        {notifLabel(n)}
                      </p>
                      <p className="mt-0.5 text-xs text-gray-400">{timeAgo(n.created_at)}</p>
                    </div>
                    {!n.read_at && (
                      <button
                        onClick={() => markOneRead.mutate(n.id)}
                        title="Mark as read"
                        className="flex-shrink-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-primary-600 dark:hover:bg-gray-700"
                      >
                        <Check className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>
                ))}
              </div>

              {notifications.length > 0 && (
                <div className="border-t border-gray-100 px-4 py-2 dark:border-gray-700">
                  <p className="text-center text-xs text-gray-400">{notifications.length} notification{notifications.length !== 1 ? 's' : ''}</p>
                </div>
              )}
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
            <div className="absolute right-0 top-10 z-20 w-48 rounded-xl border border-gray-100 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
              <div className="p-2">
                <div className="mb-1 px-3 py-2">
                  <p className="text-xs font-semibold text-gray-900 dark:text-white truncate">{user?.name}</p>
                  <p className="text-xs text-gray-400 capitalize">{user?.role}</p>
                </div>
                <div className="border-t border-gray-100 dark:border-gray-700 mb-1" />
                <button className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                  <User className="h-4 w-4" /> Profile
                </button>
                <button
                  onClick={handleLogout}
                  className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                >
                  <LogOut className="h-4 w-4" /> Logout
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
