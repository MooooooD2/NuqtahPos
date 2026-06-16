import { NavLink, useLocation } from 'react-router-dom'
import { useUIStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/authStore'
import { usePermission } from '@/hooks/usePermission'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/services/api'
import { clsx } from 'clsx'
import {
  LayoutDashboard, ShoppingCart, Package, Boxes, Users, Truck,
  ShoppingBag, FileText, BarChart3, Settings, UserCog, BookOpen,
  Heart, Users2, Warehouse, ChevronLeft, ChevronRight, Store,
  DollarSign, RotateCcw, Banknote, Tag, PackageX, Zap, Gift,
  CreditCard, Receipt, TrendingUp, GitBranch, LineChart,
  Monitor, MessageCircle, Coins, Trash2, PieChart,
  UtensilsCrossed, QrCode, Clock, Pill, LayoutGrid, Building2,
} from 'lucide-react'

interface NavItem {
  labelKey: string
  path: string
  icon: React.ComponentType<{ className?: string }>
  permission?: string | string[]
  adminOnly?: boolean
  planFeature?: string
  /** If set, item is only shown when current business type is in this list */
  businessTypes?: string[]
}

interface NavGroup {
  groupKey: string
  items: NavItem[]
}

// Business types that use POS / retail flows
const POS_TYPES = ['retail', 'restaurant', 'pharmacy', 'general']
// Business types that see restaurant-specific modules
const RESTAURANT_TYPES = ['restaurant', 'general']
// Pharmacy-specific modules
const PHARMACY_TYPES = ['pharmacy', 'general']
// Sales-marketing tools (not meaningful for contracting)
const SALES_TYPES = ['retail', 'restaurant', 'pharmacy', 'general']

const navGroups: NavGroup[] = [
  {
    groupKey: 'nav_group_main',
    items: [
      { labelKey: 'dashboard',  path: '/',         icon: LayoutDashboard },
      { labelKey: 'pos',        path: '/pos',       icon: ShoppingCart,       permission: 'view_pos',    planFeature: 'pos',       businessTypes: POS_TYPES },
      { labelKey: 'my_shift',   path: '/my-shift',  icon: Clock,                                                                   businessTypes: POS_TYPES },
    ],
  },
  {
    groupKey: 'nav_group_sales',
    items: [
      { labelKey: 'invoices',      path: '/invoices',       icon: FileText,   permission: ['view_pos', 'view_reports'],   planFeature: 'reports_basic' },
      { labelKey: 'returns',       path: '/returns',        icon: RotateCcw,  permission: 'view_returns',                planFeature: 'returns',        businessTypes: POS_TYPES },
      { labelKey: 'customers',     path: '/customers',      icon: Users,      permission: ['view_customers', 'view_pos'], planFeature: 'customers' },
      { labelKey: 'promotions',    path: '/promotions',     icon: Tag,        permission: 'view_reports',                planFeature: 'promotions',     businessTypes: SALES_TYPES },
      { labelKey: 'pricing_rules', path: '/pricing-rules',  icon: Zap,        permission: 'view_reports',                planFeature: 'pricing_rules',  businessTypes: SALES_TYPES },
      { labelKey: 'cashback',      path: '/cashback',       icon: Gift,       permission: 'manage_cashback',             planFeature: 'cashback',       businessTypes: SALES_TYPES },
      { labelKey: 'crm',           path: '/crm',            icon: Heart,      permission: 'view_warehouse',              planFeature: 'crm',            businessTypes: SALES_TYPES },
    ],
  },
  {
    groupKey: 'nav_group_inventory',
    items: [
      { labelKey: 'products',         path: '/products',   icon: Package,   permission: ['view_products', 'view_warehouse'], planFeature: 'inventory' },
      { labelKey: 'inventory',        path: '/inventory',  icon: Boxes,     permission: 'view_warehouse',                    planFeature: 'inventory' },
      { labelKey: 'warehouse',        path: '/warehouse',  icon: Warehouse, permission: 'view_warehouse',                    planFeature: 'multi_warehouse' },
      { labelKey: 'pharmacy',         path: '/pharmacy',   icon: Pill,      permission: 'view_warehouse',                    planFeature: 'pharmacy',       businessTypes: PHARMACY_TYPES },
      { labelKey: 'waste_management', path: '/waste',      icon: Trash2,    permission: 'manage_waste',                      planFeature: 'waste_tracking' },
    ],
  },
  {
    groupKey: 'nav_group_purchasing',
    items: [
      { labelKey: 'suppliers',          path: '/suppliers',          icon: Truck,       permission: 'view_warehouse', planFeature: 'purchase_orders' },
      { labelKey: 'purchase_orders',    path: '/purchases',          icon: ShoppingBag, permission: 'view_warehouse', planFeature: 'purchase_orders' },
      { labelKey: 'purchase_returns',   path: '/purchase-returns',   icon: PackageX,    permission: 'view_warehouse', planFeature: 'purchase_orders' },
      { labelKey: 'supplier_payments',  path: '/supplier-payments',  icon: CreditCard,  permission: 'view_warehouse', planFeature: 'purchase_orders' },
      { labelKey: 'supplier_accounts',  path: '/supplier-accounts',  icon: Receipt,     permission: 'view_warehouse', planFeature: 'purchase_orders' },
    ],
  },
  {
    groupKey: 'nav_group_finance',
    items: [
      { labelKey: 'expenses',                     path: '/expenses',          icon: DollarSign, permission: 'view_pos',        planFeature: 'expenses' },
      { labelKey: 'cash_register_reconciliation', path: '/cash-register',     icon: Banknote,   permission: 'view_pos',        planFeature: 'pos',               businessTypes: POS_TYPES },
      { labelKey: 'accounting',                   path: '/accounting',         icon: BookOpen,   permission: 'view_accounting', planFeature: 'accounting' },
      { labelKey: 'financial_reports',            path: '/financial-reports',  icon: PieChart,   permission: 'view_accounting', planFeature: 'reports_financial' },
      { labelKey: 'reports',                      path: '/reports',            icon: BarChart3,  permission: 'view_reports',    planFeature: 'reports_basic' },
      { labelKey: 'profit_reports',               path: '/profit-reports',     icon: TrendingUp, permission: 'view_reports',    planFeature: 'reports_advanced' },
    ],
  },
  {
    groupKey: 'nav_group_operations',
    items: [
      { labelKey: 'kitchen_display',      path: '/kitchen',     icon: UtensilsCrossed, permission: 'view_kitchen',      planFeature: 'kitchen_display', businessTypes: RESTAURANT_TYPES },
      { labelKey: 'qr_tables',            path: '/qr',          icon: QrCode,          permission: 'manage_qr_orders',  planFeature: 'qr_ordering',     businessTypes: RESTAURANT_TYPES },
      { labelKey: 'whatsapp',             path: '/whatsapp',    icon: MessageCircle,   permission: 'manage_roles',      planFeature: 'whatsapp' },
      { labelKey: 'ai_forecasting_title', path: '/forecasting', icon: LineChart,       permission: 'view_reports',      planFeature: 'ai_forecasting' },
    ],
  },
  {
    groupKey: 'nav_group_admin',
    items: [
      { labelKey: 'users_roles',     path: '/users',           icon: UserCog,   permission: 'manage_roles' },
      { labelKey: 'branches',        path: '/branches',        icon: GitBranch, permission: 'manage_roles',  planFeature: 'multi_branch' },
      { labelKey: 'hr_module',       path: '/hr',              icon: Users2,    permission: 'manage_hr',     planFeature: 'hr_module' },
      { labelKey: 'currencies',      path: '/currencies',      icon: Coins,     permission: 'manage_roles',  planFeature: 'currencies' },
      { labelKey: 'device_sessions', path: '/device-sessions', icon: Monitor,                                planFeature: 'device_sessions' },
      { labelKey: 'settings',        path: '/settings',        icon: Settings,  permission: 'manage_settings' },
    ],
  },
]

export default function Sidebar() {
  const { sidebarCollapsed, sidebarMobileOpen, toggleSidebar, setSidebarMobileOpen, language } = useUIStore()
  const isRTL = language === 'ar'
  const user = useAuthStore((s) => s.user)
  const location = useLocation()
  const { hasPermission } = usePermission()
  const { t } = useTranslation('pos')

  const companyCode = localStorage.getItem('pos-company-code') ?? ''
  const isMaster = companyCode === 'main'

  const { data: sub } = useQuery({
    queryKey: ['subscription'],
    queryFn: () => api.get<{ features: string[]; business_type?: string }>('/subscription').then((r) => r.data),
    staleTime: 1000 * 60 * 10,
    enabled: !!user && !isMaster,
  })

  // Master tenant always gets all features; non-master use plan features (null = still loading → show all)
  const planFeatures: string[] | null = isMaster ? null : (sub?.features ?? null)
  // business_type drives which modules are visible; null means still loading → show all
  const businessType: string | null = isMaster ? null : (sub?.business_type ?? null)

  const isVisible = (item: NavItem) => {
    if (item.adminOnly && user?.role !== 'admin') return false
    if (item.permission) {
      const perms = Array.isArray(item.permission) ? item.permission : [item.permission]
      if (!hasPermission(...perms)) return false
    }
    if (item.planFeature && planFeatures !== null && !planFeatures.includes(item.planFeature)) return false
    if (item.businessTypes && businessType && !item.businessTypes.includes(businessType)) return false
    return true
  }

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
          isRTL ? 'right-0' : 'left-0',
          sidebarMobileOpen
            ? 'translate-x-0'
            : isRTL
              ? 'translate-x-full lg:translate-x-0'
              : '-translate-x-full lg:translate-x-0',
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
        <nav className="flex-1 overflow-y-auto py-3 px-2 space-y-4">
          {/* Master-tenant admin section */}
          {user?.role === 'admin' && isMaster && (
            <div>
              {!sidebarCollapsed && (
                <p className="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-amber-500/80 select-none">
                  {t('nav_group_saas_admin')}
                </p>
              )}
              {sidebarCollapsed && <div className="border-t border-amber-500/30 mx-2 mb-1" />}
              <div className="space-y-0.5">
                {[
                  { labelKey: 'admin_cpanel', path: '/admin', icon: LayoutGrid },
                  { labelKey: 'admin_tenants', path: '/admin/tenants', icon: Building2 },
                  { labelKey: 'admin_plans', path: '/admin/plans', icon: Tag },
                  { labelKey: 'admin_payment_accounts', path: '/admin/payment-accounts', icon: CreditCard },
                ].map((item) => {
                  const label = t(item.labelKey)
                  const isActive = item.path === '/admin'
                    ? location.pathname === '/admin'
                    : location.pathname.startsWith(item.path)
                  return (
                    <NavLink
                      key={item.path}
                      to={item.path}
                      onClick={() => setSidebarMobileOpen(false)}
                      className={clsx(
                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                        isActive
                          ? 'bg-amber-600 text-white shadow-sm'
                          : 'text-amber-400/70 hover:bg-amber-900/30 hover:text-amber-200',
                        sidebarCollapsed && 'justify-center',
                      )}
                      title={sidebarCollapsed ? label : undefined}
                    >
                      <item.icon className="h-4 w-4 flex-shrink-0" />
                      {!sidebarCollapsed && <span className="truncate">{label}</span>}
                    </NavLink>
                  )
                })}
              </div>
            </div>
          )}

          {navGroups.map((group) => {
            const visibleItems = group.items.filter(isVisible)
            if (visibleItems.length === 0) return null
            return (
              <div key={group.groupKey}>
                {!sidebarCollapsed && (
                  <p className="px-3 mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-500 select-none">
                    {t(group.groupKey)}
                  </p>
                )}
                {sidebarCollapsed && (
                  <div className="border-t border-slate-700/60 mx-2 mb-1" />
                )}
                <div className="space-y-0.5">
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
                          'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                          isActive
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-slate-400 hover:bg-slate-800 hover:text-white',
                          sidebarCollapsed && 'justify-center',
                        )}
                        title={sidebarCollapsed ? label : undefined}
                      >
                        <item.icon className={clsx('h-4 w-4 flex-shrink-0', isActive ? 'text-white' : 'text-slate-400 group-hover:text-white')} />
                        {!sidebarCollapsed && <span className="truncate">{label}</span>}
                      </NavLink>
                    )
                  })}
                </div>
              </div>
            )
          })}
        </nav>

        {/* User info */}
        {!sidebarCollapsed && user && (
          <div className="p-3 border-t border-slate-700 flex-shrink-0">
            <div className="flex items-center gap-3 px-1">
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
        {sidebarCollapsed && user && (
          <div className="p-3 border-t border-slate-700 flex justify-center flex-shrink-0">
            <div className="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white text-sm font-semibold" title={user.name}>
              {user.name.charAt(0).toUpperCase()}
            </div>
          </div>
        )}
      </aside>
    </>
  )
}
