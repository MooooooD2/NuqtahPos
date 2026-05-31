@extends('layouts.app')
@section('title', app()->getLocale()==='ar' ? 'مجموعات العملاء' : 'Customer Groups')
@section('page-title', app()->getLocale()==='ar' ? 'مجموعات العملاء' : 'Customer Groups')

@push('styles')
<style @nonce>
.group-card { transition: box-shadow .15s; }
.group-card:hover { box-shadow: 0 .25rem .75rem rgba(0,0,0,.12); }
.price-level-badge { font-size:.7rem; padding:.3em .65em; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-layer-group me-2 text-primary"></i>
            {{ app()->getLocale()==='ar' ? 'مجموعات العملاء' : 'Customer Groups' }}
        </h5>
        <button class="btn btn-primary btn-sm" data-fn="openGroupModal" data-args="[null]">
            <i class="fas fa-plus me-1"></i>
            {{ app()->getLocale()==='ar' ? 'مجموعة جديدة' : 'New Group' }}
        </button>
    </div>

    {{-- Search --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-sm-4">
                    <input type="text" class="form-control form-control-sm" id="groupSearch"
                        placeholder="{{ app()->getLocale()==='ar' ? 'بحث...' : 'Search...' }}"
                        data-on-input="loadGroups">
                </div>
                <div class="col-sm-3">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" id="showInactive" data-on-change="loadGroups">
                        <label class="form-check-label small" for="showInactive">
                            {{ app()->getLocale()==='ar' ? 'إظهار غير النشطة' : 'Show inactive' }}
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>{{ app()->getLocale()==='ar' ? 'الاسم' : 'Name' }}</th>
                            <th>{{ app()->getLocale()==='ar' ? 'مستوى السعر' : 'Price Level' }}</th>
                            <th class="text-center">{{ app()->getLocale()==='ar' ? 'نسبة الخصم' : 'Discount %' }}</th>
                            <th class="text-center">{{ app()->getLocale()==='ar' ? 'العملاء' : 'Customers' }}</th>
                            <th class="text-center">{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
                            <th>{{ __('pos.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="groupsBody">
                        <tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ── Create / Edit Modal ── --}}
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="groupModalTitle">
                    <i class="fas fa-layer-group me-2"></i>
                    {{ app()->getLocale()==='ar' ? 'مجموعة جديدة' : 'New Group' }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="groupId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            {{ app()->getLocale()==='ar' ? 'الاسم' : 'Name' }} <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="groupName" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            {{ app()->getLocale()==='ar' ? 'مستوى السعر' : 'Price Level' }}
                        </label>
                        <select class="form-select" id="groupPriceLevel">
                            <option value="retail">{{ app()->getLocale()==='ar' ? 'تجزئة' : 'Retail' }}</option>
                            <option value="wholesale">{{ app()->getLocale()==='ar' ? 'جملة' : 'Wholesale' }}</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            {{ app()->getLocale()==='ar' ? 'نسبة الخصم %' : 'Discount %' }}
                        </label>
                        <input type="number" class="form-control" id="groupDiscount" value="0" min="0" max="100" step="0.01">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            {{ app()->getLocale()==='ar' ? 'الوصف' : 'Description' }}
                        </label>
                        <textarea class="form-control" id="groupDesc" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="groupActive" checked>
                            <label class="form-check-label" for="groupActive">
                                {{ app()->getLocale()==='ar' ? 'نشط' : 'Active' }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveGroup">
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

const priceLevelLabels = {
    retail:    isAr ? 'تجزئة'  : 'Retail',
    wholesale: isAr ? 'جملة'   : 'Wholesale',
    vip:       'VIP',
};
const priceLevelColors = { retail: 'secondary', wholesale: 'info text-dark', vip: 'warning text-dark' };

async function loadGroups() {
    const search   = document.getElementById('groupSearch').value;
    const inactive = document.getElementById('showInactive').checked;
    let url = '{{ route("customer-groups.index") }}?per_page=50';
    if (search)   url += `&search=${encodeURIComponent(search)}`;
    if (inactive) url += `&with_inactive=1`;

    const res    = await apiCall(url);
    const groups = res.data || res.groups || [];

    document.getElementById('groupsBody').innerHTML = groups.length
        ? groups.map(g => `
            <tr>
                <td class="fw-semibold">${escapeHtml(g.name)}</td>
                <td><span class="badge bg-${priceLevelColors[g.price_level] || 'secondary'} price-level-badge">${priceLevelLabels[g.price_level] || g.price_level}</span></td>
                <td class="text-center">${g.discount_percent > 0 ? g.discount_percent + '%' : '—'}</td>
                <td class="text-center">${g.customers_count ?? '—'}</td>
                <td class="text-center">
                    <span class="badge bg-${g.is_active ? 'success' : 'secondary'}">
                        ${g.is_active ? (isAr ? 'نشط' : 'Active') : (isAr ? 'غير نشط' : 'Inactive')}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openGroupModal(${JSON.stringify(g)})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteGroup(${g.id}, ${g.customers_count || 0})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`)
            .join('')
        : `<tr><td colspan="6" class="text-center text-muted py-4">${isAr ? 'لا توجد مجموعات' : 'No groups found'}</td></tr>`;
}

function openGroupModal(group) {
    const editing = group !== null;
    document.getElementById('groupId').value           = editing ? group.id  : '';
    document.getElementById('groupName').value         = editing ? group.name : '';
    document.getElementById('groupPriceLevel').value   = editing ? group.price_level : 'retail';
    document.getElementById('groupDiscount').value     = editing ? group.discount_percent : 0;
    document.getElementById('groupDesc').value         = editing ? (group.description || '') : '';
    document.getElementById('groupActive').checked     = editing ? group.is_active : true;
    document.getElementById('groupModalTitle').innerHTML =
        `<i class="fas fa-layer-group me-2"></i>${editing ? (isAr ? 'تعديل مجموعة' : 'Edit Group') : (isAr ? 'مجموعة جديدة' : 'New Group')}`;
    new bootstrap.Modal(document.getElementById('groupModal')).show();
}

async function saveGroup() {
    const id   = document.getElementById('groupId').value;
    const name = document.getElementById('groupName').value.trim();
    if (!name) {
        showToast(isAr ? 'الاسم مطلوب' : 'Name is required', 'danger');
        return;
    }
    const payload = {
        name:             name,
        price_level:      document.getElementById('groupPriceLevel').value,
        discount_percent: parseFloat(document.getElementById('groupDiscount').value) || 0,
        description:      document.getElementById('groupDesc').value.trim() || null,
        is_active:        document.getElementById('groupActive').checked,
    };
    const url    = id ? `{{ url('/api/customer-groups') }}/${id}` : '{{ route("customer-groups.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, payload);
    if (res.success) {
        showToast(isAr ? 'تم الحفظ بنجاح' : 'Saved successfully', 'success');
        bootstrap.Modal.getInstance(document.getElementById('groupModal')).hide();
        loadGroups();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function deleteGroup(id, customerCount) {
    if (customerCount > 0) {
        showToast(isAr ? 'لا يمكن الحذف — المجموعة تحتوي على عملاء' : 'Cannot delete — group has customers', 'danger');
        return;
    }
    if (!confirm(isAr ? 'هل تريد حذف هذه المجموعة؟' : 'Delete this group?')) return;
    const res = await apiCall(`{{ url('/api/customer-groups') }}/${id}`, 'DELETE');
    if (res.success) {
        showToast(isAr ? 'تم الحذف' : 'Deleted', 'success');
        loadGroups();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

loadGroups();
</script>
@endpush
