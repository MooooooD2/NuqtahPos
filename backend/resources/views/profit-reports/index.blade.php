@extends('layouts.app')
@section('title', __('pos.profit_report'))
@section('page-title', __('pos.profit_report'))

@section('content')

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">{{ __('pos.start_date') }}</label>
                <input type="date" class="form-control" id="startDate"
                    value="{{ date('Y-m-01') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">{{ __('pos.end_date') }}</label>
                <input type="date" class="form-control" id="endDate"
                    value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">{{ __('pos.category') }}</label>
                <select class="form-select" id="categoryFilter">
                    <option value="">{{ __('pos.all_categories') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">{{ __('pos.view') }}</label>
                <select class="form-select" id="viewType" data-on-change="loadReport">
                    <option value="product">{{ __('pos.by_product') }}</option>
                    <option value="daily">{{ __('pos.daily') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" data-fn="loadReport">
                    <i class="fas fa-search me-1"></i>{{ __('pos.view') }}
                </button>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-success" data-fn="exportExcel" title="Excel">
                    <i class="fas fa-file-excel"></i>
                </button>
                <button class="btn btn-danger" data-fn="printReport" title="{{ __('pos.print') }}">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card blue text-center">
            <p class="mb-1 small opacity-75">{{ __('pos.total_revenue') }}</p>
            <h4 class="mb-0 fw-bold" id="sumRevenue">-</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card red text-center">
            <p class="mb-1 small opacity-75">{{ __('pos.total_cost') }}</p>
            <h4 class="mb-0 fw-bold" id="sumCost">-</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card green text-center">
            <p class="mb-1 small opacity-75">{{ __('pos.total_profit') }}</p>
            <h4 class="mb-0 fw-bold" id="sumProfit">-</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card orange text-center">
            <p class="mb-1 small opacity-75">{{ __('pos.profit_margin') }}</p>
            <h4 class="mb-0 fw-bold" id="sumMargin">-</h4>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span id="tableTitle"><i class="fas fa-chart-line me-2"></i>{{ __('pos.profit_report') }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="profitTable">
                <thead class="table-dark" id="tableHead"></thead>
                <tbody id="tableBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">{{ __('pos.select_period_view') }}</td></tr>
                </tbody>
                <tfoot class="table-secondary fw-bold" id="tableFoot" style="display:none"></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
let reportData   = [];
let reportType   = 'product';
let reportTotals = {};

const _t = {
    selectPeriod:       '{{ __('pos.select_period') }}',
    errorLoading:       '{{ __('pos.error_loading') }}',
    noDataExport:       '{{ __('pos.no_data_export') }}',
    noDataPrint:        '{{ __('pos.no_data_print') }}',
    productProfit:      '{{ __('pos.product_profitability') }}',
    dailyProfit:        '{{ __('pos.daily_profitability') }}',
    product:            '{{ __('pos.product') }}',
    category:           '{{ __('pos.category') }}',
    quantity:           '{{ __('pos.quantity') }}',
    revenue:            '{{ __('pos.revenue') }}',
    cost:               '{{ __('pos.cost') }}',
    profit:             '{{ __('pos.profit') }}',
    margin:             '{{ __('pos.margin') }}',
    profitMargin:       '{{ __('pos.profit_margin') }}',
    invoicesCount:      '{{ __('pos.invoices_count') }}',
    date:               '{{ __('pos.date') }}',
    total:              '{{ __('pos.total') }}',
    noData:             '{{ __('pos.no_data') }}',
    profitReport:       '{{ __('pos.profit_report') }}',
    startDate:          '{{ __('pos.start_date') }}',
    endDate:            '{{ __('pos.end_date') }}',
    totalRevenue:       '{{ __('pos.total_revenue') }}',
    totalCost:          '{{ __('pos.total_cost') }}',
    totalProfit:        '{{ __('pos.total_profit') }}',
    allCategories:      '{{ __('pos.all_categories') }}',
    printDate:          '{{ __('pos.print_date') }}',
};

async function loadReport() {
    reportType = document.getElementById('viewType').value;
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    if (!start || !end) { showToast(_t.selectPeriod, 'danger'); return; }

    document.getElementById('tableBody').innerHTML =
        '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border"></div></td></tr>';

    const url  = reportType === 'product'
        ? '{{ route("reports.profit-product") }}'
        : '{{ route("reports.profit-daily") }}';

    const body = { start_date: start, end_date: end };
    const cat  = document.getElementById('categoryFilter').value;
    if (cat && reportType === 'product') body.category = cat;

    const res = await apiCall(url, 'POST', body);
    if (!res.success) { showToast(_t.errorLoading, 'danger'); return; }

    reportTotals = res.totals || {};
    reportData   = reportType === 'product' ? (res.products || []) : (res.daily || []);

    if (reportType === 'product') {
        const cats = [...new Set(reportData.map(r => r.category).filter(Boolean))];
        const sel  = document.getElementById('categoryFilter');
        const cur  = sel.value;
        sel.innerHTML = `<option value="">${_t.allCategories}</option>` +
            cats.map(c => `<option value="${c}" ${c===cur?'selected':''}>${c}</option>`).join('');
    }

    updateSummaryCards();
    renderTable();
}

function updateSummaryCards() {
    const t = reportTotals;
    document.getElementById('sumRevenue').textContent = formatCurrency(t.total_revenue || t.revenue || 0);
    document.getElementById('sumCost').textContent    = formatCurrency(t.total_cost    || t.cost    || 0);
    document.getElementById('sumProfit').textContent  = formatCurrency(t.gross_profit  || t.profit  || 0);
    document.getElementById('sumMargin').textContent  = (t.profit_margin || t.margin || 0) + '%';
    const profitEl = document.getElementById('sumProfit');
    profitEl.style.color = (t.gross_profit || t.profit || 0) >= 0 ? '' : '#dc3545';
}

function renderTable() {
    const isProduct = reportType === 'product';
    document.getElementById('tableTitle').innerHTML =
        `<i class="fas fa-chart-line me-2"></i>${isProduct ? _t.productProfit : _t.dailyProfit}`;

    if (isProduct) {
        document.getElementById('tableHead').innerHTML = `<tr>
            <th>#</th><th>${_t.product}</th><th>${_t.category}</th><th>${_t.quantity}</th>
            <th>${_t.revenue}</th><th>${_t.cost}</th>
            <th class="text-success">${_t.profit}</th><th>${_t.profitMargin}</th>
        </tr>`;
        document.getElementById('tableBody').innerHTML = reportData.length
            ? reportData.map((r, i) => `<tr>
                <td>${i+1}</td>
                <td class="fw-semibold">${escapeHtml(r.product_name)}</td>
                <td><span class="badge bg-secondary">${r.category||'-'}</span></td>
                <td>${r.total_qty}</td>
                <td>${formatCurrency(r.total_revenue)}</td>
                <td class="text-danger">${formatCurrency(r.total_cost)}</td>
                <td class="fw-bold ${r.gross_profit>=0?'text-success':'text-danger'}">${formatCurrency(r.gross_profit)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:8px;">
                            <div class="progress-bar ${r.profit_margin>=30?'bg-success':r.profit_margin>=10?'bg-warning':'bg-danger'}"
                                style="width:${Math.min(100,Math.max(0,r.profit_margin))}%"></div>
                        </div>
                        <span class="small">${r.profit_margin}%</span>
                    </div>
                </td>
            </tr>`).join('')
            : `<tr><td colspan="8" class="text-center text-muted py-4">${_t.noData}</td></tr>`;

        const t = reportTotals;
        document.getElementById('tableFoot').style.display = '';
        document.getElementById('tableFoot').innerHTML = `<tr>
            <td colspan="4" class="text-end">${_t.total}</td>
            <td>${formatCurrency(t.total_revenue)}</td>
            <td class="text-danger">${formatCurrency(t.total_cost)}</td>
            <td class="text-success">${formatCurrency(t.gross_profit)}</td>
            <td>${t.profit_margin}%</td>
        </tr>`;
    } else {
        document.getElementById('tableHead').innerHTML = `<tr>
            <th>${_t.date}</th><th>${_t.invoicesCount}</th><th>${_t.revenue}</th>
            <th>${_t.cost}</th><th class="text-success">${_t.profit}</th><th>${_t.margin}</th>
        </tr>`;
        document.getElementById('tableBody').innerHTML = reportData.length
            ? reportData.map(r => `<tr>
                <td class="fw-semibold">${r.date}</td>
                <td>${r.invoices_count}</td>
                <td>${formatCurrency(r.revenue)}</td>
                <td class="text-danger">${formatCurrency(r.cost)}</td>
                <td class="fw-bold ${r.profit>=0?'text-success':'text-danger'}">${formatCurrency(r.profit)}</td>
                <td>${r.margin}%</td>
            </tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-muted py-4">${_t.noData}</td></tr>`;

        const t = reportTotals;
        document.getElementById('tableFoot').style.display = '';
        document.getElementById('tableFoot').innerHTML = `<tr>
            <td colspan="2" class="text-end">${_t.total}</td>
            <td>${formatCurrency(t.revenue)}</td>
            <td class="text-danger">${formatCurrency(t.cost)}</td>
            <td class="text-success">${formatCurrency(t.profit)}</td>
            <td>-</td>
        </tr>`;
    }
}

function exportExcel() {
    if (!reportData.length) { showToast(_t.noDataExport, 'warning'); return; }
    const isProduct = reportType === 'product';
    const header    = isProduct
        ? ['#', _t.product, _t.category, _t.quantity, _t.revenue, _t.cost, _t.profit, _t.profitMargin]
        : [_t.date, _t.invoicesCount, _t.revenue, _t.cost, _t.profit, _t.margin];
    const rows = [header];
    if (isProduct) {
        reportData.forEach((r,i) => rows.push([
            i+1, r.product_name, r.category||'', r.total_qty,
            r.total_revenue, r.total_cost, r.gross_profit, r.profit_margin+'%'
        ]));
    } else {
        reportData.forEach(r => rows.push([
            r.date, r.invoices_count, r.revenue, r.cost, r.profit, r.margin+'%'
        ]));
    }
    const csv = '﻿' + rows.map(r => r.map(v=>`"${v}"`).join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download= `profit_report_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

function printReport() {
    if (!reportData.length) { showToast(_t.noDataPrint, 'warning'); return; }
    const tableHtml = document.getElementById('profitTable').outerHTML;
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    const locale = '{{ app()->getLocale() }}';
    const w = window.open('','_blank');
    w.document.write(`<!DOCTYPE html><html dir="${locale==='ar'?'rtl':'ltr'}" lang="${locale}"><head><meta charset="utf-8">
        <title>${_t.profitReport}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap${locale==='ar'?'.rtl':''}.min.css" rel="stylesheet">
        <style>body{padding:20px;font-family:'Cairo',sans-serif;font-size:12px;}</style>
        </head><body>
        <h4 class="text-center mb-1">${_t.profitReport}</h4>
        <p class="text-center text-muted mb-1">${_t.startDate}: ${start} — ${_t.endDate}: ${end}</p>
        <div class="row g-2 mb-3 text-center">
            <div class="col-3"><div class="border rounded p-2">
                <small class="d-block text-muted">${_t.totalRevenue}</small>
                <strong>${document.getElementById('sumRevenue').textContent}</strong>
            </div></div>
            <div class="col-3"><div class="border rounded p-2">
                <small class="d-block text-muted">${_t.totalCost}</small>
                <strong>${document.getElementById('sumCost').textContent}</strong>
            </div></div>
            <div class="col-3"><div class="border rounded p-2">
                <small class="d-block text-muted">${_t.totalProfit}</small>
                <strong>${document.getElementById('sumProfit').textContent}</strong>
            </div></div>
            <div class="col-3"><div class="border rounded p-2">
                <small class="d-block text-muted">${_t.profitMargin}</small>
                <strong>${document.getElementById('sumMargin').textContent}</strong>
            </div></div>
        </div>
        ${tableHtml}
        <p class="text-center text-muted small mt-3">${_t.printDate}: ${new Date().toLocaleString(locale+'-EG')}</p>
        </body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint = () => w.close();
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

loadReport();
</script>
@endpush
