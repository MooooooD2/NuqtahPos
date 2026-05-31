{{-- FILE: resources/views/supplier-payments/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.supplier_payments'))
@section('page-title', __('pos.supplier_payments'))

@section('content')

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card green text-center">
            <p class="mb-1 small opacity-75">إجمالي المدفوعات</p>
            <h4 class="mb-0 fw-bold" id="totalPaymentsAmount">-</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card blue text-center">
            <p class="mb-1 small opacity-75">عدد المدفوعات</p>
            <h4 class="mb-0 fw-bold" id="totalPaymentsCount">-</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card orange text-center">
            <p class="mb-1 small opacity-75">آخر مدفوعة</p>
            <h4 class="mb-0 fw-bold" id="lastPaymentDate">-</h4>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-money-bill-wave me-2"></i>{{ __('pos.supplier_payments') }}</span>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-success btn-sm" data-fn="exportPaymentsExcel">
                <i class="fas fa-file-excel me-1"></i>تصدير Excel
            </button>
            <button class="btn btn-danger btn-sm" data-fn="printPaymentsList">
                <i class="fas fa-print me-1"></i>طباعة
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                <i class="fas fa-plus me-1"></i>{{ __('pos.add_payment') }}
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3 g-2">
            <div class="col-md-4">
                <select class="form-select" id="paySupplierFilter" data-on-change="loadPayments">
                    <option value="">كل الموردين</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" id="filterFrom" data-on-change="loadPayments"
                    placeholder="من تاريخ">
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" id="filterTo" data-on-change="loadPayments"
                    placeholder="إلى تاريخ">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" data-fn="clearFilters">
                    <i class="fas fa-times me-1"></i>مسح
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>رقم الدفعة</th>
                        <th>المورد</th>
                        <th>التليفون</th>
                        <th>المبلغ المدفوع</th>
                        <th>طريقة الدفع</th>
                        <th>التاريخ</th>
                        <th>ملاحظات</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="paymentsBody">
                    <tr><td colspan="8" class="text-center py-4"><div class="spinner-border"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal: إضافة دفعة --}}
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>{{ __('pos.add_payment') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">المورد *</label>
                    <select class="form-select" id="paySupplier" data-on-change="onSupplierSelected" required>
                        <option value="">-- اختر المورد --</option>
                    </select>
                </div>
                {{-- بيانات المورد المختار --}}
                <div id="selectedSupplierInfo" class="alert alert-info d-none mb-3 p-2">
                    <small>
                        <strong>التليفون:</strong> <span id="infoPhone">-</span> |
                        <strong>المتبقي عليه:</strong>
                        <span id="infoBalance" class="fw-bold text-danger">-</span>
                    </small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">المبلغ المدفوع *</label>
                    <input type="number" class="form-control" id="payAmount" step="0.01" min="0.01"
                        placeholder="0.00" data-on-input="calcRemaining">
                </div>
                <div id="remainingAfter" class="alert alert-warning d-none mb-3 p-2">
                    <small>المتبقي بعد الدفع: <strong id="remainingAmount">-</strong></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">طريقة الدفع *</label>
                    <select class="form-select" id="payMethod">
                        <option value="cash">نقدي</option>
                        <option value="card">بطاقة</option>
                        <option value="transfer">تحويل بنكي</option>
                        <option value="check">شيك</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">تاريخ الدفع *</label>
                    <input type="date" class="form-control" id="payDate" value="{{ date('Y-m-d') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">ملاحظات</label>
                    <textarea class="form-control" id="payNotes" rows="2"
                        placeholder="مثال: دفعة مقدم — فاتورة رقم ..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-success" data-fn="savePayment">
                    <i class="fas fa-check me-1"></i>تأكيد الدفع
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: طباعة إيصال دفعة --}}
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>إيصال الدفع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button class="btn btn-primary" data-fn="printReceipt">
                    <i class="fas fa-print me-1"></i>طباعة الإيصال
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
let suppliers          = [];
let allPayments        = [];
let suppStats          = {};
let renderedPayments   = [];
let renderedPaySuppliers = [];

async function init() {
    const res  = await apiCall('{{ route("suppliers.all") }}?all=1');
    suppliers  = res.suppliers?.data || res.suppliers || [];

    // تحميل أرصدة الموردين
    await Promise.all(suppliers.map(async s => {
        try {
            const r = await apiCall(`{{ url('/api/supplier-accounts') }}/${s.id}`);
            suppStats[s.id] = { phone: s.phone||'', balance: r.balance||0 };
        } catch { suppStats[s.id] = { phone: s.phone||'', balance:0 }; }
    }));

    const opts = suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    document.getElementById('paySupplier').innerHTML      = '<option value="">-- اختر المورد --</option>' + opts;
    document.getElementById('paySupplierFilter').innerHTML = '<option value="">كل الموردين</option>' + opts;
    loadPayments();
}

