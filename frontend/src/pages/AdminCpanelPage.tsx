import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet } from '@/services/api'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Link } from 'react-router-dom'
import {
  Building2, TrendingUp, Users, Clock, XCircle, AlertTriangle,
  BarChart2, ArrowUpRight,
} from 'lucide-react'
import { clsx } from 'clsx'

interface CpanelData {
  mrr: number
  arr: number
  total: number
  status_counts: Record<string, number>
  monthly_growth: { label: string; count: number }[]
  expiring_soon: { id: string; name: string; plan: string; subscription_status: string; ends_at: string }[]
  recent_tenants: { id: string; name: string; plan: string; subscription_status: string; created_at: string }[]
  plan_distribution: { id: string; name: string; count: number; active: number; revenue: number }[]
}

const STATUS_COLORS: Record<string, string> = {
  active: 'bg-green-500',
  trial: 'bg-yellow-500',
  expired: 'bg-red-500',
  suspended: 'bg-orange-500',
  cancelled: 'bg-gray-400',
}

const STATUS_LABELS: Record<string, string> = {
  active: 'نشط',
  trial: 'تجريبي',
  expired: 'منتهي',
  suspended: 'موقوف',
  cancelled: 'ملغي',
}

const PLAN_COLORS: Record<string, string> = {
  basic: 'badge-info',
  pro: 'badge-warning',
  enterprise: 'badge-success',
}

