import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { useAuthStore } from '@/stores/authStore'
import { useUIStore } from '@/stores/uiStore'
import { api, fetchCsrfCookie } from '@/services/api'
import {
  Eye, EyeOff, Loader2, Building2, User, KeyRound, BadgeCheck,
  Check, Sparkles, ShoppingCart, UtensilsCrossed, Pill, HardHat, LayoutGrid, ChevronRight, Syringe, Home, Globe,
} from 'lucide-react'
import toast from 'react-hot-toast'

interface Plan {
  id: string
  name: string
  monthly_price: number
  trial_days: number
  features: ({ en: string; ar: string } | string)[]
}

const SAVED_CODE_KEY = 'pos-company-code'

// ─── Business types ───────────────────────────────────────────────────────────

type BusinessType = 'retail' | 'restaurant' | 'pharmacy' | 'medical_supplies' | 'contracting' | 'general'

interface BusinessTypeOption {
  id: BusinessType
  labelEn: string
  labelAr: string
  descEn: string
  descAr: string
  icon: React.ComponentType<{ className?: string }>
  color: string
  bg: string
  border: string
  examplesEn: string
  examplesAr: string
}

const BUSINESS_TYPES: BusinessTypeOption[] = [
  {
    id: 'retail',
    labelEn: 'Retail / Supermarket',
    labelAr: 'ماركت / سوبرماركت',
    descEn: 'Products, inventory, barcode, multi-branch',
    descAr: 'منتجات، مخزون، باركود، فروع متعددة',
    icon: ShoppingCart,
    color: 'text-navy-400',
    bg: 'bg-navy-500/15',
    border: 'border-navy-500/40',
    examplesEn: 'Supermarket · Retail shop · Hypermarket',
    examplesAr: 'سوبرماركت · محل تجزئة · هايبر ماركت',
  },
  {
    id: 'restaurant',
    labelEn: 'Restaurant / Café',
    labelAr: 'مطعم / كافيه',
    descEn: 'Kitchen display, QR tables, shifts',
    descAr: 'شاشة المطبخ، QR للطاولات، الورديات',
    icon: UtensilsCrossed,
    color: 'text-orange-400',
    bg: 'bg-orange-500/15',
    border: 'border-orange-500/40',
    examplesEn: 'Restaurant · Café · Fast food · Cafeteria',
    examplesAr: 'مطعم · كافيه · فاست فود · كافتيريا',
  },
  {
    id: 'pharmacy',
    labelEn: 'Pharmacy',
    labelAr: 'صيدلية',
    descEn: 'Batch tracking, expiry alerts, prescriptions',
    descAr: 'تتبع الدُّفعات، تنبيهات الانتهاء، الوصفات',
    icon: Pill,
    color: 'text-primary-400',
    bg: 'bg-primary-500/15',
    border: 'border-primary-500/40',
    examplesEn: 'Pharmacy · Medicine warehouse',
    examplesAr: 'صيدلية · مستودع أدوية',
  },
  {
    id: 'medical_supplies',
    labelEn: 'Medical Supplies',
    labelAr: 'مستلزمات طبية',
    descEn: 'Batch tracking, expiry alerts, serials',
    descAr: 'تتبع الدُّفعات، تنبيهات الانتهاء، الأرقام التسلسلية',
    icon: Syringe,
    color: 'text-navy-400',
    bg: 'bg-navy-500/15',
    border: 'border-navy-500/40',
    examplesEn: 'Medical supplies · Medical devices · Equipment warehouse',
    examplesAr: 'مستلزمات طبية · أجهزة طبية · مستودع أدوات',
  },
  {
    id: 'contracting',
    labelEn: 'Contracting / Services',
    labelAr: 'مقاولات / خدمات',
    descEn: 'Purchasing, HR, expenses, accounting',
    descAr: 'المشتريات، الموارد البشرية، المصروفات، المحاسبة',
    icon: HardHat,
    color: 'text-yellow-400',
    bg: 'bg-yellow-500/15',
    border: 'border-yellow-500/40',
    examplesEn: 'Contracting · Maintenance · Services company',
    examplesAr: 'مقاولات · صيانة · شركة خدمات',
  },
  {
    id: 'general',
    labelEn: 'General / Other',
    labelAr: 'عام / أخرى',
    descEn: 'All modules available',
    descAr: 'جميع الوحدات متاحة',
    icon: LayoutGrid,
    color: 'text-slate-400',
    bg: 'bg-slate-500/15',
    border: 'border-slate-500/40',
    examplesEn: 'Any other kind of business',
    examplesAr: 'أي نوع تجاري آخر',
  },
]

// ─── Form schema ──────────────────────────────────────────────────────────────

