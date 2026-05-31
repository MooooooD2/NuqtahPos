@extends('layouts.app')
@section('title', __('pos.customers'))
@section('page-title', __('pos.customers'))

@push('styles')
<style @nonce>
    .customer-type-badge { font-size:.7rem; padding:.3em .6em; }
    .balance-neg { color:#dc3545; }
    .balance-pos { color:#198754; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-users me-2 text-primary"></i>{{ __('pos.customers') }}
        </h5>
        @permission('view_warehouse')
        <button class="btn btn-primary btn-sm" data-fn="openCustomerModal" data-args="[null]">
            <i class="fas fa-plus me-1"></i>{{ __('pos.new_customer') }}
        </button>
        @endpermission
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <input type="text" class="form-control form-control-sm" id="filterSearch"
                        placeholder="{{ app()->getLocale() === 'ar' ? 'بحث بالاسم أو الهاتف أو الكود...' : 'Search by name, phone or code...' }}"
                        data-on-input="applyFilters">
                </div>
                <div class="col-sm-2">
                    <select class="form-select form-select-sm" id="filterType" data-on-change="applyFilters">
                        <option value="">{{ app()->getLocale() === 'ar' ? 'كل الأنواع' : 'All types' }}</option>
                        <option value="individual">{{ app()->getLocale() === 'ar' ? 'أفراد' : 'Individual' }}</option>
                        <option value="business">{{ app()->getLocale() === 'ar' ? 'شركات' : 'Business' }}</option>
                    </select>
                </div>
                <div class="col-sm-2">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" id="filterInactive" data-on-change="applyFilters">
                        <label class="form-check-label small" for="filterInactive">
                            {{ app()->getLocale() === 'ar' ? 'غير النشطين' : 'Show inactive' }}
                        </label>
                    </div>
                </div>
                <div class="col-sm-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" data-fn="resetFilters">
                        <i class="fas fa-times me-1"></i>{{ app()->getLocale() === 'ar' ? 'مسح' : 'Reset' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ app()->getLocale() === 'ar' ? 'الكود' : 'Code' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الاسم' : 'Name' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الهاتف' : 'Phone' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'النوع' : 'Type' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الرصيد' : 'Balance' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'نقاط الولاء' : 'Points' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                            <th class="text-center">{{ app()->getLocale() === 'ar' ? 'إجراء' : 'Action' }}</th>
                        </tr>
                    </thead>
                    <tbody id="customersTbody">
                        <tr><td colspan="8" class="text-center py-4">
                            <i class="fas fa-spinner fa-spin me-1"></i>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center py-2">
            <span id="customersTotal" class="text-muted small"></span>
            <div id="customersPagination" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

{{-- Customer Modal --}}
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalTitle">{{ __('pos.new_customer') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="customerId">
                <div class="row g-3">
                    <div class="col-sm-8">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'الاسم' : 'Name' }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="customerName" maxlength="150">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'النوع' : 'Type' }}</label>
                        <select class="form-select" id="customerType">
                            <option value="individual">{{ app()->getLocale() === 'ar' ? 'فرد' : 'Individual' }}</option>
                            <option value="business">{{ app()->getLocale() === 'ar' ? 'شركة' : 'Business' }}</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">
                            <i class="fab fa-whatsapp text-success me-1"></i>
                            {{ app()->getLocale() === 'ar' ? 'رقم الهاتف (واتساب)' : 'Phone (WhatsApp)' }}
                        </label>
                        <input type="text" class="form-control" id="customerPhone" maxlength="20"
                            placeholder="{{ app()->getLocale() === 'ar' ? '01xxxxxxxxx' : '01xxxxxxxxx' }}">
                        <small class="text-muted">{{ app()->getLocale() === 'ar' ? 'يُستخدم لإرسال الفواتير عبر واتساب' : 'Used to send invoices via WhatsApp' }}</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'البريد الإلكتروني' : 'Email' }}</label>
                        <input type="email" class="form-control" id="customerEmail" maxlength="150">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'الرقم القومي' : 'National ID' }}</label>
                        <input type="text" class="form-control" id="customerNationalId" maxlength="14">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'الرقم الضريبي' : 'Tax Number' }}</label>
                        <input type="text" class="form-control" id="customerTaxNumber" maxlength="20">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'المحافظة' : 'Governate' }}</label>
                        <input type="text" class="form-control" id="customerGovernate" maxlength="50">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'المدينة' : 'City' }}</label>
                        <input type="text" class="form-control" id="customerCity" maxlength="100">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'العنوان' : 'Address' }}</label>
                        <input type="text" class="form-control" id="customerAddress" maxlength="500">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'المجموعة' : 'Group' }}</label>
                        <select class="form-select" id="customerGroupId">
                            <option value="">— {{ app()->getLocale()==='ar' ? 'بدون مجموعة' : 'No group' }} —</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'مستوى السعر' : 'Price Level' }}</label>
                        <select class="form-select" id="customerPriceLevel">
                            <option value="retail">{{ app()->getLocale()==='ar' ? 'تجزئة' : 'Retail' }}</option>
                            <option value="wholesale">{{ app()->getLocale()==='ar' ? 'جملة' : 'Wholesale' }}</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'حد الائتمان' : 'Credit Limit' }}</label>
                        <input type="number" class="form-control" id="customerCreditLimit" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'ملاحظات' : 'Notes' }}</label>
                        <input type="text" class="form-control" id="customerNotes" maxlength="500">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="customerIsActive" checked>
                            <label class="form-check-label" for="customerIsActive">
                                {{ app()->getLocale() === 'ar' ? 'نشط' : 'Active' }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    {{ app()->getLocale() === 'ar' ? 'إلغاء' : 'Cancel' }}
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-fn="saveCustomer" id="saveCustomerBtn">
                    <i class="fas fa-save me-1"></i>{{ app()->getLocale() === 'ar' ? 'حفظ' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Account Statement Modal --}}
<div class="modal fade" id="statementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statementTitle">{{ app()->getLocale() === 'ar' ? 'كشف حساب العميل' : 'Customer Statement' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="statementBody"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
const API_CUSTOMERS = '{{ url("/api/customers") }}';
let currentPage = 1;

async function loadCustomerGroups() {
    const res = await apiCall('{{ route("customer-groups.index") }}?per_page=100');
    const groups = res.data || res.groups || [];
    const sel = document.getElementById('customerGroupId');
    const existing = sel.value;
    sel.innerHTML = `<option value="">— ${isAr ? 'بدون مجموعة' : 'No group'} —</option>`
        + groups.map(g => `<option value="${g.id}">${escHtml(g.name)}</option>`).join('');
    if (existing) sel.value = existing;
}
loadCustomerGroups();

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
// Safe embedding of arbitrary strings inside HTML attribute values (double-quoted)
function escAttr(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
}

async function loadCustomers(page = 1) {
    currentPage = page;
    const tbody = document.getElementById('customersTbody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin me-1"></i></td></tr>`;

    const params = new URLSearchParams({ page, per_page: 20 });
    const s  = document.getElementById('filterSearch').value.trim();
    const t  = document.getElementById('filterType').value;
    const ia = document.getElementById('filterInactive').checked;
    if (s)  params.set('search', s);
    if (t)  params.set('type', t);
    if (ia) params.set('with_inactive', '1');

    const res = await apiCall(`${API_CUSTOMERS}?${params}`);
    if (!res.success) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${res.message}</td></tr>`;
        return;
    }

    // ApiResponse merges array data at top level: { success, current_page, data:[...], total, ... }
    const data = res;
    document.getElementById('customersTotal').textContent =
        (isAr ? 'الإجمالي: ' : 'Total: ') + data.total;

    if (!data.data || !data.data.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">${isAr ? 'لا يوجد عملاء' : 'No customers found'}</td></tr>`;
        document.getElementById('customersPagination').innerHTML = '';
        return;
    }

    tbody.innerHTML = data.data.map(c => {
        const typeLabel = c.type === 'business' ? (isAr?'شركة':'Business') : (isAr?'فرد':'Individual');
        const typeClass = c.type === 'business' ? 'bg-info' : 'bg-secondary';
        const bal = parseFloat(c.balance ?? 0);
        const balClass = bal > 0 ? 'balance-neg' : (bal < 0 ? 'balance-pos' : '');
        return `<tr>
            <td><span class="badge bg-light text-dark border">${escHtml(c.code)}</span></td>
            <td class="fw-semibold">${escHtml(c.name)}</td>
            <td>${c.phone ? `<a href="tel:${escHtml(c.phone)}">${escHtml(c.phone)}</a>` : '<span class="text-muted">—</span>'}</td>
            <td><span class="badge ${typeClass} customer-type-badge">${typeLabel}</span></td>
            <td class="text-end ${balClass} fw-semibold">${formatCurrency(bal)}</td>
            <td class="text-end">${c.loyalty_points ?? 0} <small class="text-muted">pts</small></td>
            <td><span class="badge ${c.is_active ? 'bg-success' : 'bg-danger'}">${c.is_active ? (isAr?'نشط':'Active') : (isAr?'غير نشط':'Inactive')}</span></td>
            <td class="text-center d-flex gap-1 justify-content-center">
                <button class="btn btn-xs btn-sm btn-outline-info"
                    data-fn="showStatement" data-args="[${c.id}]" data-cname="${escAttr(c.name)}"
                    title="${isAr?'كشف حساب':'Statement'}">
                    <i class="fas fa-file-alt"></i>
                </button>
                <button class="btn btn-xs btn-sm btn-outline-primary"
                    data-fn="openCustomerModal" data-args="[${escAttr(JSON.stringify(c))}]"
                    title="${isAr?'تعديل':'Edit'}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-xs btn-sm btn-outline-danger"
                    data-fn="deleteCustomer" data-args="[${c.id}]" data-cname="${escAttr(c.name)}"
                    title="${isAr?'حذف':'Delete'}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    renderPagination(data);
}

function renderPagination(data) {
    const el = document.getElementById('customersPagination');
    if (data.last_page <= 1) { el.innerHTML = ''; return; }
    let html = '';
    if (data.current_page > 1)
        html += `<button class="btn btn-sm btn-outline-secondary" data-fn="loadCustomers" data-args="[${data.current_page - 1}]">‹</button>`;
    html += `<span class="btn btn-sm btn-secondary disabled">${data.current_page}/${data.last_page}</span>`;
    if (data.current_page < data.last_page)
        html += `<button class="btn btn-sm btn-outline-secondary" data-fn="loadCustomers" data-args="[${data.current_page + 1}]">›</button>`;
    el.innerHTML = html;
}

window.applyFilters = function() { loadCustomers(1); };
window.resetFilters = function() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value   = '';
    document.getElementById('filterInactive').checked = false;
    loadCustomers(1);
};

window.openCustomerModal = function(c) {
    document.getElementById('customerId').value            = c?.id            ?? '';
    document.getElementById('customerName').value          = c?.name          ?? '';
    document.getElementById('customerPhone').value         = c?.phone         ?? '';
    document.getElementById('customerEmail').value         = c?.email         ?? '';
    document.getElementById('customerType').value          = c?.type          ?? 'individual';
    document.getElementById('customerNationalId').value    = c?.national_id   ?? '';
    document.getElementById('customerTaxNumber').value     = c?.tax_number    ?? '';
    document.getElementById('customerGovernate').value     = c?.governate     ?? '';
    document.getElementById('customerCity').value          = c?.city          ?? '';
    document.getElementById('customerAddress').value       = c?.address       ?? '';
    document.getElementById('customerCreditLimit').value   = c?.credit_limit  ?? 0;
    document.getElementById('customerNotes').value         = c?.notes         ?? '';
    document.getElementById('customerGroupId').value        = c?.customer_group_id ?? '';
    document.getElementById('customerPriceLevel').value    = c?.price_level      ?? 'retail';
    document.getElementById('customerIsActive').checked    = c ? !!c.is_active : true;
    document.getElementById('customerModalTitle').textContent = c
        ? (isAr ? 'تعديل بيانات العميل' : 'Edit Customer')
        : (isAr ? 'عميل جديد' : 'New Customer');
    new bootstrap.Modal(document.getElementById('customerModal')).show();
};

window.saveCustomer = async function() {
    const id   = document.getElementById('customerId').value;
    const name = document.getElementById('customerName').value.trim();
    if (!name) { showToast(isAr ? 'الاسم مطلوب' : 'Name is required', 'error'); return; }

    const payload = {
        name,
        phone:               document.getElementById('customerPhone').value.trim()       || null,
        email:               document.getElementById('customerEmail').value.trim()       || null,
        type:                document.getElementById('customerType').value,
        national_id:         document.getElementById('customerNationalId').value.trim()  || null,
        tax_number:          document.getElementById('customerTaxNumber').value.trim()   || null,
        governate:           document.getElementById('customerGovernate').value.trim()   || null,
        city:                document.getElementById('customerCity').value.trim()        || null,
        address:             document.getElementById('customerAddress').value.trim()     || null,
        credit_limit:        parseFloat(document.getElementById('customerCreditLimit').value) || 0,
        notes:               document.getElementById('customerNotes').value.trim()       || null,
        customer_group_id:   document.getElementById('customerGroupId').value            || null,
        price_level:         document.getElementById('customerPriceLevel').value,
        is_active:           document.getElementById('customerIsActive').checked,
    };

    const btn = document.getElementById('saveCustomerBtn');
    btn.disabled = true;
    const url    = id ? `${API_CUSTOMERS}/${id}` : API_CUSTOMERS;
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, payload);
    btn.disabled = false;

    if (!res.success) { showToast(res.message || (isAr?'حدث خطأ':'Error'), 'error'); return; }
    bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
    showToast(isAr ? (id?'تم التعديل':'تم الحفظ') : (id?'Updated':'Saved'));
    loadCustomers(currentPage);
};

window.deleteCustomer = async function(id, el) {
    const name = el?.dataset?.cname ?? id;
    if (!confirm(isAr ? `هل تريد حذف العميل "${name}"؟` : `Delete customer "${name}"?`)) return;
    const res = await apiCall(`${API_CUSTOMERS}/${id}`, 'DELETE');
    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    showToast(res.message || (isAr?'تم الحذف':'Deleted'));
    loadCustomers(currentPage);
};

window.showStatement = async function(id, el) {
    const name = el?.dataset?.cname ?? id;
    document.getElementById('statementTitle').textContent =
        (isAr ? 'كشف حساب: ' : 'Statement: ') + name;
    document.getElementById('statementBody').innerHTML =
        `<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>`;
    new bootstrap.Modal(document.getElementById('statementModal')).show();

    const res = await apiCall(`${API_CUSTOMERS}/${id}`);
    if (!res.success) {
        document.getElementById('statementBody').innerHTML =
            `<div class="text-danger text-center">${res.message}</div>`;
        return;
    }

    const c = res.customer;
    const entries = c.account_entries ?? [];
    let html = `
        <div class="row g-2 mb-3">
            <div class="col-sm-4"><strong>${isAr?'الكود':'Code'}:</strong> ${escHtml(c.code)}</div>
            <div class="col-sm-4"><strong>${isAr?'الهاتف':'Phone'}:</strong> ${escHtml(c.phone||'—')}</div>
            <div class="col-sm-4"><strong>${isAr?'الرصيد':'Balance'}:</strong>
                <span class="${parseFloat(c.balance)>0?'text-danger':'text-success'} fw-bold">${formatCurrency(c.balance)}</span>
            </div>
            <div class="col-sm-4"><strong>${isAr?'نقاط الولاء':'Loyalty Points'}:</strong> ${c.loyalty_points}</div>
            <div class="col-sm-4"><strong>${isAr?'حد الائتمان':'Credit Limit'}:</strong> ${formatCurrency(c.credit_limit)}</div>
        </div>`;

    if (!entries.length) {
        html += `<p class="text-muted text-center">${isAr?'لا توجد حركات':'No transactions'}</p>`;
    } else {
        html += `<div class="table-responsive"><table class="table table-sm table-bordered">
            <thead class="table-light"><tr>
                <th>${isAr?'النوع':'Type'}</th>
                <th class="text-end">${isAr?'مدين':'Debit'}</th>
                <th class="text-end">${isAr?'دائن':'Credit'}</th>
                <th class="text-end">${isAr?'الرصيد':'Balance'}</th>
                <th>${isAr?'التاريخ':'Date'}</th>
            </tr></thead><tbody>
            ${entries.slice(-30).map(e => `<tr>
                <td>${escHtml(e.type)}</td>
                <td class="text-end">${e.debit > 0 ? formatCurrency(e.debit) : '—'}</td>
                <td class="text-end text-success">${e.credit > 0 ? formatCurrency(e.credit) : '—'}</td>
                <td class="text-end">${formatCurrency(e.balance_after)}</td>
                <td class="small text-muted">${formatDate(e.created_at)}</td>
            </tr>`).join('')}
            </tbody></table></div>`;
    }
    document.getElementById('statementBody').innerHTML = html;
};

window.loadCustomers = loadCustomers;

document.addEventListener('DOMContentLoaded', () => loadCustomers(1));
</script>
@endpush
