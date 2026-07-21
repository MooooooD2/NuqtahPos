import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { motion, AnimatePresence, type Variants } from 'framer-motion'
import {
  Store, BarChart3, ShoppingCart, Package, Users, FileText,
  DollarSign, Pill, ChefHat, MessageSquare, TrendingUp, Shield,
  Globe, Zap, ArrowRight, CheckCircle2, Sparkles, WifiOff, Lock,
  Server, Monitor, Building2, Coffee, Layers, Database, Check, X,
  Menu, ArrowUp,
} from 'lucide-react'
import { api } from '@/services/api'
import { useUIStore } from '@/stores/uiStore'

/* ─── Shared scroll-reveal animation helpers ────────────────────────────── */

const fadeUp: Variants = {
  hidden: { opacity: 0, y: 28 },
  show: { opacity: 1, y: 0, transition: { duration: 0.55, ease: [0.22, 1, 0.36, 1] } },
}

const staggerContainer: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.08 } },
}

// Mount-triggered (not scroll-gated) so content is never left permanently invisible
// if the viewport IntersectionObserver misses a fast/programmatic scroll.
function Reveal({ children, className, delay = 0 }: { children: React.ReactNode; className?: string; delay?: number }) {
  return (
    <motion.div
      className={className}
      initial="hidden"
      animate="show"
      variants={fadeUp}
      transition={{ delay }}
    >
      {children}
    </motion.div>
  )
}

function RevealGroup({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <motion.div
      className={className}
      initial="hidden"
      animate="show"
      variants={staggerContainer}
    >
      {children}
    </motion.div>
  )
}

/* ─── Types ─────────────────────────────────────────────────────────────── */

interface PlanFeature { en: string; ar: string }
interface Plan {
  id: string
  name: string
  monthly_price: number
  annual_price: number | null
  trial_days: number
  max_users: number | null
  max_products: number | null
  features: (PlanFeature | string)[]
}
interface PaymentMethod { id: number; method: string; account_number: string }
interface ContactInfo   { whatsapp: string }

/* ─── Static data ───────────────────────────────────────────────────────── */

const statKeys = [
  { value: '500+', key: 'stores' },
  { value: '10M+', key: 'transactions' },
  { value: '99.9%', key: 'uptime' },
  { value: '50+',  key: 'features' },
]

const features = [
  { icon: ShoppingCart,  color: 'text-primary-600', bg: 'bg-primary-50',  key: 'pos' },
  { icon: Package,       color: 'text-navy-600',  bg: 'bg-navy-50',   key: 'inventory' },
  { icon: BarChart3,     color: 'text-navy-600',     bg: 'bg-navy-50',      key: 'analytics' },
  { icon: DollarSign,    color: 'text-amber-600',   bg: 'bg-amber-50',    key: 'accounting' },
  { icon: Users,         color: 'text-pink-600',    bg: 'bg-pink-50',     key: 'crm' },
  { icon: FileText,      color: 'text-orange-600',  bg: 'bg-orange-50',   key: 'invoices' },
  { icon: Pill,          color: 'text-primary-600',    bg: 'bg-primary-50',     key: 'pharmacy' },
  { icon: ChefHat,       color: 'text-rose-600',    bg: 'bg-rose-50',     key: 'kitchen' },
  { icon: MessageSquare, color: 'text-green-600',   bg: 'bg-green-50',    key: 'whatsapp' },
  { icon: TrendingUp,    color: 'text-navy-600',  bg: 'bg-navy-50',   key: 'forecasting' },
  { icon: Shield,        color: 'text-navy-600',    bg: 'bg-navy-50',     key: 'roles' },
  { icon: Globe,         color: 'text-navy-600',    bg: 'bg-navy-50',     key: 'bilingual' },
]

const industries = [
  { icon: Building2, color: 'from-primary-50 to-white', border: 'border-primary-100', iconColor: 'text-primary-600', key: 'retail' },
  { icon: Coffee,     color: 'from-rose-50 to-white',    border: 'border-rose-100',    iconColor: 'text-rose-600',    key: 'restaurants' },
  { icon: Pill,       color: 'from-primary-50 to-white',    border: 'border-primary-100',    iconColor: 'text-primary-600',    key: 'pharmacies' },
]

