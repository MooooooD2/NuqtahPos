@extends('layouts.app')
@section('title', __('pos.warehouses'))
@section('page-title', __('pos.warehouses'))

@push('styles')
<style @nonce>
    .wh-card { transition: box-shadow .2s; }
    .wh-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); }
    .transfer-badge { font-size: .7rem; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    <ul class="nav nav-tabs mb-4" id="whTab">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabWarehouses">
                <i class="fas fa-warehouse me-1"></i>
                {{ app()->getLocale() === 'ar' ? 'المستودعات' : 'Warehouses' }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabStock" data-fn="onStockTabOpen">
                <i class="fas fa-boxes me-1"></i>
                {{ app()->getLocale() === 'ar' ? 'المخزون' : 'Stock' }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabTransfers" data-fn="loadTransfers">
                <i class="fas fa-exchange-alt me-1"></i>
                {{ app()->getLocale() === 'ar' ? 'التحويلات' : 'Transfers' }}
            </a>
        </li>
    </ul>

    <div class="tab-content pt-2">

        {{-- ── Warehouses ── --}}
        <div class="tab-pane fade show active" id="tabWarehouses">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">{{ app()->getLocale() === 'ar' ? 'قائمة المستودعات' : 'Warehouse List' }}</h6>
                @permission('manage_roles')
                <button class="btn btn-primary btn-sm" data-fn="openWhModal" data-args="[null]">
                    <i class="fas fa-plus me-1"></i>{{ app()->getLocale() === 'ar' ? 'إضافة مستودع' : 'Add Warehouse' }}
                </button>
                @endpermission
            </div>
            <div class="row g-3" id="warehousesGrid">
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
        </div>

        {{-- ── Stock ── --}}
        <div class="tab-pane fade" id="tabStock">
            <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
                <select class="form-select form-select-sm" class="u-mw-200" id="stockWhSelect"
                    data-on-change="loadStock">
                    <option value="">{{ app()->getLocale() === 'ar' ? 'اختر مستودعاً' : 'Select warehouse' }}</option>
                </select>
                <input type="text" class="form-control form-control-sm" class="u-mw-200"
                    id="stockSearch" placeholder="{{ app()->getLocale() === 'ar' ? 'بحث...' : 'Search...' }}"
                    data-on-input="filterStock">
                <button class="btn btn-sm btn-outline-success ms-auto" id="syncStockBtn"
                    data-fn="syncStock" title="{{ app()->getLocale() === 'ar' ? 'مزامنة المخزون مع بيانات المنتجات' : 'Sync stock from product quantities' }}">
                    <i class="fas fa-sync-alt me-1"></i>
                    {{ app()->getLocale() === 'ar' ? 'مزامنة' : 'Sync' }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>{{ app()->getLocale() === 'ar' ? 'المنتج' : 'Product' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الوحدة' : 'Unit' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الكمية' : 'Qty' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'محجوز' : 'Reserved' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'متاح' : 'Available' }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الحد الأدنى' : 'Min' }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="stockTbody">
                        <tr><td colspan="7" class="text-center py-4 text-muted">
                            {{ app()->getLocale() === 'ar' ? 'اختر مستودعاً أولاً' : 'Select a warehouse first' }}
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Transfers ── --}}
        <div class="tab-pane fade" id="tabTransfers">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">{{ app()->getLocale() === 'ar' ? 'تحويلات المخزون' : 'Stock Transfers' }}</h6>
                @permission('view_warehouse')
                <button class="btn btn-outline-primary btn-sm" data-fn="openTransferModal">
                    <i class="fas fa-plus me-1"></i>{{ app()->getLocale() === 'ar' ? 'تحويل جديد' : 'New Transfer' }}
                </button>
                @endpermission
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'من' : 'From' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'إلى' : 'To' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'التاريخ' : 'Date' }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="transfersTbody">
                        <tr><td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-spinner fa-spin"></i>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Warehouse Modal --}}
<div class="modal fade" id="whModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="whModalTitle">{{ app()->getLocale() === 'ar' ? 'إضافة مستودع' : 'Add Warehouse' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="whId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'الاسم' : 'Name' }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="whName">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'الكود' : 'Code' }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="whCode" placeholder="WH-001" maxlength="20">
                    <div class="form-text text-muted">{{ app()->getLocale() === 'ar' ? 'حروف وأرقام فقط، بحد أقصى 20 حرفاً' : 'Letters & numbers only, max 20 chars' }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'العنوان' : 'Address' }}</label>
                    <input type="text" class="form-control" id="whAddress">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'أمين المستودع' : 'Keeper Name' }}</label>
                    <input type="text" class="form-control" id="whKeeper">
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="whIsDefault">
                    <label class="form-check-label" for="whIsDefault">{{ app()->getLocale() === 'ar' ? 'افتراضي' : 'Default' }}</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="whIsActive" checked>
                    <label class="form-check-label" for="whIsActive">{{ app()->getLocale() === 'ar' ? 'نشط' : 'Active' }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" id="saveWhBtn" data-fn="saveWarehouse">
                    {{ __('pos.save') }}
                    <span id="saveWhSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Adjust Stock Modal --}}
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h6 class="modal-title fw-bold mb-0">
                    <i class="fas fa-sliders-h me-1"></i>
                    {{ app()->getLocale() === 'ar' ? 'تعديل الكمية' : 'Adjust Stock' }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="adjProductId">
                <p class="mb-2 fw-semibold" id="adjProductName"></p>
                <div class="mb-3">
                    <label class="form-label small text-muted">
                        {{ app()->getLocale() === 'ar' ? 'الكمية الحالية' : 'Current Qty' }}
                    </label>
                    <input type="number" class="form-control" id="adjCurrentQty" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">
                        {{ app()->getLocale() === 'ar' ? 'الكمية الصحيحة *' : 'Correct Qty *' }}
                    </label>
                    <input type="number" class="form-control" id="adjNewQty" min="0" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted">
                        {{ app()->getLocale() === 'ar' ? 'السبب' : 'Reason' }}
                    </label>
                    <input type="text" class="form-control form-control-sm" id="adjReason"
                        placeholder="{{ app()->getLocale() === 'ar' ? 'تصحيح يدوي...' : 'Manual correction...' }}">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-warning btn-sm" id="saveAdjBtn" data-fn="saveStockAdjust">
                    <i class="fas fa-check me-1"></i>{{ app()->getLocale() === 'ar' ? 'حفظ' : 'Save' }}
                    <span id="adjSpinner" class="spinner-border spinner-border-sm ms-1 d-none"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Transfer Modal --}}
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ app()->getLocale() === 'ar' ? 'تحويل مخزون جديد' : 'New Stock Transfer' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'من مستودع' : 'From' }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="tfFrom"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'إلى مستودع' : 'To' }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="tfTo"></select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'ملاحظات' : 'Notes' }}</label>
                    <input type="text" class="form-control" id="tfNotes">
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">{{ app()->getLocale() === 'ar' ? 'المنتجات' : 'Items' }}</h6>
                </div>
                <div class="row g-2 mb-1 small text-muted fw-semibold">
                    <div class="col-7">{{ app()->getLocale() === 'ar' ? 'المنتج' : 'Product' }}</div>
                    <div class="col-4">{{ app()->getLocale() === 'ar' ? 'الكمية' : 'Qty' }}</div>
                </div>
                <div id="tfItems"></div>
                <button class="btn btn-sm btn-outline-primary mt-2" data-fn="addTfRow">
                    <i class="fas fa-plus me-1"></i>{{ app()->getLocale() === 'ar' ? 'إضافة منتج' : 'Add Item' }}
                </button>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" id="saveTfBtn" data-fn="saveTransfer">
                    <i class="fas fa-paper-plane me-1"></i>{{ app()->getLocale() === 'ar' ? 'إرسال' : 'Send' }}
                    <span id="saveTfSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
