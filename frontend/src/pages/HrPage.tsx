import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Users2, Plus, Pencil, Trash2, Clock, Calendar } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Employee { id: number; name: string; email?: string; phone?: string; position?: string; department?: string; salary?: string; status?: string; hire_date?: string }
interface Shift { id: number; employee_name?: string; start_time: string; end_time?: string; status?: string; total_hours?: string }

const emptyEmp = { name: '', email: '', phone: '', position: '', department: '', salary: '', status: 'active', hire_date: '' }

export default function HrPage() {
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [tab, setTab] = useState<'employees' | 'shifts'>('employees')
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ ...emptyEmp })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const { data: empData, isLoading: empLoading, isError: empError } = useQuery({
    queryKey: ['hr-employees'],
    queryFn: () => apiGet<{ success: boolean; data: Employee[] }>('/hr/employees?per_page=50'),
    staleTime: 120_000,
    retry: false,
  })
  const { data: shiftData, isLoading: shiftLoading } = useQuery({
    queryKey: ['hr-shifts'],
    queryFn: () => apiGet<{ success: boolean; data: Shift[] }>('/shifts?per_page=30'),
    staleTime: 60_000,
    enabled: tab === 'shifts',
    retry: false,
  })

  const employees = empData?.data ?? []
  const shifts = shiftData?.data ?? []
  const canManage = hasPermission('manage_hr')

  const f = (field: keyof typeof form) => ({
    value: form[field],
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm((p) => ({ ...p, [field]: e.target.value })),
  })

  const openAdd = () => { setForm({ ...emptyEmp }); setEditId(null); setModal('add') }
  const openEdit = (e: Employee) => {
    setForm({ name: e.name, email: e.email ?? '', phone: e.phone ?? '', position: e.position ?? '', department: e.department ?? '', salary: e.salary ?? '', status: e.status ?? 'active', hire_date: e.hire_date ?? '' })
    setEditId(e.id); setModal('edit')
  }

  const saveMutation = useMutation({
    mutationFn: (payload: object) => editId ? apiPut(`/hr/employees/${editId}`, payload) : apiPost('/hr/employees', payload),
    onSuccess: () => { toast.success(editId ? 'Employee updated' : 'Employee created'); qc.invalidateQueries({ queryKey: ['hr-employees'] }); setModal(null) },
    onError: () => toast.error('Failed to save employee'),
  })
  const deleteMutation = useMutation({
    mutationFn: (id: number) => apiDelete(`/hr/employees/${id}`),
    onSuccess: () => { toast.success('Employee deleted'); qc.invalidateQueries({ queryKey: ['hr-employees'] }); setDeleteId(null) },
    onError: () => toast.error('Failed to delete'),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name) return toast.error('Name required')
    saveMutation.mutate({ name: form.name, email: form.email || undefined, phone: form.phone || undefined, position: form.position || undefined, department: form.department || undefined, salary: form.salary || undefined, status: form.status, hire_date: form.hire_date || undefined })
  }

  if (empError && !canManage) {
    return (
      <div className="card p-8 text-center text-gray-400 space-y-3">
        <Users2 className="h-10 w-10 mx-auto opacity-40" />
        <p className="font-medium">HR module not accessible</p>
        <p className="text-sm">Requires manage_hr permission</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Users2 className="h-6 w-6 text-primary-500" /> Human Resources</h1>
        {canManage && tab === 'employees' && <button onClick={openAdd} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> Add Employee</button>}
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {(['employees', 'shifts'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors', tab === t ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700')}>{t}</button>
        ))}
      </div>

      {tab === 'employees' && (
        <div className="card overflow-hidden">
          {empLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 dark:bg-gray-700">
                  <tr>{['Name', 'Position', 'Department', 'Phone', 'Hire Date', 'Salary', 'Status', ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                  {employees.length === 0 ? <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">No employees found</td></tr>
                    : employees.map((e) => (
                      <tr key={e.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{e.name}</td>
                        <td className="px-4 py-3 text-gray-500">{e.position ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-500">{e.department ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-500">{e.phone ?? '—'}</td>
                        <td className="px-4 py-3 text-gray-400 text-xs">{e.hire_date?.slice(0, 10) ?? '—'}</td>
                        <td className="px-4 py-3 font-semibold text-primary-600">{e.salary ? parseFloat(e.salary).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}</td>
                        <td className="px-4 py-3"><span className={clsx('badge capitalize', e.status === 'active' ? 'badge-success' : 'badge-gray')}>{e.status ?? 'active'}</span></td>
                        <td className="px-4 py-3">
                          {canManage && (
                            <div className="flex gap-1 justify-end">
                              <button onClick={() => openEdit(e)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"><Pencil className="h-4 w-4" /></button>
                              <button onClick={() => setDeleteId(e.id)} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"><Trash2 className="h-4 w-4" /></button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {tab === 'shifts' && (
        <div className="card overflow-hidden">
          {shiftLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700"><tr>{['Employee', 'Start', 'End', 'Hours', 'Status'].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {shifts.length === 0 ? <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400">No shifts found</td></tr>
                  : shifts.map((s) => (
                    <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{s.employee_name ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{s.start_time?.slice(0, 16)}</td>
                      <td className="px-4 py-3 text-gray-500">{s.end_time?.slice(0, 16) ?? 'Ongoing'}</td>
                      <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{s.total_hours ? `${s.total_hours}h` : '—'}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize', s.status === 'active' ? 'badge-success' : 'badge-gray')}>{s.status ?? 'completed'}</span></td>
                    </tr>
                  ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      <Modal open={modal !== null} onClose={() => setModal(null)} title={editId ? 'Edit Employee' : 'Add Employee'} size="lg"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">Cancel</button><button onClick={handleSubmit} disabled={saveMutation.isPending} className="btn btn-primary">{saveMutation.isPending ? 'Saving…' : editId ? 'Update' : 'Create'}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2"><label className="label">Full Name *</label><input {...f('name')} className="input w-full" required /></div>
            <div><label className="label">Email</label><input {...f('email')} type="email" className="input w-full" /></div>
            <div><label className="label">Phone</label><input {...f('phone')} className="input w-full" /></div>
            <div><label className="label">Position</label><input {...f('position')} className="input w-full" placeholder="e.g. Cashier" /></div>
            <div><label className="label">Department</label><input {...f('department')} className="input w-full" placeholder="e.g. Sales" /></div>
            <div><label className="label">Salary</label><input {...f('salary')} type="number" step="0.01" min="0" className="input w-full" /></div>
            <div><label className="label">Hire Date</label><input {...f('hire_date')} type="date" className="input w-full" /></div>
            <div><label className="label">Status</label><select {...f('status')} className="input w-full"><option value="active">Active</option><option value="inactive">Inactive</option><option value="terminated">Terminated</option></select></div>
          </div>
        </form>
      </Modal>

      <ConfirmDialog open={deleteId !== null} title="Delete Employee" message="Delete this employee record?" loading={deleteMutation.isPending} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} onCancel={() => setDeleteId(null)} />
    </div>
  )
}
