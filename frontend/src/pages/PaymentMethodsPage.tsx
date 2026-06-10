import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, ArrowRight, Copy, Check, Store } from 'lucide-react'
import { api } from '@/services/api'

interface PaymentMethod {
  id: number
  method: string
  label_en: string
  label_ar: string
  account_number: string
  account_name: string | null
  notes: string | null
  icon: string
  color: string
}

interface ContactInfo {
  name: string
  phone: string
  email: string
  whatsapp: string
}

/* ── Method icon map: font-awesome class → inline SVG path or emoji ─────── */
const METHOD_ICONS: Record<string, string> = {
  instapay:  '⚡',
  vodafone:  '📱',
  etisalat:  '📱',
  orange:    '🍊',
  fawry:     '🏪',
  bank:      '🏦',
}

function WhatsAppIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  )
}

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false)

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      /* clipboard not available */
    }
  }

  return (
    <button
      onClick={handleCopy}
      className="flex items-center gap-1 rounded-lg border border-white/10 bg-white/8 px-3 py-1.5 text-xs font-medium text-slate-300 transition-all hover:bg-white/15 active:scale-95"
      title="Copy account number"
    >
      {copied ? <Check className="h-3.5 w-3.5 text-emerald-400" /> : <Copy className="h-3.5 w-3.5" />}
      {copied ? 'Copied!' : 'Copy'}
    </button>
  )
}

