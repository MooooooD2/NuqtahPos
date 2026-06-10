import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Store, BarChart3, ShoppingCart, Package, Users, FileText,
  DollarSign, Pill, ChefHat, MessageSquare, TrendingUp, Shield,
  Globe, Zap, ArrowRight, CheckCircle2, Sparkles, WifiOff, Lock,
  Server, Monitor, Building2, Coffee, Layers, Database, Check, X,
} from 'lucide-react'
import { api } from '@/services/api'

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
interface ContactInfo {
  name: string
  phone: string
  email: string
  whatsapp: string
}

/* ─── Static data ───────────────────────────────────────────────────────── */

const stats = [
  { value: '500+', label: 'Active stores' },
  { value: '10M+', label: 'Transactions processed' },
  { value: '99.9%', label: 'Uptime SLA' },
  { value: '50+',  label: 'Built-in features' },
]

const features = [
  { icon: ShoppingCart,  color: 'text-sky-400',    bg: 'bg-sky-500/15',    title: 'Point of Sale',         desc: 'Blazing-fast POS with barcode scanning, split payments, multi-currency, and held orders.' },
  { icon: Package,       color: 'text-violet-400', bg: 'bg-violet-500/15', title: 'Inventory & Warehouse',  desc: 'Real-time stock across multiple warehouses with low-stock alerts and transfer requests.' },
  { icon: BarChart3,     color: 'text-emerald-400',bg: 'bg-emerald-500/15',title: 'Analytics & Reports',   desc: 'Profit, sales, and financial reports with interactive charts and exportable data.' },
  { icon: DollarSign,    color: 'text-yellow-400', bg: 'bg-yellow-500/15', title: 'Accounting',             desc: 'Full double-entry ledger, journal entries, fiscal periods, and financial statements.' },
  { icon: Users,         color: 'text-pink-400',   bg: 'bg-pink-500/15',   title: 'CRM & Customers',       desc: 'Customer loyalty, groups, purchase history, and targeted promotions.' },
  { icon: FileText,      color: 'text-orange-400', bg: 'bg-orange-500/15', title: 'Invoices & Returns',    desc: 'Professional invoices, proforma quotes, and streamlined return workflows.' },
  { icon: Pill,          color: 'text-teal-400',   bg: 'bg-teal-500/15',   title: 'Pharmacy Module',       desc: 'Batch tracking, expiry alerts, and prescription management built in.' },
  { icon: ChefHat,       color: 'text-rose-400',   bg: 'bg-rose-500/15',   title: 'Kitchen Display',       desc: 'Live order routing to kitchen screens, table QR ordering for restaurants.' },
  { icon: MessageSquare, color: 'text-green-400',  bg: 'bg-green-500/15',  title: 'WhatsApp Integration',  desc: 'Send invoices and alerts directly to customers over WhatsApp.' },
  { icon: TrendingUp,    color: 'text-indigo-400', bg: 'bg-indigo-500/15', title: 'AI Forecasting',        desc: 'Predict demand and optimise purchasing with machine-learning insights.' },
  { icon: Shield,        color: 'text-sky-400',    bg: 'bg-sky-500/15',    title: 'Roles & Permissions',   desc: 'Granular RBAC with customisable roles, per-module access control.' },
  { icon: Globe,         color: 'text-cyan-400',   bg: 'bg-cyan-500/15',   title: 'Arabic / English',      desc: 'Full bilingual support with automatic RTL layout switching.' },
]

const industries = [
  {
    icon: Building2, color: 'from-sky-500/20 to-sky-600/5', border: 'border-sky-500/20', iconColor: 'text-sky-400',
    title: 'Retail & Supermarkets',
    desc: 'Multi-branch retail operations, from corner shops to supermarket chains.',
    points: ['Barcode & RFID scanning', 'Multi-warehouse transfers', 'Pricing rules & cashback', 'Supplier purchase orders'],
  },
  {
    icon: Coffee, color: 'from-rose-500/20 to-rose-600/5', border: 'border-rose-500/20', iconColor: 'text-rose-400',
    title: 'Restaurants & Cafés',
    desc: 'Table management, kitchen routing, and QR-code ordering in one platform.',
    points: ['Kitchen display screens', 'QR table ordering', 'Recipe & waste tracking', 'Shift management'],
  },
  {
    icon: Pill, color: 'from-teal-500/20 to-teal-600/5', border: 'border-teal-500/20', iconColor: 'text-teal-400',
    title: 'Pharmacies',
    desc: 'Built-in pharmacy workflows for compliant, safe medication management.',
    points: ['Batch & expiry tracking', 'Prescription management', 'Controlled substance logs', 'Supplier integration'],
  },
]

