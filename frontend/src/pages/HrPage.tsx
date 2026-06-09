import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Users2, Plus, Pencil, Trash2, Clock, Calendar, UserCheck, FileText, DollarSign, CheckCircle, XCircle, Coffee, ChevronDown, ChevronUp } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Employee { id: number; name: string; email?: string; phone?: string; position?: string; department?: string; salary?: string; status?: string; hire_date?: string }
interface ShiftBreak { id: number; started_at: string; ended_at?: string; duration_minutes?: number; type: 'meal' | 'rest' | 'personal' }
interface Shift {
  id: number
  user?: { id: number; name: string }
  clock_in_at: string
  clock_out_at?: string
  hours_worked?: number
  status: string
  on_break?: boolean
  break_started_at?: string
  breaks?: ShiftBreak[]
}
interface AttRecord { id: number; user_name?: string; work_date: string; check_in?: string; check_out?: string; hours_worked?: number; break_minutes?: number; status: string; notes?: string; is_working_now?: boolean; has_checked_out?: boolean }
interface Leave { id: number; user_name?: string; leave_type: string; starts_at: string; ends_at: string; days_count: number; status: string; reason?: string }
interface PayrollRun { id: number; period: string; employee_count: number; gross_salary: string; net_salary: string; status: string }

const emptyEmp = { name: '', email: '', phone: '', position: '', department: '', salary: '', status: 'active', hire_date: '' }
const emptyAtt = { user_id: '', work_date: '', check_in: '', check_out: '', notes: '' }
const emptyLeave = { user_id: '', leave_type: 'annual', starts_at: '', ends_at: '', reason: '' }

function calcDays(start: string, end: string): number {
  if (!start || !end) return 0
  const s = new Date(start), e = new Date(end)
  if (isNaN(s.getTime()) || isNaN(e.getTime()) || e < s) return 0
  return Math.round((e.getTime() - s.getTime()) / 86400000) + 1
}

