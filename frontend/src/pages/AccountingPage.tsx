import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { BookOpen, Plus, Lock, Unlock, RefreshCw } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

interface Account { id: number; account_code: string; account_name: string; account_type: string; balance: string; is_active?: boolean }
interface JournalEntry { id: number; date: string; reference: string; description: string; total_debit: string; is_locked: boolean; is_posted?: boolean }
interface FiscalPeriod { id: number; name: string; start_date: string; end_date: string; is_closed: boolean }

const accountTypeClass: Record<string, string> = { asset: 'badge-success', liability: 'badge-danger', equity: 'badge-info', revenue: 'badge-success', expense: 'badge-warning' }
const accountTypes = ['asset', 'liability', 'equity', 'revenue', 'expense']
const emptyAccount = { code: '', name: '', type: 'asset', parent_id: '' }
const emptyJE = { date: new Date().toISOString().slice(0, 10), reference: '', description: '', lines: [{ account_id: '', debit: '', credit: '' }, { account_id: '', debit: '', credit: '' }] }

export default function AccountingPage() {
  const { t } = useTranslation('pos')
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
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['accounts'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })

  const addJE = useMutation({
    mutationFn: (payload: object) => apiPost('/journal-entries', payload),
    onSuccess: () => { toast.success(t('created_success')); qc.invalidateQueries({ queryKey: ['journal-entries'] }); setModal(null) },
    onError: () => toast.error(t('save_failed')),
  })

  const addJELine = () => setJeForm((p) => ({ ...p, lines: [...p.lines, { account_id: '', debit: '', credit: '' }] }))
  const removeJELine = (i: number) => setJeForm((p) => ({ ...p, lines: p.lines.filter((_, idx) => idx !== i) }))

  const totalDebit = jeForm.lines.reduce((s, l) => s + (parseFloat(l.debit) || 0), 0)
  const totalCredit = jeForm.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0)
  const balanced = totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.001

  const handleCreateJE = () => {
    if (!jeForm.description) return toast.error(t('error'))
    const validLines = jeForm.lines.filter((l) => l.account_id)
    if (validLines.length < 2) return toast.error(t('error'))
    if (totalDebit === 0) return toast.error(t('error'))
    if (!balanced) return toast.error(t('error'))
    addJE.mutate({
      entry_date: jeForm.date,
      reference: jeForm.reference || undefined,
      description: jeForm.description,
      lines: validLines.map((l) => ({
        account_id: parseInt(l.account_id),
        debit: parseFloat(l.debit) || 0,
        credit: parseFloat(l.credit) || 0,
      })),
    })
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><BookOpen className="h-6 w-6 text-primary-500" /> {t('accounting')}</h1>
        {canManage && (
          <div className="flex gap-2">
            {tab === 'accounts' && <button onClick={() => { setAccForm({ ...emptyAccount }); setModal('account') }} className="btn btn-primary flex items-center gap-2 text-sm"><Plus className="h-4 w-4" /> {t('add_account')}</button>}
            {tab === 'journal' && <button onClick={() => { setJeForm({ ...emptyJE }); setModal('journal') }} className="btn btn-primary flex items-center gap-2 text-sm"><Plus className="h-4 w-4" /> {t('journal_entry')}</button>}
          </div>
        )}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['accounts', 'journal', 'periods'] as const).map((tab_key) => (
          <button key={tab_key} onClick={() => setTab(tab_key)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === tab_key ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{tab_key === 'journal' ? t('journal_entry') : tab_key === 'periods' ? t('fiscal_periods') : t('accounts')}</button>
        ))}
      </div>

      {tab === 'accounts' && (
        <div className="card overflow-hidden">
          {accLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[550px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('account_code'), t('account_name'), t('account_type'), t('balance')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {accounts.length === 0 ? <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
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
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {accounts.length === 0 ? <p className="px-4 py-10 text-center text-gray-400">{t('no_data')}</p>
                  : accounts.map((acc) => (
                    <div key={acc.id} className="p-4 space-y-1.5">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-semibold text-gray-900 dark:text-white">{acc.account_name}</span>
                        <span className={clsx('badge capitalize text-xs', accountTypeClass[acc.account_type] ?? 'badge-gray')}>{acc.account_type}</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="font-mono text-xs text-gray-500">{acc.account_code}</span>
                        <span className="font-semibold text-gray-700 dark:text-gray-300">{parseFloat(acc.balance ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                      </div>
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}

      {tab === 'journal' && (
        <div className="card overflow-hidden">
          {jLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[550px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('date'), t('reference'), t('description'), t('debit'), t('status')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {journal.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
                      : journal.map((je) => (
                        <tr key={je.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 text-gray-400 text-xs">{je.date?.slice(0, 10)}</td>
                          <td className="px-4 py-3 font-mono text-xs text-primary-600">{je.reference}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-48 truncate">{je.description}</td>
                          <td className="px-4 py-3 font-semibold">{parseFloat(je.total_debit ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                          <td className="px-4 py-3 flex gap-1">
                            {je.is_locked ? <span className="badge badge-gray text-xs flex items-center gap-1"><Lock className="h-3 w-3" />{t('locked')}</span> : je.is_posted ? <span className="badge badge-success text-xs">{t('posted')}</span> : <span className="badge badge-warning text-xs">{t('draft')}</span>}
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {journal.length === 0 ? <p className="px-4 py-10 text-center text-gray-400">{t('no_data')}</p>
                  : journal.map((je) => (
                    <div key={je.id} className="p-4 space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <p className="font-semibold text-gray-900 dark:text-white truncate">{je.description}</p>
                          {je.reference && <p className="font-mono text-xs text-primary-600">{je.reference}</p>}
                        </div>
                        <span className="shrink-0">
                          {je.is_locked ? <span className="badge badge-gray text-xs flex items-center gap-1"><Lock className="h-3 w-3" />{t('locked')}</span> : je.is_posted ? <span className="badge badge-success text-xs">{t('posted')}</span> : <span className="badge badge-warning text-xs">{t('draft')}</span>}
                        </span>
                      </div>
                      <div className="flex items-center gap-3 text-sm">
                        <span className="font-semibold text-gray-700 dark:text-gray-300">{parseFloat(je.total_debit ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        <span className="text-xs text-gray-400">{je.date?.slice(0, 10)}</span>
                      </div>
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}

      {tab === 'periods' && (
        <div className="card overflow-hidden">
          {pLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[550px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700"><tr>{[t('period'), t('date_from'), t('date_to'), t('status')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {periods.length === 0 ? <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
                      : periods.map((p) => (
                        <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.name}</td>
                          <td className="px-4 py-3 text-gray-500">{p.start_date?.slice(0, 10)}</td>
                          <td className="px-4 py-3 text-gray-500">{p.end_date?.slice(0, 10)}</td>
                          <td className="px-4 py-3">{p.is_closed ? <span className="badge badge-danger flex items-center gap-1 w-fit"><Lock className="h-3 w-3" />{t('locked')}</span> : <span className="badge badge-success flex items-center gap-1 w-fit"><Unlock className="h-3 w-3" />{t('unlocked')}</span>}</td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {periods.length === 0 ? <p className="px-4 py-10 text-center text-gray-400">{t('no_data')}</p>
                  : periods.map((p) => (
                    <div key={p.id} className="p-4 space-y-1.5">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-semibold text-gray-900 dark:text-white">{p.name}</span>
                        {p.is_closed ? <span className="badge badge-danger flex items-center gap-1 text-xs"><Lock className="h-3 w-3" />{t('locked')}</span> : <span className="badge badge-success flex items-center gap-1 text-xs"><Unlock className="h-3 w-3" />{t('unlocked')}</span>}
                      </div>
                      <div className="text-xs text-gray-500">{p.start_date?.slice(0, 10)} – {p.end_date?.slice(0, 10)}</div>
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}

      {/* Add Account Modal */}
      <Modal open={modal === 'account'} onClose={() => setModal(null)} title={t('add_account')} size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={() => { if (!accForm.code || !accForm.name) return toast.error(t('error')); addAccount.mutate({ account_code: accForm.code, account_name: accForm.name, account_type: accForm.type }) }} disabled={addAccount.isPending} className="btn btn-primary">{addAccount.isPending ? t('saving') : t('create')}</button></>}>
        <div className="space-y-4">
          <div><label className="label">{t('account_code')} *</label><input value={accForm.code} onChange={(e) => setAccForm((p) => ({ ...p, code: e.target.value }))} className="input w-full" placeholder="e.g. 1000" /></div>
          <div><label className="label">{t('account_name')} *</label><input value={accForm.name} onChange={(e) => setAccForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" /></div>
          <div>
            <label className="label">{t('type')}</label>
            <select value={accForm.type} onChange={(e) => setAccForm((p) => ({ ...p, type: e.target.value }))} className="input w-full">
              {accountTypes.map((at) => <option key={at} value={at} className="capitalize">{t(at as any)}</option>)}
            </select>
          </div>
        </div>
      </Modal>

      {/* Journal Entry Modal */}
      <Modal open={modal === 'journal'} onClose={() => setModal(null)} title={t('journal_entry')} size="xl"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleCreateJE} disabled={addJE.isPending || !balanced} className="btn btn-primary disabled:opacity-50">{addJE.isPending ? t('saving') : t('create')}</button></>}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div><label className="label">{t('date')}</label><input value={jeForm.date} type="date" onChange={(e) => setJeForm((p) => ({ ...p, date: e.target.value }))} className="input w-full" /></div>
            <div><label className="label">{t('reference')}</label><input value={jeForm.reference} onChange={(e) => setJeForm((p) => ({ ...p, reference: e.target.value }))} className="input w-full" placeholder={t('optional_notes')} /></div>
            <div className="col-span-2"><label className="label">{t('description')} *</label><input value={jeForm.description} onChange={(e) => setJeForm((p) => ({ ...p, description: e.target.value }))} className="input w-full" /></div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="label mb-0">{t('je_lines')}</label>
              <button type="button" onClick={addJELine} className="text-xs text-primary-600 hover:underline flex items-center gap-1"><Plus className="h-3 w-3" />{t('add')}</button>
            </div>
            <div className="space-y-2">
              <div className="grid grid-cols-3 gap-2 text-xs font-semibold text-gray-400 px-1"><span>{t('accounts')}</span><span>{t('debit')}</span><span>{t('credit')}</span></div>
              {jeForm.lines.map((line, i) => (
                <div key={i} className="grid grid-cols-3 gap-2 items-center">
                  <select value={line.account_id} onChange={(e) => setJeForm((p) => ({ ...p, lines: p.lines.map((l, idx) => idx === i ? { ...l, account_id: e.target.value } : l) }))} className="input text-sm">
                    <option value="">—</option>
                    {accounts.map((a) => <option key={a.id} value={a.id}>{a.account_code} — {a.account_name}</option>)}
                  </select>
                  <input value={line.debit} type="number" step="0.01" min="0" onChange={(e) => setJeForm((p) => ({ ...p, lines: p.lines.map((l, idx) => idx === i ? { ...l, debit: e.target.value } : l) }))} className="input" placeholder="0.00" />
                  <div className="flex gap-1">
                    <input value={line.credit} type="number" step="0.01" min="0" onChange={(e) => setJeForm((p) => ({ ...p, lines: p.lines.map((l, idx) => idx === i ? { ...l, credit: e.target.value } : l) }))} className="input flex-1" placeholder="0.00" />
                    {jeForm.lines.length > 2 && <button type="button" onClick={() => removeJELine(i)} className="text-red-400 hover:text-red-600 px-1">×</button>}
                  </div>
                </div>
              ))}
            </div>
            <div className={clsx('mt-2 p-2 rounded text-xs font-semibold flex justify-between', balanced ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700')}>
              <span>{t('debit')}: {totalDebit.toFixed(2)}</span>
              <span className="flex items-center gap-1">{balanced ? `✓ ${t('balanced')}` : totalDebit === 0 ? t('enter_amounts') : <><RefreshCw className="h-3 w-3" />{t('unbalanced')}</>}</span>
              <span>{t('credit')}: {totalCredit.toFixed(2)}</span>
            </div>
          </div>
        </div>
      </Modal>
    </div>
  )
}
