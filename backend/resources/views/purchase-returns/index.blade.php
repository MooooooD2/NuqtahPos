@extends('layouts.app')
@section('title', __('pos.purchase_returns'))
@section('page-title', __('pos.purchase_returns'))

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-undo-alt me-2 text-info"></i>{{ __('pos.purchase_returns') }}
        </h5>
        @permission('view_warehouse')
        <button class="btn btn-info btn-sm text-white" data-fn="openReturnModal">
            <i class="fas fa-plus me-1"></i>{{ __('pos.new_purchase_return') }}
        </button>
        @endpermission
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4 col-12">
                    <label class="form-label form-label-sm mb-1">{{ app()->getLocale() === 'ar' ? 'رقم أمر الشراء' : 'Purchase Order No.' }}</label>
                    <input type="text" class="form-control form-control-sm" id="filterPO" placeholder="{{ app()->getLocale() === 'ar' ? 'بحث...' : 'Search...' }}" data-on-input="applyReturnFilters">
                </div>
                <div class="col-sm-2 col-12">
                    <button class="btn btn-outline-secondary btn-sm w-100" data-fn="resetReturnFilters">
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
                            <th>{{ __('pos.return_number') }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'المورد' : 'Supplier' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'تاريخ الإرجاع' : 'Return Date' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإجمالي' : 'Total' }}</th>
                            <th>{{ __('pos.refund_method') }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'بواسطة' : 'By' }}</th>
                            <th class="text-center">{{ app()->getLocale() === 'ar' ? 'تفاصيل' : 'Details' }}</th>
                        </tr>
                    </thead>
                    <tbody id="returnsTbody">
                        <tr><td colspan="8" class="text-center py-4">
                            <i class="fas fa-spinner fa-spin me-1"></i>
                            {{ app()->getLocale() === 'ar' ? 'جاري التحميل...' : 'Loading...' }}
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center py-2">
            <span id="returnsTotal" class="fw-bold text-info"></span>
            <div id="returnsPagination" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

