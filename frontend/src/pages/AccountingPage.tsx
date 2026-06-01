import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { BookOpen, Plus, Lock, Unlock, RefreshCw } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Account { id: number; account_code: string; account_name: string; account_type: string; balance: string; is_active?: boolean }
interface JournalEntry { id: number; date: string; reference: string; description: string; total_debit: string; is_locked: boolean; is_posted?: boolean }
interface FiscalPeriod { id: number; name: string; start_date: string; end_date: string; is_closed: boolean }

const accountTypeClass: Record<string, string> = { asset: 'badge-success', liability: 'badge-danger', equity: 'badge-info', revenue: 'badge-success', expense: 'badge-warning' }
const accountTypes = ['asset', 'liability', 'equity', 'revenue', 'expense']
const emptyAccount = { code: '', name: '', type: 'asset', parent_id: '' }
const emptyJE = { date: new Date().toISOString().slice(0, 10), reference: '', description: '', lines: [{ account_id: '', debit: '', credit: '' }, { account_id: '', debit: '', credit: '' }] }

export default function AccountingPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [tab, setTab] = useState<'accounts' | 'journal' | 'periods'>('accounts')
  const [modal, setModal] = useState<'account' | 'journal' | null>(null)
  const [accForm, setAccForm] = useState({ ...emptyAccount })
  const [jeForm, setJeForm] = useState({ ...emptyJE })

  const { data: accountsData, isLoading: accLoading } = useQuery({
    queryKey: ['accounts'], queryFn: () => apiGet<{ success: boolean; accounts: Account[] }>('/accounts'), staleTime: 120_000,
  })
  const { data: journalData, isLoading: jLoading } = useQuery({
    queryKey: ['journal-entries'], queryFn: () => apiGet<{ success: boolean; data: JournalEntry[] }>('/journal-entries?per_page=30'), staleTime: 60_000, enabled: tab === 'journal',
  })
  const { data: periodsData, isLoading: pLoading } = useQuery({
    queryKey: ['fiscal-periods'], queryFn: () => apiGet<{ success: boolean; data: FiscalPeriod[] }>('/fiscal-periods'), staleTime: 120_000, enabled: tab === 'periods',
  })

  const accounts = accountsData?.accounts ?? []
  const journal = journalData?.data ?? []
  const periods = periodsData?.data ?? []
  const canManage = hasPermission('manage_accounting', 'view_accounting')

  const addAccount = useMutation({
    mutationFn: (payload: object) => apiPost('/accounts', payload),
    onSuccess: () => { toast.success('Account created'); qc.invalidateQueries({ queryKey: ['accounts'] }); setModal(null) },
    onError: () => toast.error('Failed to create account'),
  })

  const addJE = useMutation({
    mutationFn: (payload: object) => apiPost('/journal-entries', payload),
    onSuccess: () => { toast.success('Journal entry created'); qc.invalidateQueries({ queryKey: ['journal-entries'] }); setModal(null) },
    onError: () => toast.error('Failed to create journal entry'),
  })

  const addJELine = () => setJeForm((p) => ({ ...p, lines: [...p.lines, { account_id: '', debit: '', credit: '' }] }))
  const removeJELine = (i: number) => setJeForm((p) => ({ ...p, lines: p.lines.filter((_, idx) => idx !== i) }))

  const totalDebit = jeForm.lines.reduce((s, l) => s + (parseFloat(l.debit) || 0), 0)
  const totalCredit = jeForm.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0)
  const balanced = Math.abs(totalDebit - totalCredit) < 0.001

  const handleCreateJE = () => {
    if (!jeForm.description) return toast.error('Description required')
    if (!balanced) return toast.error('Debits must equal credits')
    addJE.mutate({
      entry_date: jeForm.date,
      reference_type: jeForm.reference ? 'manual' : undefined,
      description: jeForm.description,
      lines: jeForm.lines.filter((l) => l.account_id).map((l) => ({
        account_id: parseInt(l.account_id),
        debit: parseFloat(l.debit) || 0,
        credit: parseFloat(l.credit) || 0,
      })),
    })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><BookOpen className="h-6 w-6 text-primary-500" /> Accounting</h1>
        {canManage && (
          <div className="flex gap-2">
            {tab === 'accounts' && <button onClick={() => { setAccForm({ ...emptyAccount }); setModal('account') }} className="btn btn-primary flex items-center gap-2 text-sm"><Plus className="h-4 w-4" /> Add Account</button>}
            {tab === 'journal' && <button onClick={() => { setJeForm({ ...emptyJE }); setModal('journal') }} className="btn btn-primary flex items-center gap-2 text-sm"><Plus className="h-4 w-4" /> New Journal Entry</button>}
          </div>
        )}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['accounts', 'journal', 'periods'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === t ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{t === 'journal' ? 'Journal Entries' : t === 'periods' ? 'Fiscal Periods' : t}</button>
        ))}
      </div>

      {tab === 'accounts' && (
        <div className="card overflow-hidden">
          {accLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{['Code', 'Name', 'Type', 'Balance'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {accounts.length === 0 ? <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">No accounts</td></tr>
                    : accounts.map((acc) => (
                      <tr key={acc.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-mono text-xs text-gray-500">{acc.account_code}</td>
                        <td className="px-4 py-3 text-gray-900 dark:text-white font-medium">{acc.account_name}</td>
                        <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', accountTypeClass[acc.account_type] ?? 'badge-gray')}>{acc.account_type}</span></td>
                        <td className="px-4 py-3 font-semibold text-right">{parseFloat(acc.balance ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {tab === 'journal' && (
        <div className="card overflow-hidden">
          {jLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{['Date', 'Reference', 'Description', 'Debit', 'Status'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {journal.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No journal entries</td></tr>
                    : journal.map((je) => (
                      <tr key={je.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 text-gray-400 text-xs">{je.date?.slice(0, 10)}</td>
                        <td className="px-4 py-3 font-mono text-xs text-primary-600">{je.reference}</td>
                        <td className="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-48 truncate">{je.description}</td>
                        <td className="px-4 py-3 font-semibold">{parseFloat(je.total_debit ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td className="px-4 py-3 flex gap-1">
                          {je.is_locked ? <span className="badge badge-gray text-xs flex items-center gap-1"><Lock className="h-3 w-3" />Locked</span> : je.is_posted ? <span className="badge badge-success text-xs">Posted</span> : <span className="badge badge-warning text-xs">Draft</span>}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {tab === 'periods' && (
        <div className="card overflow-hidden">
          {pLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700"><tr>{['Period', 'Start', 'End', 'Status'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {periods.length === 0 ? <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">No fiscal periods</td></tr>
                  : periods.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                      <td className="px-4 py-3 text-gray-500">{p.start_date?.slice(0, 10)}</td>
                      <td className="px-4 py-3 text-gray-500">{p.end_date?.slice(0, 10)}</td>
                      <td className="px-4 py-3">{p.is_closed ? <span className="badge badge-danger flex items-center gap-1 w-fit"><Lock className="h-3 w-3" />Closed</span> : <span className="badge badge-success flex items-center gap-1 w-fit"><Unlock className="h-3 w-3" />Open</span>}</td>
                    </tr>
                  ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Add Account Modal */}
      <Modal open={modal === 'account'} onClose={() => setModal(null)} title="Add Account" size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={() => { if (!accForm.code || !accForm.name) return toast.error('Code and name required'); addAccount.mutate({ account_code: accForm.code, account_name: accForm.name, account_type: accForm.type }) }} disabled={addAccount.isPending} className="btn btn-primary">{addAccount.isPending ? 'Creating…' : 'Create'}</button></>}>
        <div className="space-y-4">
          <div><label className="label">Account Code *</label><input value={accForm.code} onChange={(e) => setAccForm((p) => ({ ...p, code: e.target.value }))} className="input w-full" placeholder="e.g. 1000" /></div>
          <div><label className="label">Account Name *</label><input value={accForm.name} onChange={(e) => setAccForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" /></div>
          <div>
            <label className="label">Type</label>
            <select value={accForm.type} onChange={(e) => setAccForm((p) => ({ ...p, type: e.target.value }))} className="input w-full">
              {accountTypes.map((t) => <option key={t} value={t} className="capitalize">{t}</option>)}
            </select>
          </div>
        </div>
      </Modal>

      {/* Journal Entry Modal */}
      <Modal open={modal === 'journal'} onClose={() => setModal(null)} title="New Journal Entry" size="xl"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={handleCreateJE} disabled={addJE.isPending || !balanced} className="btn btn-primary disabled:opacity-50">{addJE.isPending ? 'Creating…' : 'Create Entry'}</button></>}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div><label className="label">Date</label><input value={jeForm.date} type="date" onChange={(e) => setJeForm((p) => ({ ...p, date: e.target.value }))} className="input w-full" /></div>
            <div><label className="label">Reference</label><input value={jeForm.reference} onChange={(e) => setJeForm((p) => ({ ...p, reference: e.target.value }))} className="input w-full" placeholder="Optional" /></div>
            <div className="col-span-2"><label className="label">Description *</label><input value={jeForm.description} onChange={(e) => setJeForm((p) => ({ ...p, description: e.target.value }))} className="input w-full" /></div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">Lines</label>
              <button type="button" onClick={addJELine} className="text-xs text-primary-600 hover:underline flex items-center gap-1"><Plus className="h-3 w-3" />Add Line</button>
            </div>
            <div className="space-y-2">
              <div className="grid grid-cols-3 gap-2 text-xs font-semibold text-gray-400 px-1"><span>Account ID</span><span>Debit</span><span>Credit</span></div>
              {jeForm.lines.map((line, i) => (
                <div key={i} className="grid grid-cols-3 gap-2 items-center">
                  <input value={line.account_id} type="number" onChange={(e) => setJeForm((p) => ({ ...p, lines: p.lines.map((l, idx) => idx === i ? { ...l, account_id: e.target.value } : l) }))} className="input" placeholder="Account ID" />
                  <input value={line.debit} type="number" step="0.01" min="0" onChange={(e) => setJeForm((p) => ({ ...p, lines: p.lines.map((l, idx) => idx === i ? { ...l, debit: e.target.value } : l) }))} className="input" placeholder="0.00" />
                  <div className="flex gap-1">
                    <input value={line.credit} type="number" step="0.01" min="0" onChange={(e) => setJeForm((p) => ({ ...p, lines: p.lines.map((l, idx) => idx === i ? { ...l, credit: e.target.value } : l) }))} className="input flex-1" placeholder="0.00" />
                    {jeForm.lines.length > 2 && <button type="button" onClick={() => removeJELine(i)} className="text-red-400 hover:text-red-600 px-1">×</button>}
                  </div>
                </div>
              ))}
            </div>
            <div className={clsx('mt-2 p-2 rounded text-xs font-semibold flex justify-between', balanced ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700')}>
              <span>Debit: {totalDebit.toFixed(2)}</span>
              <span className="flex items-center gap-1">{balanced ? '✓ Balanced' : <><RefreshCw className="h-3 w-3" />Unbalanced</>}</span>
              <span>Credit: {totalCredit.toFixed(2)}</span>
            </div>
          </div>
        </div>
      </Modal>
    </div>
  )
}
