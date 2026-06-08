import { useState, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import Modal from '@/components/common/Modal'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { CreditCard, Plus, Printer, DollarSign, Calendar, Hash, X } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Supplier { id: number; name: string; phone?: string; outstanding_balance?: string }
interface SupplierPayment { id: number; payment_number?: string; supplier?: { name: string; phone?: string }; amount: string; payment_method: string; payment_date: string; notes?: string }

const methodClass: Record<string, string> = { cash: 'badge-success', card: 'badge-info', transfer: 'badge-warning', check: 'badge-gray' }
const emptyForm = { supplier_id: '', amount: '', payment_method: 'cash', payment_date: new Date().toISOString().slice(0, 10), notes: '' }

export default function SupplierPaymentsPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const qc = useQueryClient()
  const [modal, setModal] = useState<'new' | 'print' | null>(null)
  const [form, setForm] = useState({ ...emptyForm })
  const [printRow, setPrintRow] = useState<SupplierPayment | null>(null)
  const receiptRef = useRef<HTMLDivElement>(null)
  const [filterSupplier, setFilterSupplier] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)

  const { data: suppliersData } = useQuery({
    queryKey: ['suppliers-all'],
    queryFn: () => apiGet<{ success: boolean; data: Supplier[] }>('/suppliers', { all: 1 }),
    staleTime: 120_000,
  })
  const datesValid = !dateFrom || !dateTo || dateFrom <= dateTo

  const { data, isLoading } = useQuery({
    queryKey: ['supplier-payments', filterSupplier, dateFrom, dateTo, page],
    queryFn: () => apiGet<{ success: boolean; data: SupplierPayment[]; total?: number }>('/supplier-payments', {
      supplier_id: filterSupplier || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      page,
      per_page: 20,
    }),
    staleTime: 30_000,
    enabled: datesValid,
  })

  const { data: settingsData } = useQuery({
    queryKey: ['settings-store'],
    queryFn: () => apiGet<Record<string, string>>('/settings/group/store'),
    staleTime: 300_000,
    retry: false,
  })

  const suppliers = suppliersData?.data ?? []
  const payments = data?.data ?? []
  const canView = hasPermission('view_warehouse')

  const storeName = settingsData?.store_name ?? settingsData?.business_name ?? 'POS Store'
  const storePhone = settingsData?.store_phone ?? settingsData?.phone ?? ''
  const storeAddress = settingsData?.store_address ?? settingsData?.address ?? ''
  const storeFooter = settingsData?.receipt_footer ?? settingsData?.footer_text ?? 'Thank you!'

  const handlePrint = () => {
    const content = receiptRef.current
    if (!content) return
    const win = window.open('', '_blank', 'width=400,height=600')
    if (!win) { window.print(); return }
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"/>
      <title>Payment Receipt</title>
      <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Courier New',monospace;font-size:12px;color:#000;background:#fff;padding:8px}
        .receipt{max-width:300px;margin:0 auto}
        .center{text-align:center}
        .bold{font-weight:bold}
        .divider{border-top:1px dashed #000;margin:6px 0}
        .row{display:flex;justify-content:space-between;margin:3px 0}
        .label{color:#555}
        @media print{body{padding:0}}
      </style>
    </head><body>${content.innerHTML}</body></html>`)
    win.document.close()
    win.focus()
    setTimeout(() => { win.print(); win.close() }, 300)
  }

  const selectedSupplier = suppliers.find((s) => String(s.id) === form.supplier_id)

  const totalPaid = payments.reduce((s, p) => s + parseFloat(p.amount || '0'), 0)
  const latestDate = payments.length > 0 ? payments[0].payment_date?.slice(0, 10) : '—'

  const createPayment = useMutation({
    mutationFn: (payload: object) => apiPost('/supplier-payments', payload),
    onSuccess: () => { toast.success(t('record_success')); qc.invalidateQueries({ queryKey: ['supplier-payments'] }); setModal(null) },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? t('record_failed'))
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.supplier_id) return toast.error(t('error'))
    if (!form.amount || parseFloat(form.amount) <= 0) return toast.error(t('error'))
    if (!form.payment_date) return toast.error(t('error'))
    createPayment.mutate({
      supplier_id: parseInt(form.supplier_id),
      amount: parseFloat(form.amount),
      payment_method: form.payment_method,
      payment_date: form.payment_date,
      notes: form.notes || undefined,
    })
  }

  const openPrint = (p: SupplierPayment) => { setPrintRow(p); setModal('print') }

  const clearFilters = () => { setFilterSupplier(''); setDateFrom(''); setDateTo(''); setPage(1) }

  if (!canView) return <div className="card p-8 text-center text-gray-400">{t('no_permission')}</div>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><CreditCard className="h-6 w-6 text-primary-500" /> {t('supplier_payments')}</h1>
        <button onClick={() => { setForm({ ...emptyForm }); setModal('new') }} className="btn btn-primary flex items-center gap-2"><Plus className="h-4 w-4" /> {t('add_payment')}</button>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-green-100 dark:bg-green-900/30">
            <DollarSign className="h-5 w-5 text-green-600 dark:text-green-400" />
          </div>
          <div><p className="text-xs text-gray-500">{t('total_paid')}</p><p className="text-xl font-bold text-gray-900 dark:text-white">{totalPaid.toFixed(2)}</p></div>
        </div>
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-blue-100 dark:bg-blue-900/30">
            <Hash className="h-5 w-5 text-blue-600 dark:text-blue-400" />
          </div>
          <div><p className="text-xs text-gray-500">{t('total_payment_count')}</p><p className="text-xl font-bold text-gray-900 dark:text-white">{data?.total ?? payments.length}</p></div>
        </div>
        <div className="card p-4 flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl flex items-center justify-center bg-amber-100 dark:bg-amber-900/30">
            <Calendar className="h-5 w-5 text-amber-600 dark:text-amber-400" />
          </div>
          <div><p className="text-xs text-gray-500">{t('latest_payment_date')}</p><p className="text-xl font-bold text-gray-900 dark:text-white">{latestDate}</p></div>
        </div>
      </div>

      <div className="card p-3 flex flex-wrap gap-3 items-end">
        <div>
          <label className="label text-xs">{t('supplier')}</label>
          <select value={filterSupplier} onChange={(e) => { setFilterSupplier(e.target.value); setPage(1) }} className="input text-sm">
            <option value="">{t('all_suppliers_option')}</option>
            {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </div>
        <div>
          <label className="label text-xs">{t('date_from')}</label>
          <input value={dateFrom} type="date" max={dateTo || undefined} onChange={(e) => { setDateFrom(e.target.value); setPage(1) }} className="input text-sm" />
        </div>
        <div>
          <label className={`label text-xs${!datesValid ? ' text-red-500' : ''}`}>{t('date_to')}</label>
          <input value={dateTo} type="date" min={dateFrom || undefined} onChange={(e) => { setDateTo(e.target.value); setPage(1) }} className={`input text-sm${!datesValid ? ' border-red-400' : ''}`} />
          {!datesValid && <p className="text-xs text-red-500 mt-0.5">{t('date_must_be_after_from')}</p>}
        </div>
        <button onClick={clearFilters} className="btn btn-secondary text-sm">{t('clear')}</button>
      </div>

      <div className="card overflow-hidden">
        {isLoading ? <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /><span className="ml-2 text-gray-400">{t('loading')}</span></div> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700">
                <tr>{[t('payment_number'), t('select_supplier'), t('phone'), t('amount'), t('method'), t('date'), t('notes'), ''].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {payments.length === 0 ? <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                  : payments.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-3 font-mono text-xs text-primary-600">{p.payment_number ?? `#${p.id}`}</td>
                      <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{p.supplier?.name ?? '—'}</td>
                      <td className="px-4 py-3 text-gray-500">{p.supplier?.phone ?? '—'}</td>
                      <td className="px-4 py-3 font-semibold text-green-600">{parseFloat(p.amount).toFixed(2)}</td>
                      <td className="px-4 py-3"><span className={clsx('badge capitalize text-xs', methodClass[p.payment_method] ?? 'badge-gray')}>{p.payment_method}</span></td>
                      <td className="px-4 py-3 text-gray-500">{p.payment_date?.slice(0, 10)}</td>
                      <td className="px-4 py-3 text-gray-400 max-w-40 truncate">{p.notes ?? '—'}</td>
                      <td className="px-4 py-3">
                        <button onClick={() => openPrint(p)} className="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded" title={t('print')}><Printer className="h-4 w-4" /></button>
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        )}
        {(data?.total ?? 0) > 20 && (
          <div className="flex items-center justify-between px-4 py-3 border-t dark:border-gray-700">
            <span className="text-sm text-gray-500">{t('page')} {page} · {data?.total} {t('total')}</span>
            <div className="flex gap-2">
              <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('prev')}</button>
              <button onClick={() => setPage((p) => p + 1)} disabled={payments.length < 20} className="btn btn-secondary text-sm py-1 disabled:opacity-40">{t('next')}</button>
            </div>
          </div>
        )}
      </div>

      <Modal open={modal === 'new'} onClose={() => setModal(null)} title={t('add_payment')} size="md"
        footer={<><button onClick={() => setModal(null)} className="btn btn-secondary">{t('cancel')}</button><button onClick={handleSubmit} disabled={createPayment.isPending} className="btn btn-primary">{createPayment.isPending ? t('loading') : t('save')}</button></>}>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="label">{t('select_supplier')} *</label>
            <select value={form.supplier_id} onChange={(e) => setForm((p) => ({ ...p, supplier_id: e.target.value }))} className="input w-full" required>
              <option value="">— {t('select_supplier')} —</option>
              {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
            {selectedSupplier && (
              <p className="mt-1 text-xs text-gray-500">{t('current_balance')}: <span className={clsx('font-semibold', parseFloat(selectedSupplier.outstanding_balance ?? '0') > 0 ? 'text-red-600' : 'text-green-600')}>{parseFloat(selectedSupplier.outstanding_balance ?? '0').toFixed(2)}</span></p>
            )}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('amount')} *</label>
              <input value={form.amount} type="number" min="0.01" step="0.01" onChange={(e) => setForm((p) => ({ ...p, amount: e.target.value }))} className="input w-full" required />
            </div>
            <div>
              <label className="label">{t('payment_method')}</label>
              <select value={form.payment_method} onChange={(e) => setForm((p) => ({ ...p, payment_method: e.target.value }))} className="input w-full">
                {['cash', 'card', 'transfer', 'check'].map((m) => <option key={m} value={m} className="capitalize">{m}</option>)}
              </select>
            </div>
          </div>
          <div>
            <label className="label">{t('payment_date')} *</label>
            <input value={form.payment_date} type="date" onChange={(e) => setForm((p) => ({ ...p, payment_date: e.target.value }))} className="input w-full" required />
          </div>
          <div>
            <label className="label">{t('notes')}</label>
            <textarea value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full h-20 resize-none" placeholder={t('optional_notes')} />
          </div>
        </form>
      </Modal>

      {/* Payment Receipt Print Modal */}
      {modal === 'print' && printRow && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setModal(null)} />
          <div className="relative z-10 flex flex-col max-h-[90vh] w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-2xl">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
              <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <Printer className="h-4 w-4 text-primary-500" /> {t('payment_receipt_title')}
              </h2>
              <button onClick={() => setModal(null)} className="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"><X className="h-5 w-5" /></button>
            </div>

            {/* Receipt preview */}
            <div className="overflow-y-auto flex-1 p-4">
              <div ref={receiptRef} className="bg-white text-black font-mono text-xs mx-auto" style={{ maxWidth: 300 }}>
                {/* Store header */}
                <div style={{ textAlign: 'center', marginBottom: 4 }}>
                  <div style={{ fontSize: 16, fontWeight: 'bold', marginBottom: 2 }} className="bold">{storeName}</div>
                  {storeAddress && <div style={{ fontSize: 11, color: '#555' }}>{storeAddress}</div>}
                  {storePhone && <div style={{ fontSize: 11, color: '#555' }}>{storePhone}</div>}
                </div>

                <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />

                <div style={{ textAlign: 'center', fontWeight: 'bold', fontSize: 13, marginBottom: 4 }}>{t('supplier_payment_receipt')}</div>

                <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />

                {/* Payment details */}
                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '3px 0' }}>
                  <span style={{ color: '#555' }}>{t('payment_num')}</span>
                  <span style={{ fontWeight: 'bold' }}>{printRow.payment_number ?? `#${printRow.id}`}</span>
                </div>
                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '3px 0' }}>
                  <span style={{ color: '#555' }}>{t('date')}</span>
                  <span>{printRow.payment_date?.slice(0, 10)}</span>
                </div>

                <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />

                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '3px 0' }}>
                  <span style={{ color: '#555' }}>{t('supplier')}</span>
                  <span style={{ fontWeight: 'bold' }}>{printRow.supplier?.name ?? '—'}</span>
                </div>
                {printRow.supplier?.phone && (
                  <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '3px 0' }}>
                    <span style={{ color: '#555' }}>{t('phone')}</span>
                    <span>{printRow.supplier.phone}</span>
                  </div>
                )}

                <div style={{ borderTop: '1px solid #000', margin: '6px 0' }} />

                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '4px 0', fontWeight: 'bold', fontSize: 14 }}>
                  <span>{t('amount_paid').toUpperCase()}</span>
                  <span>{parseFloat(printRow.amount).toFixed(2)}</span>
                </div>

                <div className="row" style={{ display: 'flex', justifyContent: 'space-between', margin: '3px 0' }}>
                  <span style={{ color: '#555' }}>{t('method')}</span>
                  <span style={{ textTransform: 'capitalize' }}>{printRow.payment_method}</span>
                </div>

                {printRow.notes && (
                  <>
                    <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }} />
                    <div style={{ fontSize: 11, color: '#555' }}>{t('note_label')}: {printRow.notes}</div>
                  </>
                )}

                <div style={{ borderTop: '1px dashed #000', margin: '8px 0 4px' }} />
                <div style={{ textAlign: 'center', fontSize: 11, color: '#555' }}>{storeFooter}</div>
              </div>
            </div>

            {/* Actions */}
            <div className="flex gap-2 px-4 py-3 border-t border-gray-100 dark:border-gray-700">
              <button onClick={() => setModal(null)} className="btn btn-secondary flex-1">{t('close')}</button>
              <button onClick={handlePrint} className="btn btn-primary flex-1 flex items-center justify-center gap-2">
                <Printer className="h-4 w-4" /> {t('print')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
