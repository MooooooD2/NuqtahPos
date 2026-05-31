{{-- FILE: resources/views/suppliers/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.suppliers'))
@section('page-title', __('pos.suppliers'))

@section('content')
{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card blue text-center">
            <p class="mb-1 small opacity-75">إجمالي الموردين</p>
            <h4 class="mb-0 fw-bold" id="totalSuppliersCount">-</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card red text-center">
            <p class="mb-1 small opacity-75">إجمالي المديونية</p>
            <h4 class="mb-0 fw-bold" id="totalDebtAll">-</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card green text-center">
            <p class="mb-1 small opacity-75">إجمالي المدفوع</p>
            <h4 class="mb-0 fw-bold" id="totalPaidAll">-</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card orange text-center">
            <p class="mb-1 small opacity-75">إجمالي المتبقي</p>
            <h4 class="mb-0 fw-bold" id="totalBalanceAll">-</h4>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-truck me-2"></i>{{ __('pos.suppliers') }}</span>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-success btn-sm" data-fn="exportSuppliersExcel">
                <i class="fas fa-file-excel me-1"></i>تصدير Excel
            </button>
            <button class="btn btn-danger btn-sm" data-fn="printSuppliersList">
                <i class="fas fa-print me-1"></i>طباعة
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal">
                <i class="fas fa-plus me-1"></i>{{ __('pos.add_supplier') }}
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" id="supplierSearch"
                    placeholder="{{ __('pos.search') }}..." data-on-input="filterSuppliers">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="suppliersTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>{{ __('pos.name') }}</th>
                        <th>{{ __('pos.phone') }}</th>
                        <th>{{ __('pos.address') }}</th>
                        <th>{{ __('pos.email') }}</th>
                        <th>المتبقي</th>
                        <th>{{ __('pos.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="suppliersBody">
                    <tr><td colspan="7" class="text-center py-4"><div class="spinner-border"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal: إضافة/تعديل --}}
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalTitle">{{ __('pos.add_supplier') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="supplierId">
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.name') }} *</label>
                    <input type="text" class="form-control" id="supplierName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.phone') }}</label>
                    <input type="text" class="form-control" id="supplierPhone" placeholder="01xxxxxxxxx">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.address') }}</label>
                    <input type="text" class="form-control" id="supplierAddress">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.email') }}</label>
                    <input type="email" class="form-control" id="supplierEmail">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveSupplier">{{ __('pos.save') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: بطاقة المورد --}}
<div class="modal fade" id="supplierCardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-id-card me-2"></i>بطاقة المورد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supplierCardBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button class="btn btn-primary" data-fn="printSupplierCard">
                    <i class="fas fa-print me-1"></i>طباعة
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
let allSuppliers      = [];
let suppliersStats    = {};
let renderedSuppliers = [];

async function loadSuppliers() {
    const res = await apiCall('{{ route("suppliers.all") }}?all=1');
    allSuppliers = res.suppliers?.data || res.suppliers || [];

    let totalDebt = 0, totalPaid = 0, totalBal = 0;

    await Promise.all(allSuppliers.map(async s => {
        try {
            const r = await apiCall(`{{ url('/api/supplier-accounts') }}/${s.id}`);
            suppliersStats[s.id] = { debt: r.total_debt||0, paid: r.total_payment||0, balance: r.balance||0 };
            totalDebt += r.total_debt    || 0;
            totalPaid += r.total_payment || 0;
            totalBal  += r.balance       || 0;
        } catch { suppliersStats[s.id] = { debt:0, paid:0, balance:0 }; }
    }));

    document.getElementById('totalSuppliersCount').textContent = allSuppliers.length;
    document.getElementById('totalDebtAll').textContent        = formatCurrency(totalDebt);
    document.getElementById('totalPaidAll').textContent        = formatCurrency(totalPaid);
    document.getElementById('totalBalanceAll').textContent     = formatCurrency(totalBal);
    renderSuppliers(allSuppliers);
}

function filterSuppliers() {
    const q = document.getElementById('supplierSearch').value.toLowerCase();
    renderSuppliers(allSuppliers.filter(s =>
        s.name.toLowerCase().includes(q) ||
        (s.phone||'').includes(q) ||
        (s.email||'').toLowerCase().includes(q)
    ));
}

function renderSuppliers(suppliers) {
    renderedSuppliers = suppliers;
    document.getElementById('suppliersBody').innerHTML = suppliers.length
        ? suppliers.map((s, i) => {
            const st  = suppliersStats[s.id] || {};
            const bal = st.balance || 0;
            const balHtml = bal > 0
                ? `<span class="badge bg-danger fs-6">${formatCurrency(bal)}</span>`
                : `<span class="badge bg-success">مسدد</span>`;
            return `<tr>
                <td>${i+1}</td>
                <td class="fw-semibold">${escapeHtml(s.name)}</td>
                <td>${s.phone ? `<a href="tel:${s.phone}">${s.phone}</a>` : '-'}</td>
                <td class="text-muted small">${s.address||'-'}</td>
                <td class="text-muted small">${s.email||'-'}</td>
                <td>${balHtml}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info" title="بطاقة المورد"
                            data-action="view-card" data-idx="${i}">
                            <i class="fas fa-id-card"></i>
                        </button>
                        <a href="{{ route('supplier-accounts') }}?supplier=${s.id}"
                           class="btn btn-secondary" title="الحساب">
                            <i class="fas fa-balance-scale"></i>
                        </a>
                        <button class="btn btn-primary" title="تعديل"
                            data-action="edit" data-idx="${i}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger" title="حذف"
                            data-action="delete" data-id="${s.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

function viewSupplierCard(s) {
    const st = suppliersStats[s.id] || {};
    document.getElementById('supplierCardBody').innerHTML = `
        <div id="printableSupplierCard">
            <div class="text-center mb-3">
                <h4 class="fw-bold"><i class="fas fa-truck me-2 text-primary"></i>${escapeHtml(s.name)}</h4>
                <small class="text-muted">تاريخ الطباعة: ${new Date().toLocaleString('ar-EG')}</small>
                <hr>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">بيانات المورد</h6>
                    <table class="table table-bordered table-sm">
                        <tr><th class="bg-light" width="40%">الاسم</th><td>${escapeHtml(s.name)}</td></tr>
                        <tr><th class="bg-light">التليفون</th><td>${s.phone||'-'}</td></tr>
                        <tr><th class="bg-light">العنوان</th><td>${s.address||'-'}</td></tr>
                        <tr><th class="bg-light">البريد</th><td>${s.email||'-'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">الحساب المالي</h6>
                    <table class="table table-bordered table-sm">
                        <tr><th class="bg-light" width="50%">إجمالي المديونية</th>
                            <td class="text-danger fw-bold">${formatCurrency(st.debt||0)}</td></tr>
                        <tr><th class="bg-light">إجمالي المدفوع</th>
                            <td class="text-success fw-bold">${formatCurrency(st.paid||0)}</td></tr>
                        <tr class="table-warning"><th class="fw-bold">المتبقي</th>
                            <td class="fw-bold fs-5">${formatCurrency(st.balance||0)}</td></tr>
                    </table>
                </div>
            </div>
        </div>`;
    new bootstrap.Modal(document.getElementById('supplierCardModal')).show();
}

function printSupplierCard() {
    const c = document.getElementById('printableSupplierCard').innerHTML;
    const w = window.open('','_blank');
    w.document.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
        <title>بطاقة مورد</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
        <style>body{padding:30px;font-family:'Cairo',sans-serif;font-size:14px;}</style>
        </head><body>${c}</body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint=()=>w.close();
}

function printSuppliersList() {
    const rows = allSuppliers.map((s,i) => {
        const st = suppliersStats[s.id]||{};
        return `<tr><td>${i+1}</td><td>${escapeHtml(s.name)}</td><td>${s.phone||'-'}</td>
            <td>${s.address||'-'}</td><td>${s.email||'-'}</td>
            <td class="text-danger fw-bold">${formatCurrency(st.balance||0)}</td></tr>`;
    }).join('');
    const w = window.open('','_blank');
    w.document.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
        <title>قائمة الموردين</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
        <style>body{padding:20px;font-family:'Cairo',sans-serif;font-size:13px;}</style>
        </head><body>
        <h4 class="text-center mb-1">قائمة الموردين</h4>
        <p class="text-center text-muted mb-3">${new Date().toLocaleDateString('ar-EG')}</p>
        <table class="table table-bordered table-sm">
            <thead class="table-dark"><tr><th>#</th><th>الاسم</th><th>التليفون</th>
            <th>العنوان</th><th>البريد</th><th>المتبقي</th></tr></thead>
            <tbody>${rows}</tbody>
        </table></body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint=()=>w.close();
}

function exportSuppliersExcel() {
    const rows = [['#','الاسم','التليفون','العنوان','البريد','إجمالي المديونية','إجمالي المدفوع','المتبقي']];
    allSuppliers.forEach((s,i) => {
        const st = suppliersStats[s.id]||{};
        rows.push([i+1, s.name, s.phone||'', s.address||'', s.email||'',
            st.debt||0, st.paid||0, st.balance||0]);
    });
    const csv = '\uFEFF' + rows.map(r => r.map(v=>`"${v}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = `suppliers_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

function editSupplier(s) {
    document.getElementById('supplierId').value      = s.id;
    document.getElementById('supplierName').value    = s.name;
    document.getElementById('supplierPhone').value   = s.phone||'';
    document.getElementById('supplierAddress').value = s.address||'';
    document.getElementById('supplierEmail').value   = s.email||'';
    document.getElementById('supplierModalTitle').textContent = '{{ __("pos.edit_supplier") }}';
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}

async function saveSupplier() {
    const id   = document.getElementById('supplierId').value;
    const data = {
        name:    document.getElementById('supplierName').value.trim(),
        phone:   document.getElementById('supplierPhone').value.trim(),
        address: document.getElementById('supplierAddress').value.trim(),
        email:   document.getElementById('supplierEmail').value.trim(),
    };
    if (!data.name) { showToast('اسم المورد مطلوب','danger'); return; }
    const res = await apiCall(id ? `/api/suppliers/${id}` : '{{ route("suppliers.store") }}',
                              id ? 'PUT' : 'POST', data);
    if (res.success) {
        showToast('{{ __("pos.success") }}');
        bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
        document.getElementById('supplierId').value = '';
        loadSuppliers();
    } else showToast(res.message||'{{ __("pos.error") }}','danger');
}

async function deleteSupplier(id) {
    if (!confirm('{{ __("pos.confirm_delete") }}')) return;
    const res = await apiCall(`/api/suppliers/${id}`,'DELETE');
    if (res.success) { showToast('{{ __("pos.success") }}'); loadSuppliers(); }
    else showToast(res.message,'danger');
}

document.getElementById('suppliersBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    if (action === 'view-card') viewSupplierCard(renderedSuppliers[parseInt(btn.dataset.idx)]);
    else if (action === 'edit')   editSupplier(renderedSuppliers[parseInt(btn.dataset.idx)]);
    else if (action === 'delete') deleteSupplier(parseInt(btn.dataset.id));
});

document.getElementById('supplierModal').addEventListener('show.bs.modal', e => {
    if (!e.relatedTarget) return;
    document.getElementById('supplierId').value = '';
    ['Name','Phone','Address','Email'].forEach(f =>
        document.getElementById('supplier'+f).value = '');
    document.getElementById('supplierModalTitle').textContent = '{{ __("pos.add_supplier") }}';
});

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

loadSuppliers();
</script>
@endpush
