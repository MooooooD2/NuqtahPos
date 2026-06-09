import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet, apiPost, apiPut, apiPatch, apiDelete } from '@/services/api'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import Modal from '@/components/common/Modal'
import toast from 'react-hot-toast'
import { clsx } from 'clsx'
import { Tag, Plus, ToggleLeft, ToggleRight, Trash2, Pencil, Check } from 'lucide-react'

interface Plan {
  id: string
  name: string
  monthly_price: number
  annual_price: number | null
  trial_days: number
  max_users: number | null
  max_products: number | null
  features: string[]
  feature_flags: string[]
  sort_order: number
  is_active: boolean
}

interface Module {
  ar: string
  en: string
  icon: string
  group: string
}

interface PlansData {
  plans: Plan[]
  tenant_counts: Record<string, number>
  all_modules: Record<string, Module>
  module_groups: Record<string, { ar: string; en: string }>
}

const emptyPlan = (): Omit<Plan, 'is_active'> => ({
  id: '',
  name: '',
  monthly_price: 0,
  annual_price: null,
  trial_days: 14,
  max_users: null,
  max_products: null,
  features: [],
  feature_flags: [],
  sort_order: 0,
})

export default function AdminPlansPage() {
  const { i18n } = useTranslation('pos')
  const isAr = i18n.language === 'ar'
  const qc = useQueryClient()

  const [showModal, setShowModal] = useState(false)
  const [editPlan, setEditPlan] = useState<Plan | null>(null)
  const [form, setForm] = useState(emptyPlan())
  const [featureInput, setFeatureInput] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['admin-plans'],
    queryFn: () => apiGet<PlansData & { success: boolean }>('/admin/plans'),
    staleTime: 30_000,
  })

  const plans = data?.plans ?? []
  const tenantCounts = data?.tenant_counts ?? {}
  const allModules = data?.all_modules ?? {}
  const moduleGroups = data?.module_groups ?? {}

  const openCreate = () => {
    setEditPlan(null)
    setForm(emptyPlan())
    setFeatureInput('')
    setShowModal(true)
  }

  const openEdit = (p: Plan) => {
    setEditPlan(p)
    setForm({ ...p })
    setFeatureInput('')
    setShowModal(true)
  }

  const toggleFlag = (key: string) => {
    setForm((prev) => ({
      ...prev,
      feature_flags: prev.feature_flags.includes(key)
        ? prev.feature_flags.filter((f) => f !== key)
        : [...prev.feature_flags, key],
    }))
  }

  const addFeature = () => {
    if (!featureInput.trim()) return
    setForm((prev) => ({ ...prev, features: [...prev.features, featureInput.trim()] }))
    setFeatureInput('')
  }

  const createMut = useMutation({
    mutationFn: (d: typeof form) => apiPost('/admin/plans', d),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-plans'] }); setShowModal(false); toast.success(isAr ? 'تم إنشاء الخطة' : 'Plan created') },
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const updateMut = useMutation({
    mutationFn: ({ id, ...d }: typeof form & { id: string }) => apiPut(`/admin/plans/${id}`, d),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-plans'] }); setShowModal(false); toast.success(isAr ? 'تم التحديث' : 'Updated') },
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const toggleMut = useMutation({
    mutationFn: (id: string) => apiPatch(`/admin/plans/${id}/toggle`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-plans'] }),
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const deleteMut = useMutation({
    mutationFn: (id: string) => apiDelete(`/admin/plans/${id}`),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-plans'] }); toast.success(isAr ? 'تم الحذف' : 'Deleted') },
    onError: (e: Error) => toast.error(e.message || (isAr ? 'خطأ في الحذف — المتجر مرتبط بالخطة' : 'Cannot delete — plan has tenants')),
  })

  const handleSubmit = () => {
    if (!form.name) { toast.error(isAr ? 'أدخل اسم الخطة' : 'Enter plan name'); return }
    if (editPlan) {
      updateMut.mutate({ ...form, id: editPlan.id })
    } else {
      if (!form.id) { toast.error(isAr ? 'أدخل معرف الخطة' : 'Enter plan ID'); return }
      createMut.mutate(form)
    }
  }

  const groupedModules = Object.entries(allModules).reduce<Record<string, [string, Module][]>>((acc, entry) => {
    const [key, mod] = entry
    const g = mod.group
    if (!acc[g]) acc[g] = []
    acc[g].push([key, mod])
    return acc
  }, {})

  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Tag className="h-6 w-6 text-primary-500" />
          {isAr ? 'الخطط والأسعار' : 'Plans & Pricing'}
        </h1>
        <button onClick={openCreate} className="btn btn-primary flex items-center gap-2">
          <Plus className="h-4 w-4" /> {isAr ? 'خطة جديدة' : 'New Plan'}
        </button>
      </div>

      {/* Plans grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {plans.map((plan) => (
          <div key={plan.id} className={clsx('card p-5', !plan.is_active && 'opacity-60')}>
            <div className="flex items-start justify-between mb-3">
              <div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">{plan.name}</h3>
                <p className="text-xs text-gray-400 font-mono mt-0.5">{plan.id}</p>
              </div>
              <div className="flex items-center gap-1">
                <button onClick={() => openEdit(plan)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600">
                  <Pencil className="h-3.5 w-3.5 text-gray-500" />
                </button>
                <button onClick={() => toggleMut.mutate(plan.id)} className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600">
                  {plan.is_active
                    ? <ToggleRight className="h-4 w-4 text-green-500" />
                    : <ToggleLeft className="h-4 w-4 text-gray-400" />}
                </button>
                <button
                  onClick={() => { if (confirm(isAr ? 'حذف الخطة؟' : 'Delete plan?')) deleteMut.mutate(plan.id) }}
                  disabled={deleteMut.isPending}
                  className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600"
                >
                  <Trash2 className="h-3.5 w-3.5 text-red-500" />
                </button>
              </div>
            </div>

            <div className="flex items-baseline gap-1 mb-4">
              <span className="text-3xl font-bold text-gray-900 dark:text-white">${plan.monthly_price}</span>
              <span className="text-sm text-gray-400">/mo</span>
              {plan.annual_price && (
                <span className="text-xs text-green-500 ms-2">${plan.annual_price}/yr</span>
              )}
            </div>

            <div className="flex flex-wrap gap-2 mb-3 text-xs text-gray-500">
              <span>{plan.trial_days}d trial</span>
              {plan.max_users && <span>· {plan.max_users} users</span>}
              {plan.max_products && <span>· {plan.max_products} products</span>}
              <span className="ms-auto font-semibold text-blue-500">{tenantCounts[plan.id] ?? 0} stores</span>
            </div>

            {plan.feature_flags.length > 0 && (
              <div className="flex flex-wrap gap-1 mt-2">
                {plan.feature_flags.slice(0, 8).map((f) => (
                  <span key={f} className="inline-flex items-center gap-1 text-xs bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 rounded px-1.5 py-0.5">
                    <Check className="h-2.5 w-2.5" />{allModules[f]?.[isAr ? 'ar' : 'en'] ?? f}
                  </span>
                ))}
                {plan.feature_flags.length > 8 && (
                  <span className="text-xs text-gray-400">+{plan.feature_flags.length - 8} more</span>
                )}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Create/Edit modal */}
      <Modal open={showModal} onClose={() => setShowModal(false)} title={editPlan ? (isAr ? 'تعديل الخطة' : 'Edit Plan') : (isAr ? 'خطة جديدة' : 'New Plan')} size="xl">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {!editPlan && (
            <div>
              <label className="label">ID (slug)</label>
              <input value={form.id} onChange={(e) => setForm((p) => ({ ...p, id: e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '') }))} className="input w-full" dir="ltr" placeholder="basic, pro, enterprise..." />
            </div>
          )}
          <div className={editPlan ? 'md:col-span-2' : ''}>
            <label className="label">{isAr ? 'اسم الخطة' : 'Plan Name'}</label>
            <input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} className="input w-full" />
          </div>
          <div>
            <label className="label">{isAr ? 'السعر الشهري ($)' : 'Monthly Price ($)'}</label>
            <input type="number" value={form.monthly_price} onChange={(e) => setForm((p) => ({ ...p, monthly_price: +e.target.value }))} className="input w-full" min={0} />
          </div>
          <div>
            <label className="label">{isAr ? 'السعر السنوي ($)' : 'Annual Price ($)'}</label>
            <input type="number" value={form.annual_price ?? ''} onChange={(e) => setForm((p) => ({ ...p, annual_price: e.target.value ? +e.target.value : null }))} className="input w-full" min={0} />
          </div>
          <div>
            <label className="label">{isAr ? 'أيام التجربة' : 'Trial Days'}</label>
            <input type="number" value={form.trial_days} onChange={(e) => setForm((p) => ({ ...p, trial_days: +e.target.value }))} className="input w-full" min={0} />
          </div>
          <div>
            <label className="label">{isAr ? 'الترتيب' : 'Sort Order'}</label>
            <input type="number" value={form.sort_order} onChange={(e) => setForm((p) => ({ ...p, sort_order: +e.target.value }))} className="input w-full" min={0} />
          </div>
          <div>
            <label className="label">{isAr ? 'حد المستخدمين' : 'Max Users'}</label>
            <input type="number" value={form.max_users ?? ''} onChange={(e) => setForm((p) => ({ ...p, max_users: e.target.value ? +e.target.value : null }))} className="input w-full" min={1} placeholder={isAr ? 'غير محدود' : 'Unlimited'} />
          </div>
          <div>
            <label className="label">{isAr ? 'حد المنتجات' : 'Max Products'}</label>
            <input type="number" value={form.max_products ?? ''} onChange={(e) => setForm((p) => ({ ...p, max_products: e.target.value ? +e.target.value : null }))} className="input w-full" min={1} placeholder={isAr ? 'غير محدود' : 'Unlimited'} />
          </div>

          {/* Features text bullets */}
          <div className="md:col-span-2">
            <label className="label">{isAr ? 'ميزات التسويق (نقاط)' : 'Marketing Features (bullets)'}</label>
            <div className="flex gap-2 mb-2">
              <input value={featureInput} onChange={(e) => setFeatureInput(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addFeature())} className="input flex-1" placeholder={isAr ? 'أضف ميزة...' : 'Add feature...'} />
              <button onClick={addFeature} className="btn btn-secondary">{isAr ? 'إضافة' : 'Add'}</button>
            </div>
            <div className="flex flex-wrap gap-1">
              {form.features.map((f, i) => (
                <span key={i} className="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded px-2 py-1 text-xs">
                  {f}
                  <button onClick={() => setForm((p) => ({ ...p, features: p.features.filter((_, j) => j !== i) }))} className="text-red-400 hover:text-red-600 ms-1">×</button>
                </span>
              ))}
            </div>
          </div>

          {/* Module flags */}
          <div className="md:col-span-2">
            <label className="label mb-2">{isAr ? 'الوحدات المتاحة' : 'Available Modules'}</label>
            <div className="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-3 space-y-4">
              {Object.entries(groupedModules).map(([group, modules]) => (
                <div key={group}>
                  <p className="text-xs font-semibold text-gray-400 uppercase mb-2">
                    {moduleGroups[group]?.[isAr ? 'ar' : 'en'] ?? group}
                  </p>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-1">
                    {modules.map(([key, mod]) => (
                      <label key={key} className="flex items-center gap-2 cursor-pointer p-1.5 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                        <input
                          type="checkbox"
                          checked={form.feature_flags.includes(key)}
                          onChange={() => toggleFlag(key)}
                          className="rounded"
                        />
                        <span className="text-xs text-gray-700 dark:text-gray-300">{mod[isAr ? 'ar' : 'en']}</span>
                      </label>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="flex justify-end gap-2 pt-4">
          <button onClick={() => setShowModal(false)} className="btn btn-secondary">{isAr ? 'إلغاء' : 'Cancel'}</button>
          <button onClick={handleSubmit} disabled={createMut.isPending || updateMut.isPending} className="btn btn-primary">
            {(createMut.isPending || updateMut.isPending) ? <LoadingSpinner size="sm" /> : (isAr ? 'حفظ' : 'Save')}
          </button>
        </div>
      </Modal>
    </div>
  )
}