function onSupplierSelected() {
    const id   = document.getElementById('paySupplier').value;
    const info = document.getElementById('selectedSupplierInfo');
    if (!id) { info.classList.add('d-none'); return; }
    const st = suppStats[id] || {};
    document.getElementById('infoPhone').textContent   = st.phone   || '-';
    document.getElementById('infoBalance').textContent = formatCurrency(st.balance || 0);
    info.classList.remove('d-none');
    calcRemaining();
}

function calcRemaining() {
    const id     = document.getElementById('paySupplier').value;
    const amount = parseFloat(document.getElementById('payAmount').value) || 0;
    if (!id || !amount) { document.getElementById('remainingAfter').classList.add('d-none'); return; }
    const bal       = suppStats[id]?.balance || 0;
    const remaining = bal - amount;
    document.getElementById('remainingAmount').textContent = formatCurrency(remaining);
    document.getElementById('remainingAmount').className   = remaining > 0 ? 'text-danger fw-bold' : 'text-success fw-bold';
    document.getElementById('remainingAfter').classList.remove('d-none');
}

async function loadPayments() {
    const suppId = document.getElementById('paySupplierFilter').value;
    const from   = document.getElementById('filterFrom').value;
    const to     = document.getElementById('filterTo').value;
    let url      = '{{ route("supplier-payments.all") }}?per_page=200';
    if (suppId) url += `&supplier_id=${suppId}`;
    const res    = await apiCall(url);
    allPayments  = res.payments?.data || [];

    // فلترة التاريخ على الـ frontend
    let pays = allPayments;
    if (from) pays = pays.filter(p => p.payment_date >= from);
    if (to)   pays = pays.filter(p => p.payment_date <= to);

    const total = pays.reduce((s, p) => s + parseFloat(p.amount||0), 0);
    document.getElementById('totalPaymentsAmount').textContent = formatCurrency(total);
    document.getElementById('totalPaymentsCount').textContent  = pays.length;
    document.getElementById('lastPaymentDate').textContent     =
        pays.length ? formatDate(pays[0].payment_date) : '-';

    renderedPayments    = pays;
    renderedPaySuppliers = pays.map(p => suppliers.find(s => s.id == p.supplier_id) || {});
    document.getElementById('paymentsBody').innerHTML = pays.length
        ? pays.map((p, i) => {
            const supp = renderedPaySuppliers[i];
            return `<tr>
                <td><span class="badge bg-success">${p.payment_number}</span></td>
                <td class="fw-semibold">${escapeHtml(p.supplier_name)}</td>
                <td>${supp.phone ? `<a href="tel:${supp.phone}">${supp.phone}</a>` : '-'}</td>
                <td class="fw-bold text-success fs-6">${formatCurrency(p.amount)}</td>
                <td><span class="badge bg-secondary">${getPaymentMethodAr(p.payment_method)}</span></td>
                <td>${formatDate(p.payment_date)}</td>
                <td class="text-muted small">${p.notes||'-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" title="طباعة الإيصال"
                        data-action="show-receipt" data-pay-idx="${i}">
                        <i class="fas fa-print"></i>
                    </button>
                </td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="8" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

document.getElementById('paymentsBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="show-receipt"]');
    if (!btn) return;
    const idx = parseInt(btn.dataset.payIdx);
    showReceipt(renderedPayments[idx], renderedPaySuppliers[idx]);
});

function clearFilters() {
    document.getElementById('paySupplierFilter').value = '';
    document.getElementById('filterFrom').value        = '';
    document.getElementById('filterTo').value          = '';
    loadPayments();
}

async function savePayment() {
    const suppId = document.getElementById('paySupplier').value;
    const amount = document.getElementById('payAmount').value;
    if (!suppId) { showToast('اختر المورد أولاً','danger'); return; }
    if (!amount || parseFloat(amount) <= 0) { showToast('أدخل مبلغاً صحيحاً','danger'); return; }

    const res = await apiCall('{{ route("supplier-payments.store") }}','POST',{
        supplier_id:    suppId,
        amount:         amount,
        payment_method: document.getElementById('payMethod').value,
        payment_date:   document.getElementById('payDate').value,
        notes:          document.getElementById('payNotes').value,
    });
    if (res.success) {
        showToast('تم تسجيل الدفع بنجاح');
        bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
        // reset
        document.getElementById('paySupplier').value = '';
        document.getElementById('payAmount').value   = '';
        document.getElementById('payNotes').value    = '';
        document.getElementById('selectedSupplierInfo').classList.add('d-none');
        document.getElementById('remainingAfter').classList.add('d-none');
        await init(); // إعادة تحميل لتحديث الأرصدة
    } else showToast(res.message||'{{ __("pos.error") }}','danger');
}

function showReceipt(p, supp) {
    const st = suppStats[p.supplier_id] || {};
    document.getElementById('receiptBody').innerHTML = `
        <div id="printableReceipt" class="u-font-cairo">
            <div class="text-center mb-3">
                <h5 class="fw-bold">إيصال دفع للمورد</h5>
                <hr>
            </div>
            <table class="table table-bordered table-sm">
                <tr><th class="bg-light" width="40%">رقم الإيصال</th>
                    <td><strong>${p.payment_number}</strong></td></tr>
                <tr><th class="bg-light">اسم المورد</th><td>${escapeHtml(p.supplier_name)}</td></tr>
                <tr><th class="bg-light">التليفون</th><td>${supp.phone||'-'}</td></tr>
                <tr><th class="bg-light">تاريخ الدفع</th><td>${formatDate(p.payment_date)}</td></tr>
                <tr><th class="bg-light">طريقة الدفع</th><td>${getPaymentMethodAr(p.payment_method)}</td></tr>
                <tr class="table-success"><th class="fw-bold">المبلغ المدفوع</th>
                    <td class="fw-bold fs-5 text-success">${formatCurrency(p.amount)}</td></tr>
                <tr class="table-warning"><th class="fw-bold">المتبقي بعد الدفع</th>
                    <td class="fw-bold">${formatCurrency(Math.max(0, (st.balance||0)))}</td></tr>
                ${p.notes ? `<tr><th class="bg-light">ملاحظات</th><td>${escapeHtml(p.notes)}</td></tr>` : ''}
            </table>
            <div class="row mt-4 pt-3 border-top text-center">
                <div class="col-6">
                    <p class="mb-0 small">توقيع المورد</p>
                    <p class="mt-4">___________________</p>
                </div>
                <div class="col-6">
                    <p class="mb-0 small">توقيع المسؤول</p>
                    <p class="mt-4">___________________</p>
                </div>
            </div>
            <p class="text-center text-muted small mt-2">تاريخ الطباعة: ${new Date().toLocaleString('ar-EG')}</p>
        </div>`;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function printReceipt() {
    const c = document.getElementById('printableReceipt').innerHTML;
    const w = window.open('','_blank');
    w.document.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
        <title>إيصال دفع</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
        <style>body{padding:30px;font-family:'Cairo',sans-serif;font-size:14px;max-width:500px;margin:auto;}</style>
        </head><body>${c}</body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint=()=>w.close();
}

function printPaymentsList() {
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    let pays   = allPayments;
    if (from) pays = pays.filter(p => p.payment_date >= from);
    if (to)   pays = pays.filter(p => p.payment_date <= to);
    const total = pays.reduce((s,p) => s + parseFloat(p.amount||0), 0);
    const rows  = pays.map((p,i) => `<tr>
        <td>${i+1}</td><td>${p.payment_number}</td>
        <td>${escapeHtml(p.supplier_name)}</td>
        <td>${formatCurrency(p.amount)}</td>
        <td>${getPaymentMethodAr(p.payment_method)}</td>
        <td>${formatDate(p.payment_date)}</td>
        <td>${p.notes||'-'}</td>
    </tr>`).join('');
    const w = window.open('','_blank');
    w.document.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
        <title>مدفوعات الموردين</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
        <style>body{padding:20px;font-family:'Cairo',sans-serif;font-size:13px;}</style>
        </head><body>
        <h4 class="text-center mb-1">تقرير مدفوعات الموردين</h4>
        <p class="text-center text-muted mb-3">
            ${from ? 'من ' + from : ''} ${to ? 'إلى ' + to : ''} — ${new Date().toLocaleDateString('ar-EG')}
        </p>
        <table class="table table-bordered table-sm">
            <thead class="table-dark"><tr><th>#</th><th>رقم الإيصال</th><th>المورد</th>
            <th>المبلغ</th><th>الطريقة</th><th>التاريخ</th><th>ملاحظات</th></tr></thead>
            <tbody>${rows}</tbody>
            <tfoot class="table-success fw-bold">
                <tr><td colspan="3" class="text-end">الإجمالي</td>
                    <td colspan="4">${formatCurrency(total)}</td></tr>
            </tfoot>
        </table></body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint=()=>w.close();
}

function exportPaymentsExcel() {
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    let pays   = allPayments;
    if (from) pays = pays.filter(p => p.payment_date >= from);
    if (to)   pays = pays.filter(p => p.payment_date <= to);
    const rows = [['#','رقم الإيصال','المورد','المبلغ','طريقة الدفع','التاريخ','ملاحظات']];
    pays.forEach((p,i) => rows.push([
        i+1, p.payment_number, p.supplier_name, p.amount,
        getPaymentMethodAr(p.payment_method), p.payment_date, p.notes||''
    ]));
    const csv = '\uFEFF' + rows.map(r => r.map(v=>`"${v}"`).join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download= `supplier_payments_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

function getPaymentMethodAr(m) {
    return {cash:'نقدي', card:'بطاقة', transfer:'تحويل', check:'شيك', wallet:'محفظة'}[m] || m;
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

init();
</script>
@endpush
