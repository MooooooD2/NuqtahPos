@extends('layouts.app')
@section('title', app()->getLocale()==='ar' ? 'العروض الترويجية' : 'Promotions')
@section('page-title', app()->getLocale()==='ar' ? 'العروض الترويجية' : 'Promotions')

@push('styles')
<style @nonce>
.promo-type-badge { font-size:.7rem; }
.promo-scope      { font-size:.8rem; color:#64748b; }
.validity-badge   { font-size:.72rem; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-tags me-2 text-primary"></i>
            {{ app()->getLocale()==='ar' ? 'العروض الترويجية' : 'Promotions' }}
        </h5>
        <button class="btn btn-primary btn-sm" data-fn="openPromoModal" data-args="[null]">
            <i class="fas fa-plus me-1"></i>
            {{ app()->getLocale()==='ar' ? 'عرض جديد' : 'New Promotion' }}
        </button>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-sm-4">
                    <input type="text" class="form-control form-control-sm" id="promoSearch"
                        placeholder="{{ app()->getLocale()==='ar' ? 'بحث...' : 'Search...' }}"
                        data-on-input="loadPromos">
                </div>
                <div class="col-sm-3">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" id="showInactivePromos" data-on-change="loadPromos">
                        <label class="form-check-label small" for="showInactivePromos">
                            {{ app()->getLocale()==='ar' ? 'غير النشطة' : 'Show inactive' }}
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>{{ app()->getLocale()==='ar' ? 'الاسم' : 'Name' }}</th>
                            <th>{{ app()->getLocale()==='ar' ? 'النوع' : 'Type' }}</th>
                            <th>{{ app()->getLocale()==='ar' ? 'القيمة' : 'Value' }}</th>
                            <th>{{ app()->getLocale()==='ar' ? 'النطاق' : 'Scope' }}</th>
                            <th>{{ app()->getLocale()==='ar' ? 'الصلاحية' : 'Validity' }}</th>
                            <th class="text-center">{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
                            <th>{{ __('pos.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="promosBody">
                        <tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Create / Edit Modal ── --}}
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="promoModalTitle">
                    <i class="fas fa-tags me-2"></i>
                    {{ app()->getLocale()==='ar' ? 'عرض ترويجي' : 'Promotion' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="promoId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'الاسم' : 'Name' }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="promoName" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'النوع' : 'Type' }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="promoType" onchange="togglePromoFields()">
                            <option value="percentage">{{ app()->getLocale()==='ar' ? 'نسبة مئوية %' : 'Percentage %' }}</option>
                            <option value="fixed">{{ app()->getLocale()==='ar' ? 'خصم ثابت' : 'Fixed Amount' }}</option>
                            <option value="buy_x_get_y">{{ app()->getLocale()==='ar' ? 'اشترِ X واحصل على Y' : 'Buy X Get Y' }}</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="promoValueGroup">
                        <label class="form-label fw-semibold" id="promoValueLabel">
                            {{ app()->getLocale()==='ar' ? 'القيمة' : 'Value' }}
                        </label>
                        <input type="number" class="form-control" id="promoValue" value="0" min="0" step="0.01">
                    </div>
                    <div class="col-md-4" id="promoBuyGetGroup" class="u-hidden">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'اشترِ / احصل على' : 'Buy / Get' }}</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="promoBuyQty" value="2" min="1" placeholder="{{ app()->getLocale()==='ar' ? 'شراء' : 'Buy' }}">
                            <span class="input-group-text">+</span>
                            <input type="number" class="form-control" id="promoGetQty" value="1" min="1" placeholder="{{ app()->getLocale()==='ar' ? 'مجاني' : 'Free' }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'منتج محدد (اختياري)' : 'Specific Product (optional)' }}</label>
                        <select class="form-select" id="promoProductId">
                            <option value="">— {{ app()->getLocale()==='ar' ? 'كل المنتجات' : 'All products' }} —</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'فئة المنتج (اختياري)' : 'Product Category (optional)' }}</label>
                        <input type="text" class="form-control" id="promoCategory" maxlength="100"
                            placeholder="{{ app()->getLocale()==='ar' ? 'اسم الفئة...' : 'Category name...' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'حد أدنى للطلب' : 'Min. Order Amount' }}</label>
                        <input type="number" class="form-control" id="promoMinOrder" value="0" min="0" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'تاريخ البداية' : 'Starts At' }}</label>
                        <input type="date" class="form-control" id="promoStartsAt">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'تاريخ الانتهاء' : 'Ends At' }}</label>
                        <input type="date" class="form-control" id="promoEndsAt">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'الوصف' : 'Description' }}</label>
                        <textarea class="form-control" id="promoDesc" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="promoActive" checked>
                            <label class="form-check-label" for="promoActive">
                                {{ app()->getLocale()==='ar' ? 'نشط' : 'Active' }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="savePromo">
                    <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
let promoProducts = [];

async function loadPromoProducts() {
    const res = await apiCall('{{ route("products.all") }}');
    promoProducts = res.products || [];
    const sel = document.getElementById('promoProductId');
    sel.innerHTML = `<option value="">— ${isAr ? 'كل المنتجات' : 'All products'} —</option>`
        + promoProducts.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
}

async function loadPromos() {
    const search   = document.getElementById('promoSearch').value;
    const inactive = document.getElementById('showInactivePromos').checked;
    let url = '{{ route("promotions.index") }}?per_page=50';
    if (search)   url += `&search=${encodeURIComponent(search)}`;
    if (inactive) url += `&with_inactive=1`;

    const res    = await apiCall(url);
    const promos = res.data || [];

    const typeLabels = {
        percentage:  isAr ? 'نسبة مئوية' : 'Percentage',
        fixed:       isAr ? 'خصم ثابت'   : 'Fixed',
        buy_x_get_y: isAr ? 'اشترِ X + Y' : 'Buy X Get Y',
    };
    const typeColors = { percentage: 'primary', fixed: 'success', buy_x_get_y: 'warning text-dark' };

    const today = new Date().toISOString().split('T')[0];

    document.getElementById('promosBody').innerHTML = promos.length
        ? promos.map(p => {
            const typeLabel = typeLabels[p.type] || p.type;
            const typeColor = typeColors[p.type] || 'secondary';

            let valueStr = '';
            if (p.type === 'percentage')  valueStr = `${p.value}%`;
            else if (p.type === 'fixed')  valueStr = formatCurrency(p.value);
            else                          valueStr = `${p.buy_qty}+${p.get_qty}`;

            let scope = isAr ? 'كل المنتجات' : 'All products';
            if (p.product?.name)    scope = p.product.name;
            else if (p.product_category) scope = p.product_category;

            let validity = '';
            const expired = p.ends_at && p.ends_at < today;
            const notyet  = p.starts_at && p.starts_at > today;
            if (expired)      validity = `<span class="badge bg-danger validity-badge">${isAr ? 'منتهي' : 'Expired'}</span>`;
            else if (notyet)  validity = `<span class="badge bg-secondary validity-badge">${isAr ? 'لم يبدأ' : 'Not yet'}</span> <small class="text-muted">${p.starts_at}</small>`;
            else if (p.ends_at) validity = `<small class="text-muted">${isAr ? 'حتى' : 'Until'} ${p.ends_at}</small>`;
            else              validity = `<span class="badge bg-success validity-badge">${isAr ? 'دائم' : 'Always'}</span>`;

            return `<tr>
                <td class="fw-semibold">${escapeHtml(p.name)}</td>
                <td><span class="badge bg-${typeColor} promo-type-badge">${typeLabel}</span></td>
                <td class="fw-bold">${valueStr}</td>
                <td class="promo-scope">${escapeHtml(scope)}</td>
                <td>${validity}</td>
                <td class="text-center">
                    <span class="badge bg-${p.is_active && !expired ? 'success' : 'secondary'}">
                        ${p.is_active && !expired ? (isAr ? 'نشط' : 'Active') : (isAr ? 'غير نشط' : 'Inactive')}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openPromoModal(${JSON.stringify(p)})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deletePromo(${p.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('')
        : `<tr><td colspan="7" class="text-center text-muted py-4">${isAr ? 'لا توجد عروض' : 'No promotions found'}</td></tr>`;
}

function togglePromoFields() {
    const type = document.getElementById('promoType').value;
    document.getElementById('promoValueGroup').style.display  = type === 'buy_x_get_y' ? 'none' : '';
    document.getElementById('promoBuyGetGroup').style.display = type === 'buy_x_get_y' ? '' : 'none';
    document.getElementById('promoValueLabel').textContent    = type === 'percentage'
        ? (isAr ? 'نسبة الخصم %' : 'Discount %')
        : (isAr ? 'مبلغ الخصم' : 'Discount Amount');
}

function openPromoModal(p) {
    document.getElementById('promoId').value         = p?.id         ?? '';
    document.getElementById('promoName').value       = p?.name       ?? '';
    document.getElementById('promoType').value       = p?.type       ?? 'percentage';
    document.getElementById('promoValue').value      = p?.value      ?? 0;
    document.getElementById('promoBuyQty').value     = p?.buy_qty    ?? 2;
    document.getElementById('promoGetQty').value     = p?.get_qty    ?? 1;
    document.getElementById('promoProductId').value  = p?.product_id ?? '';
    document.getElementById('promoCategory').value   = p?.product_category ?? '';
    document.getElementById('promoMinOrder').value   = p?.min_order_amount ?? 0;
    document.getElementById('promoStartsAt').value   = p?.starts_at  ?? '';
    document.getElementById('promoEndsAt').value     = p?.ends_at    ?? '';
    document.getElementById('promoDesc').value       = p?.description ?? '';
    document.getElementById('promoActive').checked   = p ? !!p.is_active : true;
    document.getElementById('promoModalTitle').innerHTML =
        `<i class="fas fa-tags me-2"></i>${p ? (isAr ? 'تعديل عرض' : 'Edit Promotion') : (isAr ? 'عرض جديد' : 'New Promotion')}`;
    togglePromoFields();
    new bootstrap.Modal(document.getElementById('promoModal')).show();
}

async function savePromo() {
    const id   = document.getElementById('promoId').value;
    const name = document.getElementById('promoName').value.trim();
    const type = document.getElementById('promoType').value;
    if (!name) { showToast(isAr ? 'الاسم مطلوب' : 'Name is required', 'danger'); return; }

    const payload = {
        name,
        type,
        value:             parseFloat(document.getElementById('promoValue').value)    || 0,
        buy_qty:           parseInt(document.getElementById('promoBuyQty').value)     || 2,
        get_qty:           parseInt(document.getElementById('promoGetQty').value)     || 1,
        product_id:        document.getElementById('promoProductId').value            || null,
        product_category:  document.getElementById('promoCategory').value.trim()      || null,
        min_order_amount:  parseFloat(document.getElementById('promoMinOrder').value) || 0,
        starts_at:         document.getElementById('promoStartsAt').value             || null,
        ends_at:           document.getElementById('promoEndsAt').value               || null,
        description:       document.getElementById('promoDesc').value.trim()          || null,
        is_active:         document.getElementById('promoActive').checked,
    };

    const url    = id ? `{{ url('/api/promotions') }}/${id}` : '{{ route("promotions.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, payload);
    if (res.success) {
        showToast(isAr ? 'تم الحفظ' : 'Saved', 'success');
        bootstrap.Modal.getInstance(document.getElementById('promoModal')).hide();
        loadPromos();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function deletePromo(id) {
    if (!confirm(isAr ? 'حذف هذا العرض؟' : 'Delete this promotion?')) return;
    const res = await apiCall(`{{ url('/api/promotions') }}/${id}`, 'DELETE');
    if (res.success) {
        showToast(isAr ? 'تم الحذف' : 'Deleted', 'success');
        loadPromos();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

function escapeHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadPromoProducts();
loadPromos();
</script>
@endpush
