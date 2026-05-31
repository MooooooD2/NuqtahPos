import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { Users, Clock } from 'lucide-react'
import LoadingSpinner from '@/components/common/LoadingSpinner'

interface Employee { id: number; name: string; position: string; department: string; salary: string; status: string }
interface HrResponse { success: boolean; data: Employee[] }

export default function HrPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['hr-employees'],
    queryFn: () => apiGet<HrResponse>('/hr/employees?per_page=50'),
    staleTime: 120_000,
    retry: false,
  })

  const employees = data?.data ?? []

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Users className="h-6 w-6 text-primary-500" /> HR</h1>

      {isError ? (
        <div className="card p-8 text-center text-gray-400">
          <Clock className="h-10 w-10 mx-auto mb-3 opacity-40" />
          <p className="font-medium">HR module not accessible</p>
          <p className="text-sm mt-1">Requires HR plan feature and manage_hr permission.</p>
        </div>
      ) : isLoading ? (
        <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700">
              <tr>{['Name', 'Position', 'Department', 'Salary', 'Status'].map(h => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {employees.length === 0 ? (
                <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No employees found.</td></tr>
              ) : employees.map(e => (
                <tr key={e.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{e.name}</td>
                  <td className="px-4 py-3 text-gray-500">{e.position ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-500">{e.department ?? '—'}</td>
                  <td className="px-4 py-3 font-semibold text-primary-600">{parseFloat(e.salary ?? '0').toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                  <td className="px-4 py-3"><span className={`badge ${e.status === 'active' ? 'badge-success' : 'badge-gray'} capitalize`}>{e.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
