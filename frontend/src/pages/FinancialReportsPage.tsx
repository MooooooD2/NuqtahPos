import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { BarChart2, TrendingUp, TrendingDown, DollarSign, BookOpen, FileText } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface AccountTotal { account_name: string; total: number | string }
interface AccountBalance { account_code: string; account_name: string; balance: number | string; children?: AccountBalance[] }
interface StmtLine { id: number; debit: number | string; credit: number | string; entry?: { entry_date?: string; description?: string; reference?: string } }
interface Account { id: number; account_code: string; account_name: string }

// API field names (camelCase from PHP compact())
interface IncomeStatement { revenues: AccountTotal[]; expenses: AccountTotal[]; totalRevenue: number; totalExpense: number; netIncome: number }
interface BalanceSheet { assets: AccountBalance[]; liabilities: AccountBalance[]; equity: AccountBalance[]; total_assets?: number; total_liabilities_equity?: number }
interface AccountStatement { lines: StmtLine[]; total_debit: number; total_credit: number; net_balance: number }

const today = new Date().toISOString().slice(0, 10)
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)

function fmt(val: string | number | null | undefined) {
  return parseFloat(String(val ?? 0)).toLocaleString(undefined, { minimumFractionDigits: 2 })
}

function AccountBalanceRows({ rows }: { rows: AccountBalance[] }) {
  return (
    <>
      {rows.map((row) => (
        <tr key={row.account_code} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td className="px-4 py-2 font-mono text-xs text-gray-500">{row.account_code}</td>
          <td className="px-4 py-2 text-gray-800 dark:text-gray-200">{row.account_name}</td>
          <td className="px-4 py-2 text-right font-semibold text-gray-900 dark:text-white">{fmt(row.balance)}</td>
        </tr>
      ))}
    </>
  )
}

