import { useState, Fragment } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { apiGet, apiPost, apiPut, apiPatch, apiDelete } from '@/services/api'
import { useAuthStore } from '@/stores/authStore'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import Modal from '@/components/common/Modal'
import toast from 'react-hot-toast'
import { clsx } from 'clsx'
import {
  Building2, Plus, Search, ToggleLeft, ToggleRight, Trash2,
  Users, RefreshCw, Ban, ChevronDown, ChevronUp, CalendarDays,
  Pencil, UserPlus, LogIn,
} from 'lucide-react'

interface Tenant {
  id: string
  name: string
  code: string
  plan: string
  is_active: boolean
  subscription_status: string
  subscription_starts_at: string | null
  subscription_ends_at: string | null
  trial_ends_at: string | null
  created_at: string
}

interface TenantUser {
  id: number
  name: string
  username: string
  is_active: boolean
  created_at: string
}

interface Plan {
  id: string
  name: string
}

const STATUS_BADGE: Record<string, string> = {
  active: 'badge-success',
  trial: 'badge-warning',
  expired: 'badge-danger',
  suspended: 'bg-orange-100 text-orange-700',
  cancelled: 'badge-danger',
}

const STATUS_AR: Record<string, string> = {
  active: 'نشط',
  trial: 'تجريبي',
  expired: 'منتهي',
  suspended: 'موقوف',
  cancelled: 'ملغي',
}

const PLAN_BADGE: Record<string, string> = {
  basic: 'badge-info',
  pro: 'badge-warning',
  enterprise: 'badge-success',
}

const ROLE_OPTIONS = [
  { value: 'admin', labelAr: 'مدير', labelEn: 'Admin' },
  { value: 'cashier', labelAr: 'كاشير', labelEn: 'Cashier' },
  { value: 'warehouse', labelAr: 'مخزن', labelEn: 'Warehouse' },
]