export default function HrPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [searchParams] = useSearchParams()
  const validTabs = ['employees', 'shifts', 'attendance', 'leaves', 'payroll'] as const
  type TabType = typeof validTabs[number]
  const initialTab = (validTabs.includes(searchParams.get('tab') as TabType)
    ? searchParams.get('tab')
    : 'employees') as TabType
  const [tab, setTab] = useState<TabType>(initialTab)

  useEffect(() => {
    const tabParam = searchParams.get('tab') as TabType
    if (validTabs.includes(tabParam)) setTab(tabParam)
  }, [searchParams])

  // ── Employees state ──
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyEmp })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  // ── Shifts state ──
  const [expandedShiftId, setExpandedShiftId] = useState<number | null>(null)

  // ── Attendance state ──
  const [attDate, setAttDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [attStatus, setAttStatus] = useState('all')
  const [attModal, setAttModal] = useState(false)
  const [attForm, setAttForm] = useState({ ...emptyAtt })

  // ── Leaves state ──
  const [leaveForm, setLeaveForm] = useState({ ...emptyLeave })
  const [rejectId, setRejectId] = useState<number | null>(null)

  // ── Payroll state ──
  const [payYear, setPayYear] = useState(() => new Date().getFullYear())
  const [payMonth, setPayMonth] = useState(() => new Date().getMonth() + 1)
  const [approveRunId, setApproveRunId] = useState<number | null>(null)
  const [markPaidRunId, setMarkPaidRunId] = useState<number | null>(null)

  const canManage = hasPermission('manage_hr')
  const canManagePayroll = hasPermission('manage_settings', 'manage_roles')

  // ── Employees queries / mutations ──
  const { data: empData, isLoading: empLoading, isError: empError } = useQuery({
    queryKey: ['hr-employees'],
    queryFn: () => apiGet<{ success: boolean; data: Employee[] }>('/hr/employees?per_page=50'),
    staleTime: 120_000,
    retry: false,
  })
  const { data: shiftData, isLoading: shiftLoading } = useQuery({
    queryKey: ['hr-shifts'],
    queryFn: () => apiGet<{ shifts: Shift[] }>('/shifts/active'),
    staleTime: 30_000,
    enabled: tab === 'shifts',
    retry: false,
  })

  const employees = empData?.data ?? []
  const shifts = shiftData?.shifts ?? []

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyEmp }); setEditId(null); setModal('add') }
  const openEdit = (e: Employee) => {
    setForm({ name: e.name, email: e.email ?? '', phone: e.phone ?? '', position: e.position ?? '', department: e.department ?? '', salary: e.salary ?? '', status: e.status ?? 'active', hire_date: e.hire_date ?? '' })
    setEditId(e.id); setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/hr/employees/${editId}`, payload) : apiPost('/hr/employees', payload),
    onSuccess: () => { toast.success(editId ? t('updated_success') : t('created_success')); qc.invalidateQueries({ queryKey: ['hr-employees'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/hr/employees/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['hr-employees'] }); setDeleteId(null) },
    onError: () => toast.error(t('delete_failed')),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error(t('error'))
    saveMutation.mutate({ name: form.name, email: form.email || undefined, phone: form.phone || undefined, position: form.position || undefined, department: form.department || undefined, salary: form.salary || undefined, status: form.status, hire_date: form.hire_date || undefined })
  }

  // ── Attendance queries / mutations ──
  const { data: attData, isLoading: attLoading } = useQuery({
    queryKey: ['hr-attendance', attDate, attStatus],
    queryFn: () => {
      const params = new URLSearchParams({ date: attDate, per_page: '30' })
      if (attStatus !== 'all') params.set('status', attStatus)
      return apiGet<{ records: AttRecord[] }>(`/hr/attendance?${params}`)
    },
    staleTime: 60_000,
    enabled: tab === 'attendance',
    retry: false,
  })

  const attRecords = attData?.records ?? []
  const attSummary = {
    working:     attRecords.filter((r) => r.is_working_now).length,
    checked_out: attRecords.filter((r) => r.has_checked_out).length,
    absent:      attRecords.filter((r) => r.status === 'absent').length,
    late:        attRecords.filter((r) => r.status === 'late').length,
  }

  const attCreateMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/hr/attendance/checkin', payload),
    onSuccess: () => { toast.success(t('record_success')); qc.invalidateQueries({ queryKey: ['hr-attendance'] }); setAttModal(false); setAttForm({ ...emptyAtt }) },
    onError: (err: any) => toast.error(err?.response?.data?.message ?? t('record_failed')),
  })

  const handleAttSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!attForm.user_id || !attForm.work_date || !attForm.check_in) return toast.error(t('error'))
    attCreateMutation.mutate({
      user_id: Number(attForm.user_id),
      work_date: attForm.work_date,
      check_in: attForm.check_in,
      check_out: attForm.check_out || undefined,
      notes: attForm.notes || undefined,
    })
  }

  const attStatusBadge = (s: string) => {
    if (s === 'working') return 'badge-success'
    if (s === 'checked_out') return 'badge-info'
    if (s === 'absent') return 'badge-danger'
    if (s === 'late') return 'badge-warning'
    return 'badge-gray'
  }

  // ── Leaves queries / mutations ──
  const { data: leavesData, isLoading: leavesLoading } = useQuery({
    queryKey: ['hr-leaves'],
    queryFn: () => apiGet<{ requests: Leave[] }>('/hr/leaves?status=pending&per_page=20'),
    staleTime: 60_000,
    enabled: tab === 'leaves',
    retry: false,
  })

  const leavesList = leavesData?.requests ?? []

  const leaveApplyMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/hr/leaves', payload),
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['hr-leaves'] }); setLeaveForm({ ...emptyLeave }) },
    onError: () => toast.error(t('save_failed')),
  })
  const leaveApproveMutation = useMutation({
    mutationFn: (id: number) => apiPost(`/hr/leaves/${id}/approve`, {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['hr-leaves'] }) },
    onError: () => toast.error(t('save_failed')),
  })
  const leaveRejectMutation = useMutation({
    mutationFn: (id: number) => apiPost(`/hr/leaves/${id}/reject`, {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['hr-leaves'] }); setRejectId(null) },
    onError: () => toast.error(t('save_failed')),
  })

  const handleLeaveSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!leaveForm.user_id || !leaveForm.starts_at || !leaveForm.ends_at) return toast.error(t('error'))
    const days = calcDays(leaveForm.starts_at, leaveForm.ends_at)
    if (days <= 0) return toast.error(t('error'))
    leaveApplyMutation.mutate({
      user_id: Number(leaveForm.user_id),
      leave_type: leaveForm.leave_type,
      starts_at: leaveForm.starts_at,
      ends_at: leaveForm.ends_at,
      reason: leaveForm.reason || undefined,
    })
  }

  const leaveStatusBadge = (s: string) => {
    if (s === 'approved') return 'badge-success'
    if (s === 'rejected') return 'badge-danger'
    if (s === 'pending') return 'badge-warning'
    return 'badge-gray'
  }

  // ── Payroll queries / mutations ──
  const { data: payrollData, isLoading: payrollLoading } = useQuery({
    queryKey: ['hr-payroll-runs'],
    queryFn: () => apiGet<{ runs: PayrollRun[] }>('/hr/payroll/runs'),
    staleTime: 60_000,
    enabled: tab === 'payroll' && canManagePayroll,
    retry: false,
  })

  const payrollRuns = payrollData?.runs ?? []

  const generatePayrollMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/hr/payroll/generate', payload),
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['hr-payroll-runs'] }) },
    onError: () => toast.error(t('save_failed')),
  })
  const approvePayrollMutation = useMutation({
    mutationFn: (id: number) => apiPost(`/hr/payroll/runs/${id}/approve`, {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['hr-payroll-runs'] }); setApproveRunId(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const markPaidMutation = useMutation({
    mutationFn: (id: number) => apiPost(`/hr/payroll/runs/${id}/mark-paid`, {}),
    onSuccess: () => { toast.success(t('updated_success')); qc.invalidateQueries({ queryKey: ['hr-payroll-runs'] }); setMarkPaidRunId(null) },
    onError: () => toast.error(t('save_failed')),
  })

  const payrollStatusBadge = (s: string) => {
    if (s === 'paid') return 'badge-success'
    if (s === 'approved') return 'badge-info'
    if (s === 'draft') return 'badge-warning'
    return 'badge-gray'
  }

  const currentYear = new Date().getFullYear()
  const years = [currentYear - 1, currentYear, currentYear + 1]
  const months = [
    { v: 1, l: t('month_jan') }, { v: 2, l: t('month_feb') }, { v: 3, l: t('month_mar') },
    { v: 4, l: t('month_apr') }, { v: 5, l: t('month_may') }, { v: 6, l: t('month_jun') },
    { v: 7, l: t('month_jul') }, { v: 8, l: t('month_aug') }, { v: 9, l: t('month_sep') },
    { v: 10, l: t('month_oct') }, { v: 11, l: t('month_nov') }, { v: 12, l: t('month_dec') },
  ]

  if (empError && !canManage) {
    return (
      <div className="card p-8 text-center text-gray-400 space-y-3">
        <Users2 className="h-10 w-10 mx-auto opacity-40" />
        <p className="font-medium">{t('access_denied')}</p>
        <p className="text-sm">{t('no_permission')}</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Users2 className="h-6 w-6 text-primary-500" /> {t('hr')}
        </h1>
        {canManage && tab === 'employees' && (
          <button onClick={openAdd} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('add_employee')}
          </button>
        )}
        {canManage && tab === 'attendance' && (
          <button onClick={() => setAttModal(true)} className="btn btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> {t('manual_entry')}
          </button>
        )}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit flex-wrap">
        {(['employees', 'shifts', 'attendance', 'leaves', 'payroll'] as const).map((tabKey) => (
          <button
            key={tabKey}
            onClick={() => setTab(tabKey)}
            className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors',
              tab === tabKey ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'
            )}
          >
            {t(`hr_tab_${tabKey}`)}
          </button>
        ))}
      </div>

      {/* ── Employees Tab ── */}
      {tab === 'employees' && (
        <div className="card overflow-hidden">
          {empLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{[t('name'), t('position'), t('department'), t('phone'), t('hire_date'), t('salary'), t('status'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {employees.length === 0 ? <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_employees')}</td></tr>
                    : employees.map((e) => (
                      <tr key={e.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{e.name}</td>
                        <td className="px-4 py-3 text-gray-500">{e.position ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-500">{e.department ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-500">{e.phone ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-400 text-xs">{e.hire_date?.slice(0, 10) ?? '—'}</td>
                        <td className="px-4 py-3 font-semibold text-primary-600">{e.salary ? parseFloat(e.salary).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}</td>
                        <td className="px-4 py-3"><span className={clsx('badge capitalize', e.status === 'active' ? 'badge-success' : 'badge-gray')}>{e.status ?? 'active'}</span></td>
                        <td className="px-4 py-3">
                          {canManage && (
                            <div className="flex gap-1 justify-end">
                              <button onClick={() => openEdit(e)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>
                              <button onClick={() => setDeleteId(e.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ── Shifts Tab ── */}
      {tab === 'shifts' && (
        <div className="space-y-3">
          {/* KPI row */}
          <div className="grid grid-cols-3 gap-4">
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg"><UserCheck className="h-5 w-5 text-green-600" /></div>
              <div><p className="text-xs text-gray-500">ورديات نشطة</p><p className="text-2xl font-bold text-green-600">{shifts.filter((s) => s.status === 'active').length}</p></div>
            </div>
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg"><Coffee className="h-5 w-5 text-yellow-600" /></div>
              <div><p className="text-xs text-gray-500">في استراحة</p><p className="text-2xl font-bold text-yellow-600">{shifts.filter((s) => s.on_break).length}</p></div>
            </div>
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg"><Clock className="h-5 w-5 text-blue-600" /></div>
              <div><p className="text-xs text-gray-500">إجمالي الاستراحات</p><p className="text-2xl font-bold text-blue-600">{shifts.reduce((sum, s) => sum + (s.breaks?.length ?? 0), 0)}</p></div>
            </div>
          </div>

          <div className="card overflow-hidden">
            {shiftLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[t('employee'), t('clock_in'), t('clock_out'), t('hours_worked'), 'الاستراحات', t('status'), ''].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {shifts.length === 0 ? (
                    <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">{t('no_shifts')}</td></tr>
                  ) : shifts.map((s) => {
                    const sBreaks = s.breaks ?? []
                    const completedBreaks = sBreaks.filter((b) => b.ended_at)
                    const totalBreakMins = completedBreaks.reduce((sum, b) => sum + (b.duration_minutes ?? 0), 0)
                    const isExpanded = expandedShiftId === s.id
                    return (
                      <>
                        <tr key={s.id} className={clsx('hover:bg-gray-50 dark:hover:bg-gray-700/50', s.on_break && 'bg-yellow-50/50 dark:bg-yellow-900/10')}>
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {s.user?.name ?? '—'}
                            {s.on_break && <span className="mr-1 badge badge-warning text-xs">استراحة</span>}
                          </td>
                          <td className="px-4 py-3 text-gray-500">{s.clock_in_at ? new Date(s.clock_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}</td>
                          <td className="px-4 py-3 text-gray-500">
                            {s.clock_out_at ? new Date(s.clock_out_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : <span className="text-green-500 font-medium">{t('ongoing')}</span>}
                          </td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                            {s.hours_worked ? <span className="badge badge-info">{s.hours_worked}h</span> : '—'}
                          </td>
                          <td className="px-4 py-3">
                            {sBreaks.length > 0 ? (
                              <span className="text-xs text-yellow-600 flex items-center gap-1">
                                <Coffee className="h-3 w-3" />
                                {sBreaks.length}×
                                {totalBreakMins > 0 && <span className="text-gray-400">({Math.floor(totalBreakMins / 60) > 0 ? `${Math.floor(totalBreakMins / 60)}h ` : ''}{totalBreakMins % 60}m)</span>}
                                {s.on_break && <span className="text-yellow-500">جارية</span>}
                              </span>
                            ) : <span className="text-gray-400 text-xs">—</span>}
                          </td>
                          <td className="px-4 py-3">
                            <span className={clsx('badge capitalize', s.status === 'active' ? 'badge-success' : 'badge-gray')}>{s.status}</span>
                          </td>
                          <td className="px-4 py-3">
                            {sBreaks.length > 0 && (
                              <button
                                onClick={() => setExpandedShiftId(isExpanded ? null : s.id)}
                                className="p-1 text-gray-400 hover:text-primary-600 rounded"
                              >
                                {isExpanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                              </button>
                            )}
                          </td>
                        </tr>
                        {isExpanded && sBreaks.length > 0 && (
                          <tr key={`${s.id}-breaks`}>
                            <td colSpan={7} className="px-4 pb-3 bg-gray-50 dark:bg-gray-800">
                              <div className="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <table className="w-full text-xs">
                                  <thead className="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                      {['#', 'النوع', 'بداية', 'نهاية', 'المدة'].map((h) => (
                                        <th key={h} className="px-3 py-2 text-left font-semibold text-gray-500">{h}</th>
                                      ))}
                                    </tr>
                                  </thead>
                                  <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {sBreaks.map((b, bi) => (
                                      <tr key={b.id} className={!b.ended_at ? 'bg-yellow-50 dark:bg-yellow-900/10' : ''}>
                                        <td className="px-3 py-2 text-gray-500">{bi + 1}</td>
                                        <td className="px-3 py-2 capitalize text-gray-700 dark:text-gray-300 flex items-center gap-1">
                                          <Coffee className="h-3 w-3 text-yellow-500" />{b.type}
                                        </td>
                                        <td className="px-3 py-2 text-gray-600">{new Date(b.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</td>
                                        <td className="px-3 py-2 text-gray-600">
                                          {b.ended_at ? new Date(b.ended_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : <span className="text-yellow-500">جارية</span>}
                                        </td>
                                        <td className="px-3 py-2">
                                          {b.duration_minutes != null ? (
                                            <span className="badge badge-gray">{b.duration_minutes}m</span>
                                          ) : <span className="text-yellow-500">—</span>}
                                        </td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            </td>
                          </tr>
                        )}
                      </>
                    )
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}

      {/* ── Attendance Tab ── */}
      {tab === 'attendance' && (
        <div className="space-y-4">
          {/* KPI Cards */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg"><UserCheck className="h-5 w-5 text-green-600" /></div>
              <div><p className="text-xs text-gray-500">{t('working_now')}</p><p className="text-2xl font-bold text-green-600">{attSummary.working}</p></div>
            </div>
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg"><Clock className="h-5 w-5 text-blue-600" /></div>
              <div><p className="text-xs text-gray-500">{t('checked_out')}</p><p className="text-2xl font-bold text-blue-600">{attSummary.checked_out}</p></div>
            </div>
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg"><XCircle className="h-5 w-5 text-red-600" /></div>
              <div><p className="text-xs text-gray-500">{t('absent')}</p><p className="text-2xl font-bold text-red-600">{attSummary.absent}</p></div>
            </div>
            <div className="card p-4 flex items-center gap-3">
              <div className="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg"><Calendar className="h-5 w-5 text-orange-500" /></div>
              <div><p className="text-xs text-gray-500">{t('late')}</p><p className="text-2xl font-bold text-orange-500">{attSummary.late}</p></div>
            </div>
          </div>

          {/* Filters */}
          <div className="card p-4 flex flex-wrap gap-3 items-end">
            <div>
              <label className="label">{t('date')}</label>
              <input type="date" value={attDate} onChange={(e) => setAttDate(e.target.value)} className="input" />
            </div>
            <div>
              <label className="label">{t('status')}</label>
              <select value={attStatus} onChange={(e) => setAttStatus(e.target.value)} className="input">
                <option value="all">{t('all')}</option>
                <option value="working">{t('working_now')}</option>
                <option value="checked_out">{t('checked_out')}</option>
                <option value="absent">{t('absent')}</option>
                <option value="late">{t('late')}</option>
              </select>
            </div>
          </div>

          {/* Table */}
          <div className="card overflow-hidden">
            {attLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('employee'), t('work_date'), t('check_in'), t('check_out'), t('hours_worked'), 'الاستراحات', t('status'), t('notes')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {attRecords.length === 0
                      ? <tr><td colSpan={8} className="px-4 py-10 text-center text-gray-400">{t('no_attendance')}</td></tr>
                      : attRecords.map((r) => (
                        <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{r.user_name ?? '—'}</td>
                          <td className="px-4 py-3 text-gray-500">{r.work_date}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{r.check_in ? r.check_in.slice(11, 16) : '—'}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{r.check_out ? r.check_out.slice(11, 16) : '—'}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{r.hours_worked ? <span className="badge badge-info">{r.hours_worked}h</span> : '—'}</td>
                          <td className="px-4 py-3">
                            {r.break_minutes ? (
                              <span className="text-xs text-yellow-600 flex items-center gap-1">
                                <Coffee className="h-3 w-3" />{r.break_minutes}m
                              </span>
                            ) : <span className="text-gray-400">—</span>}
                          </td>
                          <td className="px-4 py-3"><span className={clsx('badge capitalize', attStatusBadge(r.status))}>{r.status.replace('_', ' ')}</span></td>
                          <td className="px-4 py-3 text-gray-400 text-xs max-w-[160px] truncate">{r.notes ?? '—'}</td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Leaves Tab ── */}
      {tab === 'leaves' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Apply Leave Sidebar */}
          <div className="card p-5 space-y-4">
            <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2"><FileText className="h-4 w-4 text-primary-500" /> {t('apply_for_leave')}</h2>
            <form onSubmit={handleLeaveSubmit} className="space-y-3">
              <div>
                <label className="label">{t('employee')} *</label>
                <select value={leaveForm.user_id} onChange={(e) => setLeaveForm((p) => ({ ...p, user_id: e.target.value }))} className="input w-full" required>
                  <option value="">{t('select_employee')}</option>
                  {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                </select>
              </div>
              <div>
                <label className="label">{t('leave_type')} *</label>
                <select value={leaveForm.leave_type} onChange={(e) => setLeaveForm((p) => ({ ...p, leave_type: e.target.value }))} className="input w-full">
                  <option value="annual">{t('leave_annual')}</option>
                  <option value="sick">{t('leave_sick')}</option>
                  <option value="unpaid">{t('leave_unpaid')}</option>
                </select>
              </div>
              <div>
                <label className="label">{t('start_date')} *</label>
                <input type="date" value={leaveForm.starts_at}
                  onChange={(e) => setLeaveForm((p) => ({ ...p, starts_at: e.target.value }))}
                  className="input w-full" required />
              </div>
              <div>
                <label className="label">{t('end_date')} *</label>
                <input type="date" value={leaveForm.ends_at}
                  onChange={(e) => setLeaveForm((p) => ({ ...p, ends_at: e.target.value }))}
                  className="input w-full" required />
              </div>
              <div>
                <label className="label">{t('days_count_label')}</label>
                <input type="number" readOnly value={calcDays(leaveForm.starts_at, leaveForm.ends_at)} className="input w-full bg-gray-50 dark:bg-gray-700 cursor-not-allowed" />
              </div>
              <div>
                <label className="label">{t('reason')}</label>
                <textarea value={leaveForm.reason} onChange={(e) => setLeaveForm((p) => ({ ...p, reason: e.target.value }))} className="input w-full min-h-[80px] resize-y" rows={3} />
              </div>
              <button type="submit" disabled={leaveApplyMutation.isPending} className="btn btn-primary w-full">
                {leaveApplyMutation.isPending ? t('submitting') : t('submit_request')}
              </button>
            </form>
          </div>

          {/* Pending Requests Table */}
          <div className="lg:col-span-2 card overflow-hidden">
            <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('pending_requests')}</h2>
            </div>
            {leavesLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('employee'), t('type'), t('start_date'), t('end_date'), t('days'), t('status'), t('actions')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {leavesList.length === 0
                      ? <tr><td colSpan={7} className="px-4 py-10 text-center text-gray-400">{t('no_leave_requests')}</td></tr>
                      : leavesList.map((lv) => (
                        <tr key={lv.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{lv.user_name ?? '—'}</td>
                          <td className="px-4 py-3 capitalize text-gray-500">{lv.leave_type}</td>
                          <td className="px-4 py-3 text-gray-500">{lv.starts_at}</td>
                          <td className="px-4 py-3 text-gray-500">{lv.ends_at}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{lv.days_count}</td>
                          <td className="px-4 py-3"><span className={clsx('badge capitalize', leaveStatusBadge(lv.status))}>{lv.status}</span></td>
                          <td className="px-4 py-3">
                            {canManage && lv.status === 'pending' && (
                              <div className="flex gap-1">
                                <button onClick={() => leaveApproveMutation.mutate(lv.id)} disabled={leaveApproveMutation.isPending} className="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded" title="Approve">
                                  <CheckCircle className="h-4 w-4" />
                                </button>
                                <button onClick={() => setRejectId(lv.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded" title="Reject">
                                  <XCircle className="h-4 w-4" />
                                </button>
                              </div>
                            )}
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Payroll Tab ── */}
      {tab === 'payroll' && (
        <div className="space-y-4">
          {/* Generate Payroll Row */}
          <div className="card p-4 flex flex-wrap gap-3 items-end">
            <div>
              <label className="label">{t('year')}</label>
              <select value={payYear} onChange={(e) => setPayYear(Number(e.target.value))} className="input">
                {years.map((y) => <option key={y} value={y}>{y}</option>)}
              </select>
            </div>
            <div>
              <label className="label">{t('month')}</label>
              <select value={payMonth} onChange={(e) => setPayMonth(Number(e.target.value))} className="input">
                {months.map((m) => <option key={m.v} value={m.v}>{m.l}</option>)}
              </select>
            </div>
            {canManagePayroll && (
              <button
                onClick={() => generatePayrollMutation.mutate({ year: payYear, month: payMonth })}
                disabled={generatePayrollMutation.isPending}
                className="btn btn-primary flex items-center gap-2"
              >
                <DollarSign className="h-4 w-4" />
                {generatePayrollMutation.isPending ? t('generating') : t('generate_payroll')}
              </button>
            )}
          </div>

          {/* Payroll Runs Table */}
          <div className="card overflow-hidden">
            {payrollLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('period'), t('employees'), t('gross_salary'), t('net_salary'), t('status'), t('actions')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {payrollRuns.length === 0
                      ? <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">{t('no_payroll_runs')}</td></tr>
                      : payrollRuns.map((run) => (
                        <tr key={run.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{run.period}</td>
                          <td className="px-4 py-3 text-gray-500">{run.employee_count}</td>
                          <td className="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">{parseFloat(run.gross_salary).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                          <td className="px-4 py-3 font-semibold text-primary-600">{parseFloat(run.net_salary).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                          <td className="px-4 py-3"><span className={clsx('badge capitalize', payrollStatusBadge(run.status))}>{run.status}</span></td>
                          <td className="px-4 py-3">
                            {canManagePayroll && (
                              <div className="flex gap-1">
                                {run.status === 'draft' && (
                                  <button onClick={() => setApproveRunId(run.id)} className="btn btn-secondary text-xs py-1 px-2">{t('approve')}</button>
                                )}
                                {run.status === 'approved' && (
                                  <button onClick={() => setMarkPaidRunId(run.id)} className="btn btn-primary text-xs py-1 px-2">{t('mark_paid')}</button>
                                )}
                              </div>
                            )}
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Employee Add/Edit Modal ── */}
      <Modal open={modal !== null} onClose={() => setModal(null)} title={editId ? t('edit_employee') : t('add_employee')} size="lg"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">{saveMutation.isPending ? t('saving') : editId ? t('update') : t('create')}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2"><label className="label">{t('full_name')} *</label><input {...f('name')} className="input w-full" required /></div>
            <div><label className="label">{t('email')}</label><input {...f('email')} type="email" className="input w-full" /></div>
            <div><label className="label">{t('phone')}</label><input {...f('phone')} className="input w-full" /></div>
            <div><label className="label">{t('position')}</label><input {...f('position')} className="input w-full" /></div>
            <div><label className="label">{t('department')}</label><input {...f('department')} className="input w-full" /></div>
            <div><label className="label">{t('salary')}</label><input {...f('salary')} type="number" step="0.01" min="0" className="input w-full" /></div>
            <div><label className="label">{t('hire_date')}</label><input {...f('hire_date')} type="date" className="input w-full" /></div>
            <div><label className="label">{t('status')}</label><select {...f('status')} className="input w-full"><option value="active">{t('active')}</option><option value="inactive">{t('inactive')}</option><option value="terminated">{t('terminated')}</option></select></div>
          </div>
        </form>
      </Modal>

      {/* ── Attendance Manual Entry Modal ── */}
      <Modal open={attModal} onClose={() => setAttModal(false)} title={t('manual_attendance_entry')} size="md"
        footer={<><button onClick={() => setAttModal(false)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleAttSubmit} disabled={attCreateMutation.isPending} className="btn btn-primary">{attCreateMutation.isPending ? t('saving') : t('save')}</button></>}>
        <form onSubmit={handleAttSubmit} className="space-y-4">
          <div>
            <label className="label">{t('employee')} *</label>
            <select value={attForm.user_id} onChange={(e) => setAttForm((p) => ({ ...p, user_id: e.target.value }))} className="input w-full" required>
              <option value="">{t('select_employee')}</option>
              {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
            </select>
          </div>
          <div>
            <label className="label">{t('work_date')} *</label>
            <input type="date" value={attForm.work_date} onChange={(e) => setAttForm((p) => ({ ...p, work_date: e.target.value }))} className="input w-full" required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('check_in')} *</label>
              <input type="time" value={attForm.check_in} onChange={(e) => setAttForm((p) => ({ ...p, check_in: e.target.value }))} className="input w-full" required />
            </div>
            <div>
              <label className="label">{t('check_out')}</label>
              <input type="time" value={attForm.check_out} onChange={(e) => setAttForm((p) => ({ ...p, check_out: e.target.value }))} className="input w-full" />
            </div>
          </div>
          <div>
            <label className="label">{t('notes')}</label>
            <input type="text" value={attForm.notes} onChange={(e) => setAttForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" />
          </div>
        </form>
      </Modal>

      {/* ── Confirm Dialogs ── */}
      <ConfirmDialog open={deleteId !== null} title={t('delete_employee')} message={t('confirm_delete')} loading={deleteMutation.isPending} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} onCancel={() => setDeleteId(null)} />
      <ConfirmDialog open={rejectId !== null} title={t('reject_leave')} message={t('confirm_reject_leave')} loading={leaveRejectMutation.isPending} onConfirm={() => rejectId && leaveRejectMutation.mutate(rejectId)} onCancel={() => setRejectId(null)} />
      <ConfirmDialog open={approveRunId !== null} title={t('approve_payroll')} message={t('confirm_approve_payroll')} loading={approvePayrollMutation.isPending} onConfirm={() => approveRunId && approvePayrollMutation.mutate(approveRunId)} onCancel={() => setApproveRunId(null)} />
      <ConfirmDialog open={markPaidRunId !== null} title={t('mark_paid')} message={t('confirm_mark_paid')} loading={markPaidMutation.isPending} onConfirm={() => markPaidRunId && markPaidMutation.mutate(markPaidRunId)} onCancel={() => setMarkPaidRunId(null)} />
    </div>
  )
}
