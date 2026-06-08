import { useState, useEffect } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiGet, apiPost } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import { useUIStore } from '@/stores/uiStore'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { Settings, Save, Monitor, Sun, Moon, Info } from 'lucide-react'
import { clsx } from 'clsx'
import toast from 'react-hot-toast'
import { invokeTauri, isTauriApp } from '@/lib/tauri'

interface SettingValue { key: string; value: string; type?: string; label_ar?: string; label_en?: string }

export default function SettingsPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const { theme, setTheme, language, setLanguage } = useUIStore()
  const [activeGroup, setActiveGroup] = useState('store')
  const [form, setForm] = useState<Record<string, string>>({})
  const [sysInfo, setSysInfo] = useState<Record<string, string> | null>(null)

  const canManage = hasPermission('manage_settings')

  const groupMap: Record<string, string> = { store: 'general', sales: 'pos', receipt: 'printing', tax: 'tax' }
  const backendGroup = groupMap[activeGroup] ?? activeGroup

  const { data, isLoading } = useQuery({
    queryKey: ['settings', activeGroup],
    queryFn: () => apiGet<{ settings: Record<string, SettingValue> }>(`/settings/group/${backendGroup}`),
    staleTime: 60_000,
    enabled: activeGroup !== 'appearance' && activeGroup !== 'desktop',
  })

  useEffect(() => {
    if (data?.settings) {
      const vals: Record<string, string> = {}
      Object.values(data.settings).forEach((s) => { vals[s.key] = s.value })
      setForm(vals)
    }
  }, [data])

  useEffect(() => {
    if (activeGroup === 'desktop' && isTauriApp()) {
      invokeTauri<Record<string, string>>('get_system_info').then((info) => { if (info) setSysInfo(info) })
    }
  }, [activeGroup])

  const saveMutation = useMutation({
    mutationFn: (payload: object) => apiPost('/settings', payload),
    onSuccess: () => toast.success('Settings saved'),
    onError: () => toast.error('Failed to save settings'),
  })

  const handleSave = () => {
    const entries = Object.entries(form).map(([key, value]) => ({ key, value }))
    saveMutation.mutate({ settings: entries })
  }

  const groups = [
    { key: 'store', label: t('store_info') },
    { key: 'sales', label: t('sales') },
    { key: 'tax', label: t('tax') },
    { key: 'receipt', label: t('receipt') },
    { key: 'appearance', label: t('appearance') },
    { key: 'desktop', label: t('desktop') },
  ]

  const storeFields = [
    { key: 'store_name', label: t('store_name'), type: 'text' },
    { key: 'store_phone', label: t('store_phone'), type: 'text' },
    { key: 'store_address', label: t('store_address'), type: 'text' },
    { key: 'store_email', label: t('store_email'), type: 'email' },
    { key: 'currency', label: t('currency_symbol'), type: 'text' },
    { key: 'timezone', label: 'Timezone', type: 'text' },
  ]
  const salesFields = [
    { key: 'allow_negative_stock', label: 'Allow Negative Stock', type: 'checkbox' },
    { key: 'allow_cashier_price_change', label: 'Allow Cashier Price Change', type: 'checkbox' },
    { key: 'auto_print_receipt', label: t('auto_print'), type: 'checkbox' },
    { key: 'require_customer', label: 'Require Customer on Sale', type: 'checkbox' },
    { key: 'default_payment_method', label: t('payment_method'), type: 'select', options: ['cash', 'card', 'wallet'] },
  ]
  const taxFields = [
    { key: 'tax_rate', label: t('tax_rate'), type: 'number' },
    { key: 'tax_included', label: 'Tax Included in Price', type: 'checkbox' },
    { key: 'tax_name', label: t('tax_name'), type: 'text' },
    { key: 'tax_number', label: t('tax_number'), type: 'text' },
  ]
  const receiptFields = [
    { key: 'receipt_header', label: 'Receipt Header', type: 'text' },
    { key: 'receipt_footer', label: t('invoice_footer'), type: 'text' },
    { key: 'show_logo', label: 'Show Logo on Receipt', type: 'checkbox' },
    { key: 'show_cashier', label: 'Show Cashier Name', type: 'checkbox' },
  ]

  const fieldSets: Record<string, typeof storeFields> = {
    store: storeFields, sales: salesFields, tax: taxFields, receipt: receiptFields,
  }

  const fields = fieldSets[activeGroup] ?? []

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><Settings className="h-6 w-6 text-primary-500" /> {t('settings')}</h1>
        {canManage && activeGroup !== 'appearance' && activeGroup !== 'desktop' && (
          <button onClick={handleSave} disabled={saveMutation.isPending} className="btn btn-primary flex items-center gap-2">
            <Save className="h-4 w-4" />{saveMutation.isPending ? 'Saving…' : t('save_changes')}
          </button>
        )}
      </div>

      <div className="flex gap-6">
        {/* Groups list */}
        <div className="w-44 flex-shrink-0 space-y-1">
          {groups.map((g) => (
            <button key={g.key} onClick={() => setActiveGroup(g.key)}
              className={clsx('w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium transition-colors', activeGroup === g.key ? 'bg-primary-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white')}>
              {g.label}
            </button>
          ))}
        </div>

        {/* Settings content */}
        <div className="flex-1 card p-6">
          {activeGroup === 'appearance' ? (
            <div className="space-y-6">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('appearance')}</h2>

              <div className="space-y-3">
                <label className="label">{t('theme')}</label>
                <div className="grid grid-cols-3 gap-3">
                  {([
                    { key: 'light', label: t('light'), icon: Sun },
                    { key: 'dark', label: t('dark'), icon: Moon },
                    { key: 'system', label: t('system'), icon: Monitor },
                  ] as const).map(({ key, label, icon: Icon }) => (
                    <button key={key} onClick={() => setTheme(key)}
                      className={clsx('flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all text-sm font-medium', theme === key ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-300')}>
                      <Icon className="h-6 w-6" />{label}
                    </button>
                  ))}
                </div>
              </div>

              <div className="space-y-3">
                <label className="label">{t('language')}</label>
                <div className="grid grid-cols-2 gap-3 max-w-xs">
                  {([{ key: 'en', label: 'English', flag: '🇺🇸' }, { key: 'ar', label: 'العربية', flag: '🇸🇦' }] as const).map(({ key, label, flag }) => (
                    <button key={key} onClick={() => setLanguage(key)}
                      className={clsx('flex items-center gap-2 p-3 rounded-xl border-2 transition-all text-sm font-medium', language === key ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'border-gray-200 dark:border-gray-600 text-gray-600 hover:border-gray-300')}>
                      <span>{flag}</span>{label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : activeGroup === 'desktop' ? (
            <div className="space-y-4">
              <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2"><Info className="h-5 w-5" /> {t('desktop')}</h2>
              <div className="grid grid-cols-2 gap-4 text-sm">
                {[
                  { label: 'Platform', value: isTauriApp() ? 'Tauri Desktop' : 'Web Browser' },
                  { label: 'App Version', value: sysInfo?.app_version ?? '1.0.0' },
                  { label: 'OS', value: sysInfo?.os ?? navigator.platform },
                  { label: 'Architecture', value: sysInfo?.arch ?? '—' },
                  { label: 'Tauri', value: isTauriApp() ? 'Yes' : 'No' },
                  { label: 'Offline Support', value: 'Yes — IndexedDB / SQLite' },
                  { label: 'Barcode Scanner', value: 'USB + Camera (jsQR)' },
                  { label: 'Auto Updates', value: isTauriApp() ? 'Enabled' : 'N/A' },
                ].map(({ label, value }) => (
                  <div key={label} className="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                    <p className="text-xs text-gray-500">{label}</p>
                    <p className="font-semibold text-gray-900 dark:text-white mt-0.5">{value}</p>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <>
              {isLoading ? <div className="flex h-40 items-center justify-center"><LoadingSpinner /></div> : (
                <div className="space-y-4">
                  <h2 className="font-semibold text-gray-900 dark:text-white capitalize">{t('general_settings')}</h2>
                  <div className="grid grid-cols-1 gap-4 max-w-2xl">
                    {fields.map((field) => (
                      <div key={field.key}>
                        <label className="label">{field.label}</label>
                        {field.type === 'checkbox' ? (
                          <label className="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" checked={form[field.key] === 'true' || form[field.key] === '1'}
                              onChange={(e) => setForm((p) => ({ ...p, [field.key]: e.target.checked ? 'true' : 'false' }))}
                              className="h-5 w-5 rounded border-gray-300 text-primary-600" disabled={!canManage} />
                            <span className="text-sm text-gray-600 dark:text-gray-400">Enable {field.label}</span>
                          </label>
                        ) : field.type === 'select' && field.options ? (
                          <select value={form[field.key] ?? ''} onChange={(e) => setForm((p) => ({ ...p, [field.key]: e.target.value }))} className="input w-full" disabled={!canManage}>
                            {field.options.map((o) => <option key={o} value={o} className="capitalize">{o}</option>)}
                          </select>
                        ) : (
                          <input type={field.type} value={form[field.key] ?? ''} onChange={(e) => setForm((p) => ({ ...p, [field.key]: e.target.value }))}
                            className="input w-full" disabled={!canManage} />
                        )}
                      </div>
                    ))}
                    {fields.length === 0 && data?.settings && (
                      <div className="space-y-4">
                        {Object.values(data.settings).map((s) => (
                          <div key={s.key}>
                            <label className="label">{s.label_en ?? s.key.replace(/_/g, ' ')}</label>
                            <input value={form[s.key] ?? s.value} onChange={(e) => setForm((p) => ({ ...p, [s.key]: e.target.value }))}
                              className="input w-full" disabled={!canManage} />
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