export default function AdminTenantsPage() {
  const { i18n } = useTranslation('pos')
  const isAr = i18n.language === 'ar'
  const qc = useQueryClient()
  const navigate = useNavigate()
  const authLogin = useAuthStore((s) => s.login)

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [expandedId, setExpandedId] = useState<string | null>(null)
  const [usersMap, setUsersMap] = useState<Record<string, TenantUser[]>>({})

  const [showCreate, setShowCreate] = useState(false)
  const [createForm, setCreateForm] = useState({ name: '', code: '', plan: '', trial_days: 14 })

  const [editTenant, setEditTenant] = useState<Tenant | null>(null)
  const [editForm, setEditForm] = useState({ name: '', plan: '' })

  const [extendTarget, setExtendTarget] = useState<Tenant | null>(null)
  const [extendDays, setExtendDays] = useState(30)

  const [addUserTarget, setAddUserTarget] = useState<Tenant | null>(null)
  const [addUserForm, setAddUserForm] = useState({ full_name: '', username: '', password: '', role: 'cashier' })
  const [showPassword, setShowPassword] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['admin-tenants'],
    queryFn: () => apiGet<{ success: boolean; tenants: Tenant[]; stats: Record<string, number> }>('/admin/tenants'),
    staleTime: 15_000,
  })

  const { data: plansData } = useQuery({
    queryKey: ['admin-plans-list'],
    queryFn: () => apiGet<{ success: boolean; plans: Plan[] }>('/admin/plans'),
    staleTime: 60_000,
  })

  const plans = plansData?.plans ?? []
  const tenants = data?.tenants ?? []
  const stats = data?.stats ?? {}

  const filtered = tenants.filter((t) => {
    const matchSearch = !search || t.name.toLowerCase().includes(search.toLowerCase()) || t.code.toLowerCase().includes(search.toLowerCase())
    const matchStatus = !statusFilter || t.subscription_status === statusFilter
    return matchSearch && matchStatus
  })

  const createMut = useMutation({
    mutationFn: (d: typeof createForm) => apiPost('/admin/tenants', d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-tenants'] })
      setShowCreate(false)
      setCreateForm({ name: '', code: '', plan: plans[0]?.id ?? '', trial_days: 14 })
      toast.success(isAr ? 'تم إنشاء المتجر' : 'Store created')
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e.response?.data?.message ?? (isAr ? 'خطأ في الإنشاء' : 'Creation failed')),
  })

  const updateMut = useMutation({
    mutationFn: ({ id, ...d }: { id: string; name: string; plan: string }) => apiPut(`/admin/tenants/${id}`, d),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-tenants'] })
      setEditTenant(null)
      toast.success(isAr ? 'تم التحديث' : 'Updated')
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e.response?.data?.message ?? (isAr ? 'خطأ في التحديث' : 'Update failed')),
  })

  const toggleMut = useMutation({
    mutationFn: (id: string) => apiPatch(`/admin/tenants/${id}/toggle`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-tenants'] }),
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: string) => apiDelete(`/admin/tenants/${id}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-tenants'] }); toast.success(isAr ? 'تم الحذف' : 'Deleted') },
    onError: () => toast.error(isAr ? 'خطأ في الحذف' : 'Delete failed'),
  })

  const extendMut = useMutation({
    mutationFn: ({ id, days }: { id: string; days: number }) => apiPost(`/admin/tenants/${id}/extend`, { days }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-tenants'] })
      setExtendTarget(null)
      toast.success(isAr ? 'تم تمديد الاشتراك' : 'Subscription extended')
    },
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const suspendMut = useMutation({
    mutationFn: (id: string) => apiPatch(`/admin/tenants/${id}/suspend`, {}),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-tenants'] }); toast.success(isAr ? 'تم الإيقاف' : 'Suspended') },
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const seedMut = useMutation({
    mutationFn: (id: string) => apiPost(`/admin/tenants/${id}/seed`, {}),
    onSuccess: () => toast.success(isAr ? 'تم زرع البيانات' : 'Data seeded'),
    onError: () => toast.error(isAr ? 'خطأ في الزرع' : 'Seed failed'),
  })

  const addUserMut = useMutation({
    mutationFn: ({ tenantId, ...d }: typeof addUserForm & { tenantId: string }) =>
      apiPost(`/admin/tenants/${tenantId}/users`, d),
    onSuccess: (_, { tenantId }) => {
      setUsersMap((prev) => { const n = { ...prev }; delete n[tenantId]; return n })
      loadUsers(tenantId)
      setAddUserTarget(null)
      setAddUserForm({ full_name: '', username: '', password: '', role: 'cashier' })
      toast.success(isAr ? 'تم إضافة المستخدم' : 'User added')
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e.response?.data?.message ?? (isAr ? 'خطأ في إضافة المستخدم' : 'Failed to add user')),
  })

  const impersonateMut = useMutation({
    mutationFn: (id: string) => apiPost<{
      success: boolean
      token: string
      tenant_code: string
      user: { id: number; name: string; username: string; role: string; language: string; permissions: string[] }
    }>(`/admin/tenants/${id}/impersonate`, {}),
    onSuccess: (res, tenantId) => {
      const tenant = tenants.find((t) => t.id === tenantId)
      localStorage.setItem('pos-company-code', res.tenant_code)
      authLogin(
        {
          id: res.user.id,
          name: res.user.name,
          username: res.user.username,
          role: res.user.role as 'admin' | 'cashier' | 'warehouse',
          language: res.user.language ?? 'ar',
          permissions: res.user.permissions ?? [],
        },
        res.token,
      )
      toast.success(isAr ? `تم تسجيل الدخول إلى ${tenant?.name ?? ''}` : `Logged in to ${tenant?.name ?? ''}`)
      navigate('/')
    },
    onError: (e: { response?: { data?: { message?: string } } }) =>
      toast.error(e.response?.data?.message ?? (isAr ? 'خطأ في تسجيل الدخول' : 'Login failed')),
  })

  const loadUsers = async (tenantId: string) => {
    try {
      const res = await apiGet<{ success: boolean; users: TenantUser[] }>(`/admin/tenants/${tenantId}/users`)
      setUsersMap((prev) => ({ ...prev, [tenantId]: res.users ?? [] }))
    } catch {
      toast.error(isAr ? 'خطأ في تحميل المستخدمين' : 'Failed to load users')
    }
  }

  const toggleExpand = (id: string) => {
    if (expandedId === id) { setExpandedId(null); return }
    setExpandedId(id)
    if (!usersMap[id]) loadUsers(id)
  }

  const toggleUserMut = useMutation({
    mutationFn: ({ tenantId, userId }: { tenantId: string; userId: number }) =>
      apiPatch(`/admin/tenants/${tenantId}/users/${userId}/toggle`, {}),
    onSuccess: (_, { tenantId }) => {
      setUsersMap((prev) => { const n = { ...prev }; delete n[tenantId]; return n })
      loadUsers(tenantId)
    },
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const openEdit = (t: Tenant) => { setEditTenant(t); setEditForm({ name: t.name, plan: t.plan }) }
  const openCreate = () => { setCreateForm({ name: '', code: '', plan: plans[0]?.id ?? '', trial_days: 14 }); setShowCreate(true) }
  const openAddUser = (t: Tenant) => { setAddUserTarget(t); setAddUserForm({ full_name: '', username: '', password: '', role: 'cashier' }); setShowPassword(false) }

  const statCards = [
    { label: isAr ? 'إجمالي' : 'Total', value: stats.total ?? 0, cls: 'text-blue-600' },
    { label: isAr ? 'نشط' : 'Active', value: stats.active ?? 0, cls: 'text-green-600' },
    { label: isAr ? 'تجريبي' : 'Trial', value: stats.trial ?? 0, cls: 'text-yellow-600' },
    { label: isAr ? 'منتهي' : 'Expired', value: stats.expired ?? 0, cls: 'text-red-600' },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <h1 className="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Building2 className="h-6 w-6 text-primary-500" />
          {isAr ? 'إدارة الاشتراكات' : 'Subscriptions'}
        </h1>
        <button onClick={openCreate} className="btn btn-primary flex items-center gap-2 text-sm">
          <Plus className="h-4 w-4" /> <span className="hidden sm:inline">{isAr ? 'مستأجر جديد' : 'New Tenant'}</span><span className="sm:hidden">{isAr ? 'جديد' : 'New'}</span>
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {statCards.map((s) => (
          <div key={s.label} className="card p-4 text-center">
            <p className={clsx('text-3xl font-bold', s.cls)}>{s.value}</p>
            <p className="text-xs text-gray-500 mt-1">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={isAr ? 'بحث...' : 'Search...'} className="input ps-9 w-full" />
        </div>
        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="input sm:w-44">
          <option value="">{isAr ? 'جميع الحالات' : 'All Status'}</option>
          {['active', 'trial', 'expired', 'suspended', 'cancelled'].map((s) => (
            <option key={s} value={s}>{STATUS_AR[s]}</option>
          ))}
        </select>
      </div>

      {/* Tenants list */}
      <div className="card overflow-hidden">
        {isLoading ? (
          <div className="flex h-40 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : filtered.length === 0 ? (
          <div className="py-12 text-center text-gray-400">{isAr ? 'لا توجد بيانات' : 'No data'}</div>
        ) : (
          <>
            {/* Desktop table */}
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[900px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[isAr ? 'المتجر' : 'Store', isAr ? 'الكود' : 'Code', isAr ? 'الخطة' : 'Plan', isAr ? 'الحالة' : 'Status', isAr ? 'الاشتراك' : 'Subscription', isAr ? 'الإنشاء' : 'Created', isAr ? 'إجراءات' : 'Actions'].map((h) => (
                      <th key={h} className="px-4 py-3 text-start text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {filtered.map((t) => (
                    <Fragment key={t.id}>
                      <tr className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2">
                            <span className="font-medium text-gray-900 dark:text-white">{t.name}</span>
                            {!t.is_active && <span className="badge badge-danger text-xs">Disabled</span>}
                          </div>
                        </td>
                        <td className="px-4 py-3 font-mono text-gray-500">{t.code}</td>
                        <td className="px-4 py-3"><span className={clsx('badge', PLAN_BADGE[t.plan] ?? 'badge-info')}>{t.plan}</span></td>
                        <td className="px-4 py-3">
                          <span className={clsx('badge', STATUS_BADGE[t.subscription_status] ?? 'badge-info')}>
                            {STATUS_AR[t.subscription_status] ?? t.subscription_status}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-gray-500 text-xs">
                          {t.subscription_ends_at
                            ? new Date(t.subscription_ends_at).toLocaleDateString()
                            : t.trial_ends_at ? `Trial: ${new Date(t.trial_ends_at).toLocaleDateString()}` : '—'}
                        </td>
                        <td className="px-4 py-3 text-gray-500 text-xs">{new Date(t.created_at).toLocaleDateString()}</td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-1">
                            <button onClick={() => { if (confirm(isAr ? `الدخول إلى ${t.name}؟ سيتم استبدال جلستك الحالية.` : `Login to ${t.name}? Your current session will be replaced.`)) impersonateMut.mutate(t.id) }} disabled={impersonateMut.isPending} className="p-1.5 rounded hover:bg-amber-100 dark:hover:bg-amber-900/30" title={isAr ? 'الدخول كمستأجر' : 'Login as tenant'}><LogIn className="h-3.5 w-3.5 text-amber-500" /></button>
                            <button onClick={() => toggleExpand(t.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'المستخدمون' : 'Users'}><Users className="h-3.5 w-3.5 text-blue-500" /></button>
                            <button onClick={() => openAddUser(t)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'إضافة مستخدم' : 'Add user'}><UserPlus className="h-3.5 w-3.5 text-green-500" /></button>
                            <button onClick={() => openEdit(t)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'تعديل' : 'Edit'}><Pencil className="h-3.5 w-3.5 text-gray-500" /></button>
                            <button onClick={() => setExtendTarget(t)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'تمديد' : 'Extend'}><CalendarDays className="h-3.5 w-3.5 text-green-500" /></button>
                            <button onClick={() => toggleMut.mutate(t.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={t.is_active ? (isAr ? 'تعطيل' : 'Disable') : (isAr ? 'تفعيل' : 'Enable')}>{t.is_active ? <ToggleRight className="h-3.5 w-3.5 text-green-500" /> : <ToggleLeft className="h-3.5 w-3.5 text-gray-400" />}</button>
                            {t.subscription_status !== 'suspended' && <button onClick={() => suspendMut.mutate(t.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'إيقاف' : 'Suspend'}><Ban className="h-3.5 w-3.5 text-orange-500" /></button>}
                            <button onClick={() => seedMut.mutate(t.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'زرع بيانات' : 'Seed data'} disabled={seedMut.isPending}><RefreshCw className={clsx('h-3.5 w-3.5 text-purple-500', seedMut.isPending && 'animate-spin')} /></button>
                            <button onClick={() => { if (confirm(isAr ? 'حذف المتجر نهائياً؟' : 'Delete this store permanently?')) deleteMut.mutate(t.id) }} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600" title={isAr ? 'حذف' : 'Delete'} disabled={deleteMut.isPending}><Trash2 className="h-3.5 w-3.5 text-red-500" /></button>
                            <button onClick={() => toggleExpand(t.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600">{expandedId === t.id ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}</button>
                          </div>
                        </td>
                      </tr>
                      {expandedId === t.id && (
                        <tr>
                          <td colSpan={7} className="px-6 py-4 bg-gray-50 dark:bg-gray-800/50">
                            <div className="flex items-center justify-between mb-2">
                              <p className="text-xs font-semibold text-gray-500">{isAr ? 'مستخدمو المتجر' : 'Store Users'}</p>
                              <button onClick={() => openAddUser(t)} className="flex items-center gap-1 text-xs text-green-600 hover:underline"><UserPlus className="h-3 w-3" /> {isAr ? 'إضافة مستخدم' : 'Add user'}</button>
                            </div>
                            {!usersMap[t.id] ? <LoadingSpinner size="sm" /> : usersMap[t.id].length === 0 ? (
                              <p className="text-xs text-gray-400">{isAr ? 'لا يوجد مستخدمون' : 'No users'}</p>
                            ) : (
                              <div className="flex flex-wrap gap-2">
                                {usersMap[t.id].map((u) => (
                                  <div key={u.id} className="flex items-center gap-2 bg-white dark:bg-gray-700 rounded-lg px-3 py-1.5 text-xs border border-gray-200 dark:border-gray-600">
                                    <span className={clsx('w-2 h-2 rounded-full', u.is_active ? 'bg-green-500' : 'bg-gray-300')} />
                                    <span className="font-medium">{u.name || u.username}</span>
                                    <span className="text-gray-400">@{u.username}</span>
                                    <button onClick={() => toggleUserMut.mutate({ tenantId: t.id, userId: u.id })} className="text-blue-500 hover:underline">{u.is_active ? (isAr ? 'تعطيل' : 'Disable') : (isAr ? 'تفعيل' : 'Enable')}</button>
                                  </div>
                                ))}
                              </div>
                            )}
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Mobile cards */}
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {filtered.map((t) => (
                <div key={t.id} className="p-4 space-y-3">
                  {/* Header row */}
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-semibold text-gray-900 dark:text-white">{t.name}</span>
                        {!t.is_active && <span className="badge badge-danger text-xs">Disabled</span>}
                      </div>
                      <p className="text-xs text-gray-400 font-mono mt-0.5">{t.code}</p>
                    </div>
                    <button onClick={() => toggleExpand(t.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600 flex-shrink-0">
                      {expandedId === t.id ? <ChevronUp className="h-4 w-4 text-gray-500" /> : <ChevronDown className="h-4 w-4 text-gray-500" />}
                    </button>
                  </div>

                  {/* Badges + info */}
                  <div className="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <span className={clsx('badge', PLAN_BADGE[t.plan] ?? 'badge-info')}>{t.plan}</span>
                    <span className={clsx('badge', STATUS_BADGE[t.subscription_status] ?? 'badge-info')}>{STATUS_AR[t.subscription_status] ?? t.subscription_status}</span>
                    <span>
                      {t.subscription_ends_at
                        ? new Date(t.subscription_ends_at).toLocaleDateString()
                        : t.trial_ends_at ? `Trial: ${new Date(t.trial_ends_at).toLocaleDateString()}` : '—'}
                    </span>
                  </div>

                  {/* Action buttons */}
                  <div className="flex items-center gap-1 flex-wrap border-t border-gray-100 dark:border-gray-700 pt-2">
                    <button onClick={() => { if (confirm(isAr ? `الدخول إلى ${t.name}؟ سيتم استبدال جلستك الحالية.` : `Login to ${t.name}? Your current session will be replaced.`)) impersonateMut.mutate(t.id) }} disabled={impersonateMut.isPending} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 hover:bg-amber-100"><LogIn className="h-3 w-3" />{isAr ? 'دخول' : 'Login'}</button>
                    <button onClick={() => openEdit(t)} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200"><Pencil className="h-3 w-3" />{isAr ? 'تعديل' : 'Edit'}</button>
                    <button onClick={() => setExtendTarget(t)} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 hover:bg-green-100"><CalendarDays className="h-3 w-3" />{isAr ? 'تمديد' : 'Extend'}</button>
                    <button onClick={() => openAddUser(t)} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 hover:bg-blue-100"><UserPlus className="h-3 w-3" />{isAr ? 'مستخدم' : 'User'}</button>
                    <button onClick={() => toggleMut.mutate(t.id)} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200">{t.is_active ? <ToggleRight className="h-3 w-3 text-green-500" /> : <ToggleLeft className="h-3 w-3 text-gray-400" />}{t.is_active ? (isAr ? 'تعطيل' : 'Disable') : (isAr ? 'تفعيل' : 'Enable')}</button>
                    {t.subscription_status !== 'suspended' && <button onClick={() => suspendMut.mutate(t.id)} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400 hover:bg-orange-100"><Ban className="h-3 w-3" />{isAr ? 'إيقاف' : 'Suspend'}</button>}
                    <button onClick={() => seedMut.mutate(t.id)} disabled={seedMut.isPending} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400 hover:bg-purple-100"><RefreshCw className={clsx('h-3 w-3', seedMut.isPending && 'animate-spin')} />{isAr ? 'بيانات' : 'Seed'}</button>
                    <button onClick={() => { if (confirm(isAr ? 'حذف المتجر نهائياً؟' : 'Delete this store permanently?')) deleteMut.mutate(t.id) }} disabled={deleteMut.isPending} className="flex items-center gap-1 px-2 py-1 text-xs rounded bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100"><Trash2 className="h-3 w-3" />{isAr ? 'حذف' : 'Delete'}</button>
                  </div>

                  {/* Expanded users on mobile */}
                  {expandedId === t.id && (
                    <div className="border-t border-gray-100 dark:border-gray-700 pt-3 space-y-2">
                      <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold text-gray-500">{isAr ? 'مستخدمو المتجر' : 'Store Users'}</p>
                        <button onClick={() => openAddUser(t)} className="flex items-center gap-1 text-xs text-green-600 hover:underline"><UserPlus className="h-3 w-3" /> {isAr ? 'إضافة' : 'Add'}</button>
                      </div>
                      {!usersMap[t.id] ? <LoadingSpinner size="sm" /> : usersMap[t.id].length === 0 ? (
                        <p className="text-xs text-gray-400">{isAr ? 'لا يوجد مستخدمون' : 'No users'}</p>
                      ) : (
                        <div className="space-y-1">
                          {usersMap[t.id].map((u) => (
                            <div key={u.id} className="flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-700 rounded-lg px-3 py-2 text-xs">
                              <div className="flex items-center gap-2 min-w-0">
                                <span className={clsx('w-2 h-2 rounded-full flex-shrink-0', u.is_active ? 'bg-green-500' : 'bg-gray-300')} />
                                <span className="font-medium truncate">{u.name || u.username}</span>
                                <span className="text-gray-400 hidden sm:inline">@{u.username}</span>
                              </div>
                              <button onClick={() => toggleUserMut.mutate({ tenantId: t.id, userId: u.id })} className="text-blue-500 hover:underline flex-shrink-0">{u.is_active ? (isAr ? 'تعطيل' : 'Disable') : (isAr ? 'تفعيل' : 'Enable')}</button>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </>
        )}
      </div>

      {/* Create tenant modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title={isAr ? 'مستأجر جديد' : 'New Tenant'}>
        <div className="space-y-4">
          <div>
            <label className="label">{isAr ? 'اسم المتجر' : 'Store Name'}</label>
            <input value={createForm.name} onChange={(e) => setCreateForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{isAr ? 'الكود (انجليزي)' : 'Code (slug)'}</label>
            <input value={createForm.code} onChange={(e) => setCreateForm((p) => ({ ...p, code: e.target.value.toLowerCase().replace(/[^a-z0-9-_]/g, '') }))} className="input w-full" dir="ltr" />
          </div>
          <div>
            <label className="label">{isAr ? 'الخطة' : 'Plan'}</label>
            <select value={createForm.plan} onChange={(e) => setCreateForm((p) => ({ ...p, plan: e.target.value }))} className="input w-full">
              {plans.map((pl) => <option key={pl.id} value={pl.id}>{pl.name}</option>)}
              {plans.length === 0 && <option value="">— {isAr ? 'لا توجد خطط' : 'No plans'} —</option>}
            </select>
          </div>
          <div>
            <label className="label">{isAr ? 'أيام التجربة' : 'Trial Days'}</label>
            <input type="number" value={createForm.trial_days} onChange={(e) => setCreateForm((p) => ({ ...p, trial_days: +e.target.value }))} className="input w-full" min={0} max={365} />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <button onClick={() => setShowCreate(false)} className="btn btn-secondary">{isAr ? 'إلغاء' : 'Cancel'}</button>
            <button onClick={() => createMut.mutate(createForm)} disabled={createMut.isPending || !createForm.name || !createForm.code || !createForm.plan} className="btn btn-primary">
              {createMut.isPending ? <LoadingSpinner size="sm" /> : (isAr ? 'إنشاء' : 'Create')}
            </button>
          </div>
        </div>
      </Modal>

      {/* Edit tenant modal */}
      {editTenant && (
        <Modal open onClose={() => setEditTenant(null)} title={isAr ? 'تعديل المتجر' : 'Edit Store'}>
          <div className="space-y-4">
            <div>
              <label className="label">{isAr ? 'اسم المتجر' : 'Store Name'}</label>
              <input value={editForm.name} onChange={(e) => setEditForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{isAr ? 'الخطة' : 'Plan'}</label>
              <select value={editForm.plan} onChange={(e) => setEditForm((p) => ({ ...p, plan: e.target.value }))} className="input w-full">
                {plans.map((pl) => <option key={pl.id} value={pl.id}>{pl.name}</option>)}
                {plans.length === 0 && <option value={editTenant.plan}>{editTenant.plan}</option>}
              </select>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setEditTenant(null)} className="btn btn-secondary">{isAr ? 'إلغاء' : 'Cancel'}</button>
              <button onClick={() => updateMut.mutate({ id: editTenant.id, name: editForm.name, plan: editForm.plan })} disabled={updateMut.isPending || !editForm.name || !editForm.plan} className="btn btn-primary">
                {updateMut.isPending ? <LoadingSpinner size="sm" /> : (isAr ? 'حفظ' : 'Save')}
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Extend modal */}
      {extendTarget && (
        <Modal open onClose={() => setExtendTarget(null)} title={isAr ? `تمديد: ${extendTarget.name}` : `Extend: ${extendTarget.name}`}>
          <div className="space-y-4">
            <div>
              <label className="label">{isAr ? 'عدد الأيام' : 'Days to extend'}</label>
              <input type="number" value={extendDays} onChange={(e) => setExtendDays(+e.target.value)} className="input w-full" min={1} max={3650} />
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setExtendTarget(null)} className="btn btn-secondary">{isAr ? 'إلغاء' : 'Cancel'}</button>
              <button onClick={() => extendMut.mutate({ id: extendTarget.id, days: extendDays })} disabled={extendMut.isPending} className="btn btn-primary">
                {extendMut.isPending ? <LoadingSpinner size="sm" /> : (isAr ? 'تمديد' : 'Extend')}
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Add user modal */}
      {addUserTarget && (
        <Modal open onClose={() => setAddUserTarget(null)} title={isAr ? `إضافة مستخدم — ${addUserTarget.name}` : `Add User — ${addUserTarget.name}`}>
          <div className="space-y-4">
            <div>
              <label className="label">{isAr ? 'الاسم الكامل' : 'Full Name'}</label>
              <input value={addUserForm.full_name} onChange={(e) => setAddUserForm((p) => ({ ...p, full_name: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{isAr ? 'اسم المستخدم' : 'Username'}</label>
              <input value={addUserForm.username} onChange={(e) => setAddUserForm((p) => ({ ...p, username: e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '') }))} className="input w-full" dir="ltr" />
            </div>
            <div>
              <label className="label">{isAr ? 'كلمة المرور' : 'Password'}</label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={addUserForm.password}
                  onChange={(e) => setAddUserForm((p) => ({ ...p, password: e.target.value }))}
                  className="input w-full pe-10"
                  dir="ltr"
                />
                <button type="button" onClick={() => setShowPassword((v) => !v)} className="absolute end-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 hover:text-gray-600">
                  {showPassword ? (isAr ? 'إخفاء' : 'Hide') : (isAr ? 'إظهار' : 'Show')}
                </button>
              </div>
            </div>
            <div>
              <label className="label">{isAr ? 'الدور' : 'Role'}</label>
              <select value={addUserForm.role} onChange={(e) => setAddUserForm((p) => ({ ...p, role: e.target.value }))} className="input w-full">
                {ROLE_OPTIONS.map((r) => (
                  <option key={r.value} value={r.value}>{isAr ? r.labelAr : r.labelEn}</option>
                ))}
              </select>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setAddUserTarget(null)} className="btn btn-secondary">{isAr ? 'إلغاء' : 'Cancel'}</button>
              <button
                onClick={() => addUserMut.mutate({ ...addUserForm, tenantId: addUserTarget.id })}
                disabled={addUserMut.isPending || !addUserForm.full_name || !addUserForm.username || !addUserForm.password}
                className="btn btn-primary"
              >
                {addUserMut.isPending ? <LoadingSpinner size="sm" /> : (isAr ? 'إضافة' : 'Add')}
              </button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}
