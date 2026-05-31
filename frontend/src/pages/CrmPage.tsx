import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { Heart, Activity } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

interface CrmStats { total_customers: number; active_customers: number; total_activities: number; pending_followups: number }
interface CrmActivity { id: number; customer_name: string; type: string; notes: string; created_at: string }

export default function CrmPage() {
  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ['crm-stats'], queryFn: () => apiGet<{ success: boolean; stats: CrmStats }>('/crm/stats'), staleTime: 60_000, retry: false,
  })
  const { data: activitiesData, isLoading: actLoading } = useQuery({
    queryKey: ['crm-followups'], queryFn: () => apiGet<{ success: boolean; data: CrmActivity[] }>('/crm/follow-ups'), staleTime: 60_000, retry: false,
  })

  const stats = statsData?.stats
  const activities = activitiesData?.data ?? []

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Heart className="h-6 w-6 text-primary-500" /> CRM</h1>

      {statsLoading ? <div className="flex h-32 items-center justify-center"><LoadingSpinner /></div> : stats ? (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {[
            { label: 'Total Customers', value: stats.total_customers ?? 0, color: 'blue' },
            { label: 'Active Customers', value: stats.active_customers ?? 0, color: 'green' },
            { label: 'Activities', value: stats.total_activities ?? 0, color: 'purple' },
            { label: 'Pending Follow-ups', value: stats.pending_followups ?? 0, color: 'yellow' },
          ].map(c => (
            <div key={c.label} className="card p-4">
              <p className="text-xs text-gray-500 uppercase font-semibold">{c.label}</p>
              <p className={`mt-1 text-2xl font-bold text-${c.color}-600 dark:text-${c.color}-400`}>{c.value}</p>
            </div>
          ))}
        </div>
      ) : (
        <div className="card p-6 text-center text-gray-400">CRM stats not available — check user permissions.</div>
      )}

      <div className="card overflow-hidden">
        <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
          <Activity className="h-4 w-4 text-primary-500" />
          <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Recent Activities</h2>
        </div>
        {actLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Customer', 'Type', 'Notes', 'Date'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {activities.length === 0 ? (
                <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">No activities recorded yet.</td></tr>
              ) : activities.map(a => (
                <tr key={a.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{a.customer_name}</td>
                  <td className="px-4 py-3"><span className="badge badge-info capitalize">{a.type}</span></td>
                  <td className="px-4 py-3 text-gray-500 truncate max-w-48">{a.notes ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-400 text-xs">{a.created_at?.slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
