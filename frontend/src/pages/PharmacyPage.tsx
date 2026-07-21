import { useState, Fragment } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiGet, apiPost, apiPut, apiDelete } from '@/services/api'
import Modal from '@/components/common/Modal'
import ConfirmDialog from '@/components/common/ConfirmDialog'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import {
  Pill, Plus, Pencil, Trash2, AlertTriangle, Package,
  ClipboardList, FlaskConical, CheckCircle, ChevronDown, ChevronUp,
} from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'

interface Medicine {
  id: number; name_ar: string; name_en?: string; generic_name?: string
  category: string; dosage_form: string; strength?: string; unit: string
  barcode?: string; manufacturer?: string
  requires_prescription: boolean; controlled_drug: boolean
  reorder_level: number; selling_price: string; cost_price: string
  notes?: string; is_active: boolean; total_stock?: number; nearest_expiry?: string
}
interface MedicineBatch {
  id: number; medicine_id: number; medicine_name?: string; lot_number?: string
  quantity: number; expiry_date: string; purchase_price: string
  supplier_name?: string; received_at?: string; notes?: string
  expiry_status: 'expired' | 'critical' | 'warning' | 'ok'; days_to_expiry: number
}
interface PrescriptionItem {
  id: number; medicine_id: number; medicine_name?: string
  quantity_prescribed: number; quantity_dispensed: number
  dosage_instructions?: string; status: string
}
interface Prescription {
  id: number; prescription_number: string; patient_name: string; patient_phone?: string
  doctor_name: string; doctor_phone?: string; clinic_name?: string
  issued_date: string; expiry_date?: string; notes?: string
  status: string; items_count?: number; items?: PrescriptionItem[]
}
interface DashboardData {
  totalMedicines: number; expiringSoon: number; expiredBatches: number
  lowStock: number; outOfStock: number; pendingRx: number; totalRxToday: number
  expiringList: MedicineBatch[]
}

const CATEGORIES = ['antibiotic', 'analgesic', 'antihypertensive', 'diabetes', 'vitamin', 'other']
const DOSAGE_FORMS = ['tablet', 'capsule', 'syrup', 'injection', 'cream', 'drops', 'other']

const emptyMed = {
  name_ar: '', name_en: '', generic_name: '', category: 'other', dosage_form: 'tablet',
  strength: '', unit: 'tablet', barcode: '', manufacturer: '',
  requires_prescription: false, controlled_drug: false,
  reorder_level: 10, selling_price: '', cost_price: '', notes: '', is_active: true,
}
const emptyBatch = { medicine_id: '', lot_number: '', quantity: '', expiry_date: '', purchase_price: '', notes: '' }
const emptyRx = { patient_name: '', patient_phone: '', doctor_name: '', doctor_phone: '', clinic_name: '', issued_date: '', expiry_date: '', notes: '' }

function expiryBadge(status: string) {
  if (status === 'expired')  return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  if (status === 'critical') return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
  if (status === 'warning')  return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
  return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
}
function rxStatusBadge(status: string) {
  if (status === 'fully_dispensed')    return 'badge-success'
  if (status === 'partially_dispensed') return 'badge-warning'
  return 'badge-gray'
}
function stockBadge(stock: number, reorder: number) {
  if (stock === 0)       return 'badge-danger'
  if (stock <= reorder)  return 'badge-warning'
  return 'badge-success'
}

