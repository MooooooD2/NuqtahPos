import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useAuthStore } from '@/stores/authStore'
import { useUIStore } from '@/stores/uiStore'
import { api, fetchCsrfCookie, getBaseUrl, SERVER_URL_KEY } from '@/services/api'
import { isTauriApp } from '@/lib/tauri'
import { Eye, EyeOff, Loader2, Building2, Server, Home, Globe } from 'lucide-react'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

const schema = z.object({
  tenant_code: z.string().min(1, 'login_validation_company_code').max(50),
  username: z.string().min(1, 'login_validation_username'),
  password: z.string().min(1, 'login_validation_password'),
})

type FormData = z.infer<typeof schema>

const SAVED_CODE_KEY = 'pos-company-code'
const isDesktop = isTauriApp()

// When built with VITE_API_URL (the official release build), the server URL is fixed —
// no need to expose the field to end-users.
const builtInServerUrl = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '')
const showServerUrlField = isDesktop && !builtInServerUrl

export default function LoginPage() {
  const { t, i18n } = useTranslation('pos')
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
  const { language, setLanguage } = useUIStore()

  const toggleLanguage = () => {
    const next = language === 'en' ? 'ar' : 'en'
    setLanguage(next)
    i18n.changeLanguage(next)
  }
  const [showPw, setShowPw] = useState(false)
  const [loading, setLoading] = useState(false)
  const [serverUrl, setServerUrl] = useState(
    localStorage.getItem(SERVER_URL_KEY) ?? builtInServerUrl ?? 'https://biskumarket.life/pos',
  )

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      tenant_code: localStorage.getItem(SAVED_CODE_KEY) ?? '',
    },
  })

  const inputCls = 'w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-slate-400 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400'

  const onSubmit = async (data: FormData) => {
    setLoading(true)
    try {
      if (isDesktop) {
        localStorage.setItem(SERVER_URL_KEY, serverUrl.replace(/\/$/, ''))
      } else {
        await fetchCsrfCookie()
      }
      const res = await api.post('/login', data)
      const { user, token } = res.data
      localStorage.setItem(SAVED_CODE_KEY, data.tenant_code.toLowerCase())
      login(user, token)
      toast.success(t('welcome_back', { name: user.name }))
      navigate('/')
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string }; status?: number }; code?: string }
      const msg = e?.response?.data?.message
        ?? (e?.response?.status ? `HTTP ${e.response.status}` : null)
        ?? (e?.code ?? t('login_failed'))
      toast.error(msg)
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
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="mb-8 text-center">
          <img src={`${import.meta.env.BASE_URL}images/nuqtah_logo_transparent_original.png`} alt={t('app_name') ?? 'Nuqtah POS'} className="mx-auto mb-4 h-24 w-auto brightness-0 invert" />
          <p className="mt-1 text-sm text-slate-400">{t('sign_in_to')}</p>
        </div>

        {/* Card */}
        <div className="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-lg p-8 shadow-2xl">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

            {/* Server URL — shown only in dev/custom desktop builds without a baked-in URL */}
            {showServerUrlField && (
              <div>
                <label className="block text-sm font-medium text-slate-200 mb-1.5">
                  {t('login_server_url')}
                </label>
                <div className="relative">
                  <Server className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
                  <input
                    type="text"
                    value={serverUrl}
                    onChange={(e) => setServerUrl(e.target.value)}
                    placeholder={t('login_server_url_placeholder')}
                    dir="ltr"
                    className={`${inputCls} ps-9`}
                  />
                </div>
                <p className="mt-1 text-xs text-slate-500">{t('login_server_url_hint')}</p>
              </div>
            )}

            {/* Company Code */}
            <div>
              <label className="block text-sm font-medium text-slate-200 mb-1.5">
                {t('company_code')}
              </label>
              <div className="relative">
                <Building2 className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
                <input
                  {...register('tenant_code')}
                  type="text"
                  autoComplete="organization"
                  placeholder="main"
                  dir="ltr"
                  className={`${inputCls} ps-9`}
                />
              </div>
              {errors.tenant_code && <p className="mt-1 text-xs text-red-400">{t(errors.tenant_code.message as string)}</p>}
            </div>

            {/* Divider */}
            <div className="relative">
              <div className="absolute inset-0 flex items-center">
                <div className="w-full border-t border-white/10" />
              </div>
              <div className="relative flex justify-center text-xs">
                <span className="bg-transparent px-2 text-slate-500">{t('your_account')}</span>
              </div>
            </div>

            {/* Username */}
            <div>
              <label className="block text-sm font-medium text-slate-200 mb-1.5">{t('username')}</label>
              <input
                {...register('username')}
                type="text"
                autoComplete="username"
                placeholder="admin"
                className={inputCls}
              />
              {errors.username && <p className="mt-1 text-xs text-red-400">{t(errors.username.message as string)}</p>}
            </div>

            {/* Password */}
            <div>
              <label className="block text-sm font-medium text-slate-200 mb-1.5">{t('password')}</label>
              <div className="relative">
                <input
                  {...register('password')}
                  type={showPw ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  className={`${inputCls} pe-10`}
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

            <button
              type="submit"
              disabled={loading}
              className="w-full flex items-center justify-center gap-2 rounded-lg bg-primary-600 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60 transition-colors"
            >
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              {loading ? t('loading') : t('sign_in')}
            </button>
          </form>
        </div>

        <p className="mt-6 text-center text-sm text-slate-400">
          {t('login_no_account')}{' '}
          <Link to="/register" className="font-medium text-primary-400 hover:text-primary-300 underline underline-offset-4">
            {t('login_register_link')}
          </Link>
        </p>

        {isDesktop && (
          <p className="mt-4 text-center text-xs text-slate-500 break-all">
            {getBaseUrl()}
          </p>
        )}
        <p className="mt-2 text-center text-xs text-slate-500">
          {t('login_copyright', { year: new Date().getFullYear() })}
        </p>
      </div>
    </div>
  )
}
