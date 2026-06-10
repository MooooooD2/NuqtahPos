import { lazy, Suspense, useEffect } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import { useUIStore } from '@/stores/uiStore'
import { Toaster } from 'react-hot-toast'
import AppLayout from '@/components/layout/AppLayout'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import ErrorBoundary from '@/components/common/ErrorBoundary'
import i18n from './i18n'

// Lazy-loaded pages
const LandingPage            = lazy(() => import('@/pages/LandingPage'))
const LoginPage              = lazy(() => import('@/pages/LoginPage'))
const RegisterPage           = lazy(() => import('@/pages/RegisterPage'))
const DashboardPage          = lazy(() => import('@/pages/DashboardPage'))
const PosPage                = lazy(() => import('@/pages/PosPage'))
const ProductsPage           = lazy(() => import('@/pages/ProductsPage'))
const InventoryPage          = lazy(() => import('@/pages/InventoryPage'))
const CustomersPage          = lazy(() => import('@/pages/CustomersPage'))
const SuppliersPage          = lazy(() => import('@/pages/SuppliersPage'))
const PurchasesPage          = lazy(() => import('@/pages/PurchasesPage'))
const InvoicesPage           = lazy(() => import('@/pages/InvoicesPage'))
const ReportsPage            = lazy(() => import('@/pages/ReportsPage'))
const SettingsPage           = lazy(() => import('@/pages/SettingsPage'))
const UsersPage              = lazy(() => import('@/pages/UsersPage'))
const AccountingPage         = lazy(() => import('@/pages/AccountingPage'))
const CrmPage                = lazy(() => import('@/pages/CrmPage'))
const HrPage                 = lazy(() => import('@/pages/HrPage'))
const WarehousePage          = lazy(() => import('@/pages/WarehousePage'))
const ExpensesPage           = lazy(() => import('@/pages/ExpensesPage'))
const ReturnsPage            = lazy(() => import('@/pages/ReturnsPage'))
const CashRegisterPage       = lazy(() => import('@/pages/CashRegisterPage'))
const PromotionsPage         = lazy(() => import('@/pages/PromotionsPage'))
const PurchaseReturnsPage    = lazy(() => import('@/pages/PurchaseReturnsPage'))
const PricingRulesPage       = lazy(() => import('@/pages/PricingRulesPage'))
const CashbackPage           = lazy(() => import('@/pages/CashbackPage'))
const SupplierPaymentsPage   = lazy(() => import('@/pages/SupplierPaymentsPage'))
const SupplierAccountsPage   = lazy(() => import('@/pages/SupplierAccountsPage'))
const ProfitReportsPage      = lazy(() => import('@/pages/ProfitReportsPage'))
const BranchesPage           = lazy(() => import('@/pages/BranchesPage'))
const ForecastingPage        = lazy(() => import('@/pages/ForecastingPage'))
const DeviceSessionsPage     = lazy(() => import('@/pages/DeviceSessionsPage'))
const WhatsAppPage           = lazy(() => import('@/pages/WhatsAppPage'))
const CurrenciesPage         = lazy(() => import('@/pages/CurrenciesPage'))
const WastePage              = lazy(() => import('@/pages/WastePage'))
const FinancialReportsPage   = lazy(() => import('@/pages/FinancialReportsPage'))
const KitchenPage            = lazy(() => import('@/pages/KitchenPage'))
const QrManagePage           = lazy(() => import('@/pages/QrManagePage'))
const MyShiftPage            = lazy(() => import('@/pages/MyShiftPage'))
const PharmacyPage           = lazy(() => import('@/pages/PharmacyPage'))
const AdminCpanelPage        = lazy(() => import('@/pages/AdminCpanelPage'))
const AdminTenantsPage       = lazy(() => import('@/pages/AdminTenantsPage'))
const AdminPlansPage         = lazy(() => import('@/pages/AdminPlansPage'))
const AdminPaymentAccountsPage = lazy(() => import('@/pages/AdminPaymentAccountsPage'))
const PaymentMethodsPage       = lazy(() => import('@/pages/PaymentMethodsPage'))

function PrivateRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  return isAuthenticated ? <>{children}</> : <Navigate to="/welcome" replace />
}

function AdminRoute({ children }: { children: React.ReactNode }) {
  const user = useAuthStore((s) => s.user)
  const hasAccess = user?.role === 'admin' && localStorage.getItem('pos-company-code') === 'main'
  return hasAccess ? <>{children}</> : <Navigate to="/" replace />
}

function PublicRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  return isAuthenticated ? <Navigate to="/" replace /> : <>{children}</>
}

const PageFallback = () => (
  <div className="flex h-full items-center justify-center">
    <LoadingSpinner size="lg" />
  </div>
)

export default function App() {
  const { theme, language } = useUIStore()

  // Apply theme & language on mount/change
  useEffect(() => {
    const root = document.documentElement
    if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      root.classList.add('dark')
    } else {
      root.classList.remove('dark')
    }
    root.dir = language === 'ar' ? 'rtl' : 'ltr'
    root.lang = language
    i18n.changeLanguage(language)
  }, [theme, language])

  return (
    <>
      <Toaster
        position={language === 'ar' ? 'top-left' : 'top-right'}
        toastOptions={{
          className: 'dark:bg-gray-800 dark:text-white',
          duration: 4000,
        }}
      />
      <ErrorBoundary>
      <Suspense fallback={<PageFallback />}>
        <Routes>
          <Route
            path="/welcome"
            element={
              <PublicRoute>
                <LandingPage />
              </PublicRoute>
            }
          />
          <Route
            path="/login"
            element={
              <PublicRoute>
                <LoginPage />
              </PublicRoute>
            }
          />
          <Route
            path="/register"
            element={
              <PublicRoute>
                <RegisterPage />
              </PublicRoute>
            }
          />
          <Route path="/payment" element={<PaymentMethodsPage />} />
          <Route
            path="/"
            element={
              <PrivateRoute>
                <AppLayout />
              </PrivateRoute>
            }
          >
            <Route index element={<DashboardPage />} />
            <Route path="pos" element={<PosPage />} />
            <Route path="products/*" element={<ProductsPage />} />
            <Route path="inventory/*" element={<InventoryPage />} />
            <Route path="customers/*" element={<CustomersPage />} />
            <Route path="suppliers/*" element={<SuppliersPage />} />
            <Route path="purchases/*" element={<PurchasesPage />} />
            <Route path="invoices/*" element={<InvoicesPage />} />
            <Route path="accounting/*" element={<AccountingPage />} />
            <Route path="crm/*" element={<CrmPage />} />
            <Route path="hr/*" element={<HrPage />} />
            <Route path="warehouse/*" element={<WarehousePage />} />
            <Route path="reports/*" element={<ReportsPage />} />
            <Route path="users/*" element={<UsersPage />} />
            <Route path="settings/*" element={<SettingsPage />} />
            <Route path="expenses/*" element={<ExpensesPage />} />
            <Route path="returns/*" element={<ReturnsPage />} />
            <Route path="cash-register/*" element={<CashRegisterPage />} />
            <Route path="promotions/*" element={<PromotionsPage />} />
            <Route path="purchase-returns/*" element={<PurchaseReturnsPage />} />
            <Route path="pricing-rules/*" element={<PricingRulesPage />} />
            <Route path="cashback/*" element={<CashbackPage />} />
            <Route path="supplier-payments/*" element={<SupplierPaymentsPage />} />
            <Route path="supplier-accounts/*" element={<SupplierAccountsPage />} />
            <Route path="profit-reports/*" element={<ProfitReportsPage />} />
            <Route path="branches/*" element={<BranchesPage />} />
            <Route path="forecasting/*" element={<ForecastingPage />} />
            <Route path="device-sessions/*" element={<DeviceSessionsPage />} />
            <Route path="whatsapp/*" element={<WhatsAppPage />} />
            <Route path="currencies/*" element={<CurrenciesPage />} />
            <Route path="waste/*" element={<WastePage />} />
            <Route path="financial-reports/*" element={<FinancialReportsPage />} />
            <Route path="kitchen/*" element={<KitchenPage />} />
            <Route path="qr/*" element={<QrManagePage />} />
            <Route path="my-shift/*" element={<MyShiftPage />} />
            <Route path="pharmacy/*" element={<PharmacyPage />} />
            {/* Admin panel (master-tenant only) */}
            <Route path="admin" element={<AdminRoute><AdminCpanelPage /></AdminRoute>} />
            <Route path="admin/tenants" element={<AdminRoute><AdminTenantsPage /></AdminRoute>} />
            <Route path="admin/plans" element={<AdminRoute><AdminPlansPage /></AdminRoute>} />
            <Route path="admin/payment-accounts" element={<AdminRoute><AdminPaymentAccountsPage /></AdminRoute>} />
          </Route>
          <Route path="*" element={<Navigate to="/welcome" replace />} />
        </Routes>
      </Suspense>
      </ErrorBoundary>
    </>
  )
}
