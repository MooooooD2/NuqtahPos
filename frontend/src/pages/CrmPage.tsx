import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Heart, Users, Activity, Clock, Plus } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface CrmStats { total_customers: number; active_customers: number; total_activities: number; pending_followups: number }
interface CrmActivity { id: number; customer_name?: string; type: string; notes?: string; created_at: string; status?: string }
interface FollowUp { id: number; customer_name?: string; due_date: string; notes?: string; status?: string; activity_type?: string }
interface Customer { id: number; name: string }

const activityTypes = ['call', 'email', 'meeting', 'note', 'task', 'other']
const emptyActivity = { customer_id: '', type: 'call', notes: '', due_date: '' }
const emptyFollowup = { customer_id: '', due_date: '', notes: '', activity_type: 'call' }

export default function CrmPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [tab, setTab] = useState<'overview' | 'activities' | 'followups'>('overview')
  const [modal, setModal] = useState<'activity' | 'followup' | null>(null)
  const [actForm, setActForm] = useState({ ...emptyActivity })
  const [fuForm, setFuForm] = useState({ ...emptyFollowup })

  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ['crm-stats'], queryFn: () => apiGet<{ success: boolean; data: CrmStats }>('/crm/stats'), staleTime: 60_000,
  })
  const { data: activitiesData, isLoading: actLoading } = useQuery({
    queryKey: ['crm-activities'], queryFn: () => apiGet<{ success: boolean; data: CrmActivity[] }>('/crm/activities?per_page=30'), staleTime: 30_000, enabled: tab === 'activities',
  })
  const { data: followupsData, isLoading: fuLoading } = useQuery({
    queryKey: ['crm-followups'], queryFn: () => apiGet<{ success: boolean; data: FollowUp[] }>('/crm/follow-ups?per_page=30'), staleTime: 30_000, enabled: tab === 'followups',
  })
  const { data: customersData } = useQuery({
    queryKey: ['customers-dropdown'], queryFn: () => apiGet<{ success: boolean; data: Customer[] }>('/customers?per_page=100'), staleTime: 120_000, enabled: modal !== null,
  })

  const stats = statsData?.data
  const activities = activitiesData?.data ?? []
  const followups = followupsData?.data ?? []
  const customers = customersData?.data ?? []
  const canManage = hasPermission('manage_crm', 'view_warehouse')

  const addActivity = useMutation({
    mutationFn: (payload: object) => apiPost('/crm/activities', payload),
    onSuccess: () => { toast.success('Activity logged'); qc.invalidateQueries({ queryKey: ['crm-activities', 'crm-stats'] }); setModal(null) },
    onError: () => toast.error('Failed to log activity'),
  })
  const addFollowup = useMutation({
    mutationFn: (payload: object) => apiPost('/crm/follow-ups', payload),
    onSuccess: () => { toast.success('Follow-up scheduled'); qc.invalidateQueries({ queryKey: ['crm-followups', 'crm-stats'] }); setModal(null) },
    onError: () => toast.error('Failed to schedule follow-up'),
  })

  const actBadge: Record<string, string> = { call: 'badge-info', email: 'badge-gray', meeting: 'badge-success', note: 'badge-warning', task: 'badge-info', other: 'badge-gray' }

  const kpis = [
    { label: 'Total Customers', value: stats?.total_customers ?? 0, icon: Users, color: 'blue' },
    { label: 'Active Customers', value: stats?.active_customers ?? 0, icon: Heart, color: 'green' },
    { label: 'Activities', value: stats?.total_activities ?? 0, icon: Activity, color: 'purple' },
    { label: 'Pending Follow-ups', value: stats?.pending_followups ?? 0, icon: Clock, color: 'amber' },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Heart className="h-6 w-6 text-primary-500" /> CRM</h1>
        {canManage && (
          <div className="flex gap-2">
            <button onClick={() => { setFuForm({ ...emptyFollowup }); setModal('followup') }} className="btn btn-secondary text-sm flex items-center gap-1"><Plus className="h-4 w-4" /> Follow-up</button>
            <button onClick={() => { setActForm({ ...emptyActivity }); setModal('activity') }} className="btn btn-primary text-sm flex items-center gap-1"><Plus className="h-4 w-4" /> Activity</button>
          </div>
        )}
      </div>

      {/* KPI Cards */}
      {statsLoading ? <div className="h-24 flex items-center justify-center"><LoadingSpinner /></div> : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((kpi) => (
            <div key={kpi.label} className="card p-4 flex items-center gap-4">
              <div className={`h-10 w-10 rounded-xl flex items-center justify-center bg-${kpi.color}-100 dark:bg-${kpi.color}-900/30`}>
                <kpi.icon className={`h-5 w-5 text-${kpi.color}-600 dark:text-${kpi.color}-400`} />
              </div>
              <div><p className="text-xs text-gray-500">{kpi.label}</p><p className="text-2xl font-bold text-gray-900 dark:text-white">{kpi.value}</p></div>
            </div>
          ))}
        </div>
      )}

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['overview', 'activities', 'followups'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === t ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{t === 'followups' ? 'Follow-ups' : t}</button>
        ))}
      </div>

      {tab === 'overview' && (
        <div className="card p-6 text-center text-gray-400">
          <Heart className="h-12 w-12 mx-auto mb-3 opacity-30" />
          <p>Switch to <strong>Activities</strong> or <strong>Follow-ups</strong> tabs to manage customer relationships</p>
        </div>
      )}

      {tab === 'activities' && (
        <div className="card overflow-hidden">
          {actLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700"><tr>{['Customer', 'Type', 'Notes', 'Date'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {activities.length === 0 ? <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">No activities yet</td></tr>
                  : activities.map((a) => (
                    <tr key={a.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{a.customer_name ?? '—'}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', actBadge[a.type] ?? 'badge-gray')}>{a.type}</span></td>
                      <td className="px-4 py-3 text-gray-500 max-w-64 truncate">{a.notes ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-400 text-xs">{a.created_at?.slice(0, 16)}</td>
                    </tr>
                  ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {tab === 'followups' && (
        <div className="card overflow-hidden">
          {fuLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700"><tr>{['Customer', 'Type', 'Due Date', 'Notes', 'Status'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {followups.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No follow-ups scheduled</td></tr>
                  : followups.map((f) => (
                    <tr key={f.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{f.customer_name ?? '—'}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', actBadge[f.activity_type ?? ''] ?? 'badge-gray')}>{f.activity_type ?? 'call'}</span></td>
                      <td className="px-4 py-3 text-gray-500">{f.due_date?.slice(0, 10)}</td>
                      <td className="px-4 py-3 text-gray-500 max-w-48 truncate">{f.notes ?? '—'}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', f.status === 'pending' ? 'badge-warning' : f.status === 'done' ? 'badge-success' : 'badge-gray')}>{f.status ?? 'pending'}</span></td>
                    </tr>
                  ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Log Activity Modal */}
      <Modal open={modal === 'activity'} onClose={() => setModal(null)} title="Log Activity" size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={() => { if (!actForm.customer_id) return toast.error('Customer required'); addActivity.mutate({ customer_id: parseInt(actForm.customer_id), type: actForm.type, notes: actForm.notes || undefined }) }} disabled={addActivity.isPending} className="btn btn-primary">{addActivity.isPending ? 'Saving…' : 'Log Activity'}</button></>}>
        <div className="space-y-4">
          <div>
            <label className="label">Customer *</label>
            <select value={actForm.customer_id} onChange={(e) => setActForm((p) => ({ ...p, customer_id: e.target.value }))} className="input w-full">
              <option value="">— Select customer —</option>
              {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div>
            <label className="label">Activity Type</label>
            <select value={actForm.type} onChange={(e) => setActForm((p) => ({ ...p, type: e.target.value }))} className="input w-full">
              {activityTypes.map((t) => <option key={t} value={t} className="capitalize">{t}</option>)}
            </select>
          </div>
          <div><label className="label">Notes</label><textarea value={actForm.notes} onChange={(e) => setActForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full h-24 resize-none" placeholder="What happened?" /></div>
        </div>
      </Modal>

      {/* Schedule Follow-up Modal */}
      <Modal open={modal === 'followup'} onClose={() => setModal(null)} title="Schedule Follow-up" size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={() => { if (!fuForm.customer_id || !fuForm.due_date) return toast.error('Customer and due date required'); addFollowup.mutate({ customer_id: parseInt(fuForm.customer_id), due_date: fuForm.due_date, notes: fuForm.notes || undefined, activity_type: fuForm.activity_type }) }} disabled={addFollowup.isPending} className="btn btn-primary">{addFollowup.isPending ? 'Saving…' : 'Schedule'}</button></>}>
        <div className="space-y-4">
          <div>
            <label className="label">Customer *</label>
            <select value={fuForm.customer_id} onChange={(e) => setFuForm((p) => ({ ...p, customer_id: e.target.value }))} className="input w-full">
              <option value="">— Select customer —</option>
              {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">Type</label>
              <select value={fuForm.activity_type} onChange={(e) => setFuForm((p) => ({ ...p, activity_type: e.target.value }))} className="input w-full">
                {activityTypes.map((t) => <option key={t} value={t} className="capitalize">{t}</option>)}
              </select>
            </div>
            <div><label className="label">Due Date *</label><input value={fuForm.due_date} type="date" onChange={(e) => setFuForm((p) => ({ ...p, due_date: e.target.value }))} className="input w-full" /></div>
          </div>
          <div><label className="label">Notes</label><textarea value={fuForm.notes} onChange={(e) => setFuForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full h-20 resize-none" /></div>
        </div>
      </Modal>
    </div>
  )
}
