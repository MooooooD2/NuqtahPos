import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet, apiPost, apiPatch } from '@/services/api'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import Modal from '@/components/common/Modal'
import toast from 'react-hot-toast'
import { clsx } from 'clsx'
import { KeyRound, Plus, Ban, Copy, Laptop, Eye, EyeOff } from 'lucide-react'

interface License {
  id: number
  tenant_id: string | null
  key_prefix: string
  device_id: string | null
  device_name: string | null
  status: 'pending' | 'active' | 'revoked' | 'expired'
  activated_at: string | null
  expires_at: string | null
  last_validated_at: string | null
}

const STATUS_BADGE: Record<License['status'], string> = {
  pending: 'badge-info',
  active: 'badge-success',
  revoked: 'badge-danger',
  expired: 'badge-warning',
}

const STATUS_LABEL_AR: Record<License['status'], string> = {
  pending: 'بانتظار التفعيل',
  active: 'نشط',
  revoked: 'مُلغى',
  expired: 'منتهي',
}

export default function AdminLicensesPage() {
  const { i18n } = useTranslation('pos')
  const isAr = i18n.language === 'ar'
  const qc = useQueryClient()

  const [showCreate, setShowCreate] = useState(false)
  const [tenantId, setTenantId] = useState('')
  const [expiresAt, setExpiresAt] = useState('')
  const [generatedKey, setGeneratedKey] = useState<string | null>(null)
  const [showKey, setShowKey] = useState(false)

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-licenses'],
    queryFn: () => apiGet<{ licenses: License[] }>('/admin/licenses'),
    staleTime: 10_000,
  })

  const licenses = data?.licenses ?? []

  const createMut = useMutation({
    mutationFn: () => apiPost<{ plain_key: string }>('/admin/licenses', {
      tenant_id: tenantId || null,
      expires_at: expiresAt || null,
    }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['admin-licenses'] })
      setGeneratedKey(res.plain_key)
    },
    onError: () => toast.error(isAr ? 'فشل إنشاء الترخيص' : 'Failed to create license'),
  })

  const revokeMut = useMutation({
    mutationFn: (id: number) => apiPatch(`/admin/licenses/${id}/revoke`, {}),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-licenses'] })
      toast.success(isAr ? 'تم إلغاء الترخيص' : 'License revoked')
    },
    onError: () => toast.error(isAr ? 'خطأ' : 'Error'),
  })

  const openCreate = () => {
    setTenantId('')
    setExpiresAt('')
    setGeneratedKey(null)
    setShowKey(false)
    setShowCreate(true)
  }

  const copyKey = (key: string) => {
    navigator.clipboard.writeText(key)
    toast.success(isAr ? 'تم نسخ المفتاح' : 'Key copied')
  }

  if (isLoading) return <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /></div>
  if (error) return (
    <div className="card p-8 text-center">
      <p className="text-red-500 font-medium">{isAr ? 'خطأ في تحميل التراخيص' : 'Failed to load licenses'}</p>
    </div>
  )

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <KeyRound className="h-6 w-6 text-primary-500" />
          {isAr ? 'تراخيص النسخة المكتبية' : 'Desktop Licenses'}
        </h1>
        <button onClick={openCreate} className="btn btn-primary flex items-center gap-2">
          <Plus className="h-4 w-4" /> {isAr ? 'إصدار مفتاح جديد' : 'Issue New Key'}
        </button>
      </div>

      <div className="card overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-gray-100 dark:border-gray-700 text-start text-xs text-gray-400 uppercase">
              <th className="px-4 py-3 text-start">{isAr ? 'المفتاح' : 'Key'}</th>
              <th className="px-4 py-3 text-start">{isAr ? 'المتجر' : 'Tenant'}</th>
              <th className="px-4 py-3 text-start">{isAr ? 'الجهاز' : 'Device'}</th>
              <th className="px-4 py-3 text-start">{isAr ? 'الحالة' : 'Status'}</th>
              <th className="px-4 py-3 text-start">{isAr ? 'تاريخ الانتهاء' : 'Expires'}</th>
              <th className="px-4 py-3 text-start">{isAr ? 'آخر تحقق' : 'Last Validated'}</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {licenses.map((l) => (
              <tr key={l.id} className="border-b border-gray-50 dark:border-gray-700/50">
                <td className="px-4 py-3 font-mono text-xs">{l.key_prefix}…</td>
                <td className="px-4 py-3 text-xs">{l.tenant_id ?? <span className="text-gray-400">—</span>}</td>
                <td className="px-4 py-3 text-xs">
                  {l.device_name
                    ? <span className="flex items-center gap-1"><Laptop className="h-3.5 w-3.5 text-gray-400" />{l.device_name}</span>
                    : <span className="text-gray-400">{isAr ? 'لم يُفعّل' : 'Not activated'}</span>}
                </td>
                <td className="px-4 py-3">
                  <span className={clsx('badge', STATUS_BADGE[l.status])}>
                    {isAr ? STATUS_LABEL_AR[l.status] : l.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-xs text-gray-500">{l.expires_at ? new Date(l.expires_at).toLocaleDateString() : (isAr ? 'دائم' : 'Perpetual')}</td>
                <td className="px-4 py-3 text-xs text-gray-500">{l.last_validated_at ? new Date(l.last_validated_at).toLocaleString() : '—'}</td>
                <td className="px-4 py-3 text-end">
                  {l.status !== 'revoked' && (
                    <button
                      onClick={() => { if (confirm(isAr ? 'إلغاء هذا الترخيص؟' : 'Revoke this license?')) revokeMut.mutate(l.id) }}
                      disabled={revokeMut.isPending}
                      className="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-600"
                      title={isAr ? 'إلغاء' : 'Revoke'}
                    >
                      <Ban className="h-3.5 w-3.5 text-red-500" />
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {licenses.length === 0 && (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-gray-400">
                  {isAr ? 'لا توجد تراخيص بعد' : 'No licenses yet'}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={showCreate} onClose={() => setShowCreate(false)} title={isAr ? 'إصدار مفتاح ترخيص جديد' : 'Issue New License Key'}>
        {generatedKey ? (
          <div className="space-y-4">
            <p className="text-sm text-gray-500">
              {isAr
                ? 'هذا المفتاح يظهر مرة واحدة فقط — احفظه أو انسخه الآن وسلّمه للعميل.'
                : 'This key is shown only once — copy it now and hand it to the customer.'}
            </p>
            <div className="flex items-center gap-2">
              <code className="flex-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-2 font-mono text-sm tracking-wider select-all">
                {showKey ? generatedKey : generatedKey.replace(/[^-]/g, '•')}
              </code>
              <button onClick={() => setShowKey(v => !v)} className="btn btn-secondary p-2" title={showKey ? 'إخفاء' : 'إظهار'}>
                {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
              <button onClick={() => copyKey(generatedKey)} className="btn btn-secondary p-2">
                <Copy className="h-4 w-4" />
              </button>
            </div>
            <div className="flex justify-end">
              <button onClick={() => setShowCreate(false)} className="btn btn-primary">
                {isAr ? 'تم' : 'Done'}
              </button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div>
              <label className="label">{isAr ? 'معرف المتجر (اختياري)' : 'Tenant ID (optional)'}</label>
              <input value={tenantId} onChange={(e) => setTenantId(e.target.value)} className="input w-full" dir="ltr" placeholder={isAr ? 'اتركه فارغًا إذا غير معروف بعد' : 'Leave blank if unknown yet'} />
            </div>
            <div>
              <label className="label">{isAr ? 'تاريخ الانتهاء (اختياري)' : 'Expiry Date (optional)'}</label>
              <input type="date" value={expiresAt} onChange={(e) => setExpiresAt(e.target.value)} className="input w-full" />
              <p className="text-xs text-gray-400 mt-1">{isAr ? 'اتركه فارغًا لترخيص دائم' : 'Leave blank for a perpetual license'}</p>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setShowCreate(false)} className="btn btn-secondary">{isAr ? 'إلغاء' : 'Cancel'}</button>
              <button onClick={() => createMut.mutate()} disabled={createMut.isPending} className="btn btn-primary">
                {createMut.isPending ? <LoadingSpinner size="sm" /> : (isAr ? 'إصدار' : 'Issue')}
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
