import { NavLink, useLocation } from 'react-router-dom'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { usePermission } from '@/hooks/usePermission'
import { useTranslation } from 'react-i18next'
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
  labelKey: string
  path: string
  icon: React.ComponentType<{ className?: string }>
  permission?: string | string[]
  adminOnly?: boolean
}

const navItems: NavItem[] = [
  { labelKey: 'dashboard',           path: '/',                  icon: LayoutDashboard },
  { labelKey: 'pos',                 path: '/pos',               icon: ShoppingCart,    permission: 'view_pos' },
  { labelKey: 'products',            path: '/products',          icon: Package,         permission: ['view_products', 'view_warehouse'] },
  { labelKey: 'inventory',           path: '/inventory',         icon: Boxes,           permission: 'view_warehouse' },
  { labelKey: 'customers',           path: '/customers',         icon: Users,           permission: ['view_customers', 'view_pos'] },
  { labelKey: 'suppliers',           path: '/suppliers',         icon: Truck,           permission: 'view_warehouse' },
  { labelKey: 'supplier_payments',   path: '/supplier-payments', icon: CreditCard,      permission: 'view_warehouse' },
  { labelKey: 'supplier_accounts',   path: '/supplier-accounts', icon: Receipt,         permission: 'view_warehouse' },
  { labelKey: 'purchase_orders',     path: '/purchases',         icon: ShoppingBag,     permission: 'view_warehouse' },
  { labelKey: 'purchase_returns',    path: '/purchase-returns',  icon: PackageX,        permission: 'view_warehouse' },
  { labelKey: 'invoices',            path: '/invoices',          icon: FileText,        permission: ['view_pos', 'view_reports'] },
  { labelKey: 'returns',             path: '/returns',           icon: RotateCcw,       permission: 'view_returns' },
  { labelKey: 'expenses',            path: '/expenses',          icon: DollarSign,      permission: 'view_pos' },
  { labelKey: 'cash_register_reconciliation', path: '/cash-register', icon: Banknote,  permission: 'view_pos' },
  { labelKey: 'promotions',          path: '/promotions',        icon: Tag,             permission: 'view_reports' },
  { labelKey: 'pricing_rules',       path: '/pricing-rules',     icon: Zap,             permission: 'view_reports' },
  { labelKey: 'cashback',            path: '/cashback',          icon: Gift,            permission: 'manage_cashback' },
  { labelKey: 'accounting',          path: '/accounting',        icon: BookOpen,        permission: 'view_accounting' },
  { labelKey: 'crm',                 path: '/crm',               icon: Heart,           permission: 'view_warehouse' },
  { labelKey: 'hr_module',           path: '/hr',                icon: Users2,          permission: 'manage_hr' },
  { labelKey: 'warehouse',           path: '/warehouse',         icon: Warehouse,       permission: 'view_warehouse' },
  { labelKey: 'reports',             path: '/reports',           icon: BarChart3,       permission: 'view_reports' },
  { labelKey: 'profit_reports',      path: '/profit-reports',    icon: TrendingUp,      permission: 'view_reports' },
  { labelKey: 'ai_forecasting_title',path: '/forecasting',       icon: LineChart,       permission: 'view_reports' },
  { labelKey: 'users_roles',         path: '/users',             icon: UserCog,         permission: 'manage_roles' },
  { labelKey: 'branches',            path: '/branches',          icon: GitBranch,       permission: 'manage_roles' },
  { labelKey: 'whatsapp',            path: '/whatsapp',          icon: MessageCircle,   permission: 'manage_roles' },
  { labelKey: 'device_sessions',     path: '/device-sessions',   icon: Monitor },
  { labelKey: 'kitchen_display',     path: '/kitchen',           icon: UtensilsCrossed, permission: 'view_kitchen' },
  { labelKey: 'qr_tables',           path: '/qr',                icon: QrCode,          permission: 'manage_qr_orders' },
  { labelKey: 'waste_management',    path: '/waste',             icon: Trash2,          permission: 'manage_waste' },
  { labelKey: 'financial_reports',   path: '/financial-reports', icon: PieChart,        permission: 'view_accounting' },
  { labelKey: 'currencies',          path: '/currencies',        icon: Coins,           permission: 'manage_roles' },
  { labelKey: 'my_shift',            path: '/my-shift',          icon: Clock },
  { labelKey: 'settings',            path: '/settings',          icon: Settings,        permission: 'manage_settings' },
]

export default function Sidebar() {
  const { sidebarCollapsed, sidebarMobileOpen, toggleSidebar, setSidebarMobileOpen } = useUIStore()
  const user = useAuthStore((s) => s.user)
  const location = useLocation()
  const { hasPermission, isAdmin } = usePermission()
  const { t } = useTranslation('pos')

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
          'fixed inset-y-0 z-30 flex flex-col bg-sidebar-bg transition-all duration-300',
          sidebarCollapsed ? 'w-16' : 'w-64',
          sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
          'ltr:left-0 rtl:right-0 rtl:translate-x-full rtl:lg:translate-x-0',
        )}
      >
        {/* Logo */}
        <div className="flex h-16 items-center justify-between px-4 border-b border-slate-700 flex-shrink-0">
          {!sidebarCollapsed && (
            <div className="flex items-center gap-2 min-w-0">
              <Store className="h-7 w-7 text-primary-400 flex-shrink-0" />
              <span className="text-lg font-bold text-white truncate">{t('app_name')}</span>
            </div>
          )}
          {sidebarCollapsed && <Store className="h-7 w-7 text-primary-400 mx-auto" />}
          <button
            onClick={toggleSidebar}
            className="hidden lg:flex items-center justify-center h-7 w-7 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 transition-colors flex-shrink-0"
          >
            {sidebarCollapsed
              ? <ChevronRight className="h-4 w-4 rtl:rotate-180" />
              : <ChevronLeft className="h-4 w-4 rtl:rotate-180" />}
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-0.5">
          {visibleItems.map((item) => {
            const label = t(item.labelKey)
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
                title={sidebarCollapsed ? label : undefined}
              >
                <item.icon className="h-5 w-5 flex-shrink-0" />
                {!sidebarCollapsed && <span>{label}</span>}
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
