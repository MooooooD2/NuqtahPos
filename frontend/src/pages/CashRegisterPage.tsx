import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Landmark, Lock, Unlock, TrendingUp, CreditCard, ShoppingCart, RotateCcw, Plus, Minus } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Session {
  id: number
  session_number?: string
  cashier_name?: string
  opening_balance: string
  closing_balance?: string
  expected_cash?: string
  actual_cash?: string
  cash_sales?: string
  card_sales?: string
  total_sales?: string
  status: string
  opened_at: string
  closed_at?: string
}

const emptyOpenForm = { opening_balance: '', notes: '' }
const emptyCloseForm = { actual_cash: '', notes: '' }
const emptyMovementForm = { type: 'deposit', amount: '', reason: '' }

export default function CashRegisterPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState<'open' | 'close' | 'movement' | null>(null)
  const [openForm, setOpenForm] = useState({ ...emptyOpenForm })
  const [closeForm, setCloseForm] = useState({ ...emptyCloseForm })
  const [movementForm, setMovementForm] = useState({ ...emptyMovementForm })

  const { data: currentData, isLoading: currentLoading } = useQuery({
    queryKey: ['cash-session-current'],
    queryFn: () => apiGet<{ success: boolean; session?: Session }>('/cash-session/current'),
    staleTime: 30_000,
  })
  const { data: historyData, isLoading: historyLoading } = useQuery({
    queryKey: ['cash-session-history'],
    queryFn: () => apiGet<{ sessions: Session[] }>('/cash-session/history'),
    staleTime: 30_000,
  })

  const session = currentData?.session ?? null
  const history = historyData?.sessions ?? []
  const canManage = hasPermission('manage_cash_register', 'view_pos')

  const openMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/cash-session/open', payload),
    onSuccess: () => {
      toast.success('Session opened')
      qc.invalidateQueries({ queryKey: ['cash-session-current'] })
      qc.invalidateQueries({ queryKey: ['cash-session-history'] })
      setModal(null)
    },
    onError: () => toast.error('Failed to open session'),
  })
  const closeMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: object }) => apiPost(`/cash-session/${id}/close`, payload),
    onSuccess: () => {
      toast.success('Session closed')
      qc.invalidateQueries({ queryKey: ['cash-session-current'] })
      qc.invalidateQueries({ queryKey: ['cash-session-history'] })
      setModal(null)
    },
    onError: () => toast.error('Failed to close session'),
  })
  const movementMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: object }) => apiPost(`/cash-session/${id}/movements`, payload),
    onSuccess: () => {
      toast.success('Movement recorded')
      qc.invalidateQueries({ queryKey: ['cash-session-current'] })
      setModal(null)
    },
    onError: () => toast.error('Failed to record movement'),
  })

  const handleOpen = (e: React.FormEvent) => {
    e.preventDefault()
    if (!openForm.opening_balance) return toast.error('Opening balance required')
    openMutation.mutate({ opening_balance: parseFloat(openForm.opening_balance), notes: openForm.notes || undefined })
  }
  const handleClose = (e: React.FormEvent) => {
    e.preventDefault()
    if (!session) return
    if (!closeForm.actual_cash) return toast.error('Actual cash amount required')
    closeMutation.mutate({ id: session.id, payload: { actual_cash: parseFloat(closeForm.actual_cash), notes: closeForm.notes || undefined } })
  }
  const handleMovement = (e: React.FormEvent) => {
    e.preventDefault()
    if (!session) return
    if (!movementForm.amount || !movementForm.reason) return toast.error('Amount and reason required')
    movementMutation.mutate({ id: session.id, payload: { type: movementForm.type, amount: parseFloat(movementForm.amount), reason: movementForm.reason } })
  }

  const diffColor = (expected: string, actual: string) => {
    const diff = parseFloat(actual) - parseFloat(expected)
    if (Math.abs(diff) < 0.001) return 'text-green-600'
    if (diff < 0) return 'text-red-600'
    return 'text-orange-500'
  }
  const diffValue = (expected: string, actual: string) => {
    const diff = parseFloat(actual) - parseFloat(expected)
    return (diff >= 0 ? '+' : '') + diff.toFixed(2)
  }

  if (currentLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Landmark className="h-6 w-6 text-primary-500" /> Cash Register
        </h1>
        {canManage && (
          <div className="flex gap-2">
            {session?.status === 'open' ? (
              <>
                <button onClick={() => { setMovementForm({ ...emptyMovementForm }); setModal('movement') }} className="btn btn-secondary flex items-center gap-2">
                  <Plus className="h-4 w-4" /> Movement
                </button>
                <button onClick={() => { setCloseForm({ ...emptyCloseForm }); setModal('close') }} className="btn btn-primary flex items-center gap-2">
                  <Lock className="h-4 w-4" /> Close Session
                </button>
              </>
            ) : (
              <button onClick={() => { setOpenForm({ ...emptyOpenForm }); setModal('open') }} className="btn btn-primary flex items-center gap-2">
                <Unlock className="h-4 w-4" /> Open Session
              </button>
            )}
          </div>
        )}
      </div>

      {/* Session Banner */}
      <div className={clsx('card p-4 border-l-4', session?.status === 'open' ? 'border-green-500' : 'border-gray-400')}>
        <div className="flex items-center justify-between flex-wrap gap-3">
          <div className="flex items-center gap-3">
            <span className={clsx('badge text-sm', session?.status === 'open' ? 'badge-success' : 'badge-gray')}>
              {session?.status === 'open' ? 'Session Open' : 'No Active Session'}
            </span>
            {session && (
              <>
                <span className="text-sm text-gray-500">Cashier: <span className="font-medium text-gray-900 dark:text-white">{session.cashier_name ?? '—'}</span></span>
                <span className="text-sm text-gray-500">Opening Balance: <span className="font-medium">{parseFloat(session.opening_balance).toFixed(2)}</span></span>
                <span className="text-sm text-gray-500">Opened: <span className="font-medium">{session.opened_at?.slice(0, 16)}</span></span>
              </>
            )}
          </div>
          {session?.session_number && <span className="font-mono text-xs text-gray-400">#{session.session_number}</span>}
        </div>
      </div>

      {/* KPI Cards (when session is open) */}
      {session?.status === 'open' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div className="card p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Cash Sales</p>
                <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{parseFloat(session.cash_sales ?? '0').toFixed(2)}</p>
              </div>
              <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-green-100 dark:bg-green-900/30">
                <TrendingUp className="h-6 w-6 text-green-600 dark:text-green-400" />
              </div>
            </div>
          </div>
          <div className="card p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Card Sales</p>
                <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{parseFloat(session.card_sales ?? '0').toFixed(2)}</p>
              </div>
              <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-blue-100 dark:bg-blue-900/30">
                <CreditCard className="h-6 w-6 text-blue-600 dark:text-blue-400" />
              </div>
            </div>
          </div>
          <div className="card p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Total Sales</p>
                <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{parseFloat(session.total_sales ?? '0').toFixed(2)}</p>
              </div>
              <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-primary-100 dark:bg-primary-900/30">
                <ShoppingCart className="h-6 w-6 text-primary-600 dark:text-primary-400" />
              </div>
            </div>
          </div>
          <div className="card p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Returns</p>
                <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{parseFloat('0').toFixed(2)}</p>
              </div>
              <div className="h-12 w-12 rounded-xl flex items-center justify-center bg-orange-100 dark:bg-orange-900/30">
                <RotateCcw className="h-6 w-6 text-orange-600 dark:text-orange-400" />
              </div>
            </div>
          </div>
        </div>
      )}

      {/* History Table */}
      <div className="card overflow-hidden">
        <div className="px-4 py-3 border-b dark:border-gray-700">
          <h2 className="font-semibold text-gray-900 dark:text-white">Session History</h2>
        </div>
        {historyLoading ? (
          <div className="flex h-40 items-center justify-center"><LoadingSpinner size="lg" /></div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>
                  {['Session #', 'Cashier', 'Opened', 'Closed', 'Expected Cash', 'Actual Cash', 'Difference', 'Status'].map((h) => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {history.length === 0 ? (
                  <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">No session history</td></tr>
                ) : history.map((s) => (
                  <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 font-mono text-xs text-primary-600">{s.session_number ?? `#${s.id}`}</td>
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{s.cashier_name ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-500 text-xs">{s.opened_at?.slice(0, 16)}</td>
                    <td className="px-4 py-3 text-gray-500 text-xs">{s.closed_at?.slice(0, 16) ?? '—'}</td>
                    <td className="px-4 py-3 font-semibold">{s.expected_cash ? parseFloat(s.expected_cash).toFixed(2) : '—'}</td>
                    <td className="px-4 py-3 font-semibold">{s.actual_cash ? parseFloat(s.actual_cash).toFixed(2) : '—'}</td>
                    <td className="px-4 py-3">
                      {s.expected_cash && s.actual_cash ? (
                        <span className={clsx('font-bold', diffColor(s.expected_cash, s.actual_cash))}>
                          {diffValue(s.expected_cash, s.actual_cash)}
                        </span>
                      ) : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={clsx('badge capitalize', s.status === 'open' ? 'badge-success' : 'badge-gray')}>{s.status}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Open Session Modal */}
      <Modal
        open={modal === 'open'}
        onClose={() => setModal(null)}
        title="Open Cash Session"
        size="sm"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleOpen} disabled={openMutation.isPending} className="btn btn-primary">
              {openMutation.isPending ? 'Opening…' : 'Open Session'}
            </button>
          </>
        }
      >
        <form onSubmit={handleOpen} className="space-y-4">
          <div>
            <label className="label">Opening Balance *</label>
            <input
              value={openForm.opening_balance}
              onChange={(e) => setOpenForm((p) => ({ ...p, opening_balance: e.target.value }))}
              type="number" step="0.01" min="0" className="input w-full" placeholder="0.00" required
            />
          </div>
          <div>
            <label className="label">Notes</label>
            <input
              value={openForm.notes}
              onChange={(e) => setOpenForm((p) => ({ ...p, notes: e.target.value }))}
              className="input w-full" placeholder="Optional notes"
            />
          </div>
        </form>
      </Modal>

      {/* Close Session Modal */}
      <Modal
        open={modal === 'close'}
        onClose={() => setModal(null)}
        title="Close Cash Session"
        size="sm"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleClose} disabled={closeMutation.isPending} className="btn btn-primary">
              {closeMutation.isPending ? 'Closing…' : 'Close Session'}
            </button>
          </>
        }
      >
        <form onSubmit={handleClose} className="space-y-4">
          {session?.expected_cash && (
            <div className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
              <span className="text-gray-500">Expected Cash:</span>
              <span className="font-bold text-gray-900 dark:text-white ml-2">{parseFloat(session.expected_cash).toFixed(2)}</span>
            </div>
          )}
          <div>
            <label className="label">Actual Cash *</label>
            <input
              value={closeForm.actual_cash}
              onChange={(e) => setCloseForm((p) => ({ ...p, actual_cash: e.target.value }))}
              type="number" step="0.01" min="0" className="input w-full" placeholder="0.00" required
            />
          </div>
          <div>
            <label className="label">Notes</label>
            <input
              value={closeForm.notes}
              onChange={(e) => setCloseForm((p) => ({ ...p, notes: e.target.value }))}
              className="input w-full" placeholder="Optional notes"
            />
          </div>
        </form>
      </Modal>

      {/* Record Movement Modal */}
      <Modal
        open={modal === 'movement'}
        onClose={() => setModal(null)}
        title="Record Cash Movement"
        size="sm"
        footer={
          <>
            <button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button>
            <button onClick={handleMovement} disabled={movementMutation.isPending} className="btn btn-primary">
              {movementMutation.isPending ? 'Saving…' : 'Record'}
            </button>
          </>
        }
      >
        <form onSubmit={handleMovement} className="space-y-4">
          <div>
            <label className="label">Type</label>
            <div className="flex gap-4">
              <label className="flex items-center gap-2 cursor-pointer text-sm">
                <input type="radio" name="mv_type" value="deposit" checked={movementForm.type === 'deposit'} onChange={() => setMovementForm((p) => ({ ...p, type: 'deposit' }))} className="accent-primary-600" />
                <Plus className="h-4 w-4 text-green-600" /> Deposit
              </label>
              <label className="flex items-center gap-2 cursor-pointer text-sm">
                <input type="radio" name="mv_type" value="withdrawal" checked={movementForm.type === 'withdrawal'} onChange={() => setMovementForm((p) => ({ ...p, type: 'withdrawal' }))} className="accent-primary-600" />
                <Minus className="h-4 w-4 text-red-600" /> Withdrawal
              </label>
            </div>
          </div>
          <div>
            <label className="label">Amount *</label>
            <input
              value={movementForm.amount}
              onChange={(e) => setMovementForm((p) => ({ ...p, amount: e.target.value }))}
              type="number" step="0.01" min="0" className="input w-full" placeholder="0.00" required
            />
          </div>
          <div>
            <label className="label">Reason *</label>
            <input
              value={movementForm.reason}
              onChange={(e) => setMovementForm((p) => ({ ...p, reason: e.target.value }))}
              className="input w-full" placeholder="Reason for this movement" required
            />
          </div>
        </form>
      </Modal>
    </div>
  )
}
