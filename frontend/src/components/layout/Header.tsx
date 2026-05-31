import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { useOfflineStore } from '@/stores/offlineStore'
import { api } from '@/services/api'
import {
  Menu, Search, Moon, Sun, WifiOff, Wifi, Bell, LogOut,
  ChevronDown, User,
} from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

export default function Header() {
  const { theme, setTheme, setSidebarMobileOpen } = useUIStore()
  const { user, logout } = useAuthStore()
  const { isOnline, syncQueue } = useOfflineStore()
  const navigate = useNavigate()
  const [userMenuOpen, setUserMenuOpen] = useState(false)

  const handleLogout = async () => {
    try {
      await api.post('/logout')
    } catch {}
    logout()
    navigate('/login')
    toast.success('Logged out successfully')
  }

  const pendingSync = syncQueue.filter((i) => i.status === 'pending').length

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
        {/* Offline indicator */}
        <div className={clsx(
          'flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
          isOnline ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                   : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        )}>
          {isOnline ? <Wifi className="h-3.5 w-3.5" /> : <WifiOff className="h-3.5 w-3.5" />}
          {isOnline ? 'Online' : `Offline${pendingSync > 0 ? ` (${pendingSync})` : ''}`}
        </div>

        {/* Theme toggle */}
        <button
          onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
          className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400"
        >
          {theme === 'dark' ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
        </button>

        {/* Notifications */}
        <button className="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400">
          <Bell className="h-5 w-5" />
          <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500" />
        </button>

        {/* User menu */}
        <div className="relative">
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
            <>
              <div className="fixed inset-0 z-10" onClick={() => setUserMenuOpen(false)} />
              <div className="absolute right-0 top-10 z-20 w-48 rounded-xl border border-gray-100 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <div className="p-2">
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
            </>
          )}
        </div>
      </div>
    </header>
  )
}
