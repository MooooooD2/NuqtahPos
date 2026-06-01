import { usePermission } from '@/hooks/usePermission'

interface PermissionGuardProps {
  permission: string | string[]
  children: React.ReactNode
  fallback?: React.ReactNode
}

export default function PermissionGuard({ permission, children, fallback = null }: PermissionGuardProps) {
  const { hasPermission } = usePermission()
  const perms = Array.isArray(permission) ? permission : [permission]
  if (!hasPermission(...perms)) return <>{fallback}</>
  return <>{children}</>
}