const WH_API = '{{ url("/api/warehouses") }}';
const TF_API = '{{ url("/api/warehouse-transfers") }}';
let warehousesList = [];
let allStock       = [];
let productsList   = [];
let whModalInst, tfModalInst;
let stockLoaded = false;

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;');
}

// ── WAREHOUSES ────────────────────────────────────────────────
async function loadWarehouses() {
    const [whRes, pRes] = await Promise.all([
        apiCall(WH_API),
        apiCall('{{ url("/api/warehouses/products-list") }}'),
    ]);
    const list = Array.isArray(whRes) ? whRes : (whRes.data ?? []);
    warehousesList = Array.isArray(list) ? list : [];
    productsList   = Array.isArray(pRes.products) ? pRes.products : (Array.isArray(pRes.products?.data) ? pRes.products.data : []);
    renderWarehouses(warehousesList);
    populateSelects();
}

function renderWarehouses(list) {
    const grid = document.getElementById('warehousesGrid');
    if (!list.length) {
        grid.innerHTML = `<div class="col-12 text-center text-muted py-5">
            <i class="fas fa-warehouse fa-3x mb-3 opacity-25"></i>
            <p>${isAr ? 'لا توجد مستودعات بعد' : 'No warehouses yet'}</p>
        </div>`;
        return;
    }
    grid.innerHTML = list.map(w => {
        const wJson = escAttr(JSON.stringify(w));
        return `<div class="col-md-6 col-xl-4">
          <div class="card wh-card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="fw-bold mb-0">${esc(w.name)}</h6>
                <div>
                  ${w.is_default ? `<span class="badge bg-primary me-1">${isAr?'افتراضي':'Default'}</span>` : ''}
                  <span class="badge ${w.is_active ? 'bg-success' : 'bg-secondary'}">
                    ${w.is_active ? (isAr?'نشط':'Active') : (isAr?'غير نشط':'Inactive')}
                  </span>
                </div>
              </div>
              <p class="text-muted small mb-1"><i class="fas fa-barcode me-1"></i>${esc(w.code)}</p>
              ${w.address     ? `<p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i>${esc(w.address)}</p>` : ''}
              ${w.keeper_name ? `<p class="text-muted small mb-1"><i class="fas fa-user me-1"></i>${esc(w.keeper_name)}</p>` : ''}
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary flex-fill"
                  data-fn="openWhModal" data-args="[${wJson}]">
                <i class="fas fa-edit me-1"></i>${isAr?'تعديل':'Edit'}
              </button>
              ${!w.is_default ? `<button class="btn btn-sm btn-outline-danger"
                  data-fn="deleteWarehouse" data-args="[${w.id}]">
                <i class="fas fa-trash"></i>
              </button>` : ''}
            </div>
          </div>
        </div>`;
    }).join('');
}

