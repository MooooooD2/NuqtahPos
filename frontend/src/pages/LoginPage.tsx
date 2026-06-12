import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useAuthStore } from '@/stores/authStore'
import { api, fetchCsrfCookie, SERVER_URL_KEY } from '@/services/api'
import { isTauriApp } from '@/lib/tauri'
import { Store, Eye, EyeOff, Loader2, Building2, Server } from 'lucide-react'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

const schema = z.object({
  tenant_code: z.string().min(1, 'Company code required').max(50),
  username: z.string().min(1, 'Username required'),
  password: z.string().min(1, 'Password required'),
})

type FormData = z.infer<typeof schema>

const SAVED_CODE_KEY = 'pos-company-code'
const isDesktop = isTauriApp()

// When built with VITE_API_URL (the official release build), the server URL is fixed —
// no need to expose the field to end-users.
const builtInServerUrl = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '')
const showServerUrlField = isDesktop && !builtInServerUrl

export default function LoginPage() {
  const { t } = useTranslation('pos')
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
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
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-primary-900 to-slate-900 p-4">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-500 shadow-lg">
            <Store className="h-9 w-9 text-white" />
          </div>
          <h1 className="text-3xl font-bold text-white">{t('app_name')}</h1>
          <p className="mt-1 text-sm text-slate-400">{t('sign_in_to')}</p>
        </div>

        {/* Card */}
        <div className="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-lg p-8 shadow-2xl">
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

            {/* Server URL — shown only in dev/custom desktop builds without a baked-in URL */}
            {showServerUrlField && (
              <div>
                <label className="block text-sm font-medium text-slate-200 mb-1.5">
                  Server URL
                </label>
                <div className="relative">
                  <Server className="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
                  <input
                    type="text"
                    value={serverUrl}
                    onChange={(e) => setServerUrl(e.target.value)}
                    placeholder="http://192.168.1.100:8000"
                    dir="ltr"
                    className={`${inputCls} ps-9`}
                  />
                </div>
                <p className="mt-1 text-xs text-slate-500">Your backend server address</p>
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
              {errors.tenant_code && <p className="mt-1 text-xs text-red-400">{errors.tenant_code.message}</p>}
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
              {errors.username && <p className="mt-1 text-xs text-red-400">{errors.username.message}</p>}
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
              {errors.password && <p className="mt-1 text-xs text-red-400">{errors.password.message}</p>}
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

        <p className="mt-6 text-center text-xs text-slate-500">
          © 2025 POS Enterprise. All rights reserved.
        </p>
      </div>
    </div>
  )
}