const schema = z.object({
  store_name: z.string().min(2, 'register.validation_store_name').max(100),
  store_code: z
    .string()
    .min(2, 'register.validation_store_code_length')
    .max(30)
    .regex(/^[a-zA-Z0-9_-]+$/, 'register.validation_store_code_format'),
  full_name: z.string().min(2, 'register.validation_full_name').max(100),
  username: z
    .string()
    .min(3, 'register.validation_username_length')
    .max(50)
    .regex(/^[a-zA-Z0-9_]+$/, 'register.validation_username_format'),
  password: z.string().min(8, 'register.validation_password'),
  password_confirmation: z.string().min(1, 'register.validation_password_confirm_required'),
}).refine((d) => d.password === d.password_confirmation, {
  message: 'register.validation_password_mismatch',
  path: ['password_confirmation'],
})

type FormData = z.infer<typeof schema>

// ─── Component ────────────────────────────────────────────────────────────────

export default function RegisterPage() {
  const { t, i18n } = useTranslation()
  const isAr = i18n.language === 'ar'
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
  const { language, setLanguage } = useUIStore()

  const toggleLanguage = () => {
    const next = language === 'en' ? 'ar' : 'en'
    setLanguage(next)
    i18n.changeLanguage(next)
  }
  const [searchParams] = useSearchParams()
  const planId = searchParams.get('plan') ?? 'basic'

  const [step, setStep] = useState<1 | 2>(1)
  const [businessType, setBusinessType] = useState<BusinessType | null>(null)
  const [showPw, setShowPw] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)
  const [loading, setLoading] = useState(false)
  const [plan, setPlan] = useState<Plan | null>(null)

  useEffect(() => {
    api.get('/public/plans')
      .then((r) => {
        const found = (r.data.plans as Plan[]).find((p) => p.id === planId)
        setPlan(found ?? (r.data.plans as Plan[])[0] ?? null)
      })
      .catch(() => {})
  }, [planId])

  const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const inputCls =
    'w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-slate-400 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400'

  const onSubmit = async (data: FormData) => {
    if (!businessType) return
    setLoading(true)
    try {
      await fetchCsrfCookie()
      const res = await api.post(
        '/register',
        { ...data, plan_id: plan?.id ?? planId, business_type: businessType },
        { timeout: 120_000 },
      )
      const { user, token } = res.data
      localStorage.setItem(SAVED_CODE_KEY, data.store_code.toLowerCase())
      login(user, token)
      toast.success(t('register.toast_welcome', { name: user.name }))
      navigate('/')
    } catch (err: unknown) {
      type ApiError = { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }
      const apiErr = err as ApiError
      const fieldErrors = apiErr?.response?.data?.errors

      if (fieldErrors) {
        const fields: Array<keyof FormData> = ['store_name', 'store_code', 'full_name', 'username', 'password', 'password_confirmation']
        let anySet = false
        for (const field of fields) {
          if (fieldErrors[field]?.[0]) {
            setError(field, { type: 'server', message: fieldErrors[field][0] })
            anySet = true
          }
        }
        if (anySet) {
          toast.error(t('register.toast_fix_fields'))
        } else {
          toast.error(apiErr?.response?.data?.message ?? t('register.toast_validation_failed'))
        }
      } else {
        toast.error(apiErr?.response?.data?.message ?? t('register.toast_registration_failed'))
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-gradient-to-br from-navy-950 via-navy-800 to-navy-950 p-4">
      <Link
        to="/welcome"
        title={t('back_to_home') ?? 'Back to home'}
        className="absolute start-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
      >
        <Home className="h-5 w-5" />
      </Link>
      <button
        type="button"
        onClick={toggleLanguage}
        title={language === 'en' ? 'التبديل إلى العربية' : 'Switch to English'}
        className="absolute end-4 top-4 flex h-10 items-center gap-1.5 rounded-lg px-2.5 text-sm font-bold tracking-wide text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
      >
        <Globe className="h-4 w-4 shrink-0" />
        {language === 'en' ? 'AR' : 'EN'}
      </button>
      <div className="w-full max-w-lg">

        {/* Logo */}
        <div className="mb-8 text-center">
          <img src={`${import.meta.env.BASE_URL}images/nuqtah_logo_transparent_original.png`} alt="Nuqtah POS" className="mx-auto mb-4 h-24 w-auto brightness-0 invert" />
          <p className="mt-1 text-sm text-slate-400">{t('register.subtitle')}</p>
        </div>

        {/* Step indicator */}
        <div className="mb-6 flex items-center justify-center gap-3">
          {/* Step 1 */}
          <div className="flex items-center gap-2">
            <div className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-colors ${step === 1 ? 'bg-primary-500 text-white' : 'bg-primary-500 text-white'}`}>
              {step === 1 ? '1' : <Check className="h-3.5 w-3.5" />}
            </div>
            <span className={`text-xs font-medium transition-colors ${step === 1 ? 'text-white' : 'text-primary-400'}`}>
              {t('register.step_business')}
            </span>
          </div>
          <div className="h-px w-8 bg-white/20" />
          {/* Step 2 */}
          <div className="flex items-center gap-2">
            <div className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-colors ${step === 2 ? 'bg-primary-500 text-white' : 'bg-white/15 text-slate-400'}`}>
              2
            </div>
            <span className={`text-xs font-medium ${step === 2 ? 'text-white' : 'text-slate-500'}`}>
              {t('register.step_details')}
            </span>
          </div>
        </div>

        {/* ── STEP 1: Business type ── */}
        {step === 1 && (
          <div className="rounded-2xl border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur-lg">
            <p className="mb-1 text-center text-lg font-bold text-white">{t('register.step1_heading')}</p>
            <p className="mb-5 text-center text-xs text-slate-400">{t('register.step1_subheading')}</p>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {BUSINESS_TYPES.map((bt) => {
                const selected = businessType === bt.id
                return (
                  <button
                    key={bt.id}
                    type="button"
                    onClick={() => setBusinessType(bt.id)}
                    className={`group relative flex items-start gap-3 rounded-xl border p-4 text-start transition-all ${
                      selected
                        ? `${bt.border} ${bt.bg} ring-1 ring-inset ${bt.border}`
                        : 'border-white/10 bg-white/5 hover:border-white/20 hover:bg-white/10'
                    }`}
                  >
                    {/* Icon */}
                    <div className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${selected ? bt.bg : 'bg-white/10'}`}>
                      <bt.icon className={`h-5 w-5 ${selected ? bt.color : 'text-slate-400'}`} />
                    </div>

                    {/* Text */}
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm font-semibold ${selected ? 'text-white' : 'text-slate-200'}`}>
                        {isAr ? bt.labelAr : bt.labelEn}
                      </p>
                      <p className="mt-0.5 text-[11px] text-slate-400 leading-relaxed">{isAr ? bt.descAr : bt.descEn}</p>
                      <p className="mt-1 text-[10px] text-slate-500">{isAr ? bt.examplesAr : bt.examplesEn}</p>
                    </div>

                    {/* Check mark */}
                    {selected && (
                      <div className={`absolute top-2 end-2 flex h-5 w-5 items-center justify-center rounded-full ${bt.bg}`}>
                        <Check className={`h-3 w-3 ${bt.color}`} />
                      </div>
                    )}
                  </button>
                )
              })}
            </div>

            <button
              type="button"
              disabled={!businessType}
              onClick={() => setStep(2)}
              className="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {t('register.next')}
              <ChevronRight className="h-4 w-4 rtl:rotate-180" />
            </button>
          </div>
        )}

        {/* ── STEP 2: Store + account form ── */}
        {step === 2 && (
          <>
            {/* Selected business type badge */}
            {businessType && (() => {
              const bt = BUSINESS_TYPES.find((b) => b.id === businessType)!
              return (
                <div className={`mb-4 flex items-center gap-2 rounded-xl border ${bt.border} ${bt.bg} px-4 py-2.5`}>
                  <bt.icon className={`h-4 w-4 shrink-0 ${bt.color}`} />
                  <span className="text-sm text-white font-medium">{isAr ? bt.labelAr : bt.labelEn}</span>
                  <button
                    type="button"
                    onClick={() => setStep(1)}
                    className="ms-auto text-xs text-slate-400 underline underline-offset-4 hover:text-white transition-colors"
                  >
                    {t('register.change')}
                  </button>
                </div>
              )
            })()}

            {/* Plan summary */}
            {plan && (
              <div className="mb-6 overflow-hidden rounded-2xl border border-navy-500/25 bg-navy-500/8">
                <div className="flex items-start gap-4 p-5">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy-500/20 text-navy-400">
                    <Sparkles className="h-5 w-5" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="mb-1 flex items-center gap-2 flex-wrap">
                      <span className="text-sm font-bold text-white">{t('register.plan_name', { name: plan.name })}</span>
                      <span className="rounded-full bg-navy-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-navy-300">
                        {t('register.trial_days', { days: plan.trial_days })}
                      </span>
                      <span className="rounded-full bg-primary-500/15 px-2.5 py-0.5 text-[11px] font-medium text-primary-400">
                        {t('register.no_card_needed')}
                      </span>
                    </div>
                    <p className="text-xs text-slate-400">
                      {t('register.price_after_trial', { price: plan.monthly_price })}
                    </p>
                    {plan.features.slice(0, 3).map((f, i) => (
                      <div key={i} className="mt-1 flex items-center gap-1.5 text-xs text-slate-400">
                        <Check className="h-3 w-3 shrink-0 text-navy-400" />
                        {typeof f === 'object' ? (isAr ? f.ar : f.en) : f}
                      </div>
                    ))}
                  </div>
                  <Link to="/welcome#pricing" className="shrink-0 text-xs text-slate-500 underline underline-offset-4 hover:text-white transition-colors">
                    {t('register.change')}
                  </Link>
                </div>
              </div>
            )}

            {/* Card */}
            <div className="rounded-2xl border border-white/10 bg-white/10 p-8 shadow-2xl backdrop-blur-lg">
              <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                {/* Store info section */}
                <div className="space-y-4">
                  <p className="text-xs font-semibold uppercase tracking-widest text-slate-400">{t('register.store_info_heading')}</p>

                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-200">{t('register.field_store_name')}</label>
                    <div className="relative">
                      <Building2 className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      <input
                        {...register('store_name')}
                        type="text"
                        placeholder={t('register.placeholder_store_name')}
                        className={`${inputCls} ps-9`}
                      />
                    </div>
                    {errors.store_name && <p className="mt-1 text-xs text-red-400">{t(errors.store_name.message as string)}</p>}
                  </div>

                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-200">{t('register.field_store_code')}</label>
                    <div className="relative">
                      <BadgeCheck className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      <input
                        {...register('store_code')}
                        type="text"
                        placeholder="my-store"
                        dir="ltr"
                        className={`${inputCls} ps-9`}
                      />
                    </div>
                    <p className="mt-1 text-xs text-slate-500">{t('register.store_code_hint')}</p>
                    {errors.store_code && <p className="mt-1 text-xs text-red-400">{t(errors.store_code.message as string)}</p>}
                  </div>
                </div>

                {/* Divider */}
                <div className="relative">
                  <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-white/10" />
                  </div>
                  <div className="relative flex justify-center text-xs">
                    <span className="bg-transparent px-2 text-slate-500">{t('register.admin_account_heading')}</span>
                  </div>
                </div>

                {/* Admin info section */}
                <div className="space-y-4">
                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-200">{t('register.field_full_name')}</label>
                    <div className="relative">
                      <User className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      <input
                        {...register('full_name')}
                        type="text"
                        placeholder={t('register.placeholder_full_name')}
                        className={`${inputCls} ps-9`}
                      />
                    </div>
                    {errors.full_name && <p className="mt-1 text-xs text-red-400">{t(errors.full_name.message as string)}</p>}
                  </div>

                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-200">{t('register.field_username')}</label>
                    <div className="relative">
                      <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">@</span>
                      <input
                        {...register('username')}
                        type="text"
                        placeholder="admin"
                        dir="ltr"
                        className={`${inputCls} ps-7`}
                      />
                    </div>
                    {errors.username && <p className="mt-1 text-xs text-red-400">{t(errors.username.message as string)}</p>}
                  </div>

                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-200">{t('register.field_password')}</label>
                    <div className="relative">
                      <KeyRound className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      <input
                        {...register('password')}
                        type={showPw ? 'text' : 'password'}
                        placeholder="••••••••"
                        className={`${inputCls} ps-9 pe-10`}
                      />
                      <button
                        type="button"
                        onClick={() => setShowPw((v) => !v)}
                        className="absolute end-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                      >
                        {showPw ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    {errors.password && <p className="mt-1 text-xs text-red-400">{t(errors.password.message as string)}</p>}
                  </div>

                  <div>
                    <label className="mb-1.5 block text-sm font-medium text-slate-200">{t('register.field_password_confirm')}</label>
                    <div className="relative">
                      <KeyRound className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      <input
                        {...register('password_confirmation')}
                        type={showConfirm ? 'text' : 'password'}
                        placeholder="••••••••"
                        className={`${inputCls} ps-9 pe-10`}
                      />
                      <button
                        type="button"
                        onClick={() => setShowConfirm((v) => !v)}
                        className="absolute end-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                      >
                        {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    {errors.password_confirmation && (
                      <p className="mt-1 text-xs text-red-400">{t(errors.password_confirmation.message as string)}</p>
                    )}
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={loading}
                  className="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60"
                >
                  {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                  {loading ? t('register.submitting') : t('register.submit')}
                </button>
              </form>
            </div>
          </>
        )}

        <p className="mt-6 text-center text-sm text-slate-400">
          {t('register.already_have_account')}{' '}
          <Link to="/login" className="font-medium text-primary-400 hover:text-primary-300 underline underline-offset-4">
            {t('register.sign_in')}
          </Link>
        </p>

        <p className="mt-3 text-center text-xs text-slate-500">
          {t('register.copyright', { year: new Date().getFullYear() })}
        </p>
      </div>
    </div>
  )
}