const trustItems = [
  { icon: WifiOff,  title: 'Offline-First Desktop',  desc: 'The Tauri desktop app keeps working with a local SQLite database and syncs automatically when back online.' },
  { icon: Database, title: 'Isolated Multi-Tenancy',  desc: 'Every store runs in its own database. Complete data isolation out of the box — no shared tables.' },
  { icon: Lock,     title: 'Enterprise Security',     desc: 'Sanctum tokens, two-factor authentication, rate limiting, full audit logging, and device session management.' },
  { icon: Server,   title: 'Docker Deployment',       desc: 'Ship to any cloud in minutes. One docker-compose file launches the API, frontend, MySQL and Redis.' },
  { icon: Layers,   title: 'API-First Architecture',  desc: 'A clean REST API powers both the web SPA and the desktop app — ready for custom integrations.' },
  { icon: Monitor,  title: 'Web + Desktop + Mobile',  desc: 'One codebase — deploy as a progressive web app or package it as a native desktop executable.' },
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
  { label: 'Cash',   pct: '45%', cls: 'bg-sky-500',    w: 'w-[45%]' },
  { label: 'Card',   pct: '35%', cls: 'bg-violet-500', w: 'w-[35%]' },
  { label: 'Online', pct: '20%', cls: 'bg-emerald-500',w: 'w-[20%]' },
]

const recentRows = [
  { id: '#INV-4821', customer: 'Ahmed Khalil', amount: '$320.00', status: 'Paid' },
  { id: '#INV-4820', customer: 'Sara Mohamed', amount: '$85.50',  status: 'Paid' },
  { id: '#INV-4819', customer: 'John Smith',   amount: '$210.00', status: 'Pending' },
]

