import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { BookOpen } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

interface Account { id: number; code: string; name: string; type: string; balance: string }
interface JournalEntry { id: number; date: string; reference: string; description: string; total_debit: string; is_locked: boolean }

const accountTypeClass: Record<string, string> = { asset: 'badge-success', liability: 'badge-danger', equity: 'badge-info', revenue: 'badge-success', expense: 'badge-warning' }

export default function AccountingPage() {
  const { data: accountsData, isLoading: accLoading } = useQuery({
    queryKey: ['accounts'], queryFn: () => apiGet<{ success: boolean; accounts: Account[] }>('/accounts'), staleTime: 120_000,
  })
  const { data: journalData, isLoading: jLoading } = useQuery({
    queryKey: ['journal-entries'], queryFn: () => apiGet<{ success: boolean; data: JournalEntry[] }>('/journal-entries?per_page=20'), staleTime: 60_000,
  })

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><BookOpen className="h-6 w-6 text-primary-500" /> Accounting</h1>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card overflow-hidden">
          <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Chart of Accounts</h2>
          </div>
          {accLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['Code', 'Name', 'Type', 'Balance'].map(h => <th key={h} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {(accountsData?.accounts ?? []).map(acc => (
                  <tr key={acc.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-2 font-mono text-xs text-gray-500">{acc.code}</td>
                    <td className="px-4 py-2 text-gray-900 dark:text-white">{acc.name}</td>
                    <td className="px-4 py-2"><span className={`badge ${accountTypeClass[acc.type] ?? 'badge-gray'} capitalize text-xs`}>{acc.type}</span></td>
                    <td className="px-4 py-2 font-semibold text-right">{parseFloat(acc.balance ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                  </tr>
                ))}
                {(accountsData?.accounts ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">No accounts</td></tr>}
              </tbody>
            </table>
          )}
        </div>

        <div className="card overflow-hidden">
          <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Journal Entries</h2>
          </div>
          {jLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{['Date', 'Ref', 'Description', 'Debit', ''].map(h => <th key={h} className="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {(journalData?.data ?? []).map(je => (
                  <tr key={je.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-2 text-gray-400 text-xs">{je.date?.slice(0, 10)}</td>
                    <td className="px-4 py-2 font-mono text-xs text-primary-600">{je.reference}</td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300 truncate max-w-32">{je.description}</td>
                    <td className="px-4 py-2 font-semibold">{parseFloat(je.total_debit ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                    <td className="px-4 py-2">{je.is_locked && <span className="badge badge-gray text-xs">Locked</span>}</td>
                  </tr>
                ))}
                {(journalData?.data ?? []).length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">No journal entries</td></tr>}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  )
}
