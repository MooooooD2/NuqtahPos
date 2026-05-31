import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Header from './Header'
import { useUIStore } from '@/stores/uiStore'
import { clsx } from 'clsx'

export default function AppLayout() {
  const { sidebarCollapsed, sidebarMobileOpen } = useUIStore()

  return (
    <div className="flex h-full bg-gray-50 dark:bg-gray-900">
      {/* Sidebar */}
      <Sidebar />

      {/* Mobile overlay */}
      {sidebarMobileOpen && (
        <div
          className="fixed inset-0 z-20 bg-black/50 lg:hidden"
          onClick={() => useUIStore.getState().setSidebarMobileOpen(false)}
        />
      )}

      {/* Main content */}
      <div
        className={clsx(
          'flex flex-1 flex-col min-h-0 transition-all duration-300',
          sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64',
        )}
      >
        <Header />
        <main className="flex-1 overflow-auto p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
