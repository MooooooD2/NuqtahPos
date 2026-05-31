@extends('layouts.app')
@section('title', app()->getLocale()==='ar' ? 'الميزانية مقابل الفعلي' : 'Budget vs Actual')
@section('page-title', app()->getLocale()==='ar' ? 'الميزانية مقابل الفعلي' : 'Budget vs Actual')

@push('styles')
<style @nonce>
.variance-pos  { color: #198754; font-weight: 600; }
.variance-neg  { color: #dc3545; font-weight: 600; }
.budget-nav-btn { min-width: 36px; }
.budget-section-header { background: #f1f5f9; font-weight: 700; font-size: .82rem;
    text-transform: uppercase; letter-spacing: .06em; color: #475569; }
.cat-row td { font-size: .875rem; }

@media print {
    /* Hide everything except the report content */
    #sidebar, #main-navbar, .container-fluid > .d-flex,
    #budgetEntryModal, .modal-backdrop,
    .no-print { display: none !important; }

    body, html { background: #fff !important; }
    .container-fluid { padding: 0 !important; }
    #reportCard { display: block !important; box-shadow: none !important; border: none !important; }
    #summaryCards .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }

    #print-header { display: block !important; }

    table { font-size: 11px !important; }
    thead.table-dark th { background: #343a40 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .variance-pos { color: #198754 !important; }
    .variance-neg { color: #dc3545 !important; }
    .text-success  { color: #198754 !important; }
    .text-danger   { color: #dc3545 !important; }

    @page { margin: 15mm 10mm; size: landscape; }
}
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- Hidden print header (visible only when printing) --}}
    <div id="print-header" style="display:none; text-align:center; margin-bottom:16px; border-bottom:2px solid #333; padding-bottom:10px;">
        <div style="font-size:18px; font-weight:bold;">
            {{ app()->getLocale()==='ar' ? 'الميزانية مقابل الفعلي' : 'Budget vs Actual' }}
        </div>
        <div id="print-period" style="font-size:13px; color:#555; margin-top:4px;"></div>
        <div style="font-size:11px; color:#888; margin-top:2px;">
            {{ app()->getLocale()==='ar' ? 'تاريخ الطباعة:' : 'Printed:' }}
            <span id="print-date"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-balance-scale me-2 text-primary"></i>
            {{ app()->getLocale()==='ar' ? 'الميزانية مقابل الفعلي' : 'Budget vs Actual' }}
        </h5>
        <div class="d-flex gap-2 no-print">
            <button class="btn btn-outline-secondary btn-sm" id="printReportBtn">
                <i class="fas fa-print me-1"></i>{{ app()->getLocale()==='ar' ? 'طباعة' : 'Print' }}
            </button>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#budgetEntryModal">
                <i class="fas fa-edit me-1"></i>{{ app()->getLocale()==='ar' ? 'تعديل الميزانية' : 'Edit Budgets' }}
            </button>
        </div>
    </div>

    {{-- Period selector --}}
    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-auto d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary budget-nav-btn" id="yearPrev">‹</button>
                    <span class="fw-bold fs-5" id="currentYear">{{ date('Y') }}</span>
                    <button class="btn btn-sm btn-outline-secondary budget-nav-btn" id="yearNext">›</button>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="monthFilter" style="width:160px">
                        <option value="">{{ app()->getLocale()==='ar' ? 'كل الأشهر' : 'All months' }}</option>
                        @foreach(range(1,12) as $m)
                        <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m)->translatedFormat('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm" data-fn="loadReport">
                        <i class="fas fa-sync me-1"></i>{{ app()->getLocale()==='ar' ? 'تحديث' : 'Refresh' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3 mb-3" id="summaryCards">
        <div class="col-12 text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
    </div>

    {{-- Detail table --}}
    <div class="card shadow-sm" id="reportCard" style="display:none">
        <div class="card-header fw-bold">
            <i class="fas fa-table me-2"></i>{{ app()->getLocale()==='ar' ? 'التفاصيل الشهرية' : 'Monthly Detail' }}
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead class="table-dark">
                        <tr>
                            <th>{{ app()->getLocale()==='ar' ? 'الشهر' : 'Month' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'إيرادات مستهدفة' : 'Budget Revenue' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'إيرادات فعلية' : 'Actual Revenue' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'فرق الإيراد' : 'Rev. Variance' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'مصروفات مستهدفة' : 'Budget Expenses' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'مصروفات فعلية' : 'Actual Expenses' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'فرق المصروفات' : 'Exp. Variance' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'صافي الميزانية' : 'Budget Net' }}</th>
                            <th class="text-end">{{ app()->getLocale()==='ar' ? 'صافي الفعلي' : 'Actual Net' }}</th>
                        </tr>
                    </thead>
                    <tbody id="reportBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ── Budget Entry Modal ── --}}
<div class="modal fade" id="budgetEntryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    {{ app()->getLocale()==='ar' ? 'تعديل الميزانية' : 'Edit Budget' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'السنة' : 'Year' }}</label>
                        <input type="number" class="form-control" id="budgetYear" value="{{ date('Y') }}" min="2020" max="2100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'الشهر' : 'Month' }}</label>
                        <select class="form-select" id="budgetMonth">
                            @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(null, $m)->translatedFormat('F') }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-outline-secondary btn-sm w-100" data-fn="loadBudgetForm">
                            <i class="fas fa-download me-1"></i>{{ app()->getLocale()==='ar' ? 'تحميل القيم' : 'Load Values' }}
                        </button>
                    </div>
                </div>

                <div class="section-divider mb-2 fw-bold text-muted small text-uppercase">
                    {{ app()->getLocale()==='ar' ? 'الإيرادات' : 'Revenue' }}
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ app()->getLocale()==='ar' ? 'الإيرادات المستهدفة' : 'Revenue Target' }}</label>
                    <input type="number" class="form-control" id="budgetRevenue" value="0" min="0" step="0.01">
                </div>

                <div class="section-divider mb-2 fw-bold text-muted small text-uppercase">
                    {{ app()->getLocale()==='ar' ? 'المصروفات (حسب الفئة)' : 'Expenses (by category)' }}
                </div>
                <div id="budgetExpensesForm">
                    <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveBudget">
                    <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr        = LOCALE === 'ar';
let reportYear    = new Date().getFullYear();
let expCategories = [];

// ── Year navigation ────────────────────────────────────────────────────────
document.getElementById('yearPrev').addEventListener('click', () => { reportYear--; document.getElementById('currentYear').textContent = reportYear; loadReport(); });
document.getElementById('yearNext').addEventListener('click', () => { reportYear++; document.getElementById('currentYear').textContent = reportYear; loadReport(); });
document.getElementById('printReportBtn').addEventListener('click', printReport);

// ── Load expense categories ────────────────────────────────────────────────
async function loadExpenseCategories() {
    const res = await apiCall('{{ route("expenses.categories") }}');
    expCategories = res.categories || [];
}

// ── Load report ────────────────────────────────────────────────────────────
async function loadReport() {
    const month = document.getElementById('monthFilter').value;
    let url = `{{ url('/api/reports/budget-vs-actual') }}?year=${reportYear}`;
    if (month) url += `&month=${month}`;

    document.getElementById('summaryCards').innerHTML =
        '<div class="col-12 text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
    document.getElementById('reportCard').style.display = 'none';

    const res = await apiCall(url);
    if (!res.success) {
        document.getElementById('summaryCards').innerHTML =
            `<div class="col-12 text-center text-danger">${res.message || '{{ __("pos.error") }}'}</div>`;
        return;
    }

    const totals = res.totals || {};
    const revBudget  = totals.revenue?.budget  || 0;
    const revActual  = totals.revenue?.actual  || 0;
    const expBudget  = totals.expenses?.budget || 0;
    const expActual  = totals.expenses?.actual || 0;
    const revVar     = revActual  - revBudget;
    const expVar     = expBudget  - expActual;

    document.getElementById('summaryCards').innerHTML = `
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">${isAr ? 'إيرادات مستهدفة' : 'Budget Revenue'}</div>
                    <div class="fw-bold fs-5">${formatCurrency(revBudget)}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">${isAr ? 'إيرادات فعلية' : 'Actual Revenue'}</div>
                    <div class="fw-bold fs-5">${formatCurrency(revActual)}</div>
                    <div class="small ${revVar >= 0 ? 'variance-pos' : 'variance-neg'}">
                        ${revVar >= 0 ? '▲' : '▼'} ${formatCurrency(Math.abs(revVar))}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">${isAr ? 'مصروفات مستهدفة' : 'Budget Expenses'}</div>
                    <div class="fw-bold fs-5">${formatCurrency(expBudget)}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body">
                    <div class="text-muted small">${isAr ? 'مصروفات فعلية' : 'Actual Expenses'}</div>
                    <div class="fw-bold fs-5">${formatCurrency(expActual)}</div>
                    <div class="small ${expVar >= 0 ? 'variance-pos' : 'variance-neg'}">
                        ${expVar >= 0 ? '▲ ' + (isAr ? 'أقل من الميزانية' : 'under budget') : '▼ ' + (isAr ? 'تجاوز الميزانية' : 'over budget')}
                        ${formatCurrency(Math.abs(expVar))}
                    </div>
                </div>
            </div>
        </div>`;

    // Detail rows
    const rows = res.rows || [];
    document.getElementById('reportBody').innerHTML = rows.map(r => {
        const rv = r.revenue.variance;
        const ev = r.expenses.variance;
        return `<tr>
            <td class="fw-semibold">${r.month_label}</td>
            <td class="text-end">${formatCurrency(r.revenue.budget)}</td>
            <td class="text-end">${formatCurrency(r.revenue.actual)}</td>
            <td class="text-end ${rv >= 0 ? 'variance-pos' : 'variance-neg'}">${rv >= 0 ? '+' : ''}${formatCurrency(rv)}</td>
            <td class="text-end">${formatCurrency(r.expenses.budget)}</td>
            <td class="text-end">${formatCurrency(r.expenses.actual)}</td>
            <td class="text-end ${ev >= 0 ? 'variance-pos' : 'variance-neg'}">${ev >= 0 ? '+' : ''}${formatCurrency(ev)}</td>
            <td class="text-end">${formatCurrency(r.net.budget)}</td>
            <td class="text-end fw-bold ${r.net.actual >= 0 ? 'text-success' : 'text-danger'}">${formatCurrency(r.net.actual)}</td>
        </tr>`;
    }).join('') || `<tr><td colspan="9" class="text-center text-muted py-3">${isAr ? 'لا توجد بيانات' : 'No data'}</td></tr>`;

    document.getElementById('reportCard').style.display = '';
}

// ── Budget entry modal ─────────────────────────────────────────────────────
document.getElementById('budgetEntryModal').addEventListener('shown.bs.modal', () => {
    buildBudgetExpenseRows();
});

function buildBudgetExpenseRows(existingValues = {}) {
    const container = document.getElementById('budgetExpensesForm');
    if (!expCategories.length) {
        container.innerHTML = `<p class="text-muted small">${isAr ? 'لا توجد فئات مصروفات محددة.' : 'No expense categories defined.'}</p>
            <div class="mb-3">
                <label class="form-label">${isAr ? 'إجمالي المصروفات' : 'Total Expenses'}</label>
                <input type="number" class="form-control" id="budgetExpCat_general" value="${existingValues['general'] ?? existingValues[isAr ? 'عام' : 'General'] ?? 0}" min="0" step="0.01" data-category="${isAr ? 'عام' : 'General'}">
            </div>`;
        return;
    }
    container.innerHTML = expCategories.map(cat =>
        `<div class="row g-2 mb-2 align-items-center">
            <div class="col-6 fw-semibold small">${escapeHtml(cat.name)}</div>
            <div class="col-6">
                <input type="number" class="form-control form-control-sm" id="budgetExpCat_${cat.id}"
                    value="${existingValues[cat.name] ?? 0}" min="0" step="0.01" data-category="${escapeHtml(cat.name)}">
            </div>
        </div>`
    ).join('');
}

async function loadBudgetForm() {
    const year  = document.getElementById('budgetYear').value;
    const month = document.getElementById('budgetMonth').value;
    const res   = await apiCall(`{{ url('/api/budgets') }}?year=${year}&month=${month}`);
    const budgets = res.budgets || [];

    const revEntry = budgets.find(b => b.type === 'revenue');
    document.getElementById('budgetRevenue').value = revEntry?.amount ?? 0;

    const expValues = {};
    budgets.filter(b => b.type === 'expense').forEach(b => {
        if (b.category) expValues[b.category] = b.amount;
    });

    buildBudgetExpenseRows(expValues);
}

async function saveBudget() {
    const year  = parseInt(document.getElementById('budgetYear').value);
    const month = parseInt(document.getElementById('budgetMonth').value);

    const entries = [{
        year, month, type: 'revenue', category: null,
        amount: parseFloat(document.getElementById('budgetRevenue').value) || 0,
    }];

    document.querySelectorAll('#budgetExpensesForm input[data-category]').forEach(el => {
        entries.push({
            year, month, type: 'expense', category: el.dataset.category,
            amount: parseFloat(el.value) || 0,
        });
    });

    const res = await apiCall('{{ route("budgets.upsert") }}', 'POST', { entries });
    if (res.success) {
        showToast(isAr ? 'تم حفظ الميزانية' : 'Budget saved', 'success');
        bootstrap.Modal.getInstance(document.getElementById('budgetEntryModal')).hide();
        loadReport();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

// ── Print ──────────────────────────────────────────────────────────────────
function printReport() {
    const year  = document.getElementById('currentYear').textContent;
    const month = document.getElementById('monthFilter');
    const monthLabel = month.value
        ? month.options[month.selectedIndex].text
        : (isAr ? 'كل الأشهر' : 'All months');

    // Fill print-only header
    document.getElementById('print-period').textContent =
        (isAr ? 'الفترة: ' : 'Period: ') + year + ' — ' + monthLabel;
    document.getElementById('print-date').textContent =
        new Date().toLocaleString(isAr ? 'ar-EG' : 'en-EG');

    window.print();
}

// ── Init ───────────────────────────────────────────────────────────────────
function escapeHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadExpenseCategories().then(() => loadReport());
</script>
@endpush
