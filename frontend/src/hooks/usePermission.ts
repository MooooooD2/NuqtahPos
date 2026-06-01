import { useAuthStore } from '@/stores/authStore'

export function usePermission() {
  const user = useAuthStore((s) => s.user)
  const permissions = user?.permissions ?? []
  const role = user?.role ?? ''

  const hasPermission = (...perms: string[]): boolean => {
    if (role === 'admin' || role === 'Admin') return true
    return perms.some((p) => permissions.includes(p))
  }

  const hasAllPermissions = (...perms: string[]): boolean => {
    if (role === 'admin' || role === 'Admin') return true
    return perms.every((p) => permissions.includes(p))
  }

  const isAdmin = role === 'admin' || role === 'Admin'
  const isCashier = role === 'cashier' || role === 'Cashier'

  return { hasPermission, hasAllPermissions, isAdmin, isCashier, role, permissions }
}