export default function PharmacyPage() {
  const { t, i18n } = useTranslation('pos')
  const isAr = i18n.language.startsWith('ar')
  const qc = useQueryClient()
  const [tab, setTab] = useState<'medicines' | 'batches' | 'prescriptions'>('medicines')

  // ── medicines state ──
  const [medModal, setMedModal] = useState<'add' | 'edit' | null>(null)
  const [editMed, setEditMed] = useState<Medicine | null>(null)
  const [medForm, setMedForm] = useState({ ...emptyMed })
  const [delMed, setDelMed] = useState<Medicine | null>(null)
  const [medSearch, setMedSearch] = useState('')

  // ── batch state ──
  const [batchModal, setBatchModal] = useState(false)
  const [batchForm, setBatchForm] = useState({ ...emptyBatch })
  const [delBatch, setDelBatch] = useState<MedicineBatch | null>(null)

  // ── prescription state ──
  const [rxModal, setRxModal] = useState<'add' | 'dispense' | null>(null)
  const [activeRx, setActiveRx] = useState<Prescription | null>(null)
  const [rxForm, setRxForm] = useState({ ...emptyRx })
  const [rxItems, setRxItems] = useState([{ medicine_id: '', quantity_prescribed: '', dosage_instructions: '' }])
  const [dispenseForm, setDispenseForm] = useState<Record<number, number>>({})
  const [expandedRxId, setExpandedRxId] = useState<number | null>(null)

  /* ── Queries ── */
  const { data: dash } = useQuery<DashboardData>({
    queryKey: ['pharmacy-dashboard'],
    queryFn: () => apiGet('/pharmacy/dashboard'),
    staleTime: 30_000,
  })
  const { data: medsData, isLoading: medsLoading } = useQuery<{ data: Medicine[] }>({
    queryKey: ['pharmacy-medicines', medSearch],
    queryFn: () => apiGet('/pharmacy/medicines', { search: medSearch }),
    staleTime: 30_000,
    enabled: tab === 'medicines',
  })
  const { data: batchesData, isLoading: batchesLoading } = useQuery<{ data: MedicineBatch[] }>({
    queryKey: ['pharmacy-batches'],
    queryFn: () => apiGet('/pharmacy/batches'),
    staleTime: 30_000,
    enabled: tab === 'batches',
  })
  const { data: rxData, isLoading: rxLoading } = useQuery<{ data: Prescription[] }>({
    queryKey: ['pharmacy-prescriptions'],
    queryFn: () => apiGet('/pharmacy/prescriptions'),
    staleTime: 30_000,
    enabled: tab === 'prescriptions',
  })
  const { data: medsForBatch } = useQuery<{ data: Medicine[] }>({
    queryKey: ['pharmacy-medicines-all'],
    queryFn: () => apiGet('/pharmacy/medicines', { per_page: 100 }),
    staleTime: 60_000,
  })
  const medicines = medsData?.data ?? []
  const batches   = batchesData?.data ?? []
  const rxList    = rxData?.data ?? []
  const allMeds   = medsForBatch?.data ?? []

  /* ── Mutations ── */
  const saveMed = useMutation({
    mutationFn: (p: object) => medModal === 'add' ? apiPost('/pharmacy/medicines', p) : apiPut(`/pharmacy/medicines/${editMed?.id}`, p),
    onSuccess: () => { toast.success(t('saved_success')); qc.invalidateQueries({ queryKey: ['pharmacy-medicines'] }); qc.invalidateQueries({ queryKey: ['pharmacy-dashboard'] }); setMedModal(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const deleteMed = useMutation({
    mutationFn: (id: number) => apiDelete(`/pharmacy/medicines/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['pharmacy-medicines'] }); qc.invalidateQueries({ queryKey: ['pharmacy-dashboard'] }); setDelMed(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const saveBatch = useMutation({
    mutationFn: (p: object) => apiPost('/pharmacy/batches', p),
    onSuccess: () => { toast.success(t('saved_success')); qc.invalidateQueries({ queryKey: ['pharmacy-batches'] }); qc.invalidateQueries({ queryKey: ['pharmacy-dashboard'] }); qc.invalidateQueries({ queryKey: ['pharmacy-medicines'] }); setBatchModal(false) },
    onError: () => toast.error(t('save_failed')),
  })
  const deleteBatch = useMutation({
    mutationFn: (id: number) => apiDelete(`/pharmacy/batches/${id}`),
    onSuccess: () => { toast.success(t('deleted_success')); qc.invalidateQueries({ queryKey: ['pharmacy-batches'] }); qc.invalidateQueries({ queryKey: ['pharmacy-medicines'] }); setDelBatch(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const saveRx = useMutation({
    mutationFn: (p: object) => apiPost('/pharmacy/prescriptions', p),
    onSuccess: () => { toast.success(t('saved_success')); qc.invalidateQueries({ queryKey: ['pharmacy-prescriptions'] }); qc.invalidateQueries({ queryKey: ['pharmacy-dashboard'] }); setRxModal(null) },
    onError: () => toast.error(t('save_failed')),
  })
  const dispenseRx = useMutation({
    mutationFn: ({ id, items }: { id: number; items: object[] }) => apiPost(`/pharmacy/prescriptions/${id}/dispense`, { items }),
    onSuccess: () => { toast.success(t('saved_success')); qc.invalidateQueries({ queryKey: ['pharmacy-prescriptions'] }); qc.invalidateQueries({ queryKey: ['pharmacy-dashboard'] }); setRxModal(null) },
    onError: () => toast.error(t('save_failed')),
  })

  /* ── Handlers ── */
  const openAddMed = () => { setMedForm({ ...emptyMed }); setEditMed(null); setMedModal('add') }
  const openEditMed = (m: Medicine) => {
    setEditMed(m)
    setMedForm({ name_ar: m.name_ar, name_en: m.name_en ?? '', generic_name: m.generic_name ?? '', category: m.category, dosage_form: m.dosage_form, strength: m.strength ?? '', unit: m.unit, barcode: m.barcode ?? '', manufacturer: m.manufacturer ?? '', requires_prescription: m.requires_prescription, controlled_drug: m.controlled_drug, reorder_level: m.reorder_level, selling_price: m.selling_price, cost_price: m.cost_price, notes: m.notes ?? '', is_active: m.is_active })
    setMedModal('edit')
  }
  const handleSaveMed = () => {
    if (!medForm.name_ar) return toast.error(t('error'))
    saveMed.mutate(medForm)
  }
  const handleSaveBatch = () => {
    if (!batchForm.medicine_id || !batchForm.quantity || !batchForm.expiry_date) return toast.error(t('error'))
    saveBatch.mutate({ ...batchForm, quantity: parseInt(batchForm.quantity), purchase_price: batchForm.purchase_price || 0 })
  }
  const handleSaveRx = () => {
    if (!rxForm.patient_name || !rxForm.doctor_name || !rxForm.issued_date) return toast.error(t('error'))
    const validItems = rxItems.filter((i) => i.medicine_id && i.quantity_prescribed)
    if (validItems.length === 0) return toast.error(t('error'))
    saveRx.mutate({ ...rxForm, items: validItems.map((i) => ({ ...i, quantity_prescribed: parseInt(i.quantity_prescribed) })) })
  }
  const openDispense = (rx: Prescription) => {
    setActiveRx(rx)
    const init: Record<number, number> = {}
    rx.items?.forEach((i) => { init[i.id] = i.quantity_dispensed })
    setDispenseForm(init)
    setRxModal('dispense')
  }
  const handleDispense = () => {
    if (!activeRx) return
    const items = activeRx.items?.map((i) => ({ id: i.id, quantity_dispensed: dispenseForm[i.id] ?? 0 })) ?? []
    dispenseRx.mutate({ id: activeRx.id, items })
  }
  const addRxItem = () => setRxItems((p) => [...p, { medicine_id: '', quantity_prescribed: '', dosage_instructions: '' }])
  const removeRxItem = (idx: number) => setRxItems((p) => p.filter((_, i) => i !== idx))

  const tabs: { key: typeof tab; label: string; icon: typeof Pill }[] = [
    { key: 'medicines',     label: t('medicines'),     icon: Pill },
    { key: 'batches',       label: t('batches'),       icon: Package },
    { key: 'prescriptions', label: t('prescriptions'), icon: ClipboardList },
  ]

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <FlaskConical className="h-6 w-6 text-primary-500" /> {t('pharmacy_module')}
      </h1>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-3">
        {[
          { label: t('total_medicines'),    value: dash?.totalMedicines ?? 0,  color: 'bg-navy-50 dark:bg-navy-900/20',    text: 'text-navy-600' },
          { label: t('low_stock'),          value: dash?.lowStock ?? 0,        color: 'bg-orange-50 dark:bg-orange-900/20', text: 'text-orange-600' },
          { label: t('out_of_stock'),       value: dash?.outOfStock ?? 0,      color: 'bg-red-50 dark:bg-red-900/20',      text: 'text-red-600' },
          { label: t('expiring_soon'),      value: dash?.expiringSoon ?? 0,    color: 'bg-yellow-50 dark:bg-yellow-900/20', text: 'text-yellow-600' },
          { label: t('expired_batches'),    value: dash?.expiredBatches ?? 0,  color: 'bg-red-50 dark:bg-red-900/20',      text: 'text-red-700' },
          { label: t('pending_rx'),         value: dash?.pendingRx ?? 0,       color: 'bg-navy-50 dark:bg-navy-900/20', text: 'text-navy-600' },
          { label: t('rx_today'),           value: dash?.totalRxToday ?? 0,    color: 'bg-green-50 dark:bg-green-900/20',  text: 'text-green-600' },
        ].map(({ label, value, color, text }) => (
          <div key={label} className={clsx('card p-3 text-center', color)}>
            <p className={clsx('text-2xl font-bold', text)}>{value}</p>
            <p className="text-xs text-gray-500 mt-0.5">{label}</p>
          </div>
        ))}
      </div>

      {/* Expiry alerts strip */}
      {(dash?.expiringList?.length ?? 0) > 0 && (
        <div className="card p-3 border-l-4 border-orange-400 bg-orange-50 dark:bg-orange-900/10">
          <p className="text-xs font-semibold text-orange-700 dark:text-orange-400 mb-2 flex items-center gap-1">
            <AlertTriangle className="h-3.5 w-3.5" /> {t('expiry_alert')}
          </p>
          <div className="flex flex-wrap gap-2">
            {dash!.expiringList.map((b) => (
              <span key={b.id} className={clsx('text-xs px-2 py-0.5 rounded-full font-medium', expiryBadge(b.expiry_status))}>
                {b.medicine_name} — {b.expiry_date} ({b.days_to_expiry < 0 ? t('expired') : `${b.days_to_expiry}d`})
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-gray-200 dark:border-gray-700">
        {tabs.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={clsx('flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
              tab === key
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300')}
          >
            <Icon className="h-4 w-4" /> {label}
          </button>
        ))}
      </div>

      {/* ══════ MEDICINES TAB ══════ */}
      {tab === 'medicines' && (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-3">
            <input
              value={medSearch} onChange={(e) => setMedSearch(e.target.value)}
              placeholder={t('search')} className="input w-64"
            />
            <button onClick={openAddMed} className="btn btn-primary flex items-center gap-2 mr-auto">
              <Plus className="h-4 w-4" /> {t('add_medicine')}
            </button>
          </div>
          <div className="card overflow-hidden">
            {medsLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <>
                <div className="hidden lg:block overflow-x-auto">
                  <table className="w-full min-w-[750px] text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>{[t('medicine_name'), t('generic_name'), t('dosage_form'), t('strength'), t('stock'), t('selling_price'), t('rx_required'), t('status'), ''].map((h, i) => (
                        <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                      ))}</tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {medicines.length === 0 ? (
                        <tr><td colSpan={9} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                      ) : medicines.map((m) => (
                        <tr key={m.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3">
                            <p className="font-medium text-gray-900 dark:text-white">{m.name_ar}</p>
                            {m.name_en && <p className="text-xs text-gray-400">{m.name_en}</p>}
                            {m.controlled_drug && <span className="text-xs text-red-500 font-semibold">⚠ {t('controlled_drug')}</span>}
                          </td>
                          <td className="px-4 py-3 text-gray-500 text-xs">{m.generic_name ?? '—'}</td>
                          <td className="px-4 py-3">
                            <span className="badge badge-gray capitalize">{t(m.dosage_form)}</span>
                          </td>
                          <td className="px-4 py-3 text-gray-500 text-xs">{m.strength ?? '—'}</td>
                          <td className="px-4 py-3">
                            <span className={clsx('badge', stockBadge(m.total_stock ?? 0, m.reorder_level))}>
                              {m.total_stock ?? 0} {m.unit}
                            </span>
                            {m.nearest_expiry && <p className="text-xs text-gray-400 mt-0.5">{t('expiry')}: {m.nearest_expiry}</p>}
                          </td>
                          <td className="px-4 py-3 font-medium">{parseFloat(m.selling_price).toFixed(2)}</td>
                          <td className="px-4 py-3">
                            {m.requires_prescription
                              ? <span className="badge badge-warning text-xs">{t('rx_required')}</span>
                              : <span className="text-gray-400 text-xs">OTC</span>}
                          </td>
                          <td className="px-4 py-3">
                            <span className={clsx('badge', m.is_active ? 'badge-success' : 'badge-gray')}>
                              {m.is_active ? t('active') : t('inactive')}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex gap-1">
                              <button onClick={() => openEditMed(m)} className="p-1 text-gray-400 hover:text-primary-600 rounded">
                                <Pencil className="h-4 w-4" />
                              </button>
                              <button onClick={() => setDelMed(m)} className="p-1 text-gray-400 hover:text-red-600 rounded">
                                <Trash2 className="h-4 w-4" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                  {medicines.length === 0 ? (
                    <div className="px-4 py-12 text-center text-gray-400">{t('no_data')}</div>
                  ) : medicines.map((m) => (
                    <div key={m.id} className="p-4 space-y-2">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <p className="font-medium text-gray-900 dark:text-white">{m.name_ar}</p>
                          {m.name_en && <p className="text-xs text-gray-400">{m.name_en}</p>}
                          {m.controlled_drug && <span className="text-xs text-red-500 font-semibold">⚠ {t('controlled_drug')}</span>}
                        </div>
                        <div className="flex flex-col items-end gap-1">
                          <span className={clsx('badge', m.is_active ? 'badge-success' : 'badge-gray')}>
                            {m.is_active ? t('active') : t('inactive')}
                          </span>
                          {m.requires_prescription
                            ? <span className="badge badge-warning text-xs">{t('rx_required')}</span>
                            : <span className="text-gray-400 text-xs">OTC</span>}
                        </div>
                      </div>
                      <div className="flex flex-wrap items-center gap-2 text-xs">
                        <span className="badge badge-gray capitalize">{t(m.dosage_form)}</span>
                        {m.strength && <span className="text-gray-500">{m.strength}</span>}
                        {m.generic_name && <span className="text-gray-500">{m.generic_name}</span>}
                      </div>
                      <div className="flex items-center justify-between text-xs">
                        <div>
                          <span className={clsx('badge', stockBadge(m.total_stock ?? 0, m.reorder_level))}>
                            {m.total_stock ?? 0} {m.unit}
                          </span>
                          {m.nearest_expiry && <p className="text-gray-400 mt-0.5">{t('expiry')}: {m.nearest_expiry}</p>}
                        </div>
                        <span className="font-semibold text-gray-900 dark:text-white">{parseFloat(m.selling_price).toFixed(2)}</span>
                      </div>
                      <div className="flex gap-2">
                        <button onClick={() => openEditMed(m)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-navy-50 text-navy-700 dark:bg-navy-900/20 dark:text-navy-400 hover:bg-navy-100 transition-colors font-medium">
                          <Pencil className="h-3 w-3" /> {isAr ? 'تعديل' : 'Edit'}
                        </button>
                        <button onClick={() => setDelMed(m)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition-colors font-medium">
                          <Trash2 className="h-3 w-3" /> {isAr ? 'حذف' : 'Delete'}
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* ══════ BATCHES TAB ══════ */}
      {tab === 'batches' && (
        <div className="space-y-3">
          <div className="flex justify-end">
            <button onClick={() => { setBatchForm({ ...emptyBatch }); setBatchModal(true) }} className="btn btn-primary flex items-center gap-2">
              <Plus className="h-4 w-4" /> {t('add_batch')}
            </button>
          </div>
          <div className="card overflow-hidden">
            {batchesLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <>
                <div className="hidden lg:block overflow-x-auto">
                  <table className="w-full min-w-[750px] text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>{[t('medicine_name'), t('lot_number'), t('quantity'), t('expiry_date'), t('status'), t('purchase_price'), t('supplier'), ''].map((h, i) => (
                        <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                      ))}</tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {batches.length === 0 ? (
                        <tr><td colSpan={8} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                      ) : batches.map((b) => (
                        <tr key={b.id} className={clsx('hover:bg-gray-50 dark:hover:bg-gray-700/50', b.expiry_status === 'expired' && 'bg-red-50/30 dark:bg-red-900/10')}>
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{b.medicine_name ?? '—'}</td>
                          <td className="px-4 py-3 font-mono text-xs text-gray-500">{b.lot_number ?? '—'}</td>
                          <td className="px-4 py-3 font-semibold">{b.quantity}</td>
                          <td className="px-4 py-3 text-gray-500">{b.expiry_date}</td>
                          <td className="px-4 py-3">
                            <span className={clsx('text-xs font-semibold px-2 py-0.5 rounded-full', expiryBadge(b.expiry_status))}>
                              {b.days_to_expiry < 0 ? t('expired') : `${b.days_to_expiry}d`}
                            </span>
                          </td>
                          <td className="px-4 py-3">{parseFloat(b.purchase_price).toFixed(2)}</td>
                          <td className="px-4 py-3 text-gray-500 text-xs">{b.supplier_name ?? '—'}</td>
                          <td className="px-4 py-3">
                            <button onClick={() => setDelBatch(b)} className="p-1 text-gray-400 hover:text-red-600 rounded">
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                  {batches.length === 0 ? (
                    <div className="px-4 py-12 text-center text-gray-400">{t('no_data')}</div>
                  ) : batches.map((b) => (
                    <div key={b.id} className={clsx('p-4 space-y-1.5', b.expiry_status === 'expired' && 'bg-red-50/30 dark:bg-red-900/10')}>
                      <div className="flex items-center justify-between gap-2">
                        <p className="font-medium text-gray-900 dark:text-white">{b.medicine_name ?? '—'}</p>
                        <span className={clsx('text-xs font-semibold px-2 py-0.5 rounded-full', expiryBadge(b.expiry_status))}>
                          {b.days_to_expiry < 0 ? t('expired') : `${b.days_to_expiry}d`}
                        </span>
                      </div>
                      <div className="flex items-center gap-3 text-xs flex-wrap">
                        {b.lot_number && <span className="font-mono text-gray-500">{b.lot_number}</span>}
                        <span className="font-semibold text-gray-900 dark:text-white">{t('quantity')}: {b.quantity}</span>
                        <span className="text-gray-500">{b.expiry_date}</span>
                      </div>
                      <div className="flex items-center justify-between text-xs">
                        <span className="text-gray-500">{b.supplier_name ?? '—'}</span>
                        <span className="font-medium text-gray-700 dark:text-gray-300">{parseFloat(b.purchase_price).toFixed(2)}</span>
                      </div>
                      <button onClick={() => setDelBatch(b)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 hover:bg-red-100 transition-colors font-medium">
                        <Trash2 className="h-3 w-3" /> {isAr ? 'حذف' : 'Delete'}
                      </button>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* ══════ PRESCRIPTIONS TAB ══════ */}
      {tab === 'prescriptions' && (
        <div className="space-y-3">
          <div className="flex justify-end">
            <button onClick={() => { setRxForm({ ...emptyRx }); setRxItems([{ medicine_id: '', quantity_prescribed: '', dosage_instructions: '' }]); setRxModal('add') }} className="btn btn-primary flex items-center gap-2">
              <Plus className="h-4 w-4" /> {t('add_prescription')}
            </button>
          </div>
          <div className="card overflow-hidden">
            {rxLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
              <>
                <div className="hidden lg:block overflow-x-auto">
                  <table className="w-full min-w-[750px] text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>{['#', t('patient_name'), t('doctor_name'), t('issued_date'), t('items_count'), t('status'), t('actions')].map((h, i) => (
                        <th key={i} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                      ))}</tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {rxList.length === 0 ? (
                        <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400">{t('no_data')}</td></tr>
                      ) : rxList.map((rx) => {
                        const isExpanded = expandedRxId === rx.id
                        return (
                          <Fragment key={rx.id}>
                            <tr className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                              <td className="px-4 py-3 font-mono text-xs text-primary-600">{rx.prescription_number}</td>
                              <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {rx.patient_name}
                                {rx.patient_phone && <p className="text-xs text-gray-400">{rx.patient_phone}</p>}
                              </td>
                              <td className="px-4 py-3 text-gray-500">
                                {rx.doctor_name}
                                {rx.clinic_name && <p className="text-xs text-gray-400">{rx.clinic_name}</p>}
                              </td>
                              <td className="px-4 py-3 text-gray-500 text-xs">{rx.issued_date}</td>
                              <td className="px-4 py-3 text-center">{rx.items_count ?? rx.items?.length ?? 0}</td>
                              <td className="px-4 py-3">
                                <span className={clsx('badge capitalize', rxStatusBadge(rx.status))}>
                                  {t(rx.status)}
                                </span>
                              </td>
                              <td className="px-4 py-3">
                                <div className="flex gap-1">
                                  {rx.status !== 'fully_dispensed' && (
                                    <button onClick={() => openDispense(rx)} className="btn btn-secondary text-xs py-1 px-2 flex items-center gap-1">
                                      <CheckCircle className="h-3 w-3" /> {t('dispense')}
                                    </button>
                                  )}
                                  {(rx.items?.length ?? 0) > 0 && (
                                    <button onClick={() => setExpandedRxId(isExpanded ? null : rx.id)} className="p-1 text-gray-400 hover:text-primary-600 rounded">
                                      {isExpanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                                    </button>
                                  )}
                                </div>
                              </td>
                            </tr>
                            {isExpanded && (rx.items?.length ?? 0) > 0 && (
                              <tr key={`${rx.id}-items`}>
                                <td colSpan={7} className="px-6 pb-3 bg-gray-50 dark:bg-gray-800">
                                  <div className="overflow-x-auto">
                                  <table className="w-full min-w-[450px] text-xs mt-1">
                                    <thead><tr className="text-gray-400">
                                      {[t('medicine_name'), t('quantity_prescribed'), t('quantity_dispensed'), t('dosage_instructions'), t('status')].map((h, i) => (
                                        <th key={i} className="px-2 py-1.5 text-left font-medium">{h}</th>
                                      ))}
                                    </tr></thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                      {rx.items!.map((item) => (
                                        <tr key={item.id}>
                                          <td className="px-2 py-1.5 font-medium">{item.medicine_name ?? '—'}</td>
                                          <td className="px-2 py-1.5">{item.quantity_prescribed}</td>
                                          <td className="px-2 py-1.5">
                                            <span className={clsx(item.quantity_dispensed >= item.quantity_prescribed ? 'text-green-600' : 'text-orange-500')}>
                                              {item.quantity_dispensed}
                                            </span>
                                          </td>
                                          <td className="px-2 py-1.5 text-gray-500">{item.dosage_instructions ?? '—'}</td>
                                          <td className="px-2 py-1.5">
                                            <span className={clsx('badge text-xs', item.status === 'dispensed' ? 'badge-success' : 'badge-gray')}>
                                              {t(item.status)}
                                            </span>
                                          </td>
                                        </tr>
                                      ))}
                                    </tbody>
                                  </table>
                                  </div>
                                </td>
                              </tr>
                            )}
                          </Fragment>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
                <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                  {rxList.length === 0 ? (
                    <div className="px-4 py-12 text-center text-gray-400">{t('no_data')}</div>
                  ) : rxList.map((rx) => {
                    const isExpanded = expandedRxId === rx.id
                    return (
                      <div key={rx.id} className="p-4 space-y-2">
                        <div className="flex items-start justify-between gap-2">
                          <div>
                            <p className="font-mono text-xs text-primary-600">{rx.prescription_number}</p>
                            <p className="font-medium text-gray-900 dark:text-white mt-0.5">{rx.patient_name}</p>
                            {rx.patient_phone && <p className="text-xs text-gray-400">{rx.patient_phone}</p>}
                          </div>
                          <span className={clsx('badge capitalize shrink-0', rxStatusBadge(rx.status))}>
                            {t(rx.status)}
                          </span>
                        </div>
                        <div className="text-xs text-gray-500 space-y-0.5">
                          <p>{rx.doctor_name}{rx.clinic_name ? ` · ${rx.clinic_name}` : ''}</p>
                          <p>{rx.issued_date} · {rx.items_count ?? rx.items?.length ?? 0} {t('items_count')}</p>
                        </div>
                        <div className="flex gap-2">
                          {rx.status !== 'fully_dispensed' && (
                            <button onClick={() => openDispense(rx)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 hover:bg-green-100 transition-colors font-medium">
                              <CheckCircle className="h-3 w-3" /> {isAr ? 'صرف' : 'Dispense'}
                            </button>
                          )}
                          {(rx.items?.length ?? 0) > 0 && (
                            <button onClick={() => setExpandedRxId(isExpanded ? null : rx.id)} className="flex items-center gap-1 px-3 py-1.5 text-xs rounded-lg bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 transition-colors font-medium">
                              {isExpanded ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                              {isAr ? 'التفاصيل' : 'Details'}
                            </button>
                          )}
                        </div>
                        {isExpanded && (rx.items?.length ?? 0) > 0 && (
                          <div className="mt-2 border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                            {rx.items!.map((item) => (
                              <div key={item.id} className="px-3 py-2 text-xs space-y-0.5">
                                <div className="flex items-center justify-between gap-2">
                                  <span className="font-medium text-gray-900 dark:text-white">{item.medicine_name ?? '—'}</span>
                                  <span className={clsx('badge text-xs', item.status === 'dispensed' ? 'badge-success' : 'badge-gray')}>{t(item.status)}</span>
                                </div>
                                <p className="text-gray-500">
                                  {t('quantity_prescribed')}: {item.quantity_prescribed} · {t('quantity_dispensed')}: <span className={clsx(item.quantity_dispensed >= item.quantity_prescribed ? 'text-green-600' : 'text-orange-500')}>{item.quantity_dispensed}</span>
                                </p>
                                {item.dosage_instructions && <p className="text-gray-400">{item.dosage_instructions}</p>}
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* ══════ MODALS ══════ */}

      {/* Medicine Add/Edit Modal */}
      <Modal
        open={medModal !== null}
        onClose={() => setMedModal(null)}
        title={medModal === 'add' ? t('add_medicine') : t('edit_medicine')}
        size="lg"
        footer={<>
          <button onClick={() => setMedModal(null)} className="btn btn-secondary">{t('cancel')}</button>
          <button onClick={handleSaveMed} disabled={saveMed.isPending} className="btn btn-primary">
            {saveMed.isPending ? t('saving') : t('save_changes')}
          </button>
        </>}
      >
        <div className="grid grid-cols-2 gap-4">
          <div className="col-span-2 grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('medicine_name_ar')} *</label>
              <input value={medForm.name_ar} onChange={(e) => setMedForm((p) => ({ ...p, name_ar: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('medicine_name_en')}</label>
              <input value={medForm.name_en} onChange={(e) => setMedForm((p) => ({ ...p, name_en: e.target.value }))} className="input w-full" />
            </div>
          </div>
          <div>
            <label className="label">{t('generic_name')}</label>
            <input value={medForm.generic_name} onChange={(e) => setMedForm((p) => ({ ...p, generic_name: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{t('manufacturer')}</label>
            <input value={medForm.manufacturer} onChange={(e) => setMedForm((p) => ({ ...p, manufacturer: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{t('category')}</label>
            <select value={medForm.category} onChange={(e) => setMedForm((p) => ({ ...p, category: e.target.value }))} className="input w-full">
              {CATEGORIES.map((c) => <option key={c} value={c}>{t(c)}</option>)}
            </select>
          </div>
          <div>
            <label className="label">{t('dosage_form')}</label>
            <select value={medForm.dosage_form} onChange={(e) => setMedForm((p) => ({ ...p, dosage_form: e.target.value }))} className="input w-full">
              {DOSAGE_FORMS.map((f) => <option key={f} value={f}>{t(f)}</option>)}
            </select>
          </div>
          <div>
            <label className="label">{t('strength')}</label>
            <input value={medForm.strength} onChange={(e) => setMedForm((p) => ({ ...p, strength: e.target.value }))} placeholder="500mg" className="input w-full" />
          </div>
          <div>
            <label className="label">{t('barcode')}</label>
            <input value={medForm.barcode} onChange={(e) => setMedForm((p) => ({ ...p, barcode: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{t('selling_price')}</label>
            <input type="number" step="0.01" value={medForm.selling_price} onChange={(e) => setMedForm((p) => ({ ...p, selling_price: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{t('cost_price')}</label>
            <input type="number" step="0.01" value={medForm.cost_price} onChange={(e) => setMedForm((p) => ({ ...p, cost_price: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{t('reorder_level')}</label>
            <input type="number" min="0" value={medForm.reorder_level} onChange={(e) => setMedForm((p) => ({ ...p, reorder_level: parseInt(e.target.value) || 0 }))} className="input w-full" />
          </div>
          <div className="flex flex-col gap-3 justify-center">
            <label className="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" checked={medForm.requires_prescription} onChange={(e) => setMedForm((p) => ({ ...p, requires_prescription: e.target.checked }))} className="h-4 w-4 rounded" />
              <span className="text-sm">{t('requires_prescription')}</span>
            </label>
            <label className="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" checked={medForm.controlled_drug} onChange={(e) => setMedForm((p) => ({ ...p, controlled_drug: e.target.checked }))} className="h-4 w-4 rounded" />
              <span className="text-sm text-red-600">{t('controlled_drug')}</span>
            </label>
            <label className="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" checked={medForm.is_active} onChange={(e) => setMedForm((p) => ({ ...p, is_active: e.target.checked }))} className="h-4 w-4 rounded" />
              <span className="text-sm">{t('active')}</span>
            </label>
          </div>
          <div className="col-span-2">
            <label className="label">{t('notes')}</label>
            <textarea value={medForm.notes} onChange={(e) => setMedForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" rows={2} />
          </div>
        </div>
      </Modal>

      {/* Batch Modal */}
      <Modal
        open={batchModal}
        onClose={() => setBatchModal(false)}
        title={t('add_batch')}
        size="md"
        footer={<>
          <button onClick={() => setBatchModal(false)} className="btn btn-secondary">{t('cancel')}</button>
          <button onClick={handleSaveBatch} disabled={saveBatch.isPending} className="btn btn-primary">
            {saveBatch.isPending ? t('saving') : t('save_changes')}
          </button>
        </>}
      >
        <div className="space-y-4">
          <div>
            <label className="label">{t('medicine_name')} *</label>
            <select value={batchForm.medicine_id} onChange={(e) => setBatchForm((p) => ({ ...p, medicine_id: e.target.value }))} className="input w-full">
              <option value="">{t('select')}</option>
              {allMeds.map((m) => <option key={m.id} value={m.id}>{m.name_ar}</option>)}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('lot_number')}</label>
              <input value={batchForm.lot_number} onChange={(e) => setBatchForm((p) => ({ ...p, lot_number: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('quantity')} *</label>
              <input type="number" min="1" value={batchForm.quantity} onChange={(e) => setBatchForm((p) => ({ ...p, quantity: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('expiry_date')} *</label>
              <input type="date" value={batchForm.expiry_date} onChange={(e) => setBatchForm((p) => ({ ...p, expiry_date: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('purchase_price')}</label>
              <input type="number" step="0.01" value={batchForm.purchase_price} onChange={(e) => setBatchForm((p) => ({ ...p, purchase_price: e.target.value }))} className="input w-full" />
            </div>
          </div>
          <div>
            <label className="label">{t('notes')}</label>
            <input value={batchForm.notes} onChange={(e) => setBatchForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" />
          </div>
        </div>
      </Modal>

      {/* Prescription Add Modal */}
      <Modal
        open={rxModal === 'add'}
        onClose={() => setRxModal(null)}
        title={t('add_prescription')}
        size="xl"
        footer={<>
          <button onClick={() => setRxModal(null)} className="btn btn-secondary">{t('cancel')}</button>
          <button onClick={handleSaveRx} disabled={saveRx.isPending} className="btn btn-primary">
            {saveRx.isPending ? t('saving') : t('save_changes')}
          </button>
        </>}
      >
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="label">{t('patient_name')} *</label>
              <input value={rxForm.patient_name} onChange={(e) => setRxForm((p) => ({ ...p, patient_name: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('patient_phone')}</label>
              <input value={rxForm.patient_phone} onChange={(e) => setRxForm((p) => ({ ...p, patient_phone: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('doctor_name')} *</label>
              <input value={rxForm.doctor_name} onChange={(e) => setRxForm((p) => ({ ...p, doctor_name: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('clinic_name')}</label>
              <input value={rxForm.clinic_name} onChange={(e) => setRxForm((p) => ({ ...p, clinic_name: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('issued_date')} *</label>
              <input type="date" value={rxForm.issued_date} onChange={(e) => setRxForm((p) => ({ ...p, issued_date: e.target.value }))} className="input w-full" />
            </div>
            <div>
              <label className="label">{t('expiry_date')}</label>
              <input type="date" value={rxForm.expiry_date} onChange={(e) => setRxForm((p) => ({ ...p, expiry_date: e.target.value }))} className="input w-full" />
            </div>
          </div>

          {/* Prescription Items */}
          <div className="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
            <div className="flex items-center justify-between">
              <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">{t('prescription_items')}</p>
              <button type="button" onClick={addRxItem} className="btn btn-secondary text-xs py-1 px-2 flex items-center gap-1">
                <Plus className="h-3 w-3" /> {t('add')}
              </button>
            </div>
            {rxItems.map((item, idx) => (
              <div key={idx} className="grid grid-cols-12 gap-2 items-end">
                <div className="col-span-5">
                  <select value={item.medicine_id} onChange={(e) => setRxItems((p) => p.map((r, i) => i === idx ? { ...r, medicine_id: e.target.value } : r))} className="input w-full text-sm">
                    <option value="">{t('select_medicine')}</option>
                    {allMeds.map((m) => <option key={m.id} value={m.id}>{m.name_ar}</option>)}
                  </select>
                </div>
                <div className="col-span-2">
                  <input type="number" min="1" placeholder={t('quantity')} value={item.quantity_prescribed}
                    onChange={(e) => setRxItems((p) => p.map((r, i) => i === idx ? { ...r, quantity_prescribed: e.target.value } : r))}
                    className="input w-full text-sm" />
                </div>
                <div className="col-span-4">
                  <input placeholder={t('dosage_instructions')} value={item.dosage_instructions}
                    onChange={(e) => setRxItems((p) => p.map((r, i) => i === idx ? { ...r, dosage_instructions: e.target.value } : r))}
                    className="input w-full text-sm" />
                </div>
                <div className="col-span-1">
                  {rxItems.length > 1 && (
                    <button onClick={() => removeRxItem(idx)} className="p-1 text-red-400 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div>
            <label className="label">{t('notes')}</label>
            <textarea value={rxForm.notes} onChange={(e) => setRxForm((p) => ({ ...p, notes: e.target.value }))} className="input w-full" rows={2} />
          </div>
        </div>
      </Modal>

      {/* Dispense Modal */}
      <Modal
        open={rxModal === 'dispense'}
        onClose={() => setRxModal(null)}
        title={t('dispense')}
        size="md"
        footer={<>
          <button onClick={() => setRxModal(null)} className="btn btn-secondary">{t('cancel')}</button>
          <button onClick={handleDispense} disabled={dispenseRx.isPending} className="btn btn-primary flex items-center gap-2">
            <CheckCircle className="h-4 w-4" />
            {dispenseRx.isPending ? t('saving') : t('dispense')}
          </button>
        </>}
      >
        {activeRx && (
          <div className="space-y-3">
            <div className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm space-y-1">
              <p><span className="text-gray-500">{t('patient_name')}:</span> <span className="font-medium">{activeRx.patient_name}</span></p>
              <p><span className="text-gray-500">{t('doctor_name')}:</span> <span className="font-medium">{activeRx.doctor_name}</span></p>
            </div>
            <div className="space-y-2">
              {activeRx.items?.map((item) => (
                <div key={item.id} className="flex items-center gap-3">
                  <div className="flex-1">
                    <p className="text-sm font-medium">{item.medicine_name}</p>
                    <p className="text-xs text-gray-400">{t('prescribed')}: {item.quantity_prescribed} {item.dosage_instructions && `— ${item.dosage_instructions}`}</p>
                  </div>
                  <div className="w-24">
                    <input
                      type="number" min="0" max={item.quantity_prescribed}
                      value={dispenseForm[item.id] ?? item.quantity_dispensed}
                      onChange={(e) => setDispenseForm((p) => ({ ...p, [item.id]: parseInt(e.target.value) || 0 }))}
                      className="input w-full text-sm text-center"
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </Modal>

      {/* Delete Medicine Confirm */}
      <ConfirmDialog
        open={!!delMed}
        title={t('confirm_delete')}
        message={`${t('delete_confirm_msg')} "${delMed?.name_ar}"?`}
        onConfirm={() => delMed && deleteMed.mutate(delMed.id)}
        onCancel={() => setDelMed(null)}
        loading={deleteMed.isPending}
      />
      {/* Delete Batch Confirm */}
      <ConfirmDialog
        open={!!delBatch}
        title={t('confirm_delete')}
        message={t('delete_confirm_msg')}
        onConfirm={() => delBatch && deleteBatch.mutate(delBatch.id)}
        onCancel={() => setDelBatch(null)}
        loading={deleteBatch.isPending}
      />
    </div>
  )
}