function populateSelects() {
    const opts = warehousesList.map(w => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
    const stockSel = document.getElementById('stockWhSelect');
    stockSel.innerHTML = `<option value="">${isAr ? 'اختر مستودعاً' : 'Select warehouse'}</option>` + opts;
    ['tfFrom','tfTo'].forEach(id => document.getElementById(id).innerHTML = opts);
}

window.openWhModal = function(w) {
    document.getElementById('whId').value          = w?.id          ?? '';
    document.getElementById('whName').value        = w?.name        ?? '';
    document.getElementById('whCode').value        = w?.code        ?? '';
    document.getElementById('whAddress').value     = w?.address     ?? '';
    document.getElementById('whKeeper').value      = w?.keeper_name ?? '';
    document.getElementById('whIsDefault').checked = w?.is_default  ?? false;
    document.getElementById('whIsActive').checked  = w ? w.is_active : true;
    document.getElementById('whModalTitle').textContent = w
        ? (isAr ? 'تعديل المستودع' : 'Edit Warehouse')
        : (isAr ? 'إضافة مستودع'  : 'Add Warehouse');
    whModalInst = whModalInst ?? new bootstrap.Modal(document.getElementById('whModal'));
    whModalInst.show();
};

window.saveWarehouse = async function() {
    const id   = document.getElementById('whId').value;
    const name = document.getElementById('whName').value.trim();
    const code = document.getElementById('whCode').value.trim();
    if (!name || !code) {
        showToast(isAr ? 'الاسم والكود مطلوبان' : 'Name and code are required', 'error');
        return;
    }

    const btn = document.getElementById('saveWhBtn');
    btn.disabled = true;
    document.getElementById('saveWhSpinner').classList.remove('d-none');

    const payload = {
        name, code,
        address:     document.getElementById('whAddress').value  || null,
        keeper_name: document.getElementById('whKeeper').value   || null,
        is_default:  document.getElementById('whIsDefault').checked,
        is_active:   document.getElementById('whIsActive').checked,
    };

    const res = await apiCall(id ? `${WH_API}/${id}` : WH_API, id ? 'PUT' : 'POST', payload);
    btn.disabled = false;
    document.getElementById('saveWhSpinner').classList.add('d-none');

    if (!res.success) {
        const msg = res.errors
            ? Object.values(res.errors).flat().join('\n')
            : (res.message || 'Error');
        showToast(msg, 'error');
        return;
    }
    whModalInst.hide();
    showToast(isAr ? (id ? 'تم التعديل' : 'تمت الإضافة') : (id ? 'Updated' : 'Added'));
    loadWarehouses();
    stockLoaded = false;
};

window.deleteWarehouse = async function(id) {
    if (!confirm(isAr ? 'هل تريد حذف هذا المستودع؟' : 'Delete this warehouse?')) return;
    const res = await apiCall(`${WH_API}/${id}`, 'DELETE');
    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    showToast(isAr ? 'تم الحذف' : 'Deleted');
    loadWarehouses();
};

// ── STOCK ─────────────────────────────────────────────────────
window.onStockTabOpen = function() { if (!stockLoaded) loadStock(); };

window.loadStock = async function() {
    const whId = document.getElementById('stockWhSelect').value;
    if (!whId) return;
    const tbody = document.getElementById('stockTbody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    const res = await apiCall(`${WH_API}/${whId}/stock`);
    if (res.success === false) {
        showToast(res.message || (isAr ? 'خطأ في تحميل المخزون' : 'Error loading stock'), 'error');
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">${res.message || 'Error'}</td></tr>`;
        return;
    }
    // API returns { success: true, stock: [...] }
    allStock = Array.isArray(res.stock) ? res.stock
             : Array.isArray(res)       ? res
             : [];
    stockLoaded = true;
    renderStock(allStock);
};

window.filterStock = function() {
    const q = document.getElementById('stockSearch').value.toLowerCase();
    renderStock(q ? allStock.filter(s => (s.product?.name || '').toLowerCase().includes(q)) : allStock);
};

function renderStock(list) {
    const tbody = document.getElementById('stockTbody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">${isAr ? 'لا توجد بيانات' : 'No data'}</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(s => {
        const avail   = (s.quantity ?? 0) - (s.reserved_qty ?? 0);
        const low     = (s.min_stock ?? 0) > 0 && avail <= s.min_stock;
        const neg     = (s.quantity ?? 0) < 0;
        const sJson   = escAttr(JSON.stringify(s));
        const unitAbbr = s.product?.unit?.abbreviation ?? s.product?.unit?.name ?? '';
        return `<tr class="${neg ? 'table-danger' : low ? 'table-warning' : ''}">
          <td>${esc(s.product?.name ?? '-')}${neg ? ' <span class="badge bg-danger ms-1">!</span>' : ''}</td>
          <td>${unitAbbr ? `<span class="badge bg-info text-dark">${esc(unitAbbr)}</span>` : '<span class="text-muted">-</span>'}</td>
          <td class="text-end ${neg ? 'text-danger fw-bold' : ''}">${s.quantity ?? 0}</td>
          <td class="text-end text-warning">${s.reserved_qty ?? 0}</td>
          <td class="text-end ${neg || low ? 'text-danger fw-bold' : ''}">${avail}</td>
          <td class="text-end text-muted">${s.min_stock ?? 0}</td>
          <td class="text-end">
            <button class="btn btn-xs btn-sm btn-outline-warning py-0 px-1"
                data-fn="openAdjustModal" data-args="[${sJson}]"
                title="${isAr ? 'تعديل الكمية' : 'Adjust'}">
              <i class="fas fa-sliders-h"></i>
            </button>
          </td>
        </tr>`;
    }).join('');
}

let adjModalInst;
window.openAdjustModal = function(s) {
    document.getElementById('adjProductId').value     = s.product_id;
    document.getElementById('adjProductName').textContent = s.product?.name ?? '#' + s.product_id;
    document.getElementById('adjCurrentQty').value    = s.quantity ?? 0;
    document.getElementById('adjNewQty').value        = Math.max(0, s.quantity ?? 0);
    document.getElementById('adjReason').value        = '';
    adjModalInst = adjModalInst ?? new bootstrap.Modal(document.getElementById('adjustStockModal'));
    adjModalInst.show();
    setTimeout(() => document.getElementById('adjNewQty').focus(), 350);
};

window.saveStockAdjust = async function() {
    const whId      = document.getElementById('stockWhSelect').value;
    const productId = document.getElementById('adjProductId').value;
    const newQty    = parseInt(document.getElementById('adjNewQty').value);
    const reason    = document.getElementById('adjReason').value.trim();

    if (!whId || isNaN(newQty) || newQty < 0) {
        showToast(isAr ? 'أدخل كمية صحيحة' : 'Enter a valid quantity', 'error');
        return;
    }

    const btn = document.getElementById('saveAdjBtn');
    btn.disabled = true;
    document.getElementById('adjSpinner').classList.remove('d-none');

    const res = await apiCall(`${WH_API}/${whId}/adjust-stock`, 'POST', {
        product_id:   parseInt(productId),
        new_quantity: newQty,
        reason:       reason || 'Manual stock correction',
    });

    btn.disabled = false;
    document.getElementById('adjSpinner').classList.add('d-none');

    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    adjModalInst.hide();
    showToast(isAr ? 'تم تعديل الكمية' : 'Stock adjusted');
    loadStock();
};

window.syncStock = async function() {
    const whId = document.getElementById('stockWhSelect').value;
    if (!whId) {
        showToast(isAr ? 'اختر مستودعاً أولاً' : 'Select a warehouse first', 'error');
        return;
    }
    const label = isAr ? 'مزامنة المخزون مع كميات المنتجات؟' : 'Sync warehouse stock to match product quantities?';
    if (!confirm(label)) return;

    const btn = document.getElementById('syncStockBtn');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>${isAr ? 'جارٍ المزامنة...' : 'Syncing...'}`;

    const res = await apiCall(`${WH_API}/${whId}/sync-stock`, 'POST');

    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-sync-alt me-1"></i>${isAr ? 'مزامنة' : 'Sync'}`;

    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    showToast(isAr ? `تمت المزامنة (${res.updated} منتج)` : `Synced ${res.updated} product(s)`);
    loadStock();
};

// ── TRANSFERS ─────────────────────────────────────────────────
window.loadTransfers = async function() {
    const tbody = document.getElementById('transfersTbody');
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></td></tr>`;
    const res  = await apiCall(TF_API);
    const list = Array.isArray(res) ? res : (res.data ?? []);
    renderTransfers(Array.isArray(list) ? list : []);
};

function renderTransfers(list) {
    const tbody = document.getElementById('transfersTbody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">${isAr ? 'لا توجد تحويلات' : 'No transfers'}</td></tr>`;
        return;
    }
    const badge = { pending:'secondary', in_transit:'warning text-dark', received:'success', cancelled:'danger' };
    tbody.innerHTML = list.map(t => `
    <tr>
      <td>#${t.id}</td>
      <td>${esc(t.from_warehouse?.name ?? '-')}</td>
      <td>${esc(t.to_warehouse?.name ?? '-')}</td>
      <td><span class="badge bg-${badge[t.status] ?? 'secondary'} transfer-badge">${t.status}</span></td>
      <td>${t.created_at ? t.created_at.slice(0,10) : '-'}</td>
      <td class="d-flex gap-1">
        ${t.status === 'in_transit' ? `
          <button class="btn btn-xs btn-sm btn-outline-success" data-fn="receiveTransfer" data-args="[${t.id}]">
            <i class="fas fa-check me-1"></i>${isAr?'استلام':'Receive'}
          </button>
          <button class="btn btn-xs btn-sm btn-outline-danger" data-fn="cancelTransfer" data-args="[${t.id}]">
            <i class="fas fa-times"></i>
          </button>` : ''}
      </td>
    </tr>`).join('');
}

