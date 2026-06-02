import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Clock, LogIn, LogOut, Coffee, CheckCircle } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Shift {
  id: number
  clock_in_at: string
  clock_out_at?: string
  status: string
  on_break?: boolean
  break_started_at?: string
  total_hours?: string
}
interface ShiftRecord {
  id: number
  date: string
  clock_in_at: string
  clock_out_at?: string
  hours_worked?: string
  status: string
}

const emptyClockOut = { cash_collected: '', cashier_note: '' }

function formatElapsed(from: string): string {
  const secs = Math.floor((Date.now() - new Date(from).getTime()) / 1000)
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

const statusBadge = (s: string) => {
  if (s === 'active') return 'badge-success'
  if (s === 'completed') return 'badge-info'
  if (s === 'on_break') return 'badge-warning'
  return 'badge-gray'
}

export default function MyShiftPage() {
  const qc = useQueryClient()
  const [clockOutModal, setClockOutModal] = useState(false)
  const [clockOutForm, setClockOutForm] = useState({ ...emptyClockOut })
  const [tick, setTick] = useState(0)

  useEffect(() => {
    const t = setInterval(() => setTick((n) => n + 1), 1000)
    return () => clearInterval(t)
  }, [])

  const { data: currentData, isLoading: currentLoading } = useQuery({
    queryKey: ['my-shift-current'],
    queryFn: () => apiGet<{ success: boolean; shift?: Shift }>('/shifts/current'),
    refetchInterval: 60_000,
    staleTime: 30_000,
  })

  const { data: historyData, isLoading: historyLoading } = useQuery({
    queryKey: ['my-shift-history'],
    queryFn: () => apiGet<{ success: boolean; data: ShiftRecord[] }>('/shifts/history', { per_page: 20 }),
    staleTime: 60_000,
  })

  const shift = currentData?.shift
  const history = historyData?.data ?? []

  const clockInMutation = useMutation({
    mutationFn: () => apiPost('/shifts/clock-in', {}),
    onSuccess: () => {
      toast.success('Clocked in successfully')
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
      qc.invalidateQueries({ queryKey: ['my-shift-history'] })
    },
    onError: () => toast.error('Failed to clock in'),
  })

  const breakStartMutation = useMutation({
    mutationFn: () => apiPost('/shifts/break/start', {}),
    onSuccess: () => {
      toast.success('Break started')
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
    },
    onError: () => toast.error('Failed to start break'),
  })

  const breakEndMutation = useMutation({
    mutationFn: () => apiPost('/shifts/break/end', {}),
    onSuccess: () => {
      toast.success('Break ended')
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
    },
    onError: () => toast.error('Failed to end break'),
  })

  const clockOutMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/shifts/clock-out', payload),
    onSuccess: () => {
      toast.success('Clocked out successfully')
      qc.invalidateQueries({ queryKey: ['my-shift-current'] })
      qc.invalidateQueries({ queryKey: ['my-shift-history'] })
      setClockOutModal(false)
      setClockOutForm({ ...emptyClockOut })
    },
    onError: () => toast.error('Failed to clock out'),
  })

  const handleClockOut = (e: React.FormEvent) => {
    e.preventDefault()
    clockOutMutation.mutate({
      cash_collected: clockOutForm.cash_collected ? parseFloat(clockOutForm.cash_collected) : undefined,
      cashier_note: clockOutForm.cashier_note || undefined,
    })
  }

  const elapsedDisplay = shift ? formatElapsed(shift.clock_in_at) : '00:00:00'
  void tick

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Clock className="h-6 w-6 text-primary-500" /> My Shift
        </h1>
      </div>

      {currentLoading ? (
        <div className="flex h-48 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : shift ? (
        <div className="card p-6 space-y-6">
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
              {shift.on_break ? 'On Break' : 'Shift in progress'}
            </p>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div className="card p-4 bg-gray-50 dark:bg-gray-700/50 space-y-1">
              <p className="text-xs text-gray-500">Clocked In</p>
              <p className="font-semibold text-gray-900 dark:text-white">{formatTime(shift.clock_in_at)}</p>
              <p className="text-xs text-gray-400">{formatDate(shift.clock_in_at)}</p>
            </div>
            <div className="card p-4 bg-gray-50 dark:bg-gray-700/50 space-y-1">
              <p className="text-xs text-gray-500">Status</p>
              <span className={clsx('badge capitalize', statusBadge(shift.status))}>
                {shift.status.replace('_', ' ')}
              </span>
            </div>
            {shift.on_break && shift.break_started_at && (
              <div className="card p-4 bg-yellow-50 dark:bg-yellow-900/20 space-y-1">
                <p className="text-xs text-yellow-600">Break Started</p>
                <p className="font-semibold text-yellow-700 dark:text-yellow-400">{formatTime(shift.break_started_at)}</p>
                <p className="text-xs text-yellow-500">{formatElapsed(shift.break_started_at)} elapsed</p>
              </div>
            )}
          </div>

          <div className="flex flex-wrap gap-3 justify-center">
            {!shift.on_break ? (
              <button
                onClick={() => breakStartMutation.mutate()}
                disabled={breakStartMutation.isPending}
                className="btn btn-secondary flex items-center gap-2"
              >
                <Coffee className="h-4 w-4" />
                {breakStartMutation.isPending ? 'Starting…' : 'Start Break'}
              </button>
            ) : (
              <button
                onClick={() => breakEndMutation.mutate()}
                disabled={breakEndMutation.isPending}
                className="btn btn-secondary flex items-center gap-2"
              >
                <CheckCircle className="h-4 w-4" />
                {breakEndMutation.isPending ? 'Ending…' : 'End Break'}
              </button>
            )}
            <button
              onClick={() => setClockOutModal(true)}
              className="btn flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white"
            >
              <LogOut className="h-4 w-4" /> Clock Out
            </button>
          </div>
        </div>
      ) : (
        <div className="card p-10 flex flex-col items-center gap-6">
          <div className="h-24 w-24 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            <LogIn className="h-12 w-12 text-gray-400" />
          </div>
          <div className="text-center space-y-1">
            <p className="text-lg font-semibold text-gray-700 dark:text-gray-200">No Active Shift</p>
            <p className="text-sm text-gray-500">You have no active shift. Click to start your shift.</p>
          </div>
          <button
            onClick={() => clockInMutation.mutate()}
            disabled={clockInMutation.isPending}
            className="btn btn-primary text-base px-8 py-3 flex items-center gap-2"
          >
            <LogIn className="h-5 w-5" />
            {clockInMutation.isPending ? 'Clocking In…' : 'Clock In'}
          </button>
        </div>
      )}

      <div className="card overflow-hidden">
        <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <Clock className="h-4 w-4 text-primary-500" /> Shift History
          </h2>
        </div>
        {historyLoading ? (
          <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  {['Date', 'Clock In', 'Clock Out', 'Hours Worked', 'Status'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {history.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-12 text-center text-gray-400">No shift history found</td>
                  </tr>
                ) : history.map((rec) => (
                  <tr key={rec.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{rec.date}</td>
                    <td className="px-4 py-3 text-gray-500">{formatTime(rec.clock_in_at)}</td>
                    <td className="px-4 py-3 text-gray-500">{rec.clock_out_at ? formatTime(rec.clock_out_at) : <span className="text-green-500 font-medium">Active</span>}</td>
                    <td className="px-4 py-3">
                      {rec.hours_worked ? (
                        <span className="badge badge-info">{rec.hours_worked}h</span>
                      ) : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={clsx('badge capitalize', statusBadge(rec.status))}>
                        {rec.status.replace('_', ' ')}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <Modal
        open={clockOutModal}
        onClose={() => setClockOutModal(false)}
        title="Clock Out"
        size="sm"
        footer={
          <>
            <button onClick={() => setClockOutModal(false)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleClockOut} disabled={clockOutMutation.isPending} className="btn bg-red-600 hover:bg-red-700 text-white">
              {clockOutMutation.isPending ? 'Clocking Out…' : 'Clock Out'}
            </button>
          </>
        }
      >
        <form onSubmit={handleClockOut} className="space-y-4">
          <div>
            <label className="label">Cash Collected</label>
            <input
              type="number"
              step="0.01"
              min="0"
              value={clockOutForm.cash_collected}
              onChange={(e) => setClockOutForm((p) => ({ ...p, cash_collected: e.target.value }))}
              className="input w-full"
              placeholder="0.00"
            />
          </div>
          <div>
            <label className="label">Cashier Note</label>
            <textarea
              value={clockOutForm.cashier_note}
              onChange={(e) => setClockOutForm((p) => ({ ...p, cashier_note: e.target.value }))}
              className="input w-full"
              rows={3}
              placeholder="Optional end-of-shift note"
            />
          </div>
        </form>
      </Modal>
    </div>
  )
}
