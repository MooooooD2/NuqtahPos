{{-- FILE: resources/views/supplier-accounts/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.supplier_accounts'))
@section('page-title', __('pos.supplier_accounts'))

@section('content')
<div class="row g-3">
    {{-- Suppliers list --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>{{ __('pos.suppliers') }}</span>
            </div>
            <div class="card-body p-2">
                <input type="text" class="form-control form-control-sm mb-2"
                    id="suppSearch"
                    placeholder="{{ __('pos.search') }}..."
                    data-on-input="filterSuppliersList">
            </div>
            <div class="list-group list-group-flush" id="suppliersList" style="max-height:70vh;overflow-y:auto;">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>

    {{-- Account details --}}
    <div class="col-md-8">
        <div id="accountPanel" style="display:none">
            {{-- Supplier info --}}
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0 fw-bold" id="acctSupplierName">-</h5>
                            <small class="text-muted" id="acctSupplierPhone">-</small>
                        </div>
                        <div class="col-auto d-flex gap-2">
                            <button class="btn btn-success btn-sm" data-fn="exportAccountExcel">
                                <i class="fas fa-file-excel me-1"></i>Excel
                            </button>
                            <button class="btn btn-danger btn-sm" data-fn="printAccountStatement">
                                <i class="fas fa-print me-1"></i>{{ __('pos.print_account_statement') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary cards --}}
            <div class="row g-3 mb-3">
                <div class="col-4">
                    <div class="stat-card red text-center">
                        <p class="mb-1 small opacity-75">{{ __('pos.total_debt') }}</p>
                        <h5 class="mb-0 fw-bold" id="acctTotalDebt">-</h5>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card green text-center">
                        <p class="mb-1 small opacity-75">{{ __('pos.total_paid') }}</p>
                        <h5 class="mb-0 fw-bold" id="acctTotalPaid">-</h5>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-card orange text-center">
                        <p class="mb-1 small opacity-75">{{ __('pos.current_balance') }}</p>
                        <h5 class="mb-0 fw-bold" id="acctBalance">-</h5>
                    </div>
                </div>
            </div>

            {{-- Movements table --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list-alt me-2"></i>{{ __('pos.detailed_statement') }}</span>
                    <div class="d-flex gap-2 align-items-center">
                        <small class="text-muted">{{ __('pos.from_date') }}</small>
                        <input type="date" class="form-control form-control-sm" id="acctFrom"
                            data-on-change="filterEntries" style="width:140px">
                        <small class="text-muted">{{ __('pos.to_date') }}</small>
                        <input type="date" class="form-control form-control-sm" id="acctTo"
                            data-on-change="filterEntries" style="width:140px">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>{{ __('pos.date') }}</th>
                                    <th>{{ __('pos.movement_type_label') }}</th>
                                    <th>{{ __('pos.reference') }}</th>
                                    <th class="text-danger">{{ __('pos.debt') }}</th>
                                    <th class="text-success">{{ __('pos.paid') }}</th>
                                    <th>{{ __('pos.balance') }}</th>
                                    <th>{{ __('pos.notes') }}</th>
                                </tr>
                            </thead>
                            <tbody id="acctBody">
                                <tr><td colspan="7" class="text-center text-muted py-3">{{ __('pos.select_supplier') }}</td></tr>
                            </tbody>
                            <tfoot id="acctFooter" style="display:none">
                                <tr class="table-dark fw-bold">
                                    <td colspan="3" class="text-end">{{ __('pos.total') }}</td>
                                    <td class="text-danger" id="footerDebt">-</td>
                                    <td class="text-success" id="footerPaid">-</td>
                                    <td id="footerBalance">-</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="noSupplierPanel" class="text-center text-muted py-5">
            <i class="fas fa-balance-scale fa-4x mb-3 d-block opacity-25"></i>
            <p>{{ __('pos.select_supplier_hint') }}</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';

const T = {
    noPhone:          '{{ __('pos.no_phone') }}',
    noSuppliers:      '{{ __('pos.no_suppliers_found') }}',
    noMovements:      '{{ __('pos.no_movements') }}',
    selectSupplier:   '{{ __('pos.select_supplier') }}',
    typePO:           '{{ __('pos.type_purchase_order') }}',
    typePayment:      '{{ __('pos.type_payment') }}',
    typeAdjustment:   '{{ __('pos.type_adjustment') }}',
    typePurchReturn:  '{{ __('pos.type_purchase_return') }}',
    totalDebt:        '{{ __('pos.total_debt') }}',
    totalPaid:        '{{ __('pos.total_paid') }}',
    currentBalance:   '{{ __('pos.current_balance') }}',
    statement:        '{{ __('pos.supplier_account_statement') }}',
    fromBeginning:    '{{ __('pos.from_beginning') }}',
    untilNow:         '{{ __('pos.until_now') }}',
    from:             '{{ __('pos.from_date') }}',
    to:               '{{ __('pos.to_date') }}',
    supplierSig:      '{{ __('pos.supplier_signature') }}',
    managerSig:       '{{ __('pos.manager_signature') }}',
    printDate:        '{{ __('pos.print_date') }}',
    total:            '{{ __('pos.total') }}',
    debt:             '{{ __('pos.debt') }}',
    paid:             '{{ __('pos.paid') }}',
    date:             '{{ __('pos.date') }}',
    type:             '{{ __('pos.movement_type_label') }}',
    reference:        '{{ __('pos.reference') }}',
    balance:          '{{ __('pos.balance') }}',
    notes:            '{{ __('pos.notes') }}',
    phone:            '{{ __('pos.phone') }}',
};

let allSuppliersAcct      = [];
let renderedSuppliersAcct = [];
let currentEntries        = [];
let currentSupplier       = null;

const typeLabels = () => ({
    purchase_order:  T.typePO,
    payment:         T.typePayment,
    adjustment:      T.typeAdjustment,
    purchase_return: T.typePurchReturn,
});

async function loadSuppliersList() {
    const res        = await apiCall('{{ route("suppliers.all") }}?all=1');
    allSuppliersAcct = res.suppliers?.data || res.suppliers || [];
    renderSuppliersList(allSuppliersAcct);

    const urlParams = new URLSearchParams(window.location.search);
    const suppId    = urlParams.get('supplier');
    if (suppId) {
        const s = allSuppliersAcct.find(x => x.id == suppId);
        if (s) loadSupplierAccount(s.id, s.name, s.phone);
    }
}

function filterSuppliersList() {
    const q = document.getElementById('suppSearch').value.toLowerCase();
    renderSuppliersList(allSuppliersAcct.filter(s =>
        s.name.toLowerCase().includes(q) || (s.phone || '').includes(q)
    ));
}

function renderSuppliersList(suppliers) {
    renderedSuppliersAcct = suppliers;
    const chevron = isAr ? 'left' : 'right';
    document.getElementById('suppliersList').innerHTML = suppliers.length
        ? suppliers.map((s, i) => `
            <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                id="suppBtn_${s.id}" data-action="load-account" data-idx="${i}">
                <div>
                    <div class="fw-semibold">${escapeHtml(s.name)}</div>
                    <small class="text-muted">${s.phone || T.noPhone}</small>
                </div>
                <i class="fas fa-chevron-${chevron} text-muted"></i>
            </button>`).join('')
        : `<div class="p-3 text-muted text-center">${T.noSuppliers}</div>`;
}

async function loadSupplierAccount(id, name, phone) {
    currentSupplier = { id, name, phone };

    document.querySelectorAll('#suppliersList button').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById(`suppBtn_${id}`);
    if (btn) btn.classList.add('active');

    document.getElementById('noSupplierPanel').style.display = 'none';
    document.getElementById('accountPanel').style.display    = 'block';
    document.getElementById('acctSupplierName').textContent  = name;
    document.getElementById('acctSupplierPhone').textContent = phone || T.noPhone;
    document.getElementById('acctBody').innerHTML =
        `<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>`;

    const res = await apiCall(`{{ url('/api/supplier-accounts') }}/${id}`);

    document.getElementById('acctTotalDebt').textContent = formatCurrency(res.total_debt);
    document.getElementById('acctTotalPaid').textContent = formatCurrency(res.total_payment);
    document.getElementById('acctBalance').textContent   = formatCurrency(res.balance);

    currentEntries = res.entries?.data || res.entries || [];
    filterEntries();
}

function filterEntries() {
    const from  = document.getElementById('acctFrom').value;
    const to    = document.getElementById('acctTo').value;
    let entries = currentEntries;

    if (from) entries = entries.filter(e => e.created_at >= from);
    if (to)   entries = entries.filter(e => e.created_at <= to + ' 23:59:59');

    const labels    = typeLabels();
    const totalDebt = entries.reduce((s, e) => s + parseFloat(e.debit  || 0), 0);
    const totalPaid = entries.reduce((s, e) => s + parseFloat(e.credit || 0), 0);
    const netBal    = totalDebt - totalPaid;

    document.getElementById('acctBody').innerHTML = entries.length
        ? entries.map(e => `
            <tr>
                <td class="small">${formatDate(e.created_at)}</td>
                <td>
                    <span class="badge ${e.transaction_type === 'payment' ? 'bg-success' : 'bg-primary'}">
                        ${labels[e.transaction_type] || e.transaction_type}
                    </span>
                </td>
                <td><code class="small">${e.reference_number || '—'}</code></td>
                <td class="text-danger fw-semibold">${e.debit  > 0 ? formatCurrency(e.debit)  : '—'}</td>
                <td class="text-success fw-semibold">${e.credit > 0 ? formatCurrency(e.credit) : '—'}</td>
                <td class="fw-bold ${e.balance > 0 ? 'text-warning' : 'text-success'}">${formatCurrency(e.balance)}</td>
                <td class="text-muted small">${e.notes || '—'}</td>
            </tr>`).join('')
        : `<tr><td colspan="7" class="text-center text-muted py-3">${T.noMovements}</td></tr>`;

    if (entries.length) {
        document.getElementById('acctFooter').style.display  = '';
        document.getElementById('footerDebt').textContent    = formatCurrency(totalDebt);
        document.getElementById('footerPaid').textContent    = formatCurrency(totalPaid);
        document.getElementById('footerBalance').textContent = formatCurrency(netBal);
    } else {
        document.getElementById('acctFooter').style.display = 'none';
    }
}

function printAccountStatement() {
    if (!currentSupplier) return;
    const from  = document.getElementById('acctFrom').value;
    const to    = document.getElementById('acctTo').value;
    let entries = currentEntries;
    if (from) entries = entries.filter(e => e.created_at >= from);
    if (to)   entries = entries.filter(e => e.created_at <= to + ' 23:59:59');

    const labels    = typeLabels();
    const totalDebt = entries.reduce((s, e) => s + parseFloat(e.debit  || 0), 0);
    const totalPaid = entries.reduce((s, e) => s + parseFloat(e.credit || 0), 0);
    const balance   = document.getElementById('acctBalance').textContent;
    const dir       = isAr ? 'rtl' : 'ltr';
    const lang      = isAr ? 'ar' : 'en';
    const bsUrl     = isAr
        ? 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css'
        : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';
    const font      = isAr ? "'Cairo',sans-serif" : "'Segoe UI',sans-serif";
    const dateRange = `${from ? T.from + ' ' + from : T.fromBeginning} — ${to ? T.to + ' ' + to : T.untilNow}`;

    const rows = entries.map(e => `<tr>
        <td>${formatDate(e.created_at)}</td>
        <td>${labels[e.transaction_type] || e.transaction_type}</td>
        <td>${e.reference_number || '—'}</td>
        <td class="text-danger">${e.debit  > 0 ? formatCurrency(e.debit)  : '—'}</td>
        <td class="text-success">${e.credit > 0 ? formatCurrency(e.credit) : '—'}</td>
        <td class="fw-bold">${formatCurrency(e.balance)}</td>
        <td>${e.notes || '—'}</td>
    </tr>`).join('');

    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html>
<html dir="${dir}" lang="${lang}">
<head>
<meta charset="utf-8">
<title>${T.statement} — ${escapeHtml(currentSupplier.name)}</title>
<link href="${bsUrl}" rel="stylesheet">
<style>body{padding:20px;font-family:${font};font-size:13px;}</style>
</head>
<body>
<h4 class="text-center mb-1">${T.statement}</h4>
<h5 class="text-center text-primary mb-1">${escapeHtml(currentSupplier.name)}</h5>
<p class="text-center text-muted mb-1">${T.phone}: ${currentSupplier.phone || '—'}</p>
<p class="text-center text-muted mb-3">${dateRange}</p>
<div class="row g-2 mb-3 text-center">
    <div class="col-4"><div class="border rounded p-2">
        <small class="text-muted d-block">${T.totalDebt}</small>
        <strong class="text-danger">${formatCurrency(totalDebt)}</strong>
    </div></div>
    <div class="col-4"><div class="border rounded p-2">
        <small class="text-muted d-block">${T.totalPaid}</small>
        <strong class="text-success">${formatCurrency(totalPaid)}</strong>
    </div></div>
    <div class="col-4"><div class="border rounded p-2 bg-warning-subtle">
        <small class="text-muted d-block">${T.currentBalance}</small>
        <strong class="fs-5">${balance}</strong>
    </div></div>
</div>
<table class="table table-bordered table-sm">
    <thead class="table-dark">
        <tr>
            <th>${T.date}</th><th>${T.type}</th><th>${T.reference}</th>
            <th>${T.debt}</th><th>${T.paid}</th><th>${T.balance}</th><th>${T.notes}</th>
        </tr>
    </thead>
    <tbody>${rows}</tbody>
    <tfoot class="table-secondary fw-bold">
        <tr>
            <td colspan="3" class="text-end">${T.total}</td>
            <td class="text-danger">${formatCurrency(totalDebt)}</td>
            <td class="text-success">${formatCurrency(totalPaid)}</td>
            <td>${formatCurrency(totalDebt - totalPaid)}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
<div class="row mt-5 text-center">
    <div class="col-6">
        <p class="mb-0 small">${T.supplierSig}</p>
        <p class="mt-4 border-top pt-2">${escapeHtml(currentSupplier.name)}</p>
    </div>
    <div class="col-6">
        <p class="mb-0 small">${T.managerSig}</p>
        <p class="mt-4 border-top pt-2">___________________</p>
    </div>
</div>
<p class="text-center text-muted small mt-3">
    ${T.printDate}: ${new Date().toLocaleString(isAr ? 'ar-EG' : 'en-US')}
</p>
</body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint = () => w.close();
}

function exportAccountExcel() {
    if (!currentSupplier) return;
    const from  = document.getElementById('acctFrom').value;
    const to    = document.getElementById('acctTo').value;
    let entries = currentEntries;
    if (from) entries = entries.filter(e => e.created_at >= from);
    if (to)   entries = entries.filter(e => e.created_at <= to + ' 23:59:59');

    const labels = typeLabels();
    const rows   = [[T.date, T.type, T.reference, T.debt, T.paid, T.balance, T.notes]];
    entries.forEach(e => rows.push([
        e.created_at,
        labels[e.transaction_type] || e.transaction_type,
        e.reference_number || '',
        e.debit  || 0,
        e.credit || 0,
        e.balance || 0,
        e.notes  || '',
    ]));
    const csv = '﻿' + rows.map(r => r.map(v => `"${v}"`).join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = `account_${currentSupplier.name}_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

document.getElementById('suppliersList').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="load-account"]');
    if (!btn) return;
    const s = renderedSuppliersAcct[parseInt(btn.dataset.idx)];
    if (s) loadSupplierAccount(s.id, s.name, s.phone || '');
});

loadSuppliersList();
</script>
@endpush
