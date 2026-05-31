@extends('layouts.app')
@section('title', __('pos.ai_forecasting_title'))
@section('page-title', '🤖 ' . __('pos.ai_forecasting_page_title'))

@section('content')
{{-- Tab Nav --}}
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#salesTab">📈 {{ __('pos.tab_sales_forecast') }}</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#productTab" data-fn="loadProductForecast">📦 {{ __('pos.tab_product_demand') }}</button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stockTab" data-fn="loadStockForecast">⚠️ {{ __('pos.tab_stock_depletion') }}</button>
  </li>
</ul>

<div class="tab-content">

  {{-- Sales Forecast Tab --}}
  <div class="tab-pane fade show active" id="salesTab">
    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">{{ __('pos.forecast_days') }}</label>
            <select class="form-select" id="forecastDays">
              <option value="7">7 {{ __('pos.days') }}</option>
              <option value="14">14 {{ __('pos.days') }}</option>
              <option value="30" selected>30 {{ __('pos.days') }}</option>
              <option value="60">60 {{ __('pos.days') }}</option>
              <option value="90">90 {{ __('pos.days') }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('pos.historical_data') }}</label>
            <select class="form-select" id="historyDays">
              <option value="30">{{ __('pos.days_history', ['days' => 30]) }}</option>
              <option value="60">{{ __('pos.days_history', ['days' => 60]) }}</option>
              <option value="90" selected>{{ __('pos.days_history', ['days' => 90]) }}</option>
              <option value="180">{{ __('pos.days_history', ['days' => 180]) }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <button class="btn btn-primary w-100" data-fn="loadSalesForecast">
              <i class="fas fa-robot me-1"></i> {{ __('pos.generate_forecast') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div id="salesErrorBox"></div>
    <div id="salesMetrics" class="row g-3 mb-4 d-none">
      <div class="col-md-3">
        <div class="card bg-primary text-white text-center">
          <div class="card-body">
            <div class="fs-4 fw-bold" id="metTotalForecast">-</div>
            <div class="small">{{ __('pos.total_forecast_revenue') }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white text-center">
          <div class="card-body">
            <div class="fs-4 fw-bold" id="metAvgDaily">-</div>
            <div class="small">{{ __('pos.avg_daily_revenue') }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center" id="trendCard">
          <div class="card-body">
            <div class="fs-4 fw-bold" id="metTrend">-</div>
            <div class="small">{{ __('pos.trend') }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-secondary text-white text-center">
          <div class="card-body">
            <div class="fs-4 fw-bold" id="metAccuracy">-</div>
            <div class="small">{{ __('pos.model_accuracy') }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card" id="salesChartCard" style="display:none">
      <div class="card-header d-flex justify-content-between">
        <span>{{ __('pos.revenue_forecast') }}</span>
        <span class="text-muted small" id="genAt"></span>
      </div>
      <div class="card-body">
        <canvas id="salesChart" style="max-height:380px"></canvas>
      </div>
    </div>
  </div>

  {{-- Product Demand Tab --}}
  <div class="tab-pane fade" id="productTab">
    <div id="productForecastContainer">
      <div class="text-center text-muted py-5">{{ __('pos.click_to_load_forecasts') }}</div>
    </div>
  </div>

  {{-- Stock Depletion Tab --}}
  <div class="tab-pane fade" id="stockTab">
    <div id="stockForecastContainer">
      <div class="text-center text-muted py-5">{{ __('pos.click_to_load_stock') }}</div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script @nonce>
// Translations for JS-rendered strings
const t = {
  generated:              "{{ __('pos.label_generated') }}",
  forecast:               "{{ __('pos.label_forecast') }}",
  upperCI:                "{{ __('pos.label_upper_ci') }}",
  lowerCI:                "{{ __('pos.label_lower_ci') }}",
  noData:                 "{{ __('pos.no_data') }}",
  reorder:                "{{ __('pos.label_reorder') }}",
  productDemand30:        "{{ __('pos.product_demand_forecast_30') }}",
  stockDepletion:         "{{ __('pos.stock_depletion_forecast') }}",
  stockHealthy:           "{{ __('pos.stock_healthy') }}",
  critical3d:             "{{ __('pos.forecast_critical_3d') }}",
  atRisk30d:              "{{ __('pos.forecast_at_risk_30d') }}",
  colProduct:             "{{ __('pos.product') }}",
  colAvgDaily:            "{{ __('pos.col_avg_daily') }}",
  colForecast30d:         "{{ __('pos.col_forecast_30d') }}",
  colStock:               "{{ __('pos.stock') }}",
  colDaysLeft:            "{{ __('pos.col_days_left') }}",
  colVelocity:            "{{ __('pos.col_velocity') }}",
  colRevenue:             "{{ __('pos.revenue') }}",
  colUrgency:             "{{ __('pos.col_urgency') }}",
  colRate:                "{{ __('pos.col_rate') }}",
  colDepletedOn:          "{{ __('pos.col_depleted_on') }}",
  colSuggestOrder:        "{{ __('pos.col_suggest_order') }}",
};

let salesChartObj = null;

async function apiFetch(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  return res.json();
}

/* ── Sales Forecast ── */
async function loadSalesForecast() {
  const days    = document.getElementById('forecastDays').value;
  const history = document.getElementById('historyDays').value;

  const data = await apiFetch(`/api/forecast/sales?days=${days}&history=${history}`);
  if (data.error) {
    document.getElementById('salesChartCard').style.display = 'none';
    document.getElementById('salesMetrics').classList.add('d-none');
    document.getElementById('salesErrorBox').innerHTML =
      `<div class="alert alert-warning d-flex align-items-center gap-2">
         <i class="fas fa-exclamation-triangle fs-5"></i>
         <span>${data.error}</span>
       </div>`;
    return;
  }
  document.getElementById('salesErrorBox').innerHTML = '';

  document.getElementById('salesMetrics').classList.remove('d-none');
  document.getElementById('salesChartCard').style.display = '';

  document.getElementById('metTotalForecast').textContent = fmt(data.total_forecast);
  document.getElementById('metAvgDaily').textContent      = fmt(data.avg_daily);
  document.getElementById('metAccuracy').textContent      = data.accuracy_pct + '%';
  document.getElementById('genAt').textContent            = t.generated + ': ' + new Date(data.generated_at).toLocaleString();

  const trendMap = { growing: 'success', stable: 'warning', declining: 'danger' };
  const trendCard = document.getElementById('trendCard');
  const trendEl   = document.getElementById('metTrend');
  trendCard.className = `card text-white bg-${trendMap[data.trend] ?? 'secondary'} text-center`;
  trendEl.textContent = '↗ ' + (data.trend ?? '-').toUpperCase();

  // Chart
  const labels   = data.forecasts.map(f => f.date);
  const forecast = data.forecasts.map(f => f.forecast);
  const lower    = data.forecasts.map(f => f.lower_ci);
  const upper    = data.forecasts.map(f => f.upper_ci);

  if (salesChartObj) salesChartObj.destroy();
  salesChartObj = new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: t.forecast, data: forecast, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)', tension: .4, fill: false },
        { label: t.upperCI,  data: upper,    borderColor: 'rgba(34,197,94,.4)', borderDash: [5,5], tension: .4, fill: false, pointRadius: 0 },
        { label: t.lowerCI,  data: lower,    borderColor: 'rgba(239,68,68,.4)', borderDash: [5,5], tension: .4, fill: '-1', backgroundColor: 'rgba(59,130,246,.05)', pointRadius: 0 },
      ],
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: false } },
    },
  });
}

