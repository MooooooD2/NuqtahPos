import { NavLink, useLocation } from 'react-router-dom'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { clsx } from 'clsx'
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Users, Truck,
  ShoppingBag, FileText, BarChart3, Settings, UserCog, BookOpen,
  Heart, Users2, Warehouse, ChevronLeft, ChevronRight, Store,
} from 'lucide-react'

interface NavItem {
  label: string
  path: string
  icon: React.ComponentType<{ className?: string }>
  permission?: string
  badge?: number
}

const navItems: NavItem[] = [
  { label: 'Dashboard',   path: '/',           icon: LayoutDashboard },
  { label: 'POS',         path: '/pos',        icon: ShoppingCart },
  { label: 'Products',    path: '/products',   icon: Package },
  { label: 'Inventory',   path: '/inventory',  icon: Boxes },
  { label: 'Customers',   path: '/customers',  icon: Users },
  { label: 'Suppliers',   path: '/suppliers',  icon: Truck },
  { label: 'Purchases',   path: '/purchases',  icon: ShoppingBag },
  { label: 'Invoices',    path: '/invoices',   icon: FileText },
  { label: 'Accounting',  path: '/accounting', icon: BookOpen },
  { label: 'CRM',         path: '/crm',        icon: Heart },
  { label: 'HR',          path: '/hr',         icon: Users2 },
  { label: 'Warehouse',   path: '/warehouse',  icon: Warehouse },
  { label: 'Reports',     path: '/reports',    icon: BarChart3 },
  { label: 'Users',       path: '/users',      icon: UserCog },
  { label: 'Settings',    path: '/settings',   icon: Settings },
]

export default function Sidebar() {
  const { sidebarCollapsed, sidebarMobileOpen, toggleSidebar, setSidebarMobileOpen } = useUIStore()
  const user = useAuthStore((s) => s.user)
  const location = useLocation()

  return (
    <aside
      className={clsx(
        'fixed inset-y-0 left-0 z-30 flex flex-col bg-sidebar-bg transition-all duration-300',
        sidebarCollapsed ? 'w-16' : 'w-64',
        sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      )}
    >
      {/* Logo */}
      <div className="flex h-16 items-center justify-between px-4 border-b border-slate-700">
        {!sidebarCollapsed && (
          <div className="flex items-center gap-2">
            <Store className="h-7 w-7 text-primary-400" />
            <span className="text-lg font-bold text-white">POS Enterprise</span>
          </div>
        )}
        {sidebarCollapsed && <Store className="h-7 w-7 text-primary-400 mx-auto" />}
        <button
          onClick={toggleSidebar}
          className="hidden lg:flex items-center justify-center h-7 w-7 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
        >
          {sidebarCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
        </button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-0.5">
        {navItems.map((item) => {
          const isActive = item.path === '/'
            ? location.pathname === '/'
            : location.pathname.startsWith(item.path)
          return (
            <NavLink
              key={item.path}
              to={item.path}
              onClick={() => setSidebarMobileOpen(false)}
              className={clsx(
                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors group',
                isActive
                  ? 'bg-primary-600 text-white'
                  : 'text-slate-400 hover:bg-slate-800 hover:text-white',
                sidebarCollapsed && 'justify-center',
              )}
              title={sidebarCollapsed ? item.label : undefined}
            >
              <item.icon className="h-5 w-5 flex-shrink-0" />
              {!sidebarCollapsed && <span>{item.label}</span>}
            </NavLink>
          )
        })}
      </nav>

      {/* User info */}
      {!sidebarCollapsed && user && (
        <div className="p-4 border-t border-slate-700">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white text-sm font-semibold">
              {user.name.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-white truncate">{user.name}</p>
              <p className="text-xs text-slate-400 truncate">{user.role}</p>
            </div>
          </div>
        </div>
      )}
    </aside>
  )
}
