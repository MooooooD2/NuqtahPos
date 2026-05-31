@extends('layouts.app')
@section('title', __('pos.branches'))
@section('page-title', __('pos.branches'))

@push('styles')
<style @nonce>
    .branch-card { transition: box-shadow .2s; }
    .branch-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); }
    .badge-default { background:#3b82f6; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-code-branch me-2 text-primary"></i>{{ __('pos.branches') }}
        </h5>
        @permission('manage_roles')
        <button class="btn btn-primary btn-sm" data-fn="openBranchModal" data-args="[null]">
            <i class="fas fa-plus me-2"></i>{{ app()->getLocale() === 'ar' ? 'إضافة فرع' : 'Add Branch' }}
        </button>
        @endpermission
    </div>

    {{-- Branches grid --}}
    <div class="row g-3" id="branchesGrid">
        <div class="col-12 text-center py-5 text-muted">
            <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
            <p>{{ app()->getLocale() === 'ar' ? 'جاري التحميل...' : 'Loading...' }}</p>
        </div>
    </div>
</div>

{{-- Branch Modal --}}
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="branchModalTitle">
                    {{ app()->getLocale() === 'ar' ? 'إضافة فرع' : 'Add Branch' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="branchId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        {{ app()->getLocale() === 'ar' ? 'اسم الفرع' : 'Branch Name' }}
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="branchName">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        {{ app()->getLocale() === 'ar' ? 'كود الفرع' : 'Branch Code' }}
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="branchCode" placeholder="e.g. BR-001">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'العنوان' : 'Address' }}</label>
                    <input type="text" class="form-control" id="branchAddress">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'هاتف الفرع' : 'Phone' }}</label>
                    <input type="text" class="form-control" id="branchPhone">
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="branchIsDefault">
                    <label class="form-check-label" for="branchIsDefault">
                        {{ app()->getLocale() === 'ar' ? 'الفرع الافتراضي' : 'Default Branch' }}
                    </label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="branchIsActive" checked>
                    <label class="form-check-label" for="branchIsActive">
                        {{ app()->getLocale() === 'ar' ? 'نشط' : 'Active' }}
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" id="saveBranchBtn" data-fn="saveBranch">
                    <span id="saveBranchText">{{ __('pos.save') }}</span>
                    <span id="saveBranchSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
let branchModalInst = null;

async function loadBranches() {
    const grid = document.getElementById('branchesGrid');
    grid.innerHTML = `<div class="col-12 text-center py-5 text-muted">
        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
        <p>${isAr ? 'جاري التحميل...' : 'Loading...'}</p>
    </div>`;

    const res = await apiCall('{{ route("branches.all") }}');
    if (!res.success && !Array.isArray(res)) {
        grid.innerHTML = `<div class="col-12 text-center text-danger py-4">
            <i class="fas fa-exclamation-circle me-2"></i>${res.message || 'Error'}
        </div>`;
        return;
    }

    const branches = Array.isArray(res) ? res : (res.data ?? res);
    renderBranches(Array.isArray(branches) ? branches : []);
}

function renderBranches(branches) {
    const grid = document.getElementById('branchesGrid');
    if (!branches.length) {
        grid.innerHTML = `<div class="col-12 text-center text-muted py-5">
            <i class="fas fa-code-branch fa-3x mb-3 opacity-25"></i>
            <p>${isAr ? 'لا توجد فروع بعد' : 'No branches yet'}</p>
        </div>`;
        return;
    }

    grid.innerHTML = branches.map(b => {
        const bJson = escAttr(JSON.stringify(b));
        return `<div class="col-md-6 col-xl-4">
            <div class="card branch-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0">${escHtml(b.name)}</h6>
                        <div>
                            ${b.is_default ? `<span class="badge badge-default me-1">${isAr?'افتراضي':'Default'}</span>` : ''}
                            <span class="badge ${b.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${b.is_active ? (isAr?'نشط':'Active') : (isAr?'غير نشط':'Inactive')}
                            </span>
                        </div>
                    </div>
                    <p class="text-muted small mb-1"><i class="fas fa-barcode me-1"></i>${escHtml(b.code)}</p>
                    ${b.address ? `<p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i>${escHtml(b.address)}</p>` : ''}
                    ${b.phone   ? `<p class="text-muted small mb-1"><i class="fas fa-phone me-1"></i>${escHtml(b.phone)}</p>`   : ''}
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill"
                        data-fn="openBranchModal" data-args="[${bJson}]">
                        <i class="fas fa-edit me-1"></i>${isAr?'تعديل':'Edit'}
                    </button>
                    ${!b.is_default ? `<button class="btn btn-sm btn-outline-danger"
                        data-fn="deleteBranch" data-args="[${b.id}]">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

window.openBranchModal = function(branch) {
    document.getElementById('branchId').value         = branch?.id      ?? '';
    document.getElementById('branchName').value       = branch?.name    ?? '';
    document.getElementById('branchCode').value       = branch?.code    ?? '';
    document.getElementById('branchAddress').value    = branch?.address ?? '';
    document.getElementById('branchPhone').value      = branch?.phone   ?? '';
    document.getElementById('branchIsDefault').checked = branch?.is_default ?? false;
    document.getElementById('branchIsActive').checked  = branch ? branch.is_active : true;
    document.getElementById('branchModalTitle').textContent = branch
        ? (isAr ? 'تعديل الفرع' : 'Edit Branch')
        : (isAr ? 'إضافة فرع'  : 'Add Branch');

    branchModalInst = branchModalInst || new bootstrap.Modal(document.getElementById('branchModal'));
    branchModalInst.show();
};

window.saveBranch = async function() {
    const id   = document.getElementById('branchId').value;
    const name = document.getElementById('branchName').value.trim();
    const code = document.getElementById('branchCode').value.trim();

    if (!name || !code) {
        showToast(isAr ? 'الاسم والكود مطلوبان' : 'Name and code are required', 'error');
        return;
    }

    const btn = document.getElementById('saveBranchBtn');
    btn.disabled = true;
    document.getElementById('saveBranchSpinner').classList.remove('d-none');

    const payload = {
        name, code,
        address:    document.getElementById('branchAddress').value  || null,
        phone:      document.getElementById('branchPhone').value    || null,
        is_default: document.getElementById('branchIsDefault').checked,
        is_active:  document.getElementById('branchIsActive').checked,
    };

    const url    = id ? `{{ url('/api/branches') }}/${id}` : '{{ route("branches.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, payload);

    btn.disabled = false;
    document.getElementById('saveBranchSpinner').classList.add('d-none');

    if (!res.success) {
        showToast(res.message || (isAr ? 'حدث خطأ' : 'An error occurred'), 'error');
        return;
    }

    branchModalInst.hide();
    showToast(isAr ? (id ? 'تم التعديل بنجاح' : 'تمت الإضافة بنجاح') : (id ? 'Updated successfully' : 'Branch added'));
    loadBranches();
};

window.deleteBranch = async function(id) {
    if (!confirm(isAr ? 'هل تريد حذف هذا الفرع؟' : 'Delete this branch?')) return;
    const res = await apiCall(`{{ url('/api/branches') }}/${id}`, 'DELETE');
    if (!res.success) { showToast(res.message || 'Error', 'error'); return; }
    showToast(res.message || (isAr ? 'تم الحذف' : 'Deleted'));
    loadBranches();
};

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/'/g,'&#39;').replace(/"/g,'&quot;');
}

loadBranches();
</script>
@endpush
