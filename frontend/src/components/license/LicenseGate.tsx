import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { KeyRound, Loader2 } from 'lucide-react'
import { useLicenseStore } from '@/stores/licenseStore'
import { activateLicense, validateLicense } from '@/services/license'

const GRACE_PERIOD_DAYS = 7

function withinGracePeriod(expiresAt: string | null): boolean {
  if (!expiresAt) return true // perpetual license
  const expiry = new Date(expiresAt).getTime()
  const graceMs = GRACE_PERIOD_DAYS * 24 * 60 * 60 * 1000
  return Date.now() <= expiry + graceMs
}

export default function LicenseGate({ children }: { children: React.ReactNode }) {
  const { licenseKey, deviceId, expiresAt, activate, clear } = useLicenseStore()
  const [checking, setChecking] = useState(true)
  const [unlocked, setUnlocked] = useState(false)
  const [keyInput, setKeyInput] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false

    async function checkExisting() {
      if (licenseKey && deviceId && withinGracePeriod(expiresAt)) {
        if (!cancelled) setUnlocked(true)
        // Re-validate in the background; never blocks the UI.
        validateLicense(licenseKey, deviceId)
          .then((res) => activate(licenseKey, deviceId, res.token, res.license.expires_at))
          .catch(() => { /* offline or revoked — grace period covers transient failures */ })
      }
      if (!cancelled) setChecking(false)
    }

    checkExisting()
    return () => { cancelled = true }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!keyInput.trim()) return
    setSubmitting(true)
    setError('')
    try {
      const deviceName = navigator.userAgent.slice(0, 80)
      const res = await activateLicense(keyInput.trim(), deviceName)
      activate(keyInput.trim(), res.deviceId, res.token, res.license.expires_at)
      setUnlocked(true)
    } catch (err: any) {
      const message = err?.response?.data?.message ?? 'فشل تفعيل الترخيص. تأكد من المفتاح وحاول مجددًا.'
      setError(message)
      toast.error(message)
    } finally {
      setSubmitting(false)
    }
  }

  if (checking) {
    return (
      <div className="flex h-screen w-screen items-center justify-center bg-slate-950">
        <Loader2 className="h-8 w-8 animate-spin text-primary-400" />
      </div>
    )
  }

  if (unlocked) {
    return <>{children}</>
  }

  return (
    <div className="flex h-screen w-screen items-center justify-center bg-slate-950 px-4">
      <form onSubmit={onSubmit} className="w-full max-w-sm rounded-2xl border border-white/10 bg-white/5 p-8">
        <div className="mb-6 flex flex-col items-center gap-2 text-center">
          <KeyRound className="h-8 w-8 text-primary-400" />
          <h1 className="text-lg font-semibold text-white">تفعيل التطبيق</h1>
          <p className="text-sm text-slate-400">أدخل مفتاح الترخيص الخاص بمتجرك لتفعيل النسخة المكتبية</p>
        </div>

        <input
          type="text"
          value={keyInput}
          onChange={(e) => { setKeyInput(e.target.value); clear() }}
          placeholder="XXXX-XXXX-XXXX-XXXX"
          autoFocus
          className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-center font-mono uppercase tracking-wider text-white placeholder-slate-500 focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"
        />

        {error && <p className="mt-3 text-center text-sm text-red-400">{error}</p>}

        <button
          type="submit"
          disabled={submitting || !keyInput.trim()}
          className="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-40"
        >
          {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
          تفعيل
        </button>
      </form>
    </div>
  )
}
