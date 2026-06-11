import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet, apiPost } from '@/services/api'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Clock, LogIn, LogOut, Coffee, CheckCircle, Timer, UtensilsCrossed, User } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface ShiftBreak {
  id: number
  started_at: string
  ended_at?: string
  duration_minutes?: number
  type: 'meal' | 'rest' | 'personal'
}

interface Shift {
  id: number
  clock_in_at: string
  clock_out_at?: string
  status: string
  on_break?: boolean
  break_started_at?: string
  total_hours?: string
  breaks?: ShiftBreak[]
}
interface ShiftRecord {
  id: number
  date: string
  shift_date: string
  clock_in_at: string
  clock_out_at?: string
  hours_worked?: number
  status: string
  breaks?: ShiftBreak[]
}

type BreakType = 'rest' | 'meal' | 'personal'

const emptyClockOut = { cash_collected: '', cashier_note: '' }

function formatElapsed(fromISO: string): string {
  const secs = Math.floor((Date.now() - new Date(fromISO).getTime()) / 1000)
  const h = Math.floor(secs / 3600)
  const m = Math.floor((secs % 3600) / 60)
  const s = secs % 60
  return [h, m, s].map((v) => String(v).padStart(2, '0')).join(':')
}

function formatTime(dt: string): string {
  return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatDate(dt: string): string {
  return new Date(dt).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatMins(mins: number): string {
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return h > 0 ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`
}

function getNetWorkedMins(shift: Shift): number {
  const totalSecs = Math.floor((Date.now() - new Date(shift.clock_in_at).getTime()) / 1000)
  const completedBreakMins = (shift.breaks ?? [])
    .filter((b) => b.ended_at)
    .reduce((sum, b) => sum + (b.duration_minutes ?? 0), 0)
  const currentBreakMins = shift.on_break && shift.break_started_at
    ? Math.floor((Date.now() - new Date(shift.break_started_at).getTime()) / 60000)
    : 0
  return Math.max(0, Math.floor(totalSecs / 60) - completedBreakMins - currentBreakMins)
}

function getTotalBreakMins(shift: Shift): number {
  const completed = (shift.breaks ?? [])
    .filter((b) => b.ended_at)
    .reduce((sum, b) => sum + (b.duration_minutes ?? 0), 0)
  const current = shift.on_break && shift.break_started_at
    ? Math.floor((Date.now() - new Date(shift.break_started_at).getTime()) / 60000)
    : 0
  return completed + current
}

const statusBadge = (s: string) => {
  if (s === 'active') return 'badge-success'
  if (s === 'completed') return 'badge-info'
  if (s === 'on_break') return 'badge-warning'
  return 'badge-gray'
}

const breakTypeIcon = (type: BreakType) => {
  if (type === 'meal') return UtensilsCrossed
  if (type === 'personal') return User
  return Coffee
}

export default function MyShiftPage() {
  const { t } = useTranslation('pos')
  const qc = useQueryClient()
  const [clockOutModal, setClockOutModal] = useState(false)
  const [clockOutForm, setClockOutForm] = useState({ ...emptyClockOut })
  const [breakModal, setBreakModal] = useState(false)
  const [selectedBreakType, setSelectedBreakType] = useState<BreakType>('rest')
  const [tick, setTick] = useState(0)

  useEffect(() => {
    const timer = setInterval(() => setTick((n) => n + 1), 1000)
    return () => clearInterval(timer)
  }, [])

  const { data: currentData, isLoading: currentLoading } = useQuery({
    queryKey: ['my-shift-current'],
    queryFn: () => apiGet<{ shift?: Shift }>('/shifts/current'),
    refetchInterval: 60_000,
    staleTime: 30_000,
  })

  const { data: historyData, isLoading: historyLoading } = useQuery({
    queryKey: ['my-shift-history'],
    queryFn: () => apiGet<{ success: boolean; data: ShiftRecord[] }>('/shifts/history', { days: 30 }),
    staleTime: 60_000,
  })

  const shift = currentData?.shift
  const history = historyData?.data ?? []

  void tick

  const clockInMutation = useMutation({
    mutationFn: () => apiPost('/shifts/clock-in', {}),
    onSuccess: () => {
      toast.success(t('record_success'))
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
      qc.invalidateQueries({ queryKey: ['my-shift-history'] })
    },
    onError: () => toast.error(t('save_failed')),
  })

  const breakStartMutation = useMutation({
    mutationFn: (type: BreakType) => apiPost('/shifts/break/start', { type }),
    onSuccess: () => {
      toast.success(t('break_started'))
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
      setBreakModal(false)
    },
    onError: () => toast.error(t('save_failed')),
  })

  const breakEndMutation = useMutation({
    mutationFn: () => apiPost('/shifts/break/end', {}),
    onSuccess: () => {
      toast.success(t('updated_success'))
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
    },
    onError: () => toast.error(t('save_failed')),
  })

  const clockOutMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/shifts/clock-out', payload),
    onSuccess: () => {
      toast.success(t('record_success'))
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
      qc.invalidateQueries({ queryKey: ['my-shift-history'] })
      setClockOutModal(false)
      setClockOutForm({ ...emptyClockOut })
    },
    onError: () => toast.error(t('save_failed')),
  })

  const handleClockOut = (e: React.FormEvent) => {
    e.preventDefault()
    clockOutMutation.mutate({
      cash_collected: clockOutForm.cash_collected ? parseFloat(clockOutForm.cash_collected) : undefined,
      cashier_note: clockOutForm.cashier_note || undefined,
    })
  }

  const elapsedDisplay = shift ? formatElapsed(shift.clock_in_at) : '00:00:00'
  const netWorkedMins = shift ? getNetWorkedMins(shift) : 0
  const totalBreakMins = shift ? getTotalBreakMins(shift) : 0
  const completedBreaks = (shift?.breaks ?? []).filter((b) => b.ended_at)

  const breakTypeOptions: { key: BreakType; label: string; icon: typeof Coffee }[] = [
    { key: 'rest',     label: t('leave_sick') === t('leave_sick') ? 'استراحة' : 'Rest',     icon: Coffee },
    { key: 'meal',     label: 'وجبة',      icon: UtensilsCrossed },
    { key: 'personal', label: 'شخصي',      icon: User },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Clock className="h-6 w-6 text-primary-500" /> {t('my_shift_title')}
        </h1>
      </div>

      {currentLoading ? (
        <div className="flex h-48 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : shift ? (
        <>
          {/* ── Active shift card ── */}
          <div className="card p-6 space-y-6">
            {/* Clock display */}
            <div className="flex flex-col items-center gap-2">
              <div className={clsx(
                'h-20 w-20 rounded-full flex items-center justify-center mb-2',
                shift.on_break ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-green-100 dark:bg-green-900/30',
              )}>
                {shift.on_break
                  ? <Coffee className="h-10 w-10 text-yellow-600" />
                  : <Clock className="h-10 w-10 text-green-600" />}
              </div>
              <p className="font-mono text-5xl font-bold text-gray-900 dark:text-white tracking-widest">
                {elapsedDisplay}
              </p>
              <p className="text-sm text-gray-500">
                {shift.on_break ? t('on_break') : t('shift_in_progress')}
              </p>
            </div>

            {/* Stats grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div className="card p-4 bg-gray-50 dark:bg-gray-700/50 space-y-1">
                <p className="text-xs text-gray-500">{t('clocked_in')}</p>
                <p className="font-semibold text-gray-900 dark:text-white">{formatTime(shift.clock_in_at)}</p>
                <p className="text-xs text-gray-400">{formatDate(shift.clock_in_at)}</p>
              </div>
              <div className="card p-4 bg-gray-50 dark:bg-gray-700/50 space-y-1">
                <p className="text-xs text-gray-500">{t('status')}</p>
                <span className={clsx('badge capitalize', statusBadge(shift.status))}>
                  {shift.status.replace('_', ' ')}
                </span>
              </div>
              <div className="card p-4 bg-green-50 dark:bg-green-900/20 space-y-1">
                <p className="text-xs text-green-600 flex items-center gap-1"><Timer className="h-3 w-3" /> صافي العمل</p>
                <p className="font-semibold text-green-700 dark:text-green-400">{formatMins(netWorkedMins)}</p>
              </div>
              <div className={clsx('card p-4 space-y-1', totalBreakMins > 0 ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-gray-50 dark:bg-gray-700/50')}>
                <p className="text-xs text-yellow-600 flex items-center gap-1"><Coffee className="h-3 w-3" /> {t('total_break_time')}</p>
                <p className={clsx('font-semibold', totalBreakMins > 0 ? 'text-yellow-700 dark:text-yellow-400' : 'text-gray-400')}>
                  {totalBreakMins > 0 ? formatMins(totalBreakMins) : '—'}
                  {completedBreaks.length > 0 && (
                    <span className="text-xs text-yellow-500 mr-1">({completedBreaks.length})</span>
                  )}
                </p>
              </div>
            </div>

            {/* Current break info */}
            {shift.on_break && shift.break_started_at && (
              <div className="flex items-center gap-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-3">
                <Coffee className="h-5 w-5 text-yellow-600 shrink-0" />
                <div className="flex-1">
                  <p className="text-sm font-medium text-yellow-700 dark:text-yellow-400">
                    {t('on_break')} — {t('elapsed')}: {formatElapsed(shift.break_started_at)}
                  </p>
                  <p className="text-xs text-yellow-500">{t('break_started')}: {formatTime(shift.break_started_at)}</p>
                </div>
              </div>
            )}

            {/* Action buttons */}
            <div className="flex flex-wrap gap-3 justify-center">
              {!shift.on_break ? (
                <button
                  onClick={() => setBreakModal(true)}
                  className="btn btn-secondary flex items-center gap-2"
                >
                  <Coffee className="h-4 w-4" /> {t('start_break')}
                </button>
              ) : (
                <button
                  onClick={() => breakEndMutation.mutate()}
                  disabled={breakEndMutation.isPending}
                  className="btn btn-secondary flex items-center gap-2"
                >
                  <CheckCircle className="h-4 w-4" />
                  {breakEndMutation.isPending ? t('saving') : t('end_break')}
                </button>
              )}
              <button
                onClick={() => setClockOutModal(true)}
                className="btn flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white"
              >
                <LogOut className="h-4 w-4" /> {t('clock_out')}
              </button>
            </div>
          </div>

          {/* ── Today's break log ── */}
          {(shift.breaks ?? []).length > 0 && (
            <div className="card overflow-hidden">
              <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                <Coffee className="h-4 w-4 text-yellow-500" />
                <h2 className="font-semibold text-gray-900 dark:text-white text-sm">سجل الاستراحات اليوم</h2>
                <span className="badge badge-warning mr-auto">{formatMins(totalBreakMins)} إجمالي</span>
              </div>
              <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {(shift.breaks ?? []).map((b) => {
                  const Icon = breakTypeIcon(b.type)
                  const isOpen = !b.ended_at
                  return (
                    <div key={b.id} className={clsx('flex items-center gap-3 px-4 py-3', isOpen && 'bg-yellow-50 dark:bg-yellow-900/10')}>
                      <Icon className={clsx('h-4 w-4 shrink-0', isOpen ? 'text-yellow-500' : 'text-gray-400')} />
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 dark:text-white capitalize">{b.type}</p>
                        <p className="text-xs text-gray-500">
                          {formatTime(b.started_at)}
                          {b.ended_at ? ` → ${formatTime(b.ended_at)}` : ` → ${t('on_break')}`}
                        </p>
                      </div>
                      <div className="text-right shrink-0">
                        {b.ended_at && b.duration_minutes != null ? (
                          <span className="badge badge-gray text-xs">{formatMins(b.duration_minutes)}</span>
                        ) : (
                          <span className="badge badge-warning text-xs">{t('ongoing')}</span>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          )}
        </>
      ) : (
        <div className="card p-10 flex flex-col items-center gap-6">
          <div className="h-24 w-24 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            <LogIn className="h-12 w-12 text-gray-400" />
          </div>
          <div className="text-center space-y-1">
            <p className="text-lg font-semibold text-gray-700 dark:text-gray-200">{t('no_shift_active')}</p>
            <p className="text-sm text-gray-500">{t('no_shift_active_msg')}</p>
          </div>
          <button
            onClick={() => clockInMutation.mutate()}
            disabled={clockInMutation.isPending}
            className="btn btn-primary text-base px-8 py-3 flex items-center gap-2"
          >
            <LogIn className="h-5 w-5" />
            {clockInMutation.isPending ? t('saving') : t('clock_in')}
          </button>
        </div>
      )}

      {/* ── Shift history ── */}
      <div className="card overflow-hidden">
        <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <Clock className="h-4 w-4 text-primary-500" /> {t('shift_history')}
          </h2>
        </div>
        {historyLoading ? (
          <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div>
        ) : (
          <>
            <div className="hidden lg:block overflow-x-auto">
              <table className="w-full min-w-[550px] text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    {[t('date'), t('clock_in'), t('clock_out'), t('hours_worked'), 'استراحات', t('status')].map((h) => (
                      <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {history.length === 0 ? (
                    <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400">{t('no_shift_history')}</td></tr>
                  ) : history.map((rec) => {
                    const recBreaks = rec.breaks ?? []
                    const recBreakMins = recBreaks.filter((b) => b.ended_at).reduce((s, b) => s + (b.duration_minutes ?? 0), 0)
                    return (
                      <tr key={rec.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{rec.date}</td>
                        <td className="px-4 py-3 text-gray-500">{formatTime(rec.clock_in_at)}</td>
                        <td className="px-4 py-3 text-gray-500">{rec.clock_out_at ? formatTime(rec.clock_out_at) : <span className="text-green-500 font-medium">{t('shift_active')}</span>}</td>
                        <td className="px-4 py-3">{rec.hours_worked ? <span className="badge badge-info">{rec.hours_worked}h</span> : '—'}</td>
                        <td className="px-4 py-3">{recBreaks.length > 0 ? <span className="text-xs text-yellow-600 flex items-center gap-1"><Coffee className="h-3 w-3" />{recBreaks.length}× {recBreakMins > 0 ? `(${formatMins(recBreakMins)})` : ''}</span> : <span className="text-gray-400">—</span>}</td>
                        <td className="px-4 py-3"><span className={clsx('badge capitalize', statusBadge(rec.status))}>{rec.status.replace('_', ' ')}</span></td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
              {history.length === 0 ? <p className="px-4 py-12 text-center text-gray-400">{t('no_shift_history')}</p>
                : history.map((rec) => {
                  const recBreaks = rec.breaks ?? []
                  const recBreakMins = recBreaks.filter((b) => b.ended_at).reduce((s, b) => s + (b.duration_minutes ?? 0), 0)
                  return (
                    <div key={rec.id} className="p-4 space-y-1.5">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-semibold text-gray-700 dark:text-gray-300">{rec.date}</span>
                        <span className={clsx('badge capitalize shrink-0', statusBadge(rec.status))}>{rec.status.replace('_', ' ')}</span>
                      </div>
                      <div className="flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                        <span>{t('clock_in')}: {formatTime(rec.clock_in_at)}</span>
                        <span>{t('clock_out')}: {rec.clock_out_at ? formatTime(rec.clock_out_at) : <span className="text-green-500">{t('shift_active')}</span>}</span>
                        {rec.hours_worked && <span className="badge badge-info">{rec.hours_worked}h</span>}
                        {recBreaks.length > 0 && <span className="text-yellow-600 flex items-center gap-1"><Coffee className="h-3 w-3" />{recBreaks.length}× {recBreakMins > 0 ? `(${formatMins(recBreakMins)})` : ''}</span>}
                      </div>
                    </div>
                  )
                })}
            </div>
          </>
        )}
      </div>

      {/* ── Break type selection modal ── */}
      <Modal
        open={breakModal}
        onClose={() => setBreakModal(false)}
        title={t('start_break')}
        size="sm"
        footer={
          <>
            <button onClick={() => setBreakModal(false)} className="btn btn-secondary">{t('cancel')}</button>
            <button
              onClick={() => breakStartMutation.mutate(selectedBreakType)}
              disabled={breakStartMutation.isPending}
              className="btn btn-primary flex items-center gap-2"
            >
              <Coffee className="h-4 w-4" />
              {breakStartMutation.isPending ? t('saving') : t('start_break')}
            </button>
          </>
        }
      >
        <div className="space-y-3">
          <p className="text-sm text-gray-600 dark:text-gray-400">اختر نوع الاستراحة:</p>
          <div className="grid grid-cols-3 gap-2">
            {breakTypeOptions.map(({ key, label, icon: Icon }) => (
              <button
                key={key}
                onClick={() => setSelectedBreakType(key)}
                className={clsx(
                  'flex flex-col items-center gap-2 p-3 rounded-xl border-2 transition-all text-sm font-medium',
                  selectedBreakType === key
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                    : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-300',
                )}
              >
                <Icon className="h-5 w-5" />
                {label}
              </button>
            ))}
          </div>
        </div>
      </Modal>

      {/* ── Clock-out modal ── */}
      <Modal
        open={clockOutModal}
        onClose={() => setClockOutModal(false)}
        title={t('clock_out')}
        size="sm"
        footer={
          <>
            <button onClick={() => setClockOutModal(false)} className="btn btn-secondary">{t('cancel')}</button>
            <button onClick={handleClockOut} disabled={clockOutMutation.isPending} className="btn bg-red-600 hover:bg-red-700 text-white">
              {clockOutMutation.isPending ? t('saving') : t('clock_out')}
            </button>
          </>
        }
      >
        <form onSubmit={handleClockOut} className="space-y-4">
          {shift && (
            <div className="rounded-lg bg-gray-50 dark:bg-gray-700 p-3 text-sm space-y-1">
              <div className="flex justify-between"><span className="text-gray-500">إجمالي الوقت:</span><span className="font-medium">{shift ? formatElapsed(shift.clock_in_at) : '—'}</span></div>
              <div className="flex justify-between"><span className="text-gray-500">وقت الاستراحات:</span><span className="font-medium text-yellow-600">{totalBreakMins > 0 ? formatMins(totalBreakMins) : '—'}</span></div>
              <div className="flex justify-between border-t border-gray-200 dark:border-gray-600 pt-1"><span className="text-gray-500">صافي العمل:</span><span className="font-semibold text-green-600">{formatMins(netWorkedMins)}</span></div>
            </div>
          )}
          <div>
            <label className="label">{t('cash_collected')}</label>
            <input
              type="number" step="0.01" min="0"
              value={clockOutForm.cash_collected}
              onChange={(e) => setClockOutForm((p) => ({ ...p, cash_collected: e.target.value }))}
              className="input w-full" placeholder="0.00"
            />
          </div>
          <div>
            <label className="label">{t('cashier_note')}</label>
            <textarea
              value={clockOutForm.cashier_note}
              onChange={(e) => setClockOutForm((p) => ({ ...p, cashier_note: e.target.value }))}
              className="input w-full" rows={3}
            />
          </div>
        </form>
      </Modal>
    </div>
  )
}