export default function PaymentMethodsPage() {
  const [methods, setMethods]   = useState<PaymentMethod[]>([])
  const [contact, setContact]   = useState<ContactInfo | null>(null)
  const [loading, setLoading]   = useState(true)

  useEffect(() => {
    Promise.all([
      api.get('/public/payment-methods'),
      api.get('/public/contact'),
    ])
      .then(([mRes, cRes]) => {
        setMethods(mRes.data.methods ?? [])
        setContact(cRes.data)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  const waLink = contact?.whatsapp
    ? `https://wa.me/${contact.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Hello! I\'ve made a payment and would like to confirm my subscription.')}`
    : null

  return (
    <div className="min-h-screen bg-[#060b18] text-white antialiased">

      {/* Dot-grid texture */}
      <div
        className="pointer-events-none fixed inset-0 opacity-[0.03]"
        style={{ backgroundImage: 'radial-gradient(circle, #ffffff 1px, transparent 1px)', backgroundSize: '28px 28px' }}
      />

      {/* ── Nav ─────────────────────────────────────────────────────── */}
      <nav className="relative z-50 border-b border-white/8 bg-[#060b18]/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <Link to="/welcome" className="flex items-center gap-3 group">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-500 shadow-lg shadow-sky-500/30">
              <Store className="h-5 w-5 text-white" />
            </div>
            <span className="text-lg font-bold tracking-tight text-white">POS Enterprise</span>
          </Link>
          <Link to="/welcome" className="flex items-center gap-1.5 text-sm text-slate-400 transition-colors hover:text-white">
            <ArrowLeft className="h-4 w-4" /> Back to home
          </Link>
        </div>
      </nav>

      {/* ── Hero ────────────────────────────────────────────────────── */}
      <section className="relative py-20 text-center">
        <div className="pointer-events-none absolute inset-0 -top-10 mx-auto h-80 w-80 rounded-full bg-emerald-500/8 blur-3xl" style={{ left: '50%', transform: 'translateX(-50%)' }} />
        <div className="relative mx-auto max-w-2xl px-6">
          <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-emerald-500/25 bg-emerald-500/10">
            <WhatsAppIcon className="h-8 w-8 text-emerald-400" />
          </div>
          <h1 className="mb-4 text-4xl font-extrabold tracking-tight">
            <span className="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">
              Payment Methods
            </span>
          </h1>
          <p className="text-slate-400">
            Choose your preferred payment method below. After completing the payment,
            contact us on WhatsApp to activate your subscription.
          </p>
        </div>
      </section>

      {/* ── Payment methods grid ─────────────────────────────────────── */}
      <section className="px-6 pb-12">
        <div className="mx-auto max-w-4xl">
          {loading ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {[1,2,3,4,5,6].map((i) => (
                <div key={i} className="h-36 animate-pulse rounded-2xl border border-white/8 bg-white/3" />
              ))}
            </div>
          ) : methods.length === 0 ? (
            <div className="rounded-2xl border border-white/8 bg-white/3 p-12 text-center text-slate-500">
              No payment methods configured yet. Contact us to arrange payment.
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {methods.map((m) => (
                <div
                  key={m.id}
                  className="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/4 p-6 transition-all hover:border-white/20 hover:bg-white/6"
                >
                  {/* Color accent strip */}
                  <div
                    className="absolute left-0 top-0 h-1 w-full rounded-t-2xl opacity-80"
                    style={{ backgroundColor: m.color }}
                  />

                  {/* Icon + label */}
                  <div className="mb-4 flex items-center gap-3">
                    <div
                      className="flex h-11 w-11 items-center justify-center rounded-xl text-xl shadow-sm"
                      style={{ backgroundColor: m.color + '22', border: `1px solid ${m.color}44` }}
                    >
                      {METHOD_ICONS[m.method] ?? '💳'}
                    </div>
                    <div>
                      <p className="font-semibold text-white">{m.label_en}</p>
                      <p className="text-xs text-slate-500">{m.label_ar}</p>
                    </div>
                  </div>

                  {/* Account number */}
                  <div className="mb-3 flex items-center justify-between rounded-lg border border-white/8 bg-white/5 px-3 py-2">
                    <span className="font-mono text-base font-semibold tracking-wider text-white">
                      {m.account_number}
                    </span>
                    <CopyButton text={m.account_number} />
                  </div>

                  {/* Account name */}
                  {m.account_name && (
                    <p className="mb-1 text-xs text-slate-400">
                      <span className="text-slate-600">Name: </span>{m.account_name}
                    </p>
                  )}

                  {/* Notes */}
                  {m.notes && (
                    <p className="mt-1 text-xs leading-relaxed text-slate-500">{m.notes}</p>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* ── Steps ───────────────────────────────────────────────────── */}
      <section className="px-6 py-16">
        <div className="mx-auto max-w-3xl">
          <h2 className="mb-10 text-center text-2xl font-bold text-white">How it works</h2>
          <ol className="space-y-6">
            {[
              { step: '01', title: 'Choose your plan', desc: 'Select the plan that fits your business from our pricing page.', link: { to: '/welcome#pricing', label: 'View plans' } },
              { step: '02', title: 'Create your store', desc: 'Register your store — you get a free trial, no payment needed upfront.', link: { to: '/register', label: 'Start free trial' } },
              { step: '03', title: 'Transfer the subscription fee', desc: 'Send the payment to any of the methods above and keep the receipt.' },
              { step: '04', title: 'Contact us on WhatsApp', desc: 'Send us your store code, plan name, and payment receipt. We\'ll activate within minutes.' },
            ].map(({ step, title, desc, link }) => (
              <li key={step} className="flex gap-5">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-sm font-bold text-slate-400">
                  {step}
                </div>
                <div className="pt-1.5">
                  <h3 className="mb-1 font-semibold text-white">{title}</h3>
                  <p className="text-sm text-slate-500">{desc}</p>
                  {link && (
                    <Link to={link.to} className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-sky-400 hover:text-sky-300 transition-colors">
                      {link.label} <ArrowRight className="h-3 w-3" />
                    </Link>
                  )}
                </div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      {/* ── WhatsApp CTA ────────────────────────────────────────────── */}
      {waLink && (
        <section className="px-6 pb-20">
          <div className="relative mx-auto max-w-2xl overflow-hidden rounded-3xl border border-green-500/20 bg-gradient-to-br from-green-950/60 via-slate-900 to-emerald-950/40 p-12 text-center">
            <div className="pointer-events-none absolute inset-0 -top-10 mx-auto h-48 w-48 rounded-full bg-green-500/10 blur-3xl" style={{ left: '50%', transform: 'translateX(-50%)' }} />
            <div className="relative">
              <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#25D366] shadow-xl shadow-green-500/30">
                <WhatsAppIcon className="h-7 w-7 text-white" />
              </div>
              <h2 className="mb-3 text-2xl font-extrabold text-white">Paid? Let us know!</h2>
              <p className="mb-7 text-slate-400">
                Send us a message with your store code and payment screenshot.
                We'll activate your subscription immediately.
              </p>
              <a
                href={waLink}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2.5 rounded-xl bg-[#25D366] px-8 py-3.5 font-semibold text-white shadow-lg shadow-green-500/25 transition-all hover:bg-[#1fb559] hover:shadow-green-500/40"
              >
                <WhatsAppIcon className="h-5 w-5" />
                Contact us on WhatsApp
              </a>
              {contact?.email && (
                <p className="mt-5 text-sm text-slate-500">
                  Or email us at{' '}
                  <a href={`mailto:${contact.email}`} className="text-slate-400 underline underline-offset-4 hover:text-white transition-colors">
                    {contact.email}
                  </a>
                </p>
              )}
            </div>
          </div>
        </section>
      )}

      {/* ── Footer CTA ──────────────────────────────────────────────── */}
      <div className="border-t border-white/8 px-6 py-8 text-center text-sm text-slate-500">
        <p>
          Ready to start?{' '}
          <Link to="/register" className="font-medium text-sky-400 underline underline-offset-4 hover:text-sky-300 transition-colors">
            Create your free store
          </Link>
          {' '}·{' '}
          <Link to="/welcome#pricing" className="font-medium text-slate-400 underline underline-offset-4 hover:text-white transition-colors">
            View pricing
          </Link>
        </p>
      </div>

      {/* ── Floating WhatsApp button ─────────────────────────────────── */}
      {waLink && (
        <a
          href={waLink}
          target="_blank"
          rel="noopener noreferrer"
          className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] shadow-xl shadow-green-500/30 transition-transform hover:scale-110"
          title="Chat on WhatsApp"
        >
          <WhatsAppIcon className="h-7 w-7 text-white" />
        </a>
      )}

    </div>
  )
}
