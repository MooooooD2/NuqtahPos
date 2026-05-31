import { lazy, Suspense } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import { useUIStore } from '@/stores/uiStore'
import { Toaster } from 'react-hot-toast'
import AppLayout from '@/components/layout/AppLayout'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { useEffect } from 'react'

// Lazy-loaded pages
const LoginPage         = lazy(() => import('@/pages/LoginPage'))
const DashboardPage     = lazy(() => import('@/pages/DashboardPage'))
const PosPage           = lazy(() => import('@/pages/PosPage'))
const ProductsPage      = lazy(() => import('@/pages/ProductsPage'))
const InventoryPage     = lazy(() => import('@/pages/InventoryPage'))
const CustomersPage     = lazy(() => import('@/pages/CustomersPage'))
const SuppliersPage     = lazy(() => import('@/pages/SuppliersPage'))
const PurchasesPage     = lazy(() => import('@/pages/PurchasesPage'))
const InvoicesPage      = lazy(() => import('@/pages/InvoicesPage'))
const ReportsPage       = lazy(() => import('@/pages/ReportsPage'))
const SettingsPage      = lazy(() => import('@/pages/SettingsPage'))
const UsersPage         = lazy(() => import('@/pages/UsersPage'))
const AccountingPage    = lazy(() => import('@/pages/AccountingPage'))
const CrmPage           = lazy(() => import('@/pages/CrmPage'))
const HrPage            = lazy(() => import('@/pages/HrPage'))
const WarehousePage     = lazy(() => import('@/pages/WarehousePage'))

function PrivateRoute({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />
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

  // Apply theme & language on mount
  useEffect(() => {
    const root = document.documentElement
    if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      root.classList.add('dark')
    } else {
      root.classList.remove('dark')
    }
    root.dir = language === 'ar' ? 'rtl' : 'ltr'
    root.lang = language
  }, [theme, language])

  return (
    <>
      <Toaster
        position="top-right"
        toastOptions={{
          className: 'dark:bg-gray-800 dark:text-white',
          duration: 4000,
        }}
      />
      <Suspense fallback={<PageFallback />}>
        <Routes>
          <Route
            path="/login"
            element={
              <PublicRoute>
                <LoginPage />
              </PublicRoute>
            }
          />
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
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Suspense>
    </>
  )
}