function buildProductOpts(selectedId = '') {
    const blank = `<option value="">${isAr ? '-- اختر منتجاً --' : '-- Select product --'}</option>`;
    const opts  = productsList.map(p =>
        `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${esc(p.name)}${p.quantity !== undefined ? ' (' + p.quantity + ')' : ''}</option>`
    ).join('');
    return blank + opts;
}

window.openTransferModal = async function() {
    // Reload products if the list is empty (e.g. initial load failed)
    if (!productsList.length) {
        const pRes   = await apiCall('{{ url("/api/warehouses/products-list") }}');
        productsList = Array.isArray(pRes.products) ? pRes.products : [];
    }
    document.getElementById('tfNotes').value = '';
    document.getElementById('tfItems').innerHTML = '';
    addTfRow();
    tfModalInst = tfModalInst ?? new bootstrap.Modal(document.getElementById('transferModal'));
    tfModalInst.show();
};

window.addTfRow = function() {
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 tf-row align-items-center';
    row.innerHTML = `
      <div class="col-7">
        <select class="form-select form-select-sm tf-product">
          ${buildProductOpts()}
        </select>
      </div>
      <div class="col-4">
        <input type="number" class="form-control form-control-sm tf-qty"
            placeholder="${isAr ? 'الكمية' : 'Qty'}" min="1">
      </div>
      <div class="col-1 text-center">
        <button class="btn btn-sm btn-outline-danger px-1 py-0" data-fn="removeTfRow">
          <i class="fas fa-times"></i>
        </button>
      </div>`;
    document.getElementById('tfItems').appendChild(row);
};