{{-- New Purchase Return Modal --}}
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pos.new_purchase_return') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Step 1: Choose PO --}}
                <div id="step1">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'رقم أمر الشراء' : 'Purchase Order Number' }} <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="poSearchInput" placeholder="{{ app()->getLocale() === 'ar' ? 'أدخل رقم أمر الشراء أو اسم المورد' : 'Enter PO number or supplier name' }}">
                            <button class="btn btn-outline-primary" data-fn="searchPurchaseOrders">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div id="poSearchResults" class="list-group mb-2"></div>
                </div>

                {{-- Step 2: Items --}}
                <div id="step2" style="display:none">
                    <div class="alert alert-info py-2 mb-3" id="selectedPoInfo"></div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ app()->getLocale() === 'ar' ? 'المنتج' : 'Product' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'قابل للإرجاع' : 'Returnable' }}</th>
                                    <th style="width:120px">{{ app()->getLocale() === 'ar' ? 'الكمية' : 'Qty' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'التكلفة' : 'Cost' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإجمالي' : 'Total' }}</th>
                                </tr>
                            </thead>
                            <tbody id="returnItemsTbody"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">{{ app()->getLocale() === 'ar' ? 'الإجمالي:' : 'Total:' }}</td>
                                    <td class="text-end fw-bold text-info" id="returnTotal">—</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">{{ __('pos.refund_method') }}</label>
                            <select class="form-select form-select-sm" id="returnRefundMethod">
                                <option value="credit_note">{{ app()->getLocale() === 'ar' ? 'إشعار دائن' : 'Credit Note' }}</option>
                                <option value="cash">{{ app()->getLocale() === 'ar' ? 'نقداً' : 'Cash' }}</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">{{ __('pos.reason') }}</label>
                            <input type="text" class="form-control form-control-sm" id="returnReason" maxlength="500" placeholder="{{ app()->getLocale() === 'ar' ? 'سبب الإرجاع...' : 'Reason for return...' }}">
                        </div>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm mt-3" data-fn="backToStep1">
                        <i class="fas fa-arrow-left me-1"></i>{{ app()->getLocale() === 'ar' ? 'تغيير أمر الشراء' : 'Change PO' }}
                    </button>
                </div>
            </div>
            <div class="modal-footer" id="returnModalFooter" style="display:none!important">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    {{ app()->getLocale() === 'ar' ? 'إلغاء' : 'Cancel' }}
                </button>
                <button type="button" class="btn btn-info btn-sm text-white" data-fn="submitReturn" id="submitReturnBtn">
                    <i class="fas fa-check me-1"></i>{{ app()->getLocale() === 'ar' ? 'تأكيد الإرجاع' : 'Confirm Return' }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Details Modal --}}
<div class="modal fade" id="returnDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ app()->getLocale() === 'ar' ? 'تفاصيل المرتجع' : 'Return Details' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="returnDetailsBody"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
let currentReturnPage = 1;
let selectedPO = null;
let returnItems = [];

async function loadReturns(page = 1) {
    currentReturnPage = page;
    const tbody = document.getElementById('returnsTbody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin me-1"></i></td></tr>`;

    const params = new URLSearchParams({ page, per_page: 20 });
    const res = await apiCall('{{ route("purchase-returns.all") }}?' + params.toString());
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${res.message}</td></tr>`; return; }

    const data = res.purchase_returns;
    if (!data.data.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">${isAr?'لا توجد مرتجعات':'No returns found'}</td></tr>`;
        document.getElementById('returnsTotal').textContent = '';
        document.getElementById('returnsPagination').innerHTML = '';
        return;
    }

    let total = 0;
    tbody.innerHTML = data.data.map(r => {
        total += parseFloat(r.total_amount);
        const refundLabel = r.refund_method === 'cash' ? (isAr?'نقداً':'Cash') : (isAr?'إشعار دائن':'Credit Note');
        return `<tr>
            <td><span class="badge bg-info">${r.return_number}</span></td>
            <td>${escHtml(r.supplier_name)}</td>
            <td>${formatDate(r.return_date)}</td>
            <td class="text-end fw-semibold">${formatCurrency(r.total_amount)}</td>
            <td><span class="badge bg-secondary">${refundLabel}</span></td>
            <td><span class="badge bg-success">${r.status}</span></td>
            <td class="small text-muted">${escHtml(r.processed_by_name||'')}</td>
            <td class="text-center">
                <button class="btn btn-xs btn-outline-info btn-sm" data-fn="showReturnDetails" data-args='[${JSON.stringify(r)}]'>
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    document.getElementById('returnsTotal').textContent = (isAr?'الإجمالي: ':'Total: ') + formatCurrency(total);
    renderReturnPagination(data);
}

function renderReturnPagination(data) {
    const el = document.getElementById('returnsPagination');
    if (data.last_page <= 1) { el.innerHTML = ''; return; }
    let html = '';
    if (data.current_page > 1)
        html += `<button class="btn btn-sm btn-outline-secondary" data-fn="loadReturns" data-args="[${data.current_page-1}]">‹</button>`;
    html += `<span class="btn btn-sm btn-secondary disabled">${data.current_page}/${data.last_page}</span>`;
    if (data.current_page < data.last_page)
        html += `<button class="btn btn-sm btn-outline-secondary" data-fn="loadReturns" data-args="[${data.current_page+1}]">›</button>`;
    el.innerHTML = html;
}

window.applyReturnFilters = window.resetReturnFilters = function() { loadReturns(1); };
window.resetReturnFilters = function() {
    document.getElementById('filterPO').value = '';
    loadReturns(1);
};

window.openReturnModal = function() {
    selectedPO = null;
    returnItems = [];
    document.getElementById('poSearchInput').value = '';
    document.getElementById('poSearchResults').innerHTML = '';
    document.getElementById('step1').style.display = '';
    document.getElementById('step2').style.display = 'none';
    document.getElementById('returnModalFooter').style.setProperty('display','none','important');
    new bootstrap.Modal(document.getElementById('returnModal')).show();
};

window.searchPurchaseOrders = async function() {
    const q = document.getElementById('poSearchInput').value.trim();
    if (!q) return;
    const res = await apiCall(`{{ route("purchase-orders.all") }}?search=${encodeURIComponent(q)}&status=received`);
    const results = document.getElementById('poSearchResults');
    if (!res.success || !res.purchase_orders?.data?.length) {
        results.innerHTML = `<div class="list-group-item text-muted small">${isAr?'لا توجد نتائج':'No results'}</div>`;
        return;
    }
    results.innerHTML = res.purchase_orders.data.slice(0, 10).map(po =>
        `<button type="button" class="list-group-item list-group-item-action small" data-fn="selectPO" data-args='[${JSON.stringify(po)}]'>
            <strong>${po.po_number}</strong> — ${escHtml(po.supplier_name)}
            <span class="text-muted ms-2">${formatDate(po.created_at)}</span>
        </button>`
    ).join('');
};

window.selectPO = async function(po) {
    selectedPO = po;
    document.getElementById('poSearchResults').innerHTML = '';
    const res = await apiCall(`{{ url('/api/purchase-orders') }}/${po.id}/returnable-items`);
    if (!res.success || !res.items?.length) {
        showToast(isAr?'لا توجد بنود قابلة للإرجاع':'No returnable items', 'error'); return;
    }

    returnItems = res.items.map(i => ({ ...i, qty: 0 }));
    document.getElementById('selectedPoInfo').innerHTML =
        `<i class="fas fa-info-circle me-1"></i><strong>${po.po_number}</strong> — ${escHtml(po.supplier_name)}`;

    renderReturnItems();
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = '';
    document.getElementById('returnModalFooter').style.removeProperty('display');
};

function renderReturnItems() {
    document.getElementById('returnItemsTbody').innerHTML = returnItems.map((item, idx) =>
        `<tr>
            <td>${escHtml(item.product_name)}${item.unit_abbreviation ? ` <span class="badge bg-secondary ms-1">${escHtml(item.unit_abbreviation)}</span>` : ''}</td>
            <td class="text-end">${item.returnable_quantity}</td>
            <td>
                <input type="number" class="form-control form-control-sm" min="0" max="${item.returnable_quantity}"
                    value="${item.qty}" data-return-idx="${idx}">
            </td>
            <td class="text-end">${formatCurrency(item.cost_price)}</td>
            <td class="text-end" id="rowTotal${idx}">${formatCurrency(item.qty * item.cost_price)}</td>
        </tr>`
    ).join('');
    updateReturnTotalDisplay();
}

document.getElementById('returnItemsTbody').addEventListener('input', function(e) {
    const input = e.target.closest('input[data-return-idx]');
    if (!input) return;
    const idx = parseInt(input.dataset.returnIdx);
    const max = returnItems[idx].returnable_quantity;
    returnItems[idx].qty = Math.min(Math.max(0, parseInt(input.value) || 0), max);
    document.getElementById(`rowTotal${idx}`).textContent =
        formatCurrency(returnItems[idx].qty * returnItems[idx].cost_price);
    updateReturnTotalDisplay();
});

function updateReturnTotalDisplay() {
    const total = returnItems.reduce((s, i) => s + i.qty * i.cost_price, 0);
    document.getElementById('returnTotal').textContent = formatCurrency(total);
}

window.backToStep1 = function() {
    document.getElementById('step1').style.display = '';
    document.getElementById('step2').style.display = 'none';
    document.getElementById('returnModalFooter').style.setProperty('display','none','important');
};

window.submitReturn = async function() {
    const items = returnItems.filter(i => i.qty > 0).map(i => ({ product_id: i.product_id, quantity: i.qty }));
    if (!items.length) { showToast(isAr?'يرجى إدخال كمية للإرجاع':'Enter at least one item quantity', 'error'); return; }

    const payload = {
        purchase_order_id: selectedPO.id,
        refund_method: document.getElementById('returnRefundMethod').value,
        reason: document.getElementById('returnReason').value || null,
        items,
    };

    const btn = document.getElementById('submitReturnBtn');
    btn.disabled = true;
    const res = await apiCall('{{ route("purchase-returns.store") }}', 'POST', payload);
    btn.disabled = false;

    if (!res.success) { showToast(res.message || (isAr?'حدث خطأ':'Error'), 'error'); return; }
    bootstrap.Modal.getInstance(document.getElementById('returnModal')).hide();
    showToast(isAr?'تم تسجيل المرتجع بنجاح':'Purchase return created successfully');
    loadReturns(currentReturnPage);
};

window.showReturnDetails = function(ret) {
    const refundLabel = ret.refund_method === 'cash' ? (isAr?'نقداً':'Cash') : (isAr?'إشعار دائن':'Credit Note');
    let html = `
        <div class="row g-2 mb-3">
            <div class="col-sm-6"><strong>${isAr?'رقم المرتجع':'Return No.'}:</strong> ${ret.return_number}</div>
            <div class="col-sm-6"><strong>${isAr?'المورد':'Supplier'}:</strong> ${escHtml(ret.supplier_name)}</div>
            <div class="col-sm-6"><strong>${isAr?'التاريخ':'Date'}:</strong> ${formatDate(ret.return_date)}</div>
            <div class="col-sm-6"><strong>${isAr?'طريقة الاسترداد':'Refund Method'}:</strong> ${refundLabel}</div>
            ${ret.reason ? `<div class="col-12"><strong>${isAr?'السبب':'Reason'}:</strong> ${escHtml(ret.reason)}</div>` : ''}
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>${isAr?'المنتج':'Product'}</th>
                        <th class="text-end">${isAr?'الكمية':'Qty'}</th>
                        <th class="text-end">${isAr?'التكلفة':'Cost'}</th>
                        <th class="text-end">${isAr?'الإجمالي':'Total'}</th>
                    </tr>
                </thead>
                <tbody>
                    ${(ret.items||[]).map(i => `<tr>
                        <td>${escHtml(i.product_name)}${i.unit_abbreviation ? ` <span class="badge bg-secondary ms-1">${escHtml(i.unit_abbreviation)}</span>` : ''}</td>
                        <td class="text-end">${i.quantity}</td>
                        <td class="text-end">${formatCurrency(i.unit_cost)}</td>
                        <td class="text-end">${formatCurrency(i.subtotal)}</td>
                    </tr>`).join('')}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">${isAr?'الإجمالي:':'Total:'}</td>
                        <td class="text-end fw-bold text-info">${formatCurrency(ret.total_amount)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>`;
    document.getElementById('returnDetailsBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('returnDetailsModal')).show();
};

function escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

window.loadReturns = loadReturns;

document.addEventListener('DOMContentLoaded', () => loadReturns(1));
</script>
@endpush
