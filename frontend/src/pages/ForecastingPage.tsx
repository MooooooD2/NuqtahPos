import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '@/services/api'
import { usePermission } from '@/hooks/usePermission'
import LoadingSpinner from '@/components/common/LoadingSpinner'
import { TrendingUp, Package, AlertTriangle, BarChart2, RefreshCw } from 'lucide-react'
import { clsx } from 'clsx'

type Tab = 'sales' | 'demand' | 'depletion'

interface ForecastPoint { date: string; forecast: string; lower_ci: string; upper_ci: string; day_of_week?: string }
interface SalesForecastData {
  forecasts: ForecastPoint[]
  total_forecast: string
  avg_daily: string
  trend: string
  accuracy_pct: string
  history_used: number
  generated_at: string
  error?: string
}

interface ProductForecast { product_name: string; avg_daily_qty: string; forecast_30_days: string; current_stock: number; days_stock_left: number; needs_reorder: boolean }
interface StockDepletion { urgency: string; product_name: string; current_stock: number; daily_rate: string; days_remaining: number; depleted_on?: string; reorder_qty: number }

export default function ForecastingPage() {
  const { t } = useTranslation('pos')
  const { hasPermission } = usePermission()
  const [tab, setTab] = useState<Tab>('sales')
  const [forecastDays, setForecastDays] = useState('30')
  const [historicalDays, setHistoricalDays] = useState('90')
  const [salesTriggered, setSalesTriggered] = useState(false)

  const salesQuery = useQuery({
    queryKey: ['forecast-sales', forecastDays, historicalDays],
    queryFn: () => apiGet<{ success: boolean; data: SalesForecastData }>('/forecast/sales', { forecast_days: forecastDays, historical_days: historicalDays }),
    enabled: salesTriggered,
    staleTime: 120_000,
  })

  const demandQuery = useQuery({
    queryKey: ['forecast-products'],
    queryFn: () => apiGet<{ success: boolean; data: { products: ProductForecast[]; generated_at: string } }>('/forecast/products'),
    enabled: tab === 'demand',
    staleTime: 120_000,
  })

  const depletionQuery = useQuery({
    queryKey: ['forecast-stock'],
    queryFn: () => apiGet<{ success: boolean; data: { alerts: StockDepletion[]; total_at_risk: number; critical: number; generated_at: string } }>('/forecast/stock'),
    enabled: tab === 'depletion',
    staleTime: 120_000,
  })

  const handleGenerate = () => {
    setSalesTriggered(true)
    salesQuery.refetch()
  }

  const trendLabel: Record<string, string> = { growing: t('trend_growing'), declining: t('trend_declining'), stable: t('trend_stable') }
  const trendBadge = (trend: string) => {
    const label = trendLabel[trend] ?? trend
    if (trend === 'growing') return <span className="badge badge-success">{label}</span>
    if (trend === 'declining') return <span className="badge badge-danger">{label}</span>
    return <span className="badge badge-info">{label}</span>
  }

  const daysLeftBadge = (days: number) => {
    const label = `${days} ${t('days')}`
    if (days <= 3) return <span className="badge badge-danger">{label}</span>
    if (days <= 7) return <span className="badge badge-warning">{label}</span>
    return <span className="badge badge-success">{label}</span>
  }

  const urgencyLabel: Record<string, string> = { critical: t('urgency_critical'), high: t('urgency_high'), medium: t('urgency_medium'), low: t('urgency_low') }
  const urgencyBadge = (urgency: string) => {
    const map: Record<string, string> = { critical: 'badge-danger', high: 'badge-warning', medium: 'badge-warning', low: 'badge-success' }
    const style = map[urgency] ?? 'badge-gray'
    return <span className={clsx('badge', style)}>{urgencyLabel[urgency] ?? urgency}</span>
  }

  const salesData = salesQuery.data?.data
  const forecastRows = salesData?.forecasts ?? []
  const demandRows = demandQuery.data?.data?.products ?? []
  const depletionRows = depletionQuery.data?.data?.alerts ?? []

  if (!hasPermission('view_reports')) {
    return (
      <div className="card p-8 text-center text-gray-400">
        <BarChart2 className="h-12 w-12 mx-auto mb-3 opacity-40" />
        <p>{t('no_permission')}</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <TrendingUp className="h-6 w-6 text-primary-500" /> {t('ai_forecasting_title')}
        </h1>
      </div>

      <div className="flex gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit">
        {([['sales', t('tab_sales_forecast')], ['demand', t('tab_product_demand')], ['depletion', t('tab_stock_depletion')]] as const).map(([key, label]) => (
          <button key={key} onClick={() => setTab(key)} className={clsx('px-4 py-1.5 rounded-md text-sm font-medium transition-colors', tab === key ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300')}>
            {label}
          </button>
        ))}
      </div>

      {tab === 'sales' && (
        <div className="space-y-4">
          <div className="card p-4">
            <div className="flex flex-wrap gap-4 items-end">
              <div>
                <label className="label">{t('forecast_days')}</label>
                <select value={forecastDays} onChange={(e) => setForecastDays(e.target.value)} className="input">
                  {['7', '14', '30', '60', '90'].map((d) => <option key={d} value={d}>{d} {t('days')}</option>)}
                </select>
              </div>
              <div>
                <label className="label">{t('historical_data')}</label>
                <select value={historicalDays} onChange={(e) => setHistoricalDays(e.target.value)} className="input">
                  {['30', '60', '90', '180'].map((d) => <option key={d} value={d}>{d} {t('days')}</option>)}
                </select>
              </div>
              <button onClick={handleGenerate} disabled={salesQuery.isFetching} className="btn btn-primary flex items-center gap-2">
                {salesQuery.isFetching ? <LoadingSpinner size="sm" /> : <RefreshCw className="h-4 w-4" />}
                {t('generate')}
              </button>
            </div>
          </div>

          {salesData?.error && (
            <div className="card p-4 border-l-4 border-yellow-400 text-yellow-700 dark:text-yellow-400">
              {salesData.error}
            </div>
          )}

          {salesData && !salesData.error && (
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <div className="card p-4">
                <p className="text-xs text-gray-500 uppercase font-semibold mb-1">{t('total_forecast_revenue')}</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">{parseFloat(salesData.total_forecast).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
              </div>
              <div className="card p-4">
                <p className="text-xs text-gray-500 uppercase font-semibold mb-1">{t('avg_daily_revenue')}</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">{parseFloat(salesData.avg_daily).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
              </div>
              <div className="card p-4">
                <p className="text-xs text-gray-500 uppercase font-semibold mb-1">{t('trend')}</p>
                <div className="mt-1">{trendBadge(salesData.trend)}</div>
              </div>
              <div className="card p-4">
                <p className="text-xs text-gray-500 uppercase font-semibold mb-1">{t('model_accuracy')}</p>
                <p className="text-xl font-bold text-gray-900 dark:text-white">{parseFloat(salesData.accuracy_pct).toFixed(1)}%</p>
              </div>
            </div>
          )}

          <div className="card overflow-hidden">
            {salesQuery.isFetching ? (
              <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /><span className="ml-2 text-gray-400">{t('loading')}</span></div>
            ) : !salesQuery.data ? (
              <div className="text-center py-16 text-gray-400">
                <TrendingUp className="h-10 w-10 mx-auto mb-2 opacity-30" />
                <p>{t('generate_forecast')}</p>
              </div>
            ) : (
              <>
                <div className="hidden lg:block overflow-x-auto">
                  <table className="w-full min-w-[650px] text-sm">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>{[t('date'), t('revenue_forecast'), t('lower_bound'), t('upper_bound')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                      {forecastRows.length === 0 ? (
                        <tr><td colSpan={4} className="px-4 py-10 text-center text-gray-400">{t('no_data')}</td></tr>
                      ) : forecastRows.map((row, i) => (
                        <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                          <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.date}</td>
                          <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{parseFloat(row.forecast).toFixed(2)}</td>
                          <td className="px-4 py-3 text-gray-500">{parseFloat(row.lower_ci).toFixed(2)}</td>
                          <td className="px-4 py-3 text-gray-500">{parseFloat(row.upper_ci).toFixed(2)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                  {forecastRows.length === 0 ? <p className="px-4 py-10 text-center text-gray-400">{t('no_data')}</p>
                    : forecastRows.map((row, i) => (
                      <div key={i} className="p-4 space-y-1">
                        <div className="font-semibold text-gray-900 dark:text-white">{row.date}</div>
                        <div className="flex items-center gap-3 text-sm flex-wrap">
                          <span className="font-semibold text-primary-600">{parseFloat(row.forecast).toFixed(2)}</span>
                          <span className="text-gray-400 text-xs">{parseFloat(row.lower_ci).toFixed(2)} – {parseFloat(row.upper_ci).toFixed(2)}</span>
                        </div>
                      </div>
                    ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {tab === 'demand' && (
        <div className="card overflow-hidden">
          {demandQuery.isLoading ? (
            <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /><span className="ml-2 text-gray-400">{t('loading')}</span></div>
          ) : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[650px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('product'), t('avg_daily_qty'), t('product_demand_forecast_30'), t('current_stock'), t('days_left'), t('needs_reorder')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {demandRows.length === 0 ? (
                      <tr><td colSpan={6} className="px-4 py-12 text-center text-gray-400"><Package className="h-8 w-8 mx-auto mb-2 opacity-30" />{t('no_data')}</td></tr>
                    ) : demandRows.map((row, i) => (
                      <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.product_name}</td>
                        <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{parseFloat(row.avg_daily_qty).toFixed(2)}</td>
                        <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{parseFloat(row.forecast_30_days).toFixed(2)}</td>
                        <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{row.current_stock.toLocaleString()}</td>
                        <td className="px-4 py-3">{daysLeftBadge(row.days_stock_left)}</td>
                        <td className="px-4 py-3"><span className={clsx('badge', row.needs_reorder ? 'badge-danger' : 'badge-success')}>{row.needs_reorder ? t('yes') : t('no')}</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {demandRows.length === 0 ? <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
                  : demandRows.map((row, i) => (
                    <div key={i} className="p-4 space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <span className="font-semibold text-gray-900 dark:text-white">{row.product_name}</span>
                        <span className={clsx('badge shrink-0', row.needs_reorder ? 'badge-danger' : 'badge-success')}>{row.needs_reorder ? t('yes') : t('no')}</span>
                      </div>
                      <div className="flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                        <span>{t('current_stock')}: {row.current_stock.toLocaleString()}</span>
                        <span>{daysLeftBadge(row.days_stock_left)}</span>
                        <span>{t('avg_daily_qty')}: {parseFloat(row.avg_daily_qty).toFixed(2)}</span>
                        <span>{t('product_demand_forecast_30')}: {parseFloat(row.forecast_30_days).toFixed(2)}</span>
                      </div>
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}

      {tab === 'depletion' && (
        <div className="card overflow-hidden">
          {depletionQuery.isLoading ? (
            <div className="flex h-64 items-center justify-center"><LoadingSpinner size="lg" /><span className="ml-2 text-gray-400">{t('loading')}</span></div>
          ) : (
            <>
              <div className="hidden lg:block overflow-x-auto">
                <table className="w-full min-w-[650px] text-sm">
                  <thead className="bg-gray-50 dark:bg-gray-700">
                    <tr>{[t('urgency'), t('product'), t('current_stock'), t('daily_rate'), t('days_remaining'), t('depleted_on'), t('suggested_order')].map((h) => <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>)}</tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                    {depletionRows.length === 0 ? (
                      <tr><td colSpan={7} className="px-4 py-12 text-center text-gray-400"><AlertTriangle className="h-8 w-8 mx-auto mb-2 opacity-30" />{t('no_data')}</td></tr>
                    ) : depletionRows.map((row, i) => (
                      <tr key={i} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-3">{urgencyBadge(row.urgency)}</td>
                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{row.product_name}</td>
                        <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{row.current_stock.toLocaleString()}</td>
                        <td className="px-4 py-3 text-gray-600 dark:text-gray-400">{parseFloat(row.daily_rate).toFixed(2)}</td>
                        <td className="px-4 py-3">{daysLeftBadge(row.days_remaining)}</td>
                        <td className="px-4 py-3 text-gray-500 text-xs">{row.depleted_on ?? '—'}</td>
                        <td className="px-4 py-3 font-semibold text-primary-600 dark:text-primary-400">{row.reorder_qty.toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="lg:hidden divide-y divide-gray-100 dark:divide-gray-700">
                {depletionRows.length === 0 ? <p className="px-4 py-12 text-center text-gray-400">{t('no_data')}</p>
                  : depletionRows.map((row, i) => (
                    <div key={i} className="p-4 space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <span className="font-semibold text-gray-900 dark:text-white">{row.product_name}</span>
                        {urgencyBadge(row.urgency)}
                      </div>
                      <div className="flex items-center gap-2 flex-wrap">
                        {daysLeftBadge(row.days_remaining)}
                        <span className="text-xs text-gray-500">{t('current_stock')}: {row.current_stock.toLocaleString()}</span>
                        <span className="text-xs text-gray-500">{t('daily_rate')}: {parseFloat(row.daily_rate).toFixed(2)}</span>
                        <span className="font-semibold text-xs text-primary-600">{t('suggested_order')}: {row.reorder_qty.toLocaleString()}</span>
                      </div>
                      {row.depleted_on && <div className="text-xs text-gray-400">{t('depleted_on')}: {row.depleted_on}</div>}
                    </div>
                  ))}
              </div>
            </>
          )}
        </div>
      )}
    </div>
  )
}