export default function AdminCpanelPage() {
  const { i18n } = useTranslation('pos')
  const isAr = i18n.language === 'ar'

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-cpanel'],
    queryFn: () => apiGet<CpanelData & { success: boolean }>('/admin/cpanel'),
    staleTime: 30_000,
  })

  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
  if (error || !data) return (
    <div className="card p-8 text-center text-red-500">
      <p>{isAr ? 'خطأ في تحميل البيانات' : 'Failed to load data'}</p>
    </div>
  )

  const d = data as CpanelData
  const totalStores = d.total
  const maxGrowth = Math.max(...d.monthly_growth.map((m) => m.count), 1)

  const kpis = [
    { label: isAr ? 'إجمالي المتاجر' : 'Total Stores', value: d.total, icon: Building2, color: 'text-blue-500', bg: 'bg-blue-50 dark:bg-blue-900/20' },
    { label: isAr ? 'إيراد شهري (MRR)' : 'Monthly Revenue', value: `$${d.mrr.toLocaleString()}`, icon: TrendingUp, color: 'text-green-500', bg: 'bg-green-50 dark:bg-green-900/20' },
    { label: isAr ? 'إيراد سنوي (ARR)' : 'Annual Revenue', value: `$${d.arr.toLocaleString()}`, icon: BarChart2, color: 'text-purple-500', bg: 'bg-purple-50 dark:bg-purple-900/20' },
    { label: isAr ? 'اشتراكات نشطة' : 'Active', value: d.status_counts.active ?? 0, icon: Users, color: 'text-emerald-500', bg: 'bg-emerald-50 dark:bg-emerald-900/20' },
    { label: isAr ? 'في فترة التجربة' : 'Trial', value: d.status_counts.trial ?? 0, icon: Clock, color: 'text-yellow-500', bg: 'bg-yellow-50 dark:bg-yellow-900/20' },
    { label: isAr ? 'منتهية / موقوفة' : 'Inactive', value: (d.status_counts.expired ?? 0) + (d.status_counts.suspended ?? 0) + (d.status_counts.cancelled ?? 0), icon: XCircle, color: 'text-red-500', bg: 'bg-red-50 dark:bg-red-900/20' },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <BarChart2 className="h-6 w-6 text-primary-500" />
          {isAr ? 'لوحة تحكم SaaS' : 'SaaS Dashboard'}
        </h1>
        <Link to="/admin/tenants" className="btn btn-primary flex items-center gap-2 text-sm">
          <Building2 className="h-4 w-4" />
          {isAr ? 'إدارة المتاجر' : 'Manage Stores'}
          <ArrowUpRight className="h-3 w-3" />
        </Link>
      </div>

      {/* KPI cards */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        {kpis.map((kpi) => (
          <div key={kpi.label} className="card p-4">
            <div className={clsx('inline-flex rounded-xl p-2 mb-3', kpi.bg)}>
              <kpi.icon className={clsx('h-5 w-5', kpi.color)} />
            </div>
            <p className="text-2xl font-bold text-gray-900 dark:text-white">{kpi.value}</p>
            <p className="text-xs text-gray-500 mt-1">{kpi.label}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Monthly growth chart */}
        <div className="card p-5 lg:col-span-2">
          <h2 className="font-semibold text-gray-900 dark:text-white mb-4">{isAr ? 'نمو المتاجر (12 شهر)' : 'Store Growth (12 months)'}</h2>
          <div className="flex items-end gap-1 h-40">
            {d.monthly_growth.map((m) => (
              <div key={m.label} className="flex-1 flex flex-col items-center gap-1">
                <div
                  className="w-full bg-primary-500 rounded-t opacity-80 hover:opacity-100 transition-all"
                  style={{ height: `${(m.count / maxGrowth) * 100}%`, minHeight: m.count > 0 ? '4px' : '0' }}
                  title={`${m.label}: ${m.count}`}
                />
                <span className="text-xs text-gray-400 rotate-45 origin-left" style={{ fontSize: '9px' }}>
                  {m.label.split(' ')[0]}
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* Status distribution */}
        <div className="card p-5">
          <h2 className="font-semibold text-gray-900 dark:text-white mb-4">{isAr ? 'توزيع الحالات' : 'Status Breakdown'}</h2>
          <div className="space-y-3">
            {Object.entries(d.status_counts).map(([status, count]) => (
              <div key={status}>
                <div className="flex justify-between text-sm mb-1">
                  <span className="text-gray-600 dark:text-gray-400">{STATUS_LABELS[status] ?? status}</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{count}</span>
                </div>
                <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                  <div
                    className={clsx('h-2 rounded-full', STATUS_COLORS[status] ?? 'bg-gray-400')}
                    style={{ width: totalStores > 0 ? `${(count / totalStores) * 100}%` : '0%' }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Plan distribution */}
        <div className="card p-5">
          <h2 className="font-semibold text-gray-900 dark:text-white mb-4">{isAr ? 'توزيع الخطط' : 'Plan Distribution'}</h2>
          <div className="space-y-3">
            {d.plan_distribution.map((p) => (
              <div key={p.id} className="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <div className="flex items-center gap-2">
                  <span className={clsx('badge', PLAN_COLORS[p.id] ?? 'badge-info')}>{p.name}</span>
                  <span className="text-sm text-gray-500">{p.count} {isAr ? 'متجر' : 'stores'}</span>
                </div>
                <span className="text-sm font-semibold text-green-600 dark:text-green-400">${p.revenue.toLocaleString()}/mo</span>
              </div>
            ))}
          </div>
        </div>

        {/* Expiring soon */}
        <div className="card p-5">
          <div className="flex items-center gap-2 mb-4">
            <AlertTriangle className="h-4 w-4 text-orange-500" />
            <h2 className="font-semibold text-gray-900 dark:text-white">{isAr ? 'تنتهي قريباً (30 يوم)' : 'Expiring Soon (30 days)'}</h2>
          </div>
          {d.expiring_soon.length === 0 ? (
            <p className="text-gray-400 text-sm text-center py-8">{isAr ? 'لا توجد اشتراكات منتهية قريباً' : 'No subscriptions expiring soon'}</p>
          ) : (
            <div className="space-y-2">
              {d.expiring_soon.map((t) => (
                <div key={t.id} className="flex items-center justify-between p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                  <div>
                    <p className="text-sm font-medium text-gray-900 dark:text-white">{t.name}</p>
                    <p className="text-xs text-gray-500">{new Date(t.ends_at).toLocaleDateString()}</p>
                  </div>
                  <span className={clsx('badge', PLAN_COLORS[t.plan] ?? 'badge-info')}>{t.plan}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Recent signups */}
      <div className="card overflow-hidden">
        <div className="p-5 border-b border-gray-100 dark:border-gray-700">
          <h2 className="font-semibold text-gray-900 dark:text-white">{isAr ? 'أحدث المتاجر' : 'Recent Signups'}</h2>
        </div>
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-700">
            <tr>
              {[isAr ? 'المتجر' : 'Store', isAr ? 'الخطة' : 'Plan', isAr ? 'الحالة' : 'Status', isAr ? 'تاريخ الإنشاء' : 'Created'].map((h) => (
                <th key={h} className="px-4 py-3 text-start text-xs font-semibold uppercase text-gray-500">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {d.recent_tenants.map((t) => (
              <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{t.name}</td>
                <td className="px-4 py-3"><span className={clsx('badge', PLAN_COLORS[t.plan] ?? 'badge-info')}>{t.plan}</span></td>
                <td className="px-4 py-3">
                  <span className={clsx('badge', t.subscription_status === 'active' ? 'badge-success' : t.subscription_status === 'trial' ? 'badge-warning' : 'badge-danger')}>
                    {STATUS_LABELS[t.subscription_status] ?? t.subscription_status}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-500">{new Date(t.created_at).toLocaleDateString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