window.removeTfRow = function(el) {
    el.closest('.tf-row')?.remove();
};

window.saveTransfer = async function() {
    const from = document.getElementById('tfFrom').value;
    const to   = document.getElementById('tfTo').value;
    if (!from || !to || from === to) {
        showToast(isAr ? 'اختر مستودعين مختلفين' : 'Select two different warehouses', 'error');
        return;
    }

    const items = [...document.querySelectorAll('.tf-row')].map(r => ({
        product_id: parseInt(r.querySelector('.tf-product').value),
        quantity:   parseInt(r.querySelector('.tf-qty').value),
    })).filter(x => x.product_id > 0 && x.quantity > 0);

    if (!items.length) {
        showToast(isAr ? 'أضف منتجاً واحداً على الأقل' : 'Add at least one item', 'error');
        return;
    }

    const btn = document.getElementById('saveTfBtn');
    btn.disabled = true;
    document.getElementById('saveTfSpinner').classList.remove('d-none');

    const res = await apiCall(TF_API, 'POST', {
        from_warehouse_id: parseInt(from),
        to_warehouse_id:   parseInt(to),
        notes: document.getElementById('tfNotes').value || null,
        items,
    });

    btn.disabled = false;
    document.getElementById('saveTfSpinner').classList.add('d-none');

    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    tfModalInst.hide();
    showToast(isAr ? 'تم إرسال التحويل' : 'Transfer created');
    window.loadTransfers();
};

window.receiveTransfer = async function(id) {
    if (!confirm(isAr ? 'تأكيد الاستلام؟' : 'Confirm receive?')) return;
    const res = await apiCall(`${TF_API}/${id}/receive`, 'POST');
    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    showToast(isAr ? 'تم الاستلام' : 'Received');
    window.loadTransfers();
};

window.cancelTransfer = async function(id) {
    if (!confirm(isAr ? 'إلغاء التحويل؟' : 'Cancel transfer?')) return;
    const res = await apiCall(`${TF_API}/${id}/cancel`, 'POST');
    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    showToast(isAr ? 'تم الإلغاء' : 'Cancelled');
    window.loadTransfers();
};

loadWarehouses();
</script>
@endpush