function DashboardMockup() {
  return (
    <div className="relative mx-auto mt-16 max-w-5xl px-4">
      <div className="pointer-events-none absolute -inset-4 rounded-3xl bg-sky-500/10 blur-3xl" />
      <div className="relative overflow-hidden rounded-2xl border border-white/10 bg-[#0b1120] shadow-[0_32px_80px_-12px_rgba(0,0,0,0.8)] ring-1 ring-white/5">
        <div className="flex items-center gap-2 border-b border-white/10 bg-slate-900/70 px-4 py-3">
          <div className="h-3 w-3 rounded-full bg-red-500/70" />
          <div className="h-3 w-3 rounded-full bg-yellow-500/70" />
          <div className="h-3 w-3 rounded-full bg-green-500/70" />
          <div className="mx-3 flex flex-1 items-center gap-2 rounded-md bg-slate-800/60 px-3 py-1">
            <div className="h-2.5 w-2.5 rounded-full bg-green-400/70" />
            <span className="text-xs text-slate-500">app.pos-enterprise.com/dashboard</span>
          </div>
        </div>
        <div className="flex" style={{ height: '340px' }}>
          <div className="flex w-12 flex-col items-center gap-3 border-r border-white/10 bg-[#070d1a] py-4">
            <div className="h-8 w-8 rounded-xl bg-sky-500/80 flex items-center justify-center">
              <Store className="h-4 w-4 text-white" />
            </div>
            {[ShoppingCart, Package, FileText, Users, BarChart3].map((Icon, i) => (
              <div key={i} className="flex h-8 w-8 items-center justify-center rounded-lg bg-white/5 text-slate-500">
                <Icon className="h-3.5 w-3.5" />
              </div>
            ))}
          </div>
          <div className="flex-1 overflow-hidden p-4">
            <div className="mb-3 grid grid-cols-4 gap-2">
              {kpis.map((k) => (
                <div key={k.label} className="rounded-xl border border-white/8 bg-white/4 p-3">
                  <div className="mb-1 text-[10px] text-slate-500">{k.label}</div>
                  <div className="text-sm font-bold text-white">{k.value}</div>
                  {k.trend && <div className={`text-[10px] font-medium ${k.up ? 'text-emerald-400' : 'text-red-400'}`}>{k.trend}</div>}
                </div>
              ))}
            </div>
            <div className="mb-3 grid grid-cols-3 gap-2">
              <div className="col-span-2 rounded-xl border border-white/8 bg-white/4 p-3">
                <div className="mb-2 text-[10px] text-slate-500">Sales — Last 30 days</div>
                <div className="flex h-20 items-end gap-px">
                  {barHeights.map((h, i) => (
                    <div key={i} className="flex-1 rounded-t-[1px] bg-sky-500/50 hover:bg-sky-400/70 transition-colors" style={{ height: `${h}%` }} />
                  ))}
                </div>
              </div>
              <div className="rounded-xl border border-white/8 bg-white/4 p-3">
                <div className="mb-3 text-[10px] text-slate-500">Payment Methods</div>
                <div className="space-y-2">
                  {payMethods.map((p) => (
                    <div key={p.label}>
                      <div className="mb-0.5 flex justify-between text-[9px]">
                        <span className="text-slate-400">{p.label}</span>
                        <span className="font-semibold text-white">{p.pct}</span>
                      </div>
                      <div className="h-1.5 w-full rounded-full bg-white/10">
                        <div className={`h-full rounded-full ${p.cls} ${p.w}`} />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            <div className="rounded-xl border border-white/8 bg-white/4 p-3">
              <div className="mb-2 text-[10px] text-slate-500">Recent Invoices</div>
              <div className="space-y-1.5">
                {recentRows.map((r) => (
                  <div key={r.id} className="flex items-center gap-3 text-[10px]">
                    <span className="font-mono text-sky-400">{r.id}</span>
                    <span className="flex-1 text-slate-400">{r.customer}</span>
                    <span className="font-semibold text-white">{r.amount}</span>
                    <span className={`rounded-full px-2 py-0.5 text-[9px] font-medium ${r.status === 'Paid' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-yellow-500/15 text-yellow-400'}`}>
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
  if (id === 'pro')        return { border: 'border-sky-500/50',    badge: 'bg-sky-600',        btn: 'bg-sky-600 hover:bg-sky-500 shadow-lg shadow-sky-600/25 text-white',    check: 'text-sky-400' }
  if (id === 'enterprise') return { border: 'border-violet-500/30', badge: 'bg-violet-600',     btn: 'bg-violet-600 hover:bg-violet-500 shadow-lg shadow-violet-600/25 text-white', check: 'text-violet-400' }
  return { border: 'border-white/8', badge: '', btn: 'border border-white/15 bg-white/8 hover:bg-white/15 text-white', check: 'text-slate-400' }
}

/* ─── Page ─────────────────────────────────────────────────────────────── */

export default function LandingPage() {
  const [plans, setPlans] = useState<Plan[]>([])
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'annual'>('monthly')
  const [contact, setContact] = useState<ContactInfo | null>(null)

  useEffect(() => {
    api.get('/public/plans').then((r) => setPlans(r.data.plans ?? [])).catch(() => {})
    api.get('/public/contact').then((r) => setContact(r.data)).catch(() => {})
  }, [])

  const waLink = contact?.whatsapp
    ? `https://wa.me/${contact.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent('Hello! I\'m interested in POS Enterprise.')}`
    : null

  return (
    <div className="min-h-screen bg-[#060b18] text-white antialiased">

      {/* Dot-grid texture */}
      <div
        className="pointer-events-none fixed inset-0 opacity-[0.03]"
        style={{ backgroundImage: 'radial-gradient(circle, #ffffff 1px, transparent 1px)', backgroundSize: '28px 28px' }}
      />

      {/* ── Navigation ────────────────────────────────────────────────── */}
      <nav className="relative z-50 border-b border-white/8 bg-[#060b18]/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-500 shadow-lg shadow-sky-500/30">
              <Store className="h-5 w-5 text-white" />
            </div>
            <span className="text-lg font-bold tracking-tight text-white">POS Enterprise</span>
          </div>

          <div className="hidden items-center gap-6 text-sm text-slate-400 md:flex">
            <a href="#features"    className="transition-colors hover:text-white">Features</a>
            <a href="#industries"  className="transition-colors hover:text-white">Industries</a>
            <a href="#pricing"     className="transition-colors hover:text-white">Pricing</a>
            <a href="#technology"  className="transition-colors hover:text-white">Technology</a>
          </div>

          <div className="flex items-center gap-3">
            <Link to="/login" className="rounded-lg px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:text-white">
              Log in
            </Link>
            <Link to="/register" className="flex items-center gap-1.5 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-sky-600/20 transition-all hover:bg-sky-500">
              Get started free <ArrowRight className="h-3.5 w-3.5" />
            </Link>
          </div>
        </div>
      </nav>

      {/* ── Hero ──────────────────────────────────────────────────────── */}
      <section className="relative overflow-hidden pb-8 pt-24 text-center">
        <div className="pointer-events-none absolute -top-40 left-1/4 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-sky-600/10 blur-3xl" />
        <div className="pointer-events-none absolute -top-20 right-1/4 h-[400px] w-[400px] translate-x-1/2 rounded-full bg-violet-600/8 blur-3xl" />

        <div className="relative mx-auto max-w-4xl px-6">
          <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-sky-500/25 bg-sky-500/10 px-4 py-1.5 text-sm text-sky-300">
            <Sparkles className="h-3.5 w-3.5" />
            Enterprise-grade POS — Built for scale
          </div>

          <h1 className="mb-5 text-5xl font-extrabold leading-[1.1] tracking-tight md:text-7xl">
            <span className="bg-gradient-to-br from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
              The POS system
            </span>
            <br />
            <span className="bg-gradient-to-r from-sky-400 via-cyan-300 to-sky-400 bg-clip-text text-transparent">
              your business deserves
            </span>
          </h1>

          <p className="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-slate-400">
            Manage sales, inventory, accounting, and your entire team from a single
            beautifully designed platform — available on web and as a native desktop app,
            online and <span className="text-slate-300">offline</span>.
          </p>

          <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
            <Link to="/register" className="flex w-full items-center justify-center gap-2 rounded-xl bg-sky-600 px-8 py-3.5 text-base font-semibold text-white shadow-xl shadow-sky-600/25 transition-all hover:bg-sky-500 hover:shadow-sky-500/30 sm:w-auto">
              Create your free store <ArrowRight className="h-4 w-4" />
            </Link>
            <Link to="/login" className="flex w-full items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/8 px-8 py-3.5 text-base font-semibold text-white backdrop-blur transition-all hover:bg-white/12 sm:w-auto">
              Sign in to your store
            </Link>
          </div>

          <p className="mt-4 text-xs text-slate-600">No credit card required · Setup in under 60 seconds</p>
        </div>

        <DashboardMockup />
      </section>

      {/* ── Stats ─────────────────────────────────────────────────────── */}
      <section className="relative z-10 mt-20 border-y border-white/8 bg-white/3 px-6 py-10">
        <div className="mx-auto max-w-4xl">
          <dl className="grid grid-cols-2 gap-8 md:grid-cols-4">
            {stats.map(({ value, label }) => (
              <div key={label} className="text-center">
                <dt className="mb-1 text-3xl font-extrabold tracking-tight text-white">{value}</dt>
                <dd className="text-sm text-slate-500">{label}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>

      {/* ── Features ──────────────────────────────────────────────────── */}
      <section id="features" className="px-6 py-28">
        <div className="mx-auto max-w-7xl">
          <div className="mb-16 text-center">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-sky-400">
              <Zap className="h-3.5 w-3.5" /> Everything included
            </div>
            <h2 className="text-4xl font-extrabold tracking-tight">
              <span className="bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">
                Built for every kind of business
              </span>
            </h2>
            <p className="mt-4 text-slate-500">From small retail shops to multi-branch restaurant chains and pharmacies — all in one platform.</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {features.map(({ icon: Icon, color, bg, title, desc }) => (
              <div key={title} className="group relative overflow-hidden rounded-2xl border border-white/8 bg-white/3 p-6 transition-all duration-200 hover:border-white/15 hover:bg-white/6">
                <div className={`mb-4 flex h-11 w-11 items-center justify-center rounded-xl ${bg} ${color} transition-transform group-hover:scale-110`}>
                  <Icon className="h-5 w-5" />
                </div>
                <h3 className="mb-2 font-semibold text-white">{title}</h3>
                <p className="text-sm leading-relaxed text-slate-500">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Industries ────────────────────────────────────────────────── */}
      <section id="industries" className="px-6 py-20">
        <div className="mx-auto max-w-6xl">
          <div className="mb-14 text-center">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-sky-400">
              <Building2 className="h-3.5 w-3.5" /> Industries
            </div>
            <h2 className="text-4xl font-extrabold tracking-tight text-white">Tailored for your sector</h2>
            <p className="mt-4 text-slate-500">Specialised workflows out of the box — no plugins required.</p>
          </div>
          <div className="grid gap-6 md:grid-cols-3">
            {industries.map(({ icon: Icon, color, border, iconColor, title, desc, points }) => (
              <div key={title} className={`relative overflow-hidden rounded-2xl border ${border} bg-gradient-to-b ${color} p-7`}>
                <div className={`mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/8 ${iconColor}`}>
                  <Icon className="h-6 w-6" />
                </div>
                <h3 className="mb-2 text-lg font-bold text-white">{title}</h3>
                <p className="mb-5 text-sm text-slate-400">{desc}</p>
                <ul className="space-y-2">
                  {points.map((p) => (
                    <li key={p} className="flex items-center gap-2 text-sm text-slate-300">
                      <CheckCircle2 className={`h-4 w-4 shrink-0 ${iconColor}`} /> {p}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Pricing ───────────────────────────────────────────────────── */}
      <section id="pricing" className="px-6 py-28">
        <div className="mx-auto max-w-6xl">
          <div className="mb-16 text-center">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-sky-400">
              <DollarSign className="h-3.5 w-3.5" /> Pricing
            </div>
            <h2 className="text-4xl font-extrabold tracking-tight text-white">Simple, transparent pricing</h2>
            <p className="mt-4 text-slate-500">Start your free trial. No credit card required.</p>

            {/* Monthly / Annual toggle */}
            <div className="mt-8 inline-flex rounded-xl border border-white/10 bg-white/5 p-1">
              {(['monthly', 'annual'] as const).map((c) => (
                <button
                  key={c}
                  onClick={() => setBillingCycle(c)}
                  className={`relative rounded-lg px-6 py-2 text-sm font-medium transition-all capitalize ${
                    billingCycle === c ? 'bg-sky-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                  }`}
                >
                  {c}
                  {c === 'annual' && (
                    <span className="ml-2 rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">
                      Save 17%
                    </span>
                  )}
                </button>
              ))}
            </div>
          </div>

          {plans.length === 0 ? (
            /* skeleton while loading */
            <div className="grid gap-6 md:grid-cols-3">
              {[1,2,3].map((i) => (
                <div key={i} className="h-96 animate-pulse rounded-2xl border border-white/8 bg-white/3" />
              ))}
            </div>
          ) : (
            <div className="grid gap-6 md:grid-cols-3">
              {plans.map((plan) => {
                const isPopular = plan.id === 'pro'
                const accent    = planAccentClass(plan.id)
                const price     = billingCycle === 'annual' && plan.annual_price
                  ? Math.round(plan.annual_price / 12)
                  : plan.monthly_price
                const billedAs  = billingCycle === 'annual' && plan.annual_price
                  ? `$${plan.annual_price}/year`
                  : null

                return (
                  <div
                    key={plan.id}
                    className={`relative flex flex-col rounded-2xl border p-8 transition-all ${accent.border} ${
                      isPopular
                        ? 'bg-gradient-to-b from-sky-950/60 to-slate-900/80 shadow-xl shadow-sky-500/10'
                        : 'bg-white/3 hover:bg-white/5'
                    }`}
                  >
                    {isPopular && (
                      <div className="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <span className="rounded-full bg-sky-600 px-4 py-1 text-xs font-bold text-white shadow-lg shadow-sky-600/30">
                          Most Popular
                        </span>
                      </div>
                    )}

                    {/* Header */}
                    <div className="mb-6">
                      <h3 className="mb-2 text-xl font-bold text-white">{plan.name}</h3>
                      <div className="flex items-baseline gap-1">
                        <span className="text-4xl font-extrabold text-white">${price}</span>
                        <span className="text-slate-500">/mo</span>
                      </div>
                      {billedAs && <p className="mt-1 text-xs text-slate-500">Billed as {billedAs}</p>}
                      <p className="mt-2 text-xs text-slate-500">
                        {plan.max_users    ? `Up to ${plan.max_users} users` : 'Unlimited users'} ·{' '}
                        {plan.max_products ? `Up to ${plan.max_products} products` : 'Unlimited products'}
                      </p>
                    </div>

                    {/* CTA */}
                    <Link
                      to={`/register?plan=${plan.id}`}
                      className={`mb-7 flex w-full items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold transition-all ${accent.btn}`}
                    >
                      Start {plan.trial_days}-day free trial
                      <ArrowRight className="h-3.5 w-3.5" />
                    </Link>

                    {/* Features */}
                    <ul className="mt-auto space-y-3">
                      {(plan.features ?? []).map((f, fi) => {
                        const label = typeof f === 'object' ? f.en : f
                        const isExclusion = label.startsWith('−') || label.startsWith('-')
                        return (
                          <li key={fi} className="flex items-start gap-2.5 text-sm">
                            {isExclusion
                              ? <X className="mt-0.5 h-4 w-4 shrink-0 text-slate-600" />
                              : <Check className={`mt-0.5 h-4 w-4 shrink-0 ${accent.check}`} />
                            }
                            <span className={isExclusion ? 'text-slate-600' : 'text-slate-300'}>{label}</span>
                          </li>
                        )
                      })}
                    </ul>
                  </div>
                )
              })}
            </div>
          )}

          {/* Payment methods note */}
          <div className="mt-12 text-center">
            <p className="text-sm text-slate-500">
              Prefer to pay manually?{' '}
              <Link to="/payment" className="font-medium text-sky-400 underline underline-offset-4 transition-colors hover:text-sky-300">
                View payment methods
              </Link>
              {waLink && (
                <>
                  {' '}or{' '}
                  <a href={waLink} target="_blank" rel="noopener noreferrer" className="font-medium text-green-400 underline underline-offset-4 transition-colors hover:text-green-300">
                    contact us on WhatsApp
                  </a>
                </>
              )}
            </p>
          </div>
        </div>
      </section>

      {/* ── Technology / Trust ────────────────────────────────────────── */}
      <section id="technology" className="px-6 py-20">
        <div className="mx-auto max-w-6xl">
          <div className="mb-14 text-center">
            <div className="mb-3 flex items-center justify-center gap-2 text-xs font-semibold uppercase tracking-widest text-sky-400">
              <Shield className="h-3.5 w-3.5" /> Architecture & Security
            </div>
            <h2 className="text-4xl font-extrabold tracking-tight text-white">Enterprise-grade foundations</h2>
            <p className="mt-4 text-slate-500">Production-ready from day one. No duct tape required.</p>
          </div>
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {trustItems.map(({ icon: Icon, title, desc }) => (
              <div key={title} className="flex gap-4 rounded-2xl border border-white/8 bg-white/3 p-6">
                <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-400">
                  <Icon className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="mb-1.5 font-semibold text-white">{title}</h3>
                  <p className="text-sm leading-relaxed text-slate-500">{desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA banner ────────────────────────────────────────────────── */}
      <section className="px-6 py-20">
        <div className="relative mx-auto max-w-4xl overflow-hidden rounded-3xl border border-sky-500/20 bg-gradient-to-br from-sky-950/80 via-slate-900 to-violet-950/50 p-14 text-center">
          <div className="pointer-events-none absolute inset-0 -top-20 mx-auto h-64 w-64 rounded-full bg-sky-500/15 blur-3xl" style={{ left: '50%', transform: 'translateX(-50%)' }} />
          <div className="relative">
            <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-500 shadow-xl shadow-sky-500/30">
              <Store className="h-7 w-7 text-white" />
            </div>
            <h2 className="mb-3 text-4xl font-extrabold tracking-tight text-white">Ready to transform your business?</h2>
            <p className="mb-8 text-slate-400">
              Join hundreds of stores already running on POS Enterprise.
              <br className="hidden sm:block" />
              Set up your account in under 60 seconds.
            </p>
            <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
              <Link to="/register" className="flex items-center gap-2 rounded-xl bg-sky-600 px-8 py-3.5 font-semibold text-white shadow-lg shadow-sky-600/25 transition-all hover:bg-sky-500">
                Create your free store <ArrowRight className="h-4 w-4" />
              </Link>
              <Link to="/login" className="text-sm font-medium text-slate-400 underline underline-offset-4 transition-colors hover:text-white">
                Already have an account? Log in →
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* ── Footer ────────────────────────────────────────────────────── */}
      <footer className="border-t border-white/8 px-6 py-14">
        <div className="mx-auto max-w-7xl">
          <div className="mb-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
            <div className="lg:col-span-2">
              <div className="mb-4 flex items-center gap-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-500">
                  <Store className="h-5 w-5 text-white" />
                </div>
                <span className="font-bold text-white">POS Enterprise</span>
              </div>
              <p className="max-w-xs text-sm leading-relaxed text-slate-500">
                The all-in-one enterprise point of sale platform for modern businesses. Built with Laravel, React, and Tauri.
              </p>
              {waLink && (
                <a href={waLink} target="_blank" rel="noopener noreferrer"
                  className="mt-4 inline-flex items-center gap-2 rounded-lg border border-green-500/25 bg-green-500/10 px-4 py-2 text-sm font-medium text-green-400 transition-colors hover:bg-green-500/20"
                >
                  <WhatsAppIcon className="h-4 w-4" /> Chat with us
                </a>
              )}
            </div>

            <div>
              <h4 className="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">Product</h4>
              <ul className="space-y-3 text-sm text-slate-500">
                <li><a href="#features"   className="transition-colors hover:text-white">Features</a></li>
                <li><a href="#industries" className="transition-colors hover:text-white">Industries</a></li>
                <li><a href="#pricing"    className="transition-colors hover:text-white">Pricing</a></li>
                <li><a href="#technology" className="transition-colors hover:text-white">Security</a></li>
              </ul>
            </div>

            <div>
              <h4 className="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">Account</h4>
              <ul className="space-y-3 text-sm text-slate-500">
                <li><Link to="/login"    className="transition-colors hover:text-white">Log in</Link></li>
                <li><Link to="/register" className="transition-colors hover:text-white">Create store</Link></li>
                <li><Link to="/payment"  className="transition-colors hover:text-white">Payment methods</Link></li>
              </ul>
            </div>

            <div>
              <h4 className="mb-4 text-xs font-semibold uppercase tracking-widest text-slate-400">Legal</h4>
              <ul className="space-y-3 text-sm text-slate-500">
                {['Privacy Policy', 'Terms of Service'].map((l) => (
                  <li key={l}><a href="#" className="transition-colors hover:text-white">{l}</a></li>
                ))}
              </ul>
            </div>
          </div>

          <div className="flex flex-col items-center justify-between gap-3 border-t border-white/8 pt-8 text-xs text-slate-600 sm:flex-row">
            <p>© {new Date().getFullYear()} POS Enterprise. All rights reserved.</p>
            <p>Built with Laravel · React 19 · Tauri · TypeScript</p>
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
          title="Chat on WhatsApp"
        >
          <WhatsAppIcon className="h-7 w-7 text-white" />
        </a>
      )}

    </div>
  )
}