/* ── Product Demand ── */
async function loadProductForecast() {
  const container = document.getElementById('productForecastContainer');
  if (container.dataset.loaded) return;
  container.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';

  const data = await apiFetch('/api/forecast/products?top=20&history=60');
  container.dataset.loaded = '1';

  if (!data.products?.length) { container.innerHTML = `<p class="text-muted">${t.noData}</p>`; return; }

  const rows = data.products.map(p => `
    <tr>
      <td>${esc(p.product_name)}</td>
      <td>${p.avg_daily_qty}</td>
      <td class="fw-bold">${p.forecast_30_days}</td>
      <td>${p.current_stock}</td>
      <td>
        <span class="badge bg-${p.days_stock_left <= 3 ? 'danger' : p.days_stock_left <= 7 ? 'warning' : 'success'}">
          ${p.days_stock_left} {{ __('pos.days') }}
        </span>
      </td>
      <td class="${p.velocity_pct > 0 ? 'text-success' : p.velocity_pct < 0 ? 'text-danger' : ''}">
        ${p.velocity_pct > 0 ? '↑' : p.velocity_pct < 0 ? '↓' : '–'} ${Math.abs(p.velocity_pct)}%
      </td>
      <td>${fmt(p.total_revenue)}</td>
      ${p.needs_reorder ? `<td><span class="badge bg-danger">⚠ ${t.reorder}</span></td>` : '<td></td>'}
    </tr>`).join('');

  container.innerHTML = `
    <div class="card">
      <div class="card-header">${t.productDemand30} <small class="text-muted">${t.generated}: ${new Date(data.generated_at).toLocaleString()}</small></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr>
            <th>${t.colProduct}</th><th>${t.colAvgDaily}</th><th>${t.colForecast30d}</th>
            <th>${t.colStock}</th><th>${t.colDaysLeft}</th><th>${t.colVelocity}</th>
            <th>${t.colRevenue}</th><th></th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    </div>`;
}