const trustItems = [
  { icon: WifiOff,  key: 'offline' },
  { icon: Database, key: 'multitenancy' },
  { icon: Lock,     key: 'security' },
  { icon: Server,   key: 'docker' },
  { icon: Layers,   key: 'api' },
  { icon: Monitor,  key: 'crossplatform' },
]

/* ─── Mock Dashboard ────────────────────────────────────────────────────── */

const kpis = [
  { label: "Today's Sales", value: '$12,480', trend: '+8.2%', up: true },
  { label: 'Invoices',      value: '248',     trend: '+12%',  up: true },
  { label: 'Products',      value: '1,842',   trend: '',      up: true },
  { label: 'Low Stock',     value: '7',       trend: '−3',    up: false },
]

const barHeights = [32,55,42,70,48,84,60,90,52,76,66,95,57,82,71,46,87,62,77,91,67,51,80,72,57,92,76,86,61,97]

const payMethods = [
  { label: 'Cash',   pct: '45%', cls: 'bg-primary-500', w: 'w-[45%]' },
  { label: 'Card',   pct: '35%', cls: 'bg-navy-500',  w: 'w-[35%]' },
  { label: 'Online', pct: '20%', cls: 'bg-amber-500',   w: 'w-[20%]' },
]

const recentRows = [
  { id: '#INV-4821', customer: 'Ahmed Khalil', amount: '$320.00', status: 'Paid' },
  { id: '#INV-4820', customer: 'Sara Mohamed', amount: '$85.50',  status: 'Paid' },
  { id: '#INV-4819', customer: 'John Smith',   amount: '$210.00', status: 'Pending' },
]

