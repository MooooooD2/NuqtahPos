import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Building2, Search, Printer, TrendingDown, TrendingUp, Scale } from 'lucide-react'
import { clsx } from 'clsx'

interface Supplier { id: number; name: string; phone?: string }
interface AccountEntry { id: number; date: string; movement_type: string; reference: string; debit: string; credit: string; balance: string; notes?: string }
interface StatementData { supplier: Supplier; entries: AccountEntry[]; totals: { total_debt: string; total_paid: string; balance: string } }

const movementBadge: Record<string, string> = { purchase_order: 'badge-info', payment: 'badge-success', purchase_return: 'badge-warning', adjustment: 'badge-gray' }

export default function SupplierAccountsPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const { hasPermission } = usePermission()
  const [search, setSearch] = useState('')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [loadTrigger, setLoadTrigger] = useState(0)

  const { data: suppliersData, isLoading: suppliersLoading } = useQuery({
    queryKey: ['suppliers-all-accounts'],
    queryFn: () => apiGet<{ success: boolean; data: Supplier[] }>('/suppliers', { all: 1 }),
    staleTime: 120_000,
  })

  const { data: statementData, isLoading: stmtLoading } = useQuery({
    queryKey: ['supplier-account', selectedId, dateFrom, dateTo, loadTrigger],
    queryFn: () => apiGet<{ success: boolean; data: StatementData }>(`/supplier-accounts/${selectedId}`, {
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
    }),
    staleTime: 0,
    enabled: selectedId !== null,
  })

  const suppliers = (suppliersData?.data ?? []).filter((s) =>
    !search || s.name.toLowerCase().includes(search.toLowerCase()) || (s.phone ?? '').includes(search)
  )

  const stmt = statementData?.data
  const totals = stmt?.totals
  const entries = stmt?.entries ?? []

  const totalDebt = parseFloat(totals?.total_debt ?? '0')
  const totalPaid = parseFloat(totals?.total_paid ?? '0')
  const netBalance = parseFloat(totals?.balance ?? '0')

  const canView = hasPermission('view_warehouse')

  const getMovementLabel = (type: string) => {
    const map: Record<string, string> = { purchase_order: t('move_purchase_order'), payment: t('move_payment'), purchase_return: t('move_purchase_return'), adjustment: t('move_adjustment') }
    return map[type] ?? type ?? '—'
  }

  if (!canView) return <div className="card p-8 text-center text-gray-400">{t('no_permission')}</div>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Building2 className="h-6 w-6 text-primary-500" /> {t('supplier_accounts')}</h1>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-red-100 dark:bg-red-900/30">
            <TrendingDown className="h-5 w-5 text-red-600 dark:text-red-400" />
          </div>
          <div>
            <p className="text-xs text-gray-500">{t('total_debt')}</p>
            <p className="text-xl font-bold text-red-600">{totalDebt.toFixed(2)}</p>
          </div>
        </div>
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-green-100 dark:bg-green-900/30">
            <TrendingUp className="h-5 w-5 text-green-600 dark:text-green-400" />
          </div>
          <div>
            <p className="text-xs text-gray-500">{t('total_paid')}</p>
            <p className="text-xl font-bold text-green-600">{totalPaid.toFixed(2)}</p>
          </div>
        </div>
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-orange-100 dark:bg-orange-900/30">
            <Scale className="h-5 w-5 text-orange-600 dark:text-orange-400" />
          </div>
          <div>
            <p className="text-xs text-gray-500">{t('current_balance')}</p>
            <p className={clsx('text-xl font-bold', netBalance > 0 ? 'text-orange-600' : netBalance < 0 ? 'text-green-600' : 'text-gray-500')}>{netBalance.toFixed(2)}</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="card overflow-hidden">
          <div className="p-3 border-b dark:border-gray-700">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('select_supplier')} className="input pl-9 w-full text-sm" />
            </div>
          </div>
          {suppliersLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /><span className="ml-2 text-gray-400">{t('loading')}</span></div> : (
            <div className="overflow-y-auto max-h-[60vh] divide-y divide-gray-100 dark:divide-gray-700">
              {suppliers.length === 0 ? <div className="px-4 py-10 text-center text-gray-400 text-sm">{t('no_data')}</div>
                : suppliers.map((s) => (
                  <button key={s.id} onClick={() => { setSelectedId(s.id); setDateFrom(''); setDateTo('') }}
                    className={clsx('w-full text-left px-4 py-3 transition-colors', selectedId === s.id ? 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-primary-500' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50')}>
                    <p className={clsx('font-medium text-sm', selectedId === s.id ? 'text-primary-700 dark:text-primary-400' : 'text-gray-900 dark:text-white')}>{s.name}</p>
                    {s.phone && <p className="text-xs text-gray-400 mt-0.5">{s.phone}</p>}
                  </button>
                ))}
            </div>
          )}
        </div>

        <div className="lg:col-span-2 space-y-4">
          {!selectedId ? (
            <div className="card p-12 text-center text-gray-400">
              <Building2 className="h-12 w-12 mx-auto mb-3 opacity-30" />
              <p>{t('select_supplier')}</p>
            </div>
          ) : (
            <>
              <div className="card p-4">
                <div className="flex items-center justify-between mb-3">
                  <h2 className="font-semibold text-gray-900 dark:text-white">{stmt?.supplier.name ?? suppliers.find((s) => s.id === selectedId)?.name}</h2>
                </div>
                <div className="flex flex-wrap gap-3 items-end">
                  <div>
                    <label className="label text-xs">{t('date_from')}</label>
                    <input value={dateFrom} type="date" onChange={(e) => setDateFrom(e.target.value)} className="input text-sm" />
                  </div>
                  <div>
                    <label className="label text-xs">{t('date_to')}</label>
                    <input value={dateTo} type="date" onChange={(e) => setDateTo(e.target.value)} className="input text-sm" />
                  </div>
                  <button onClick={() => setLoadTrigger((n) => n + 1)} className="btn btn-primary text-sm flex items-center gap-1">{t('load_statement')}</button>
                  {entries.length > 0 && (
                    <button onClick={() => window.print()} className="btn btn-secondary text-sm flex items-center gap-1"><Printer className="h-4 w-4" />{t('print')}</button>
                  )}
                </div>
              </div>

              <div className="card overflow-hidden">
                {stmtLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /><span className="ml-2 text-gray-400">{t('loading')}</span></div> : entries.length === 0 ? (
                  <div className="px-4 py-12 text-center text-gray-400 text-sm">{t('no_movements')}</div>
                ) : (
                  <>
                    {/* Desktop table */}
                    <div className="hidden lg:block overflow-x-auto">
                      <table className="w-full min-w-[600px] text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-700">
                          <tr>{[t('date'), t('type'), t('invoice_number'), t('debt'), t('paid'), t('current_balance')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                          {entries.map((entry) => {
                            const bal = parseFloat(entry.balance)
                            return (
                              <tr key={entry.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td className="px-4 py-3 text-gray-500 text-xs">{entry.date?.slice(0, 10) || '—'}</td>
                                <td className="px-4 py-3">{entry.movement_type ? <span className={clsx('badge text-xs', movementBadge[entry.movement_type] ?? 'badge-gray')}>{getMovementLabel(entry.movement_type)}</span> : <span className="text-gray-400">—</span>}</td>
                                <td className="px-4 py-3 font-mono text-xs text-primary-600">{entry.reference || '—'}</td>
                                <td className="px-4 py-3 text-red-600 font-medium">{parseFloat(entry.debit) > 0 ? parseFloat(entry.debit).toFixed(2) : '—'}</td>
                                <td className="px-4 py-3 text-green-600 font-medium">{parseFloat(entry.credit) > 0 ? parseFloat(entry.credit).toFixed(2) : '—'}</td>
                                <td className={clsx('px-4 py-3 font-bold', bal > 0 ? 'text-red-600' : bal < 0 ? 'text-green-600' : 'text-gray-500')}>{bal.toFixed(2)}</td>
                              </tr>
                            )
                          })}
                        </tbody>
                        {totals && (
                          <tfoot className="bg-gray-50 dark:bg-gray-700 border-t-2 dark:border-gray-600">
                            <tr>
                              <td colSpan={3} className="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{t('current_balance')}</td>
                              <td className="px-4 py-3 font-bold text-red-600">{parseFloat(totals.total_debt).toFixed(2)}</td>
                              <td className="px-4 py-3 font-bold text-green-600">{parseFloat(totals.total_paid).toFixed(2)}</td>
                              <td className={clsx('px-4 py-3 font-bold', parseFloat(totals.balance) > 0 ? 'text-red-600' : parseFloat(totals.balance) < 0 ? 'text-green-600' : 'text-gray-500')}>{parseFloat(totals.balance).toFixed(2)}</td>
                            </tr>
                          </tfoot>
                        )}
                      </table>
                    </div>
                    {/* Mobile cards */}
                    <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                      {entries.map((entry) => {
                        const bal = parseFloat(entry.balance)
                        return (
                          <div key={entry.id} className="p-4 space-y-1.5">
                            <div className="flex items-center justify-between gap-2">
                              {entry.movement_type ? (
                                <span className={clsx('badge text-xs', movementBadge[entry.movement_type] ?? 'badge-gray')}>{getMovementLabel(entry.movement_type)}</span>
                              ) : <span className="text-gray-400 text-xs">—</span>}
                              <span className={clsx('font-bold text-sm', bal > 0 ? 'text-red-600' : bal < 0 ? 'text-green-600' : 'text-gray-500')}>{bal.toFixed(2)}</span>
                            </div>
                            <div className="flex items-center gap-3 text-xs flex-wrap">
                              {parseFloat(entry.debit) > 0 && <span className="text-red-600 font-medium">{t('debt')}: {parseFloat(entry.debit).toFixed(2)}</span>}
                              {parseFloat(entry.credit) > 0 && <span className="text-green-600 font-medium">{t('paid')}: {parseFloat(entry.credit).toFixed(2)}</span>}
                              {entry.reference && <span className="font-mono text-primary-600">{entry.reference}</span>}
                            </div>
                            <div className="text-xs text-gray-400">{entry.date?.slice(0, 10) || '—'}</div>
                          </div>
                        )
                      })}
                      {totals && (
                        <div className="p-4 bg-gray-50 dark:bg-gray-700 flex items-center justify-between gap-2">
                          <span className="text-xs font-semibold uppercase text-gray-500">{t('current_balance')}</span>
                          <div className="flex gap-3 text-sm">
                            <span className="text-red-600 font-bold">{parseFloat(totals.total_debt).toFixed(2)}</span>
                            <span className="text-green-600 font-bold">{parseFloat(totals.total_paid).toFixed(2)}</span>
                            <span className={clsx('font-bold', parseFloat(totals.balance) > 0 ? 'text-red-600' : parseFloat(totals.balance) < 0 ? 'text-green-600' : 'text-gray-500')}>{parseFloat(totals.balance).toFixed(2)}</span>
                          </div>
                        </div>
                      )}
                    </div>
                  </>
                )}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
