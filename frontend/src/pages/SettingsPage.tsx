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
type FieldDef = { key: string; label: string; type: string; options?: string[]; hint?: string; section?: string }

export default function SettingsPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const { theme, setTheme, language, setLanguage } = useUIStore()
  const [activeGroup, setActiveGroup] = useState('store')
  const [form, setForm] = useState<Record<string, string>>({})
  const [sysInfo, setSysInfo] = useState<Record<string, string> | null>(null)

  const canManage = hasPermission('manage_settings')

  const groupMap: Record<string, string> = {
    store: 'general', tax: 'tax', invoice: 'invoice', pos: 'pos',
    accounting: 'accounting', printing: 'printing', loyalty: 'loyalty', inventory: 'inventory',
  }
  const backendGroup = groupMap[activeGroup]
  const isLocalGroup = !backendGroup

  const { data, isLoading } = useQuery({
    queryKey: ['settings', activeGroup],
    queryFn: () => apiGet<{ settings: Record<string, SettingValue> }>(`/settings/group/${backendGroup}`),
    staleTime: 60_000,
    enabled: !isLocalGroup,
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
    onSuccess: () => toast.success(t('saved_success')),
    onError: () => toast.error(t('save_failed')),
  })

  const handleSave = () => {
    const entries = Object.entries(form).map(([key, value]) => ({ key, value }))
    saveMutation.mutate({ settings: entries })
  }

  const groups = [
    { key: 'store',      label: t('store_info') },
    { key: 'tax',        label: t('tax_settings') },
    { key: 'invoice',    label: t('invoice_settings') },
    { key: 'pos',        label: t('pos_settings') },
    { key: 'accounting', label: t('accounting_settings') },
    { key: 'printing',   label: t('printing_settings') },
    { key: 'loyalty',    label: t('loyalty_settings') },
    { key: 'inventory',  label: t('inventory_settings') },
    { key: 'appearance', label: t('appearance') },
    { key: 'desktop',    label: t('desktop') },
  ]

  const fieldSets: Record<string, FieldDef[]> = {
    store: [
      { key: 'store_name',       label: t('store_name'),       type: 'text' },
      { key: 'store_phone',      label: t('store_phone'),      type: 'text' },
      { key: 'store_address',    label: t('store_address'),    type: 'text' },
      { key: 'store_email',      label: t('store_email'),      type: 'email' },
      { key: 'currency',         label: t('currency_code'),    type: 'text' },
      { key: 'currency_symbol',  label: t('currency_symbol'),  type: 'text' },
      { key: 'default_language', label: t('default_language'), type: 'select', options: ['ar', 'en'] },
    ],
    tax: [
      { key: 'tax_enabled',   label: t('tax_enabled'),  type: 'checkbox' },
      { key: 'tax_rate',      label: t('tax_rate'),     type: 'number' },
      { key: 'tax_name_ar',   label: t('tax_name_ar'),  type: 'text' },
      { key: 'tax_name_en',   label: t('tax_name_en'),  type: 'text' },
      { key: 'tax_inclusive', label: t('tax_included'), type: 'checkbox' },
      { key: 'tax_number',    label: t('tax_number'),   type: 'text' },
    ],
    invoice: [
      { key: 'invoice_prefix',   label: t('invoice_prefix'),   type: 'text' },
      { key: 'invoice_footer',   label: t('invoice_footer'),   type: 'text' },
      { key: 'show_tax_invoice', label: t('show_tax_invoice'), type: 'checkbox' },
      { key: 'auto_print',       label: t('auto_print'),       type: 'checkbox' },
    ],
    pos: [
      { key: 'pos_sound',                  label: t('pos_sound'),                  type: 'checkbox' },
      { key: 'low_stock_alert',            label: t('low_stock_alert'),            type: 'checkbox' },
      { key: 'allow_negative_stock',       label: t('allow_negative_stock'),       type: 'checkbox' },
      { key: 'default_payment',            label: t('default_payment'),            type: 'select', options: ['cash', 'card', 'wallet'] },
      { key: 'max_discount_percent',       label: t('max_discount_percent'),       type: 'number' },
      { key: 'allow_cashier_price_change', label: t('allow_cashier_price_change'), type: 'checkbox' },
    ],
    accounting: [
      { key: 'max_daily_withdrawal', label: t('max_daily_withdrawal'), type: 'number', hint: t('zero_means_unlimited'), section: 'treasury_limits' },
      { key: 'min_cash_balance',     label: t('min_cash_balance'),     type: 'number', hint: t('zero_means_no_alert'),   section: 'treasury_limits' },
      { key: 'cash_account_code',    label: t('cash_account_code'),    type: 'text',   hint: t('example_1001'),          section: 'accounting_link' },
      { key: 'revenue_account_code', label: t('revenue_account_code'), type: 'text',   hint: t('example_4001'),          section: 'accounting_link' },
      { key: 'profit_margin_target', label: t('profit_margin_target'), type: 'number', section: 'profitability' },
    ],
    printing: [
      { key: 'print_on_sale',           label: t('print_on_sale'),           type: 'checkbox', section: 'auto_trigger' },
      { key: 'print_on_return',         label: t('print_on_return'),         type: 'checkbox', section: 'auto_trigger' },
      { key: 'print_on_shift_close',    label: t('print_on_shift_close'),    type: 'checkbox', section: 'auto_trigger' },
      { key: 'receipt_copies',          label: t('receipt_copies'),          type: 'number',   section: 'receipt_settings' },
      { key: 'receipt_template',        label: t('receipt_template'),        type: 'select',   options: ['default'],         section: 'receipt_settings' },
      { key: 'receipt_show_qr',         label: t('receipt_show_qr'),         type: 'checkbox', section: 'receipt_settings' },
      { key: 'receipt_show_barcode',    label: t('receipt_show_barcode'),    type: 'checkbox', section: 'receipt_settings' },
      { key: 'print_fallback_browser',  label: t('print_fallback_browser'),  type: 'checkbox', section: 'receipt_settings' },
      { key: 'tax_registration_number', label: t('tax_registration_number'), type: 'text',     section: 'receipt_settings' },
      { key: 'kitchen_printer_id',      label: t('kitchen_printer_id'),      type: 'text',     section: 'connected_printers' },
      { key: 'barcode_printer_id',      label: t('barcode_printer_id'),      type: 'text',     section: 'connected_printers' },
    ],
    loyalty: [
      { key: 'loyalty_enabled',      label: t('loyalty_enabled'),      type: 'checkbox' },
      { key: 'loyalty_earn_rate',    label: t('loyalty_earn_rate'),    type: 'number' },
      { key: 'loyalty_redeem_value', label: t('loyalty_redeem_value'), type: 'number' },
      { key: 'loyalty_min_redeem',   label: t('loyalty_min_redeem'),   type: 'number' },
    ],
    inventory: [
      { key: 'inventory_valuation_method', label: t('inventory_valuation_method'), type: 'select', options: ['weighted_average', 'fifo', 'lifo'] },
    ],
  }

  const sectionLabels: Record<string, string> = {
    treasury_limits:    t('treasury_limits'),
    accounting_link:    t('accounting_link_auto'),
    profitability:      t('profitability_settings'),
    auto_trigger:       t('auto_trigger'),
    receipt_settings:   t('receipt_settings'),
    connected_printers: t('connected_printers'),
  }

  const fields = fieldSets[activeGroup] ?? []

  // Group fields by section for card-based layout
  const sections: { key?: string; fields: FieldDef[] }[] = []
  for (const f of fields) {
    const last = sections[sections.length - 1]
    if (!last || last.key !== f.section) {
      sections.push({ key: f.section, fields: [f] })
    } else {
      last.fields.push(f)
    }
  }

  const getOptionLabel = (fieldKey: string, option: string): string => {
    if (fieldKey === 'default_language') return option === 'ar' ? 'العربية' : 'English'
    if (fieldKey === 'inventory_valuation_method') {
      if (option === 'weighted_average') return t('weighted_average')
      return option.toUpperCase()
    }
    if (option === 'default') return t('default_value')
    return t(option) || option
  }

  const renderField = (field: FieldDef) => (
    <div key={field.key}>
      {field.type !== 'checkbox' && <label className="label">{field.label}</label>}
      {field.hint && field.type !== 'checkbox' && (
        <p className="text-xs text-gray-400 dark:text-gray-500 mb-1">{field.hint}</p>
      )}
      {field.type === 'checkbox' ? (
        <label className="flex items-center gap-3 cursor-pointer py-1">
          <input type="checkbox"
            checked={form[field.key] === 'true' || form[field.key] === '1'}
            onChange={(e) => setForm((p) => ({ ...p, [field.key]: e.target.checked ? '1' : '0' }))}
            className="h-5 w-5 rounded border-gray-300 text-primary-600"
            disabled={!canManage} />
          <span className="text-sm text-gray-700 dark:text-gray-300">{field.label}</span>
        </label>
      ) : field.type === 'select' && field.options ? (
        <select value={form[field.key] ?? ''} onChange={(e) => setForm((p) => ({ ...p, [field.key]: e.target.value }))}
          className="input w-full" disabled={!canManage}>
          {field.options.map((o) => <option key={o} value={o}>{getOptionLabel(field.key, o)}</option>)}
        </select>
      ) : (
        <input type={field.type} value={form[field.key] ?? ''}
          onChange={(e) => setForm((p) => ({ ...p, [field.key]: e.target.value }))}
          className="input w-full" disabled={!canManage} />
      )}
    </div>
  )

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <Settings className="h-6 w-6 text-primary-500" /> {t('settings')}
        </h1>
        {canManage && !isLocalGroup && (
          <button onClick={handleSave} disabled={saveMutation.isPending} className="btn btn-primary flex items-center gap-2">
            <Save className="h-4 w-4" />{saveMutation.isPending ? t('saving') : t('save_changes')}
          </button>
        )}
      </div>

      <div className="flex gap-6">
        <div className="w-52 flex-shrink-0 space-y-1">
          {groups.map((g) => (
            <button key={g.key} onClick={() => setActiveGroup(g.key)}
              className={clsx('w-full text-right px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                activeGroup === g.key
                  ? 'bg-primary-600 text-white'
                  : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white')}>
              {g.label}
            </button>
          ))}
        </div>

        <div className="flex-1 min-w-0">
          {activeGroup === 'appearance' ? (
            <div className="card p-6 space-y-6">
              <h2 className="font-semibold text-gray-900 dark:text-white">{t('appearance')}</h2>
              <div className="space-y-3">
                <label className="label">{t('theme')}</label>
                <div className="grid grid-cols-3 gap-3 max-w-sm">
                  {([{ key: 'light', label: t('light'), icon: Sun }, { key: 'dark', label: t('dark'), icon: Moon }, { key: 'system', label: t('system'), icon: Monitor }] as const).map(({ key, label, icon: Icon }) => (
                    <button key={key} onClick={() => setTheme(key)}
                      className={clsx('flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all text-sm font-medium',
                        theme === key ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-300')}>
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
                      className={clsx('flex items-center gap-2 p-3 rounded-xl border-2 transition-all text-sm font-medium',
                        language === key ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'border-gray-200 dark:border-gray-600 text-gray-600 hover:border-gray-300')}>
                      <span>{flag}</span>{label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : activeGroup === 'desktop' ? (
            <div className="card p-6 space-y-4">
              <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <Info className="h-5 w-5" /> {t('desktop')}
              </h2>
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
          ) : isLoading ? (
            <div className="card p-6 flex h-40 items-center justify-center"><LoadingSpinner /></div>
          ) : (
            <div className="space-y-4">
              {sections.map((section, si) => (
                <div key={si} className="card p-6 space-y-4">
                  {section.key && (
                    <h2 className="font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-3">
                      {sectionLabels[section.key] ?? section.key}
                    </h2>
                  )}
                  {section.key === 'accounting_link' && (
                    <div className="flex gap-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3 text-sm text-blue-700 dark:text-blue-300">
                      <Info className="h-4 w-4 shrink-0 mt-0.5" />
                      <span>{t('accounting_link_info')}</span>
                    </div>
                  )}
                  <div className="grid grid-cols-1 gap-4 max-w-2xl">
                    {section.fields.map(renderField)}
                  </div>
                </div>
              ))}
              {sections.length === 0 && data?.settings && (
                <div className="card p-6 space-y-4">
                  <div className="grid grid-cols-1 gap-4 max-w-2xl">
                    {Object.values(data.settings).map((s) => (
                      <div key={s.key}>
                        <label className="label">{s.label_en ?? s.key.replace(/_/g, ' ')}</label>
                        <input value={form[s.key] ?? s.value} onChange={(e) => setForm((p) => ({ ...p, [s.key]: e.target.value }))}
                          className="input w-full" disabled={!canManage} />
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
