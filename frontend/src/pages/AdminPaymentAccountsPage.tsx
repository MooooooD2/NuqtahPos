import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet, apiPut } from '@/services/api'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import toast from 'react-hot-toast'
import { clsx } from 'clsx'
import { CreditCard, Save, ToggleLeft, ToggleRight } from 'lucide-react'

interface PaymentAccount {
  id: number
  method: string
  label: string
  account_number: string | null
  account_name: string | null
  notes: string | null
  is_active: boolean
  sort_order: number
}

const METHOD_ICONS: Record<string, string> = {
  whatsapp: '📱',
  bank: '🏦',
  wallet: '💳',
  cash: '💵',
}

export default function AdminPaymentAccountsPage() {
  const { i18n } = useTranslation('pos')
  const isAr = i18n.language === 'ar'
  const qc = useQueryClient()

  const [edits, setEdits] = useState<Record<number, Partial<PaymentAccount>>>({})
  const [saving, setSaving] = useState<Record<number, boolean>>({})

  const { data, isLoading } = useQuery({
    queryKey: ['admin-payment-accounts'],
    queryFn: () => apiGet<{ success: boolean; accounts: PaymentAccount[] }>('/admin/payment-accounts'),
    staleTime: 30_000,
  })

  const accounts = data?.accounts ?? []

  const updateMut = useMutation({
    mutationFn: ({ id, ...d }: Partial<PaymentAccount> & { id: number }) => apiPut(`/admin/payment-accounts/${id}`, d),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: ['admin-payment-accounts'] })
      setEdits((p) => { const n = { ...p }; delete n[id]; return n })
      setSaving((p) => ({ ...p, [id]: false }))
      toast.success(isAr ? 'تم الحفظ' : 'Saved')
    },
    onError: (_, { id }) => {
      setSaving((p) => ({ ...p, [id]: false }))
      toast.error(isAr ? 'خطأ في الحفظ' : 'Save failed')
    },
  })

  const getField = (acc: PaymentAccount, field: keyof PaymentAccount) =>
    edits[acc.id]?.[field] !== undefined ? edits[acc.id][field] : acc[field]

  const setField = (id: number, field: keyof PaymentAccount, value: string | boolean) =>
    setEdits((p) => ({ ...p, [id]: { ...p[id], [field]: value } }))

  const handleSave = (acc: PaymentAccount) => {
    setSaving((p) => ({ ...p, [acc.id]: true }))
    updateMut.mutate({ id: acc.id, ...edits[acc.id] })
  }

  const isDirty = (id: number) => Object.keys(edits[id] ?? {}).length > 0

  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <CreditCard className="h-6 w-6 text-primary-500" />
        {isAr ? 'وسائل الدفع' : 'Payment Methods'}
      </h1>
      <p className="text-sm text-gray-500">{isAr ? 'ضبط أرقام الحسابات التي تظهر للمستأجرين عند الاشتراك.' : 'Configure payment account details shown to tenants during subscription.'}</p>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {accounts.map((acc) => (
          <div key={acc.id} className={clsx('card p-5', !getField(acc, 'is_active') && 'opacity-60')}>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <span className="text-2xl">{METHOD_ICONS[acc.method] ?? '💳'}</span>
                <div>
                  <h3 className="font-semibold text-gray-900 dark:text-white">{acc.label}</h3>
                  <p className="text-xs text-gray-400 font-mono">{acc.method}</p>
                </div>
              </div>
              <button
                onClick={() => {
                  const newVal = !getField(acc, 'is_active')
                  setField(acc.id, 'is_active', newVal as boolean)
                }}
              >
                {getField(acc, 'is_active')
                  ? <ToggleRight className="h-6 w-6 text-green-500" />
                  : <ToggleLeft className="h-6 w-6 text-gray-400" />}
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="label">{isAr ? 'رقم الحساب / الهاتف' : 'Account Number / Phone'}</label>
                <input
                  value={(getField(acc, 'account_number') as string) ?? ''}
                  onChange={(e) => setField(acc.id, 'account_number', e.target.value)}
                  className="input w-full"
                  dir="ltr"
                  placeholder="+966..."
                />
              </div>
              <div>
                <label className="label">{isAr ? 'اسم صاحب الحساب' : 'Account Holder Name'}</label>
                <input
                  value={(getField(acc, 'account_name') as string) ?? ''}
                  onChange={(e) => setField(acc.id, 'account_name', e.target.value)}
                  className="input w-full"
                />
              </div>
              <div>
                <label className="label">{isAr ? 'ملاحظات / تعليمات' : 'Notes / Instructions'}</label>
                <textarea
                  value={(getField(acc, 'notes') as string) ?? ''}
                  onChange={(e) => setField(acc.id, 'notes', e.target.value)}
                  className="input w-full h-20 resize-none"
                />
              </div>
            </div>

            {isDirty(acc.id) && (
              <div className="mt-4 flex justify-end">
                <button
                  onClick={() => handleSave(acc)}
                  disabled={saving[acc.id]}
                  className="btn btn-primary flex items-center gap-2 text-sm"
                >
                  {saving[acc.id] ? <LoadingSpinner size="sm" /> : <Save className="h-4 w-4" />}
                  {isAr ? 'حفظ' : 'Save'}
                </button>
              </div>
            )}
          </div>
        ))}

        {accounts.length === 0 && (
          <div className="md:col-span-2 card p-12 text-center text-gray-400">
            <CreditCard className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>{isAr ? 'لا توجد وسائل دفع مضافة في قاعدة البيانات' : 'No payment accounts found in database'}</p>
          </div>
        )}
      </div>
    </div>
  )
}
