import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api, apiGet, apiPost, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Gift, Plus, TrendingUp, TrendingDown, Percent, ToggleRight, Trash2 } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface CashbackRule { id: number; name: string; percentage: string; min_purchase: string; max_cashback?: string; is_active: boolean }
interface CashbackTx { id: number; customer_name?: string; type: string; amount: string; balance_after: string; created_at: string }

const emptyRule = { name: '', percentage: '', min_purchase_amount: '0', max_cashback: '' }

export default function CashbackPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState(false)
  const [form, setForm] = useState({ ...emptyRule })
  const [page, setPage] = useState(1)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const { data: rulesData, isLoading: rulesLoading } = useQuery({
    queryKey: ['cashback-rules'],
    queryFn: () => apiGet<{ success: boolean; data: CashbackRule[] }>('/cashback/rules'),
    staleTime: 60_000,
  })
  const { data: txData, isLoading: txLoading } = useQuery({
    queryKey: ['cashback-history', page],
    queryFn: () => apiGet<{ success: boolean; data: CashbackTx[]; total?: number }>('/cashback/history', { page, per_page: 20 }),
    staleTime: 30_000,
  })

  const rules = rulesData?.data ?? []
  const txs = txData?.data ?? []
  const canManage = hasPermission('manage_cashback')

  const activeRule = rules.find((r) => r.is_active)
  const totalEarned = txs.filter((t) => t.type === 'earned').reduce((s, t) => s + parseFloat(t.amount || '0'), 0)
  const totalRedeemed = txs.filter((t) => t.type === 'redeemed').reduce((s, t) => s + parseFloat(t.amount || '0'), 0)

  const createRule = useMutation({
    mutationFn: (payload: object) => apiPost('/cashback/rules', payload),
    onSuccess: () => { toast.success('Rule created'); qc.invalidateQueries({ queryKey: ['cashback-rules'] }); setModal(false) },
    onError: () => toast.error('Failed to create rule'),
  })

  const activateRule = useMutation({
    mutationFn: (id: number) => api.patch(`/cashback/rules/${id}/activate`).then((r) => r.data),
    onSuccess: () => { toast.success('Rule activated'); qc.invalidateQueries({ queryKey: ['cashback-rules'] }) },
    onError: () => toast.error('Failed to activate rule'),
  })

  const deleteRule = useMutation({
    mutationFn: (id: number) => apiDelete(`/cashback/rules/${id}`),
    onSuccess: () => { toast.success('Rule deleted'); qc.invalidateQueries({ queryKey: ['cashback-rules'] }); setDeleteId(null) },
    onError: () => toast.error('Failed to delete rule'),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error('Name required')
    if (!form.percentage || parseFloat(form.percentage) <= 0) return toast.error('Percentage must be > 0')
    createRule.mutate({
      name: form.name,
      percentage: parseFloat(form.percentage),
      min_purchase: parseFloat(form.min_purchase_amount) || 0,
      max_cashback: form.max_cashback ? parseFloat(form.max_cashback) : undefined,
    })
  }

  const txTypeBadge: Record<string, string> = { earned: 'badge-success', redeemed: 'badge-danger', adjusted: 'badge-gray' }
  const txAmountClass = (type: string) => type === 'earned' ? 'text-green-600 font-semibold' : type === 'redeemed' ? 'text-red-600 font-semibold' : 'text-gray-500'

  const kpis = [
    { label: 'Total Earned', value: totalEarned.toFixed(2), icon: TrendingUp, color: 'green' },
    { label: 'Total Redeemed', value: totalRedeemed.toFixed(2), icon: TrendingDown, color: 'red' },
    { label: 'Active Rate', value: activeRule ? `${activeRule.percentage}%` : 'No Active Rule', icon: Percent, color: 'blue' },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Gift className="h-6 w-6 text-primary-500" /> Cashback</h1>
        {canManage && (
          <button onClick={() => { setForm({ ...emptyRule }); setModal(true) }} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> New Rule</button>
        )}
      </div>

      <div className="grid grid-cols-3 gap-4">
        {kpis.map((kpi) => (
          <div key={kpi.label} className="card p-4 flex items-center gap-4">
            <div className={`h-10 w-10 rounded-xl flex items-center justify-center bg-${kpi.color}-100 dark:bg-${kpi.color}-900/30`}>
              <kpi.icon className={`h-5 w-5 text-${kpi.color}-600 dark:text-${kpi.color}-400`} />
            </div>
            <div>
              <p className="text-xs text-gray-500">{kpi.label}</p>
              <p className="text-xl font-bold text-gray-900 dark:text-white">{kpi.value}</p>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="card overflow-hidden">
          <div className="px-4 py-3 border-b dark:border-gray-700">
            <h2 className="font-semibold text-gray-900 dark:text-white">Cashback Rules</h2>
          </div>
          {rulesLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{['Name', '%', 'Min Purchase', 'Max Cashback', 'Status', ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {rules.length === 0 ? <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">No cashback rules yet</td></tr>
                    : rules.map((r) => (
                      <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{r.name}</td>
                        <td className="px-4 py-3 text-primary-600 font-semibold">{r.percentage}%</td>
                        <td className="px-4 py-3 text-gray-500">{parseFloat(r.min_purchase ?? '0').toFixed(2)}</td>
                        <td className="px-4 py-3 text-gray-500">{r.max_cashback ? parseFloat(r.max_cashback).toFixed(2) : '—'}</td>
                        <td className="px-4 py-3">
                          <span className={clsx('badge text-xs', r.is_active ? 'badge-success' : 'badge-gray')}>
                            {r.is_active ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-2">
                            {r.is_active ? (
                              <ToggleRight className="h-5 w-5 text-green-500" />
                            ) : (
                              <button
                                onClick={() => activateRule.mutate(r.id)}
                                disabled={activateRule.isPending}
                                className="px-3 py-1 text-xs font-medium rounded-full border border-green-500 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                              >
                                {activateRule.isPending ? '…' : 'Activate'}
                              </button>
                            )}
                            <button
                              onClick={() => setDeleteId(r.id)}
                              disabled={deleteRule.isPending}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded disabled:opacity-50"
                              title="Delete rule"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="card overflow-hidden">
          <div className="px-4 py-3 border-b dark:border-gray-700">
            <h2 className="font-semibold text-gray-900 dark:text-white">Recent Transactions</h2>
          </div>
          {txLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{['Customer', 'Type', 'Amount', 'Balance After', 'Date'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {txs.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No transactions yet</td></tr>
                    : txs.map((t) => (
                      <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{t.customer_name ?? '—'}</td>
                        <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', txTypeBadge[t.type] ?? 'badge-gray')}>{t.type}</span></td>
                        <td className={clsx('px-4 py-3', txAmountClass(t.type))}>{parseFloat(t.amount).toFixed(2)}</td>
                        <td className="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">{parseFloat(t.balance_after).toFixed(2)}</td>
                        <td className="px-4 py-3 text-gray-400 text-xs">{t.created_at?.slice(0, 16)}</td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
          {(txData?.total ?? 0) > 20 && (
            <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
              <span className="text-sm text-gray-500">Page {page} · {txData?.total} total</span>
              <div className="flex gap-2">
                <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Prev</button>
                <button onClick={() => setPage((p) => p + 1)} disabled={txs.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">Next</button>
              </div>
            </div>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={deleteId !== null}
        title="Delete Cashback Rule"
        message="Delete this cashback rule? This cannot be undone."
        loading={deleteRule.isPending}
        onConfirm={() => deleteId && deleteRule.mutate(deleteId)}
        onCancel={() => setDeleteId(null)}
      />

      <Modal open={modal} onClose={() => setModal(false)} title="New Cashback Rule" size="md"
        footer={<><button onClick={() => setModal(false)} className="btn btn-secondary">Cancel</button><button onClick={handleSubmit} disabled={createRule.isPending} className="btn btn-primary">{createRule.isPending ? 'Creating…' : 'Create Rule'}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div><label className="label">Rule Name *</label><input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" placeholder="e.g. Standard Cashback" required /></div>
          <div className="grid grid-cols-2 gap-4">
            <div><label className="label">Percentage (%) *</label><input value={form.percentage} type="number" min="0.01" max="100" step="0.01" onChange={(e) => setForm((p) => ({ ...p, percentage: e.target.value }))} className="input w-full" required /></div>
            <div><label className="label">Min Purchase Amount</label><input value={form.min_purchase_amount} type="number" min="0" step="0.01" onChange={(e) => setForm((p) => ({ ...p, min_purchase_amount: e.target.value }))} className="input w-full" /></div>
          </div>
          <div><label className="label">Max Cashback (optional)</label><input value={form.max_cashback} type="number" min="0" step="0.01" onChange={(e) => setForm((p) => ({ ...p, max_cashback: e.target.value }))} className="input w-full" placeholder="Leave empty for unlimited" /></div>
        </form>
      </Modal>
    </div>
  )
}