function DashboardMockup() {
  return (
    <div className="relative mx-auto mt-10 max-w-5xl px-4 sm:mt-16">
      <div className="pointer-events-none absolute -inset-4 rounded-3xl bg-primary-200/40 blur-3xl" />
      <div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_32px_80px_-12px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/5">
        <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-3 sm:px-4">
          <div className="hidden items-center gap-2 sm:flex">
            <div className="h-3 w-3 rounded-full bg-red-400" />
            <div className="h-3 w-3 rounded-full bg-yellow-400" />
            <div className="h-3 w-3 rounded-full bg-green-400" />
          </div>
          <div className="mx-0 flex min-w-0 flex-1 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1 sm:mx-3">
            <div className="h-2.5 w-2.5 shrink-0 rounded-full bg-green-500" />
            <span className="truncate text-xs text-slate-400">app.nuqtah-pos.com/dashboard</span>
          </div>
        </div>
        <div className="flex">
          <div className="flex w-10 shrink-0 flex-col items-center gap-2.5 border-r border-slate-800 bg-[#132a4c] py-4 sm:w-12 sm:gap-3">
            <div className="flex h-7 w-7 items-center justify-center rounded-xl bg-primary-500 sm:h-8 sm:w-8">
              <Store className="h-4 w-4 text-white" />
            </div>
            {[ShoppingCart, Package, FileText, Users, BarChart3].map((Icon, i) => (
              <div key={i} className="flex h-7 w-7 items-center justify-center rounded-lg bg-white/5 text-slate-400 sm:h-8 sm:w-8">
                <Icon className="h-3.5 w-3.5" />
              </div>
            ))}
          </div>
          <div className="min-w-0 flex-1 bg-slate-50 p-3 sm:p-4">
            <div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
              {kpis.map((k) => (
                <div key={k.label} className="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3">
                  <div className="mb-1 truncate text-[9px] text-slate-500 sm:text-[10px]">{k.label}</div>
                  <div className="text-sm font-bold text-slate-900">{k.value}</div>
                  {k.trend && <div className={`text-[10px] font-medium ${k.up ? 'text-primary-600' : 'text-red-500'}`}>{k.trend}</div>}
                </div>
              ))}
            </div>
            <div className="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <div className="rounded-xl border border-slate-200 bg-white p-2.5 sm:col-span-2 sm:p-3">
                <div className="mb-2 text-[10px] text-slate-500">Sales — Last 30 days</div>
                <div className="flex h-16 items-end gap-px sm:h-20">
                  {barHeights.map((h, i) => (
                    <div key={i} className="flex-1 rounded-t-[1px] bg-primary-500/70 transition-colors hover:bg-primary-500" style={{ height: `${h}%` }} />
                  ))}
                </div>
              </div>
              <div className="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3">
                <div className="mb-3 text-[10px] text-slate-500">Payment Methods</div>
                <div className="space-y-2">
                  {payMethods.map((p) => (
                    <div key={p.label}>
                      <div className="mb-0.5 flex justify-between text-[9px]">
                        <span className="text-slate-500">{p.label}</span>
                        <span className="font-semibold text-slate-900">{p.pct}</span>
                      </div>
                      <div className="h-1.5 w-full rounded-full bg-slate-100">
                        <div className={`h-full rounded-full ${p.cls} ${p.w}`} />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3">
              <div className="mb-2 text-[10px] text-slate-500">Recent Invoices</div>
              <div className="space-y-1.5">
                {recentRows.map((r) => (
                  <div key={r.id} className="flex items-center gap-2 text-[10px] sm:gap-3">
                    <span className="shrink-0 font-mono text-primary-600">{r.id}</span>
                    <span className="min-w-0 flex-1 truncate text-slate-500">{r.customer}</span>
                    <span className="shrink-0 font-semibold text-slate-900">{r.amount}</span>
                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-[9px] font-medium ${r.status === 'Paid' ? 'bg-primary-50 text-primary-700' : 'bg-amber-50 text-amber-700'}`}>
                      {r.status}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

/* ─── WhatsApp SVG icon ─────────────────────────────────────────────────── */
function WhatsAppIcon({ className }: { className?: string }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="currentColor">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  )
}

/* ─── Pricing helpers ───────────────────────────────────────────────────── */

function planAccentClass(id: string) {
  if (id === 'pro')        return { border: 'border-primary-300', badge: 'bg-primary-600', btn: 'bg-primary-600 hover:bg-primary-700 shadow-lg shadow-primary-600/20 text-white', check: 'text-primary-600' }
  if (id === 'enterprise') return { border: 'border-navy-200',  badge: 'bg-navy-600',  btn: 'bg-navy-600 hover:bg-navy-700 shadow-lg shadow-navy-600/20 text-white',    check: 'text-navy-600' }
  return { border: 'border-slate-200', badge: '', btn: 'border border-slate-300 bg-white hover:bg-slate-50 text-slate-700', check: 'text-slate-500' }
}

/* ─── Page ─────────────────────────────────────────────────────────────── */

const navLinks = [
  { href: '#features', key: 'features' },
  { href: '#industries', key: 'industries' },
  { href: '#pricing', key: 'pricing' },
  { href: '#technology', key: 'technology' },
]

export default function LandingPage() {
  const { t, i18n } = useTranslation()
  const { language, setLanguage } = useUIStore()
  const [plans, setPlans] = useState<Plan[]>([])
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'annual'>('monthly')
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([])
  const [contact, setContact] = useState<ContactInfo | null>(null)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [showScrollTop, setShowScrollTop] = useState(false)

  useEffect(() => {
    api.get('/public/plans').then((r) => setPlans(r.data.plans ?? [])).catch(() => {})
    api.get('/public/payment-methods').then((r) => setPaymentMethods(r.data.methods ?? [])).catch(() => {})
    api.get('/public/contact').then((r) => setContact(r.data)).catch(() => {})
  }, [])

  useEffect(() => {
    const onScroll = () => setShowScrollTop(window.scrollY > 600)
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const toggleLanguage = () => {
    const next = language === 'en' ? 'ar' : 'en'
    setLanguage(next)
    i18n.changeLanguage(next)
  }

  const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' })

  const waNumber = paymentMethods.find((m) => m.method === 'whatsapp')?.account_number || contact?.whatsapp
  const waLink = waNumber
    ? `https://wa.me/${waNumber.replace(/\D/g, '')}?text=${encodeURIComponent('Hello! I\'m interested in Nuqtah POS.')}`
    : null

  return (
    <div className="min-h-screen bg-white text-slate-900 antialiased">

      {/* ── Navigation ────────────────────────────────────────────────── */}
      <nav className="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-2 sm:gap-3">
            <img src={`${import.meta.env.BASE_URL}images/nuqtah_logo_transparent_original.png`} alt="Nuqtah POS" className="h-10 w-auto sm:h-12" />
          </div>

          <div className="hidden items-center gap-6 text-sm text-slate-600 md:flex">
            {navLinks.map((l) => (
              <a key={l.href} href={l.href} className="transition-colors hover:text-slate-900">{t(`landing.nav.${l.key}`)}</a>
            ))}
          </div>

          <div className="flex items-center gap-1.5 sm:gap-3">
            <button
              type="button"
              onClick={toggleLanguage}
              title={language === 'en' ? t('landing.nav.switch_to_ar') : t('landing.nav.switch_to_en')}
              className="flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 px-2 text-xs font-bold tracking-wide text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900 sm:px-2.5"
            >
              <Globe className="h-4 w-4 shrink-0" />
              <span className="hidden sm:inline">{language === 'en' ? 'AR' : 'EN'}</span>
            </button>
            <Link to="/login" className="hidden rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 sm:inline-block">
              {t('landing.nav.login')}
            </Link>
            <Link to="/register" className="hidden items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-md shadow-primary-600/20 transition-all hover:bg-primary-700 sm:flex sm:px-4">
              {t('landing.nav.get_started')} <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" />
            </Link>
            <button
              type="button"
              onClick={() => setMobileNavOpen((v) => !v)}
              aria-label="Toggle navigation menu"
              className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors hover:text-slate-900 md:hidden"
            >
              <Menu className="h-4.5 w-4.5" />
            </button>
          </div>
        </div>

        <AnimatePresence>
          {mobileNavOpen && (
            <motion.div
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.25, ease: 'easeInOut' }}
              className="overflow-hidden border-t border-slate-200 md:hidden"
            >
              <div className="flex flex-col gap-1 px-6 py-3">
                {navLinks.map((l) => (
                  <a
                    key={l.href}
                    href={l.href}
                    onClick={() => setMobileNavOpen(false)}
                    className="rounded-lg px-3 py-2.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900"
                  >
                    {t(`landing.nav.${l.key}`)}
                  </a>
                ))}
                <Link
                  to="/login"
                  onClick={() => setMobileNavOpen(false)}
                  className="rounded-lg px-3 py-2.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900"
                >
                  {t('landing.nav.login')}
                </Link>
                <Link
                  to="/register"
                  onClick={() => setMobileNavOpen(false)}
                  className="mt-1 flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary-600/20 transition-colors hover:bg-primary-700"
                >
                  {t('landing.nav.get_started')} <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" />
                </Link>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </nav>

      {/* ── Hero ──────────────────────────────────────────────────────── */}
      <section className="relative overflow-hidden pb-8 pt-24 text-center">
        <div className="pointer-events-none absolute -top-40 left-1/4 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-primary-200/50 blur-3xl" />
        <div className="pointer-events-none absolute -top-20 right-1/4 h-[400px] w-[400px] translate-x-1/2 rounded-full bg-navy-200/40 blur-3xl" />

        <motion.div
          className="relative mx-auto max-w-4xl px-6"
          initial="hidden"
          animate="show"
          variants={staggerContainer}
        >
          <motion.div variants={fadeUp} className="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-4 py-1.5 text-sm text-primary-700">
            <Sparkles className="h-3.5 w-3.5" />
            {t('landing.hero.badge')}
          </motion.div>

          <motion.h1 variants={fadeUp} className="mb-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl md:text-7xl">
            {t('landing.hero.title_line1')}
            <br />
            <span className="bg-gradient-to-r from-primary-600 via-primary-500 to-navy-600 bg-clip-text text-transparent">
              {t('landing.hero.title_line2')}
            </span>
          </motion.h1>

          <motion.p variants={fadeUp} className="mx-auto mb-10 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
            {t('landing.hero.subtitle', { offline: t('landing.hero.subtitle_offline') })}
          </motion.p>

          <motion.div variants={fadeUp} className="flex flex-col items-center justify-center gap-4 sm:flex-row">
            <Link to="/register" className="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-8 py-3.5 text-base font-semibold text-white shadow-xl shadow-primary-600/20 transition-all hover:bg-primary-700 hover:shadow-primary-600/30 hover:-translate-y-0.5 sm:w-auto">
              {t('landing.hero.cta_primary')} <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            </Link>
            <Link to="/login" className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-8 py-3.5 text-base font-semibold text-slate-700 transition-all hover:border-slate-400 hover:bg-slate-50 hover:-translate-y-0.5 sm:w-auto">
              {t('landing.hero.cta_secondary')}
            </Link>
          </motion.div>

          <motion.p variants={fadeUp} className="mt-4 text-xs text-slate-400">{t('landing.hero.note')}</motion.p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 40 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.7, delay: 0.35, ease: [0.22, 1, 0.36, 1] }}
        >
          <DashboardMockup />
        </motion.div>
      </section>

      {/* ── Stats ─────────────────────────────────────────────────────── */}
      <section className="relative z-10 mt-20 border-y border-slate-200 bg-slate-50 px-6 py-10">
        <RevealGroup className="mx-auto max-w-4xl">
          <dl className="grid grid-cols-2 gap-6 sm:gap-8 md:grid-cols-4">
            {statKeys.map(({ value, key }) => (
              <motion.div key={key} variants={fadeUp} className="text-center">
                <dt className="mb-1 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{value}</dt>
                <dd className="text-xs text-slate-500 sm:text-sm">{t(`landing.stats.${key}`)}</dd>
              </motion.div>
            ))}
          </dl>
        </RevealGroup>
      </section>

      {/* ── Features ──────────────────────────────────────────────────── */}
      <section id="features" className="px-6 py-20 sm:py-28">
        <div className="mx-auto max-w-7xl">
          <Reveal className="mb-12 text-center sm:mb-16">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary-600">
              <Zap className="h-3.5 w-3.5" /> {t('landing.features.eyebrow')}
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
              {t('landing.features.title')}
            </h2>
            <p className="mt-4 text-slate-500">{t('landing.features.subtitle')}</p>
          </Reveal>
          <RevealGroup className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {features.map(({ icon: Icon, color, bg, key }) => (
              <motion.div
                key={key}
                variants={fadeUp}
                whileHover={{ y: -4 }}
                className="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-200 hover:border-primary-200 hover:shadow-md"
              >
                <div className={`mb-4 flex h-11 w-11 items-center justify-center rounded-xl ${bg} ${color} transition-transform group-hover:scale-110`}>
                  <Icon className="h-5 w-5" />
                </div>
                <h3 className="mb-2 font-semibold text-slate-900">{t(`landing.features.${key}.title`)}</h3>
                <p className="text-sm leading-relaxed text-slate-500">{t(`landing.features.${key}.desc`)}</p>
              </motion.div>
            ))}
          </RevealGroup>
        </div>
      </section>

      {/* ── Industries ────────────────────────────────────────────────── */}
      <section id="industries" className="bg-slate-50 px-6 py-20">
        <div className="mx-auto max-w-6xl">
          <Reveal className="mb-12 text-center sm:mb-14">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary-600">
              <Building2 className="h-3.5 w-3.5" /> {t('landing.industries.eyebrow')}
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{t('landing.industries.title')}</h2>
            <p className="mt-4 text-slate-500">{t('landing.industries.subtitle')}</p>
          </Reveal>
          <RevealGroup className="grid gap-6 md:grid-cols-3">
            {industries.map(({ icon: Icon, color, border, iconColor, key }) => {
              const points = t(`landing.industries.${key}.points`, { returnObjects: true }) as string[]
              return (
                <motion.div
                  key={key}
                  variants={fadeUp}
                  whileHover={{ y: -4 }}
                  className={`relative overflow-hidden rounded-2xl border ${border} bg-gradient-to-b ${color} p-7 shadow-sm`}
                >
                  <div className={`mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm ${iconColor}`}>
                    <Icon className="h-6 w-6" />
                  </div>
                  <h3 className="mb-2 text-lg font-bold text-slate-900">{t(`landing.industries.${key}.title`)}</h3>
                  <p className="mb-5 text-sm text-slate-600">{t(`landing.industries.${key}.desc`)}</p>
                  <ul className="space-y-2">
                    {points.map((p) => (
                      <li key={p} className="flex items-center gap-2 text-sm text-slate-700">
                        <CheckCircle2 className={`h-4 w-4 shrink-0 ${iconColor}`} /> {p}
                      </li>
                    ))}
                  </ul>
                </motion.div>
              )
            })}
          </RevealGroup>
        </div>
      </section>

      {/* ── Pricing ───────────────────────────────────────────────────── */}
      <section id="pricing" className="px-6 py-20 sm:py-28">
        <div className="mx-auto max-w-6xl">
          <Reveal className="mb-12 text-center sm:mb-16">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary-600">
              <DollarSign className="h-3.5 w-3.5" /> {t('landing.pricing.eyebrow')}
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{t('landing.pricing.title')}</h2>
            <p className="mt-4 text-slate-500">{t('landing.pricing.subtitle')}</p>

            {/* Monthly / Annual toggle */}
            <div className="mt-8 inline-flex rounded-xl border border-slate-200 bg-slate-100 p-1">
              {(['monthly', 'annual'] as const).map((c) => (
                <button
                  key={c}
                  onClick={() => setBillingCycle(c)}
                  className={`relative rounded-lg px-4 py-2 text-sm font-medium transition-all sm:px-6 ${
                    billingCycle === c ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'
                  }`}
                >
                  {t(`landing.pricing.${c}`)}
                  {c === 'annual' && (
                    <span className="ms-2 rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-semibold text-primary-700">
                      {t('landing.pricing.save')}
                    </span>
                  )}
                </button>
              ))}
            </div>
          </Reveal>

          {plans.length === 0 ? (
            /* skeleton while loading */
            <div className="grid gap-6 sm:grid-cols-2 md:grid-cols-3">
              {[1,2,3].map((i) => (
                <div key={i} className="h-96 animate-pulse rounded-2xl border border-slate-200 bg-slate-50" />
              ))}
            </div>
          ) : (
            <RevealGroup className="grid gap-6 sm:grid-cols-2 md:grid-cols-3">
              {plans.map((plan) => {
                const isPopular = plan.id === 'pro'
                const accent    = planAccentClass(plan.id)
                const price     = billingCycle === 'annual' && plan.annual_price
                  ? Math.round(plan.annual_price / 12)
                  : plan.monthly_price
                const billedAs  = billingCycle === 'annual' && plan.annual_price
                  ? `$${plan.annual_price}/${t('landing.pricing.annual').toLowerCase()}`
                  : null

                return (
                  <motion.div
                    key={plan.id}
                    variants={fadeUp}
                    whileHover={{ y: -4 }}
                    className={`relative flex flex-col rounded-2xl border p-8 transition-all ${accent.border} ${
                      isPopular
                        ? 'bg-white shadow-xl shadow-primary-600/10 ring-1 ring-primary-100'
                        : 'bg-white shadow-sm hover:shadow-md'
                    }`}
                  >

                    {isPopular && (
                      <div className="absolute -top-3.5 start-1/2 -translate-x-1/2">
                        <span className="rounded-full bg-primary-600 px-4 py-1 text-xs font-bold text-white shadow-lg shadow-primary-600/25">
                          {t('landing.pricing.most_popular')}
                        </span>
                      </div>
                    )}

                    {/* Header */}
                    <div className="mb-6">
                      <h3 className="mb-2 text-xl font-bold text-slate-900">{plan.name}</h3>
                      <div className="flex items-baseline gap-1">
                        <span className="text-4xl font-extrabold text-slate-900">${price}</span>
                        <span className="text-slate-500">{t('landing.pricing.per_month')}</span>
                      </div>
                      {billedAs && <p className="mt-1 text-xs text-slate-500">{t('landing.pricing.billed_as', { amount: billedAs })}</p>}
                      <p className="mt-2 text-xs text-slate-500">
                        {plan.max_users    ? t('landing.pricing.users_limited', { count: plan.max_users }) : t('landing.pricing.users_unlimited')} ·{' '}
                        {plan.max_products ? t('landing.pricing.products_limited', { count: plan.max_products }) : t('landing.pricing.products_unlimited')}
                      </p>
                    </div>

                    {/* CTA */}
                    <Link
                      to={`/register?plan=${plan.id}`}
                      className={`mb-7 flex w-full items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold transition-all ${accent.btn}`}
                    >
                      {t('landing.pricing.start_trial', { days: plan.trial_days })}
                      <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" />
                    </Link>

                    {/* Features */}
                    <ul className="mt-auto space-y-3">
                      {(plan.features ?? []).map((f, fi) => {
                        const label = typeof f === 'object' ? (language === 'ar' ? f.ar : f.en) : f
                        const isExclusion = label.startsWith('−') || label.startsWith('-')
                        return (
                          <li key={fi} className="flex items-start gap-2.5 text-sm">
                            {isExclusion
                              ? <X className="mt-0.5 h-4 w-4 shrink-0 text-slate-300" />
                              : <Check className={`mt-0.5 h-4 w-4 shrink-0 ${accent.check}`} />
                            }
                            <span className={isExclusion ? 'text-slate-400' : 'text-slate-700'}>{label}</span>
                          </li>
                        )
                      })}
                    </ul>
                  </motion.div>
                )
              })}
            </RevealGroup>
          )}

          {/* Payment methods note */}
          <Reveal className="mt-12 text-center">
            <p className="text-sm text-slate-500">
              {t('landing.pricing.pay_manually')}{' '}
              <Link to="/payment" className="font-medium text-primary-600 underline underline-offset-4 transition-colors hover:text-primary-700">
                {t('landing.pricing.view_payment_methods')}
              </Link>
              {waLink && (
                <>
                  {' '}or{' '}
                  <a href={waLink} target="_blank" rel="noopener noreferrer" className="font-medium text-green-600 underline underline-offset-4 transition-colors hover:text-green-700">
                    contact us on WhatsApp
                  </a>
                </>
              )}
            </p>
          </Reveal>
        </div>
      </section>

      {/* ── Technology / Trust ────────────────────────────────────────── */}
      <section id="technology" className="bg-slate-50 px-6 py-20">
        <div className="mx-auto max-w-6xl">
          <Reveal className="mb-14 text-center">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary-600">
              <Shield className="h-3.5 w-3.5" /> {t('landing.technology.eyebrow')}
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{t('landing.technology.title')}</h2>
            <p className="mt-4 text-slate-500">{t('landing.technology.subtitle')}</p>
          </Reveal>
          <RevealGroup className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {trustItems.map(({ icon: Icon, key }) => (
              <motion.div key={key} variants={fadeUp} whileHover={{ y: -4 }} className="flex gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                  <Icon className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="mb-1.5 font-semibold text-slate-900">{t(`landing.technology.${key}.title`)}</h3>
                  <p className="text-sm leading-relaxed text-slate-500">{t(`landing.technology.${key}.desc`)}</p>
                </div>
              </motion.div>
            ))}
          </RevealGroup>
        </div>
      </section>

      {/* ── CTA banner ────────────────────────────────────────────────── */}
      <section className="px-6 py-20">
        <Reveal className="relative mx-auto max-w-4xl overflow-hidden rounded-3xl bg-gradient-to-br from-primary-600 via-primary-700 to-[#132a4c] p-8 text-center shadow-xl shadow-primary-600/20 sm:p-14">
          <div className="pointer-events-none absolute inset-0 -top-20 mx-auto h-64 w-64 rounded-full bg-white/10 blur-3xl" style={{ left: '50%', transform: 'translateX(-50%)' }} />
          <div className="relative">
            <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
              <Store className="h-7 w-7 text-white" />
            </div>
            <h2 className="mb-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{t('landing.cta.title')}</h2>
            <p className="mb-8 text-primary-100">{t('landing.cta.subtitle')}</p>
            <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
              <Link to="/register" className="flex w-full items-center justify-center gap-2 rounded-xl bg-white px-8 py-3.5 font-semibold text-primary-700 shadow-lg transition-all hover:bg-primary-50 sm:w-auto">
                {t('landing.cta.primary')} <ArrowRight className="h-4 w-4 rtl:rotate-180" />
              </Link>
              <Link to="/login" className="text-sm font-medium text-primary-100 underline underline-offset-4 transition-colors hover:text-white">
                {t('landing.cta.secondary')}
              </Link>
            </div>
          </div>
        </Reveal>
      </section>

      {/* ── Footer ────────────────────────────────────────────────────── */}
      <footer className="border-t border-slate-800 bg-[#132a4c] px-6 py-14 text-slate-400">
        <div className="mx-auto max-w-7xl">
          <div className="mb-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
            <div className="lg:col-span-2">
              <div className="mb-4 flex items-center gap-3">
                <img src={`${import.meta.env.BASE_URL}images/nuqtah_logo_transparent_original.png`} alt="Nuqtah POS" className="h-11 w-auto brightness-0 invert" />
              </div>
              <p className="max-w-xs text-sm leading-relaxed text-slate-400">
                {t('landing.footer.tagline')}
              </p>
              {waLink && (
                <a href={waLink} target="_blank" rel="noopener noreferrer"
                  className="mt-4 inline-flex items-center gap-2 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-2 text-sm font-medium text-green-400 transition-colors hover:bg-green-500/20"
                >
                  <WhatsAppIcon className="h-4 w-4" /> {t('landing.footer.chat')}
                </a>
              )}
            </div>

            <div>
              <h4 className="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-500">{t('landing.footer.product')}</h4>
              <ul className="space-y-3 text-sm text-slate-400">
                <li><a href="#features"   className="transition-colors hover:text-white">{t('landing.nav.features')}</a></li>
                <li><a href="#industries" className="transition-colors hover:text-white">{t('landing.nav.industries')}</a></li>
                <li><a href="#pricing"    className="transition-colors hover:text-white">{t('landing.nav.pricing')}</a></li>
                <li><a href="#technology" className="transition-colors hover:text-white">{t('landing.footer.security')}</a></li>
              </ul>
            </div>

            <div>
              <h4 className="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-500">{t('landing.footer.account')}</h4>
              <ul className="space-y-3 text-sm text-slate-400">
                <li><Link to="/login"    className="transition-colors hover:text-white">{t('landing.nav.login')}</Link></li>
                <li><Link to="/register" className="transition-colors hover:text-white">{t('landing.footer.create_store')}</Link></li>
                <li><Link to="/payment"  className="transition-colors hover:text-white">{t('landing.pricing.view_payment_methods')}</Link></li>
              </ul>
            </div>

            <div>
              <h4 className="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-500">{t('landing.footer.legal')}</h4>
              <ul className="space-y-3 text-sm text-slate-400">
                <li><a href="#" className="transition-colors hover:text-white">{t('landing.footer.privacy')}</a></li>
                <li><a href="#" className="transition-colors hover:text-white">{t('landing.footer.terms')}</a></li>
              </ul>
            </div>
          </div>

          <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-800 pt-8 text-xs text-slate-500 sm:flex-row">
            <p>© {new Date().getFullYear()} Nuqtah POS. {t('landing.footer.rights')}</p>
            <p>{t('landing.footer.built_with')}</p>
          </div>
        </div>
      </footer>

      {/* ── Floating WhatsApp button ───────────────────────────────────── */}
      {waLink && (
        <a
          href={waLink}
          target="_blank"
          rel="noopener noreferrer"
          className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] shadow-xl shadow-green-500/30 transition-transform hover:scale-110"
          title={t('payment_page.chat_whatsapp')}
        >
          <WhatsAppIcon className="h-7 w-7 text-white" />
        </a>
      )}

      {/* ── Scroll-to-top button ─────────────────────────────────────── */}
      <AnimatePresence>
        {showScrollTop && (
          <motion.button
            type="button"
            onClick={scrollToTop}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 10 }}
            className={`fixed z-50 flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-lg transition-colors hover:bg-slate-50 hover:text-slate-900 ${waLink ? 'bottom-24 right-6' : 'bottom-6 right-6'}`}
            title={t('landing.back_to_top')}
          >
            <ArrowUp className="h-5 w-5" />
          </motion.button>
        )}
      </AnimatePresence>

    </div>
  )
}