/* ── Stock Depletion ── */
async function loadStockForecast() {
  const container = document.getElementById('stockForecastContainer');
  if (container.dataset.loaded) return;
  container.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';

  const data = await apiFetch('/api/forecast/stock?history=30');
  container.dataset.loaded = '1';

  if (!data.alerts?.length) {
    container.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>${t.stockHealthy}</div>`;
    return;
  }

  const urgencyColor = { critical: 'danger', high: 'warning', medium: 'info', low: 'secondary' };

  const rows = data.alerts.map(a => `
    <tr class="${a.urgency === 'critical' ? 'table-danger' : a.urgency === 'high' ? 'table-warning' : ''}">
      <td><span class="badge bg-${urgencyColor[a.urgency]}">${a.urgency.toUpperCase()}</span></td>
      <td>${esc(a.product_name)}</td>
      <td>${a.current_stock}</td>
      <td>${a.daily_rate}/{{ __('pos.day') }}</td>
      <td class="fw-bold">${a.days_remaining} {{ __('pos.days') }}</td>
      <td>${a.depleted_on}</td>
      <td>~${a.reorder_qty} {{ __('pos.units') }}</td>
    </tr>`).join('');

  container.innerHTML = `
    <div class="row g-3 mb-3">
      <div class="col-md-4"><div class="card text-center border-danger"><div class="card-body"><div class="fs-4 fw-bold text-danger">${data.critical}</div><div class="small">${t.critical3d}</div></div></div></div>
      <div class="col-md-4"><div class="card text-center border-warning"><div class="card-body"><div class="fs-4 fw-bold text-warning">${data.total_at_risk}</div><div class="small">${t.atRisk30d}</div></div></div></div>
    </div>
    <div class="card">
      <div class="card-header">${t.stockDepletion}</div>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr>
            <th>${t.colUrgency}</th><th>${t.colProduct}</th><th>${t.colStock}</th>
            <th>${t.colRate}</th><th>${t.colDaysLeft}</th><th>${t.colDepletedOn}</th>
            <th>${t.colSuggestOrder}</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    </div>`;
}

function fmt(n) { return parseFloat(n || 0).toLocaleString(undefined, {minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Auto-load sales on page visit
loadSalesForecast();
</script>
@endpush
