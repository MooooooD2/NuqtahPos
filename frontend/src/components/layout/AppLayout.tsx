import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Header from './Header'
import TrialBanner from './TrialBanner'
import { useUIStore } from '@/stores/uiStore'
import { clsx } from 'clsx'

export default function AppLayout() {
  const { sidebarCollapsed } = useUIStore()

  return (
    <div className="flex h-full bg-gray-50 dark:bg-gray-900">
      {/* Sidebar */}
      <Sidebar />

      {/* Main content */}
      <div
        className={clsx(
          'flex flex-1 flex-col min-h-0 transition-all duration-300',
          sidebarCollapsed ? 'lg:ml-16 rtl:lg:ml-0 rtl:lg:mr-16' : 'lg:ml-64 rtl:lg:ml-0 rtl:lg:mr-64',
        )}
      >
        <Header />
        <TrialBanner />
        <main className="flex-1 overflow-auto p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