export default function FinancialReportsPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const [tab, setTab] = useState<'income' | 'balance' | 'statement'>('income')

  const [incomeStart, setIncomeStart] = useState(firstOfMonth)
  const [incomeEnd, setIncomeEnd] = useState(today)
  const [incomeData, setIncomeData] = useState<IncomeStatement | null>(null)
  const [incomeLoading, setIncomeLoading] = useState(false)

  const [balanceData, setBalanceData] = useState<BalanceSheet | null>(null)
  const [balanceLoading, setBalanceLoading] = useState(false)
  const [balanceLoaded, setBalanceLoaded] = useState(false)

  const [stmtAccountId, setStmtAccountId] = useState('')
  const [stmtStart, setStmtStart] = useState(firstOfMonth)
  const [stmtEnd, setStmtEnd] = useState(today)
  const [stmtData, setStmtData] = useState<AccountStatement | null>(null)
  const [stmtLoading, setStmtLoading] = useState(false)

  const { data: accountsData, isLoading: accountsLoading } = useQuery({
    queryKey: ['accounts'],
    queryFn: () => apiGet<{ success: boolean; accounts: Account[] }>('/accounts'),
    staleTime: 120_000,
    enabled: tab === 'statement',
  })
  const accounts = accountsData?.accounts ?? []

  const canView = hasPermission('view_accounting')

  const handleGenerateIncome = async () => {
    if (!incomeStart || !incomeEnd) { toast.error(t('select_dates')); return }
    setIncomeLoading(true)
    try {
      const res = await apiPost<IncomeStatement>('/reports/income-statement', { start_date: incomeStart, end_date: incomeEnd })
      setIncomeData(res)
    } catch {
      toast.error(t('error'))
    } finally {
      setIncomeLoading(false)
    }
  }

  const handleLoadBalance = async () => {
    setBalanceLoading(true)
    setBalanceLoaded(true)
    try {
      const res = await apiGet<BalanceSheet>('/reports/balance-sheet')
      setBalanceData(res)
    } catch {
      toast.error(t('error'))
    } finally {
      setBalanceLoading(false)
    }
  }

  const handleLoadStatement = async () => {
    if (!stmtAccountId) { toast.error(t('error')); return }
    if (!stmtStart || !stmtEnd) { toast.error(t('select_dates')); return }
    setStmtLoading(true)
    try {
      const res = await apiPost<AccountStatement>(`/reports/account-statement/${stmtAccountId}`, { start_date: stmtStart, end_date: stmtEnd })
      setStmtData(res)
    } catch {
      toast.error(t('error'))
    } finally {
      setStmtLoading(false)
    }
  }

  const handleTabChange = (t: 'income' | 'balance' | 'statement') => {
    setTab(t)
    if (t === 'balance' && !balanceLoaded) handleLoadBalance()
  }

  const incomeTotalRevenue = incomeData?.totalRevenue ?? 0
  const incomeTotalExpenses = incomeData?.totalExpense ?? 0
  const netIncome = incomeData?.netIncome ?? 0

  if (!canView) {
    return (
      <div className="card p-8 text-center text-gray-400">
        <BarChart2 className="h-12 w-12 mx-auto mb-3 opacity-40" />
        <p>{t('access_denied_msg')}</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <BarChart2 className="h-6 w-6 text-primary-500" /> {t('financial_reports')}
        </h1>
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {([
          { key: 'income', label: t('income_statement'), icon: TrendingUp },
          { key: 'balance', label: t('balance_sheet'), icon: BookOpen },
          { key: 'statement', label: t('select_period_view'), icon: FileText },
        ] as const).map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => handleTabChange(key)}
            className={clsx('px-4 py-1.5 rounded-md text-sm font-medium transition-colors flex items-center gap-1.5',
              tab === key ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}
          >
            <Icon className="h-4 w-4" />{label}
          </button>
        ))}
      </div>

      {/* Income Statement */}
      {tab === 'income' && (
        <div className="space-y-4">
          <div className="card p-4">
            <div className="flex flex-wrap gap-4 items-end">
              <div>
                <label className="label">{t('start_date')}</label>
                <input type="date" value={incomeStart} onChange={(e) => setIncomeStart(e.target.value)} className="input" />
              </div>
              <div>
                <label className="label">{t('end_date')}</label>
                <input type="date" value={incomeEnd} onChange={(e) => setIncomeEnd(e.target.value)} className="input" />
              </div>
              <button onClick={handleGenerateIncome} disabled={incomeLoading} className="btn btn-primary flex items-center gap-2">
                {incomeLoading ? <LoadingSpinner size="sm" /> : <BarChart2 className="h-4 w-4" />}
                {t('generate')}
              </button>
            </div>
          </div>

          {incomeLoading && <div className="flex h-40 items-center justify-center"><LoadingSpinner size="lg" /></div>}

          {!incomeLoading && incomeData && (
            <>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="card p-5 border-l-4 border-blue-500">
                  <div className="flex items-center gap-2 mb-1">
                    <TrendingUp className="h-4 w-4 text-blue-500" />
                    <p className="text-xs text-gray-500 uppercase font-semibold">{t('total_assets')}</p>
                  </div>
                  <p className="text-2xl font-bold text-gray-900 dark:text-white">{incomeTotalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                </div>
                <div className="card p-5 border-l-4 border-red-500">
                  <div className="flex items-center gap-2 mb-1">
                    <TrendingDown className="h-4 w-4 text-red-500" />
                    <p className="text-xs text-gray-500 uppercase font-semibold">{t('total_liabilities')}</p>
                  </div>
                  <p className="text-2xl font-bold text-gray-900 dark:text-white">{incomeTotalExpenses.toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                </div>
                <div className={clsx('card p-5 border-l-4', netIncome >= 0 ? 'border-green-500' : 'border-red-500')}>
                  <div className="flex items-center gap-2 mb-1">
                    <DollarSign className={clsx('h-4 w-4', netIncome >= 0 ? 'text-green-500' : 'text-red-500')} />
                    <p className="text-xs text-gray-500 uppercase font-semibold">{t('net_income')}</p>
                  </div>
                  <p className={clsx('text-2xl font-bold', netIncome >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')}>
                    {netIncome.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div className="card overflow-hidden">
                  <div className="px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border-b dark:border-gray-700">
                    <h3 className="font-semibold text-blue-700 dark:text-blue-300 flex items-center gap-2"><TrendingUp className="h-4 w-4" /> {t('revenue')}</h3>
                  </div>
                  <>
                    <div className="hidden lg:block overflow-x-auto">
                      <table className="w-full min-w-[600px] text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                          <tr>
                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">{t('account_name')}</th>
                            <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">{t('amount')}</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                          {incomeData.revenues.length === 0 ? (
                            <tr><td colSpan={2} className="px-4 py-6 text-center text-gray-400">{t('no_data')}</td></tr>
                          ) : incomeData.revenues.map((row, i) => (
                            <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                              <td className="px-4 py-2 text-gray-800 dark:text-gray-200">{row.account_name}</td>
                              <td className="px-4 py-2 text-right font-semibold text-green-600 dark:text-green-400">{fmt(row.total)}</td>
                            </tr>
                          ))}
                        </tbody>
                        <tfoot className="bg-gray-50 dark:bg-gray-700 border-t dark:border-gray-600">
                          <tr>
                            <td className="px-4 py-2 font-bold text-gray-900 dark:text-white">{t('total')}</td>
                            <td className="px-4 py-2 text-right font-bold text-green-600 dark:text-green-400">{incomeTotalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                    <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                      {incomeData.revenues.length === 0 ? (
                        <div className="px-4 py-6 text-center text-gray-400 text-sm">{t('no_data')}</div>
                      ) : incomeData.revenues.map((row, i) => (
                        <div key={i} className="px-4 py-3 flex items-center justify-between gap-2">
                          <span className="text-gray-800 dark:text-gray-200 text-sm">{row.account_name}</span>
                          <span className="font-semibold text-green-600 dark:text-green-400 text-sm">{fmt(row.total)}</span>
                        </div>
                      ))}
                      <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                        <span className="font-bold text-gray-900 dark:text-white text-sm">{t('total')}</span>
                        <span className="font-bold text-green-600 dark:text-green-400 text-sm">{incomeTotalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                      </div>
                    </div>
                  </>
                </div>

                <div className="card overflow-hidden">
                  <div className="px-4 py-3 bg-red-50 dark:bg-red-900/20 border-b dark:border-gray-700">
                    <h3 className="font-semibold text-red-700 dark:text-red-300 flex items-center gap-2"><TrendingDown className="h-4 w-4" /> {t('expenses')}</h3>
                  </div>
                  <>
                    <div className="hidden lg:block overflow-x-auto">
                      <table className="w-full min-w-[600px] text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                          <tr>
                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">{t('account_name')}</th>
                            <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">{t('amount')}</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                          {incomeData.expenses.length === 0 ? (
                            <tr><td colSpan={2} className="px-4 py-6 text-center text-gray-400">{t('no_data')}</td></tr>
                          ) : incomeData.expenses.map((row, i) => (
                            <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                              <td className="px-4 py-2 text-gray-800 dark:text-gray-200">{row.account_name}</td>
                              <td className="px-4 py-2 text-right font-semibold text-red-600 dark:text-red-400">{fmt(row.total)}</td>
                            </tr>
                          ))}
                        </tbody>
                        <tfoot className="bg-gray-50 dark:bg-gray-700 border-t dark:border-gray-600">
                          <tr>
                            <td className="px-4 py-2 font-bold text-gray-900 dark:text-white">{t('total')}</td>
                            <td className="px-4 py-2 text-right font-bold text-red-600 dark:text-red-400">{incomeTotalExpenses.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                    <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                      {incomeData.expenses.length === 0 ? (
                        <div className="px-4 py-6 text-center text-gray-400 text-sm">{t('no_data')}</div>
                      ) : incomeData.expenses.map((row, i) => (
                        <div key={i} className="px-4 py-3 flex items-center justify-between gap-2">
                          <span className="text-gray-800 dark:text-gray-200 text-sm">{row.account_name}</span>
                          <span className="font-semibold text-red-600 dark:text-red-400 text-sm">{fmt(row.total)}</span>
                        </div>
                      ))}
                      <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                        <span className="font-bold text-gray-900 dark:text-white text-sm">{t('total')}</span>
                        <span className="font-bold text-red-600 dark:text-red-400 text-sm">{incomeTotalExpenses.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                      </div>
                    </div>
                  </>
                </div>
              </div>
            </>
          )}

          {!incomeLoading && !incomeData && (
            <div className="card text-center py-16 text-gray-400">
              <BarChart2 className="h-10 w-10 mx-auto mb-2 opacity-30" />
              <p>{t('select_period_view')}</p>
            </div>
          )}
        </div>
      )}

      {/* Balance Sheet */}
      {tab === 'balance' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button onClick={handleLoadBalance} disabled={balanceLoading} className="btn btn-secondary flex items-center gap-2">
              {balanceLoading ? <LoadingSpinner size="sm" /> : <BookOpen className="h-4 w-4" />}
              {t('reload')}
            </button>
          </div>

          {balanceLoading && <div className="flex h-40 items-center justify-center"><LoadingSpinner size="lg" /></div>}

          {!balanceLoading && balanceData && (
            <>
              {([
                { label: t('assets'), rows: balanceData.assets, total: balanceData.total_assets, color: 'text-green-600 dark:text-green-400', border: 'border-green-500', bg: 'bg-green-50 dark:bg-green-900/20', hdr: 'text-green-700 dark:text-green-300' },
                { label: t('liabilities'), rows: balanceData.liabilities, total: null, color: 'text-red-600 dark:text-red-400', border: 'border-red-500', bg: 'bg-red-50 dark:bg-red-900/20', hdr: 'text-red-700 dark:text-red-300' },
                { label: t('equity'), rows: balanceData.equity, total: balanceData.total_liabilities_equity, color: 'text-blue-600 dark:text-blue-400', border: 'border-blue-500', bg: 'bg-blue-50 dark:bg-blue-900/20', hdr: 'text-blue-700 dark:text-blue-300' },
              ]).map(({ label, rows, total, color, border, bg, hdr }) => (
                <div key={label} className={clsx('card overflow-hidden border-l-4', border)}>
                  <div className={clsx('px-4 py-3 border-b dark:border-gray-700', bg)}>
                    <h3 className={clsx('font-semibold', hdr)}>{label}</h3>
                  </div>
                  <>
                    <div className="hidden lg:block overflow-x-auto">
                      <table className="w-full min-w-[600px] text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                          <tr>
                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">{t('account_code')}</th>
                            <th className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">{t('account_name')}</th>
                            <th className="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500">{t('balance')}</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                          {rows.length === 0 ? (
                            <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">{t('no_data')}</td></tr>
                          ) : <AccountBalanceRows rows={rows} />}
                        </tbody>
                        {total !== null && (
                          <tfoot className="bg-gray-50 dark:bg-gray-700 border-t dark:border-gray-600">
                            <tr>
                              <td colSpan={2} className="px-4 py-2 font-bold text-gray-900 dark:text-white">{t('total')} {label}</td>
                              <td className={clsx('px-4 py-2 text-right font-bold', color)}>{fmt(total)}</td>
                            </tr>
                          </tfoot>
                        )}
                      </table>
                    </div>
                    <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                      {rows.length === 0 ? (
                        <div className="px-4 py-6 text-center text-gray-400 text-sm">{t('no_data')}</div>
                      ) : rows.map((row, i) => (
                        <div key={i} className="px-4 py-3 flex items-center justify-between gap-2">
                          <div>
                            <p className="text-gray-800 dark:text-gray-200 text-sm">{row.account_name}</p>
                            {row.account_code && <p className="text-xs text-gray-500 font-mono">{row.account_code}</p>}
                          </div>
                          <span className={clsx('font-bold text-sm', color)}>{fmt(row.balance)}</span>
                        </div>
                      ))}
                      {total !== null && (
                        <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                          <span className="font-bold text-gray-900 dark:text-white text-sm">{t('total')} {label}</span>
                          <span className={clsx('font-bold text-sm', color)}>{fmt(total)}</span>
                        </div>
                      )}
                    </div>
                  </>
                </div>
              ))}
            </>
          )}

          {!balanceLoading && !balanceData && (
            <div className="card text-center py-16 text-gray-400">
              <BookOpen className="h-10 w-10 mx-auto mb-2 opacity-30" />
              <p>{t('loading')}</p>
            </div>
          )}
        </div>
      )}

      {/* Account Statement */}
      {tab === 'statement' && (
        <div className="space-y-4">
          <div className="card p-4">
            <div className="flex flex-wrap gap-4 items-end">
              <div>
                <label className="label">{t('account_name')}</label>
                {accountsLoading ? (
                  <div className="input w-48 flex items-center"><LoadingSpinner size="sm" /></div>
                ) : (
                  <select value={stmtAccountId} onChange={(e) => { setStmtAccountId(e.target.value); setStmtData(null) }} className="input w-56">
                    <option value="">—</option>
                    {accounts.map((a) => <option key={a.id} value={a.id}>{a.account_code} — {a.account_name}</option>)}
                  </select>
                )}
              </div>
              <div>
                <label className="label">{t('start_date')}</label>
                <input type="date" value={stmtStart} onChange={(e) => setStmtStart(e.target.value)} className="input" />
              </div>
              <div>
                <label className="label">{t('end_date')}</label>
                <input type="date" value={stmtEnd} onChange={(e) => setStmtEnd(e.target.value)} className="input" />
              </div>
              <button onClick={handleLoadStatement} disabled={stmtLoading} className="btn btn-primary flex items-center gap-2">
                {stmtLoading ? <LoadingSpinner size="sm" /> : <FileText className="h-4 w-4" />}
                {t('generate')}
              </button>
            </div>
          </div>

          {stmtLoading && <div className="flex h-40 items-center justify-center"><LoadingSpinner size="lg" /></div>}

          {!stmtLoading && stmtData && (
            <div className="card overflow-hidden">
              <>
                <div className="hidden lg:block overflow-x-auto">
                  <table className="w-full min-w-[600px] text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        {[t('entry_number'), t('date'), t('description'), t('debit'), t('credit')].map((h, i) => (
                          <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {stmtData.lines.length === 0 ? (
                        <tr><td colSpan={5} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                      ) : stmtData.lines.map((line) => (
                        <tr key={line.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-mono text-xs text-primary-600 dark:text-primary-400">{line.entry?.reference ?? '—'}</td>
                          <td className="px-4 py-3 text-gray-500 text-xs">{line.entry?.entry_date?.slice(0, 10) ?? '—'}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-xs truncate">{line.entry?.description ?? '—'}</td>
                          <td className="px-4 py-3 font-semibold text-green-600 dark:text-green-400">
                            {parseFloat(String(line.debit)) > 0 ? fmt(String(line.debit)) : '—'}
                          </td>
                          <td className="px-4 py-3 font-semibold text-red-600 dark:text-red-400">
                            {parseFloat(String(line.credit)) > 0 ? fmt(String(line.credit)) : '—'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot className="bg-gray-50 dark:bg-gray-700 border-t-2 border-gray-200 dark:border-gray-600">
                      <tr>
                        <td colSpan={3} className="px-4 py-3 font-bold text-gray-900 dark:text-white">{t('totals')}</td>
                        <td className="px-4 py-3 font-bold text-green-600 dark:text-green-400">{fmt(String(stmtData.total_debit))}</td>
                        <td className="px-4 py-3 font-bold text-red-600 dark:text-red-400">{fmt(String(stmtData.total_credit))}</td>
                      </tr>
                      <tr>
                        <td colSpan={3} className="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">{t('net_balance')}</td>
                        <td colSpan={2} className={clsx('px-4 py-2 font-bold text-lg', Number(stmtData.net_balance) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')}>
                          {fmt(String(stmtData.net_balance))}
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                  {stmtData.lines.length === 0 ? (
                    <div className="px-4 py-12 text-center text-gray-400">{t('no_data')}</div>
                  ) : stmtData.lines.map((line) => (
                    <div key={line.id} className="p-4 space-y-1.5">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-mono text-xs text-primary-600 dark:text-primary-400">{line.entry?.reference ?? '—'}</span>
                        <span className="text-xs text-gray-500">{line.entry?.entry_date?.slice(0, 10) ?? '—'}</span>
                      </div>
                      {line.entry?.description && (
                        <p className="text-sm text-gray-700 dark:text-gray-300 line-clamp-2">{line.entry.description}</p>
                      )}
                      <div className="flex gap-4 text-xs">
                        {parseFloat(String(line.debit)) > 0 && (
                          <span className="font-semibold text-green-600 dark:text-green-400">{t('debit')}: {fmt(String(line.debit))}</span>
                        )}
                        {parseFloat(String(line.credit)) > 0 && (
                          <span className="font-semibold text-red-600 dark:text-red-400">{t('credit')}: {fmt(String(line.credit))}</span>
                        )}
                      </div>
                    </div>
                  ))}
                  <div className="p-4 bg-gray-50 dark:bg-gray-700 space-y-2">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-bold text-gray-900 dark:text-white">{t('totals')}</span>
                      <div className="flex gap-3">
                        <span className="font-bold text-green-600 dark:text-green-400">{fmt(String(stmtData.total_debit))}</span>
                        <span className="font-bold text-red-600 dark:text-red-400">{fmt(String(stmtData.total_credit))}</span>
                      </div>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{t('net_balance')}</span>
                      <span className={clsx('font-bold text-lg', Number(stmtData.net_balance) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')}>
                        {fmt(String(stmtData.net_balance))}
                      </span>
                    </div>
                  </div>
                </div>
              </>
            </div>
          )}

          {!stmtLoading && !stmtData && (
            <div className="card text-center py-16 text-gray-400">
              <FileText className="h-10 w-10 mx-auto mb-2 opacity-30" />
              <p>{t('no_data')}</p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
