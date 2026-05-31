{{-- FILE: resources/views/financial-reports/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.financial_reports'))
@section('page-title', __('pos.financial_reports'))

@section('content')
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#incomeTab">
            <i class="fas fa-chart-bar me-1"></i>{{ __('pos.income_statement') }}
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#balanceTab" data-fn="loadBalanceSheet">
            <i class="fas fa-balance-scale me-1"></i>{{ __('pos.balance_sheet') }}
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#accountStmtTab">
            <i class="fas fa-file-alt me-1"></i>{{ __('pos.Account Statement') }}
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- Income Statement --}}
    <div class="tab-pane fade show active" id="incomeTab">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pos.start_date') }}</label>
                        <input type="date" class="form-control" id="isStart" value="{{ date('Y-m-01') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pos.end_date') }}</label>
                        <input type="date" class="form-control" id="isEnd" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" data-fn="loadIncomeStatement">
                            <i class="fas fa-search me-1"></i> {{ __('pos.Generate') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="incomeResult" class="u-hidden">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-arrow-up me-2"></i>{{ __('pos.revenue') }}
                        </div>
                        <div class="card-body p-0">
                            <table class="table mb-0">
                                <tbody id="isRevenueBody"></tbody>
                                <tfoot class="table-success fw-bold">
                                    <tr>
                                        <td>Total {{ __('pos.revenue') }}</td>
                                        <td id="isTotalRevenue" class="text-end">-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <i class="fas fa-arrow-down me-2"></i>{{ __('pos.expense') }}
                        </div>
                        <div class="card-body p-0">
                            <table class="table mb-0">
                                <tbody id="isExpenseBody"></tbody>
                                <tfoot class="table-danger fw-bold">
                                    <tr>
                                        <td>Total {{ __('pos.expense') }}</td>
                                        <td id="isTotalExpense" class="text-end">-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body text-center" id="isNetCard">
                    <h4>{{ __('pos.net_income') }}</h4>
                    <h2 id="isNetIncome" class="fw-bold">-</h2>
                </div>
            </div>
        </div>
    </div>

    {{-- Balance Sheet --}}
    <div class="tab-pane fade" id="balanceTab">
        <div id="bsResult">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-building me-2"></i>{{ __('pos.asset') }}
                        </div>
                        <div class="card-body p-0">
                            <table class="table mb-0">
                                <tbody id="bsAssetsBody"></tbody>
                                <tfoot class="table-primary fw-bold">
                                    <tr><td>{{ __('pos.total_assets') }}</td><td id="bsTotalAssets" class="text-end">-</td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <i class="fas fa-hand-holding-usd me-2"></i>{{ __('pos.liability') }} & {{ __('pos.equity') }}
                        </div>
                        <div class="card-body p-0">
                            <table class="table mb-0">
                                <tbody id="bsLiabBody"></tbody>
                                <tfoot class="table-warning fw-bold">
                                    <tr><td>{{ __('pos.total_liabilities') }} & Equity</td><td id="bsTotalLiab" class="text-end">-</td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Account Statement --}}
    <div class="tab-pane fade" id="accountStmtTab">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('pos.Account') }}</label>
                        <select class="form-select" id="stmtAccount">
                            <option value="">-- Select Account --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pos.start_date') }}</label>
                        <input type="date" class="form-control" id="stmtStart" value="{{ date('Y-m-01') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pos.end_date') }}</label>
                        <input type="date" class="form-control" id="stmtEnd" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" data-fn="loadAccountStatement">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="stmtResult" class="u-hidden">
            <div class="card-header" id="stmtHeader"></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Entry #</th>
                            <th>{{ __('pos.date') }}</th>
                            <th>Description</th>
                            <th>{{ __('pos.debit') }}</th>
                            <th>{{ __('pos.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody id="stmtBody"></tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end">{{ __('pos.total') }}</td>
                            <td id="stmtTotalDr"></td>
                            <td id="stmtTotalCr"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end">Net Balance</td>
                            <td colspan="2" id="stmtNet" class="fw-bold"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
async function loadIncomeStatement() {
    const res = await apiCall('{{ route("reports.income-statement") }}', 'POST', {
        start_date: document.getElementById('isStart').value,
        end_date:   document.getElementById('isEnd').value,
    });

    document.getElementById('incomeResult').style.display = 'block';

    document.getElementById('isRevenueBody').innerHTML = (res.revenues || []).map(r =>
        `<tr><td>${r.account_name}</td><td class="text-end">${formatCurrency(r.total || 0)}</td></tr>`
    ).join('') || '<tr><td colspan="2" class="text-muted">No data</td></tr>';

    document.getElementById('isExpenseBody').innerHTML = (res.expenses || []).map(e =>
        `<tr><td>${e.account_name}</td><td class="text-end">${formatCurrency(e.total || 0)}</td></tr>`
    ).join('') || '<tr><td colspan="2" class="text-muted">No data</td></tr>';

    document.getElementById('isTotalRevenue').textContent = formatCurrency(res.totalRevenue);
    document.getElementById('isTotalExpense').textContent = formatCurrency(res.totalExpense);

    const net = res.netIncome;
    const netEl = document.getElementById('isNetIncome');
    netEl.textContent = formatCurrency(net);
    netEl.className   = `fw-bold ${net >= 0 ? 'text-success' : 'text-danger'}`;
    document.getElementById('isNetCard').className = `card-body text-center ${net >= 0 ? 'bg-success' : 'bg-danger'} bg-opacity-10`;
}

async function loadBalanceSheet() {
    const res = await apiCall('{{ route("reports.balance-sheet") }}');

    let assetsTotal = 0, liabTotal = 0;

    // Assets
    const assetsHtml = (res.assets || []).flatMap(a => {
        const rows = [`<tr class="fw-bold table-light"><td>${a.account_code} - ${a.account_name}</td><td class="text-end">${formatCurrency(a.balance)}</td></tr>`];
        assetsTotal += a.balance || 0;
        (a.children || []).forEach(c => {
            rows.push(`<tr><td class="ps-4 text-muted">${c.account_code} - ${c.account_name}</td><td class="text-end">${formatCurrency(c.balance)}</td></tr>`);
        });
        return rows;
    }).join('');
    document.getElementById('bsAssetsBody').innerHTML = assetsHtml;
    document.getElementById('bsTotalAssets').textContent = formatCurrency(assetsTotal);

    // Liabilities + Equity
    let liabHtml = '';
    [...(res.liabilities || []), ...(res.equity || [])].forEach(a => {
        liabHtml += `<tr class="fw-bold table-light"><td>${a.account_code} - ${a.account_name}</td><td class="text-end">${formatCurrency(a.balance)}</td></tr>`;
        liabTotal += a.balance || 0;
        (a.children || []).forEach(c => {
            liabHtml += `<tr><td class="ps-4 text-muted">${c.account_code} - ${c.account_name}</td><td class="text-end">${formatCurrency(c.balance)}</td></tr>`;
        });
    });
    document.getElementById('bsLiabBody').innerHTML = liabHtml;
    document.getElementById('bsTotalLiab').textContent = formatCurrency(liabTotal);
}

async function loadAccountsList() {
    const res = await apiCall('{{ route("accounts.all") }}');
    document.getElementById('stmtAccount').innerHTML = '<option value="">-- Select --</option>' +
        (res.accounts || []).map(a => `<option value="${a.id}">${a.account_code} - ${a.account_name}</option>`).join('');
}

async function loadAccountStatement() {
    const accId = document.getElementById('stmtAccount').value;
    if (!accId) return;

    const res = await apiCall(`/api/reports/account-statement/${accId}`, 'POST', {
        start_date: document.getElementById('stmtStart').value,
        end_date:   document.getElementById('stmtEnd').value,
    });

    document.getElementById('stmtResult').style.display = 'block';
    document.getElementById('stmtHeader').textContent = res.account?.account_name + ' - ' + res.account?.account_code;

    document.getElementById('stmtBody').innerHTML = (res.lines || []).length
        ? res.lines.map(l => `
            <tr>
                <td><code>${l.entry?.entry_number}</code></td>
                <td>${l.entry ? formatDate(l.entry.entry_date) : '-'}</td>
                <td>${l.description || l.entry?.description || '-'}</td>
                <td class="text-success">${l.debit > 0 ? formatCurrency(l.debit) : '-'}</td>
                <td class="text-primary">${l.credit > 0 ? formatCurrency(l.credit) : '-'}</td>
            </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted py-3">{{ __("pos.no_data") }}</td></tr>';

    document.getElementById('stmtTotalDr').textContent = formatCurrency(res.total_debit);
    document.getElementById('stmtTotalCr').textContent = formatCurrency(res.total_credit);
    const net = res.net_balance;
    document.getElementById('stmtNet').textContent = formatCurrency(net);
    document.getElementById('stmtNet').className   = `fw-bold ${net >= 0 ? 'text-success' : 'text-danger'}`;
}

loadAccountsList();
</script>
@endpush
