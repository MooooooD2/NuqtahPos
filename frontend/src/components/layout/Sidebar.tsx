import { NavLink, useLocation } from 'react-router-dom'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { usePermission } from '@/hooks/usePermission'
import { clsx } from 'clsx'
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Users, Truck,
  ShoppingBag, FileText, BarChart3, Settings, UserCog, BookOpen,
  Heart, Users2, Warehouse, ChevronLeft, ChevronRight, Store,
  DollarSign, RotateCcw, Banknote, Tag, PackageX, Zap, Gift,
  CreditCard, Receipt, TrendingUp, GitBranch, LineChart,
  Monitor, MessageCircle, Coins, Trash2, PieChart,
  UtensilsCrossed, QrCode, Clock,
} from 'lucide-react'

interface NavItem {
  label: string
  path: string
  icon: React.ComponentType<{ className?: string }>
  permission?: string | string[]
  adminOnly?: boolean
}

const navItems: NavItem[] = [
  { label: 'Dashboard',          path: '/',                 icon: LayoutDashboard },
  { label: 'POS',                path: '/pos',              icon: ShoppingCart,    permission: 'view_pos' },
  { label: 'Products',           path: '/products',         icon: Package,         permission: ['view_products', 'view_warehouse'] },
  { label: 'Inventory',          path: '/inventory',        icon: Boxes,           permission: 'view_warehouse' },
  { label: 'Customers',          path: '/customers',        icon: Users,           permission: ['view_customers', 'view_pos'] },
  { label: 'Suppliers',          path: '/suppliers',        icon: Truck,           permission: 'view_warehouse' },
  { label: 'Supplier Payments',  path: '/supplier-payments',icon: CreditCard,      permission: 'view_warehouse' },
  { label: 'Supplier Accounts',  path: '/supplier-accounts',icon: Receipt,         permission: 'view_warehouse' },
  { label: 'Purchases',          path: '/purchases',        icon: ShoppingBag,     permission: 'view_warehouse' },
  { label: 'Purchase Returns',   path: '/purchase-returns', icon: PackageX,        permission: 'view_warehouse' },
  { label: 'Invoices',           path: '/invoices',         icon: FileText,        permission: ['view_pos', 'view_reports'] },
  { label: 'Returns',            path: '/returns',          icon: RotateCcw,       permission: 'view_returns' },
  { label: 'Expenses',           path: '/expenses',         icon: DollarSign,      permission: 'view_pos' },
  { label: 'Cash Register',      path: '/cash-register',    icon: Banknote,        permission: 'view_pos' },
  { label: 'Promotions',         path: '/promotions',       icon: Tag,             permission: 'view_reports' },
  { label: 'Pricing Rules',      path: '/pricing-rules',    icon: Zap,             permission: 'view_reports' },
  { label: 'Cashback',           path: '/cashback',         icon: Gift,            permission: 'manage_cashback' },
  { label: 'Accounting',         path: '/accounting',       icon: BookOpen,        permission: 'view_accounting' },
  { label: 'CRM',                path: '/crm',              icon: Heart,           permission: 'view_warehouse' },
  { label: 'HR',                 path: '/hr',               icon: Users2,          permission: 'manage_hr' },
  { label: 'Warehouse',          path: '/warehouse',        icon: Warehouse,       permission: 'view_warehouse' },
  { label: 'Reports',            path: '/reports',          icon: BarChart3,       permission: 'view_reports' },
  { label: 'Profit Reports',     path: '/profit-reports',   icon: TrendingUp,      permission: 'view_reports' },
  { label: 'Forecasting',        path: '/forecasting',      icon: LineChart,       permission: 'view_reports' },
  { label: 'Users',              path: '/users',            icon: UserCog,         permission: 'manage_roles' },
  { label: 'Branches',           path: '/branches',         icon: GitBranch,       permission: 'manage_roles' },
  { label: 'WhatsApp',           path: '/whatsapp',         icon: MessageCircle,   permission: 'manage_roles' },
  { label: 'Device Sessions',    path: '/device-sessions',  icon: Monitor },
  { label: 'Kitchen',            path: '/kitchen',          icon: UtensilsCrossed, permission: 'view_kitchen' },
  { label: 'QR Tables',          path: '/qr',               icon: QrCode,          permission: 'manage_qr_orders' },
  { label: 'Waste Recording',    path: '/waste',            icon: Trash2,          permission: 'manage_waste' },
  { label: 'Financial Reports',  path: '/financial-reports',icon: PieChart,        permission: 'view_accounting' },
  { label: 'Currencies',         path: '/currencies',       icon: Coins,           permission: 'manage_roles' },
  { label: 'My Shift',           path: '/my-shift',         icon: Clock },
  { label: 'Settings',           path: '/settings',         icon: Settings,        permission: 'manage_settings' },
]

export default function Sidebar() {
  const { sidebarCollapsed, sidebarMobileOpen, toggleSidebar, setSidebarMobileOpen } = useUIStore()
  const user = useAuthStore((s) => s.user)
  const location = useLocation()
  const { hasPermission, isAdmin } = usePermission()

  const visibleItems = navItems.filter((item) => {
    if (!item.permission) return true
    if (item.adminOnly && !isAdmin) return false
    const perms = Array.isArray(item.permission) ? item.permission : [item.permission]
    return hasPermission(...perms)
  })

  return (
    <>
      {/* Mobile overlay */}
      {sidebarMobileOpen && (
        <div
          className="fixed inset-0 z-20 bg-black/50 lg:hidden"
          onClick={() => setSidebarMobileOpen(false)}
        />
      )}

      <aside
        className={clsx(
          'fixed inset-y-0 left-0 z-30 flex flex-col bg-sidebar-bg transition-all duration-300',
          sidebarCollapsed ? 'w-16' : 'w-64',
          sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        )}
      >
        {/* Logo */}
        <div className="flex h-16 items-center justify-between px-4 border-b border-slate-700 flex-shrink-0">
          {!sidebarCollapsed && (
            <div className="flex items-center gap-2 min-w-0">
              <Store className="h-7 w-7 text-primary-400 flex-shrink-0" />
              <span className="text-lg font-bold text-white truncate">POS Enterprise</span>
            </div>
          )}
          {sidebarCollapsed && <Store className="h-7 w-7 text-primary-400 mx-auto" />}
          <button
            onClick={toggleSidebar}
            className="hidden lg:flex items-center justify-center h-7 w-7 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors flex-shrink-0"
          >
            {sidebarCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-0.5">
          {visibleItems.map((item) => {
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
          <div className="p-4 border-t border-slate-700 flex-shrink-0">
            <div className="flex items-center gap-3">
              <div className="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                {user.name.charAt(0).toUpperCase()}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white truncate">{user.name}</p>
                <p className="text-xs text-slate-400 truncate capitalize">{user.role}</p>
              </div>
            </div>
          </div>
        )}
      </aside>
    </>
  )
}
