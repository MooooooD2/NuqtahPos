import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useAuthStore } from '@/stores/authStore'
import { api, fetchCsrfCookie } from '@/services/api'
import { Store, Eye, EyeOff, Loader2 } from 'lucide-react'
import toast from 'react-hot-toast'
import { useTranslation } from 'react-i18next'

const schema = z.object({
  username: z.string().min(1, 'Username required'),
  password: z.string().min(1, 'Password required'),
})

type FormData = z.infer<typeof schema>

export default function LoginPage() {
  const { t } = useTranslation('pos')
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
  const [showPw, setShowPw] = useState(false)
  const [loading, setLoading] = useState(false)

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  const onSubmit = async (data: FormData) => {
    setLoading(true)
    try {
      await fetchCsrfCookie()
      const res = await api.post('/login', { ...data, tenant_code: 'main' })
      const { user, token } = res.data
      login(user, token)
      toast.success(`Welcome back, ${user.name}!`)
      navigate('/')
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Login failed'
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
            <div>
              <label className="block text-sm font-medium text-slate-200 mb-1.5">{t('username')}</label>
              <input
                {...register('username')}
                type="text"
                autoComplete="username"
                placeholder="admin"
                className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-slate-400 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
              />
              {errors.username && <p className="mt-1 text-xs text-red-400">{errors.username.message}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-200 mb-1.5">{t('password')}</label>
              <div className="relative">
                <input
                  {...register('password')}
                  type={showPw ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-white placeholder-slate-400 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400 pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPw((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
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
