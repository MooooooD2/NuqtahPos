@extends('layouts.app')
@section('title', __('pos.roles_permissions'))
@section('page-title', __('pos.roles_permissions'))

@section('content')

{{-- Tabs --}}
<ul class="nav nav-tabs mb-3" id="rolesTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#usersTab">
            <i class="fas fa-users me-1"></i>{{ __('pos.users') }}
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#rolesTab">
            <i class="fas fa-user-shield me-1"></i>{{ __('pos.roles') }}
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- ── USERS TAB ──────────────────────────────────────────────────────── --}}
    <div class="tab-pane fade show active" id="usersTab">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users me-2"></i>{{ __('pos.users') }}</span>
                <button class="btn btn-primary btn-sm" data-fn="openUserModal">
                    <i class="fas fa-plus me-1"></i>{{ __('pos.username') }}
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>{{ __('pos.username') }}</th>
                                <th>{{ __('pos.name') }}</th>
                                <th>{{ __('pos.roles') }}</th>
                                <th>{{ __('pos.status') }}</th>
                                <th>{{ __('pos.date') }}</th>
                                <th>{{ __('pos.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="usersBody">
                            <tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── ROLES TAB ──────────────────────────────────────────────────────── --}}
    <div class="tab-pane fade" id="rolesTab">
        <div class="row g-3">

            {{-- Roles list --}}
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-shield me-2"></i>{{ __('pos.roles') }}</span>
                        <button class="btn btn-primary btn-sm" data-fn="openRoleModal">
                            <i class="fas fa-plus me-1"></i>{{ __('pos.create_role') }}
                        </button>
                    </div>
                    <div class="list-group list-group-flush" id="rolesList">
                        <div class="list-group-item text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
                    </div>
                </div>
            </div>

            {{-- Permissions for selected role --}}
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span id="permCardTitle"><i class="fas fa-key me-2"></i>{{ __('pos.permissions') }}</span>
                        <button class="btn btn-success btn-sm d-none" id="savePermBtn" data-fn="savePermissions">
                            <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                        </button>
                    </div>
                    <div class="card-body" id="permissionsPanel">
                        <p class="text-muted text-center py-4">{{ app()->getLocale() === 'ar' ? 'اختر دوراً لإدارة صلاحياته' : 'Select a role to manage its permissions' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── USER MODAL ────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">{{ __('pos.username') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="userId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">{{ __('pos.username') }} *</label>
                        <input type="text" class="form-control" id="userUsername" autocomplete="off">
                        <div class="form-text">{{ app()->getLocale() === 'ar' ? 'أحرف إنجليزية وأرقام وشرطة سفلية فقط' : 'Letters, numbers and underscore only' }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('pos.name') }} *</label>
                        <input type="text" class="form-control" id="userFullName">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('pos.roles') }} *</label>
                        <select class="form-select" id="userRole"></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" id="passwordLabel">{{ __('pos.password') }} *</label>
                        <input type="password" class="form-control" id="userPassword" autocomplete="new-password">
                        <div class="form-text">{{ app()->getLocale() === 'ar' ? '8 أحرف على الأقل، تشمل حروف كبيرة وصغيرة وأرقام' : 'Min 8 chars, mixed case + numbers' }}</div>
                    </div>
                    <div class="col-12" id="passwordConfirmRow">
                        <label class="form-label">{{ app()->getLocale() === 'ar' ? 'تأكيد كلمة المرور' : 'Confirm Password' }}</label>
                        <input type="password" class="form-control" id="userPasswordConfirm" autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="userIsActive" checked>
                            <label class="form-check-label" for="userIsActive">{{ app()->getLocale() === 'ar' ? 'حساب نشط' : 'Active Account' }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveUser">{{ __('pos.save') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- ── ROLE MODAL ────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalTitle">{{ __('pos.create_role') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="roleId">
                <label class="form-label">{{ __('pos.role_name') }} *</label>
                <input type="text" class="form-control" id="roleName">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveRole">{{ __('pos.save') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script @nonce>
let allRoles = [];
let allPermissions = [];
let allUsers = [];
let selectedRoleId = null;

// ── PERMISSION GROUPS ─────────────────────────────────────────────────────────
const PERM_GROUPS = {
    '{{ app()->getLocale() === "ar" ? "لوحة التحكم" : "Dashboard" }}':    ['view_dashboard'],
    '{{ app()->getLocale() === "ar" ? "نقطة البيع" : "POS" }}':           ['view_pos', 'create_invoice', 'search_products'],
    '{{ app()->getLocale() === "ar" ? "المرتجعات" : "Returns" }}':        ['view_returns', 'create_return'],
    '{{ app()->getLocale() === "ar" ? "المنتجات" : "Products" }}':        ['view_warehouse', 'add_product', 'edit_product', 'delete_product', 'add_stock'],
    '{{ app()->getLocale() === "ar" ? "الموردون" : "Suppliers" }}':       ['view_suppliers', 'add_supplier', 'edit_supplier', 'delete_supplier'],
    '{{ app()->getLocale() === "ar" ? "أوامر الشراء" : "Purchase Orders" }}': ['view_purchase_orders', 'create_purchase_order', 'receive_purchase_order'],
    '{{ app()->getLocale() === "ar" ? "مدفوعات الموردين" : "Supplier Payments" }}': ['view_supplier_payments', 'create_supplier_payment'],
    '{{ app()->getLocale() === "ar" ? "المحاسبة" : "Accounting" }}':      ['view_accounting', 'manage_accounts', 'create_journal_entry'],
    '{{ app()->getLocale() === "ar" ? "التقارير" : "Reports" }}':         ['view_reports', 'view_financial_reports'],
    '{{ app()->getLocale() === "ar" ? "الإعدادات" : "Settings" }}':       ['view_settings', 'update_settings'],
    '{{ app()->getLocale() === "ar" ? "الأدوار والمستخدمون" : "Roles & Users" }}': ['manage_roles', 'manage_permissions'],
};

// ── BOOT ──────────────────────────────────────────────────────────────────────
async function boot() {
    const [rolesRes, permRes, usersRes] = await Promise.all([
        apiCall('{{ route("roles.all") }}'),
        apiCall('{{ route("permissions.all") }}'),
        apiCall('{{ route("users.all") }}'),
    ]);
    allRoles       = rolesRes.roles       || [];
    allPermissions = permRes.permissions  || [];
    allUsers       = usersRes.users       || [];
    renderUsers();
    renderRolesList();
}

// ── USERS ─────────────────────────────────────────────────────────────────────
function renderUsers() {
    document.getElementById('usersBody').innerHTML = allUsers.length
        ? allUsers.map((u, i) => {
            const isAdmin = (u.role === 'admin') || (Array.isArray(u.roles) && u.roles.includes('admin'));
            return `
            <tr class="${u.is_active ? '' : 'table-secondary opacity-75'}">
                <td class="text-muted small">${i + 1}</td>
                <td class="fw-semibold font-monospace">${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.full_name || '')}</td>
                <td>${(u.roles || [u.role]).map(r => `<span class="badge bg-primary me-1">${escapeHtml(r)}</span>`).join('')}</td>
                <td>
                    <span class="badge ${u.is_active ? 'bg-success' : 'bg-secondary'}">
                        ${u.is_active ? '{{ __("pos.completed") }}' : '{{ app()->getLocale() === "ar" ? "معطل" : "Inactive" }}'}
                    </span>
                </td>
                <td class="text-muted small">${u.created_at || ''}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-primary" data-action="edit-user" data-user-idx="${i}" title="{{ __('pos.edit') }}">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${isAdmin ? `
                        <span class="btn btn-sm btn-outline-secondary disabled px-2" title="{{ app()->getLocale() === 'ar' ? 'حساب المدير محمي' : 'Admin account is protected' }}">
                            <i class="fas fa-lock"></i>
                        </span>` : `
                        <button class="btn ${u.is_active ? 'btn-warning' : 'btn-success'}" data-action="toggle-user" data-id="${u.id}" title="{{ app()->getLocale() === 'ar' ? 'تفعيل/تعطيل' : 'Toggle Active' }}">
                            <i class="fas fa-${u.is_active ? 'ban' : 'check'}"></i>
                        </button>
                        <button class="btn btn-danger" data-action="delete-user" data-id="${u.id}" title="{{ __('pos.delete') }}">
                            <i class="fas fa-trash"></i>
                        </button>`}
                    </div>
                </td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

function openUserModal() {
    document.getElementById('userId').value       = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userFullName').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPasswordConfirm').value = '';
    document.getElementById('userIsActive').checked = true;
    document.getElementById('userUsername').disabled = false;
    document.getElementById('passwordLabel').textContent = '{{ __("pos.password") }} *';
    document.getElementById('userModalTitle').textContent = '{{ app()->getLocale() === "ar" ? "إضافة مستخدم" : "Add User" }}';
    populateRoleSelect('userRole', null);
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function editUser(u) {
    document.getElementById('userId').value       = u.id;
    document.getElementById('userUsername').value = u.username;
    document.getElementById('userUsername').disabled = true;
    document.getElementById('userFullName').value = u.full_name || '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPasswordConfirm').value = '';
    document.getElementById('userIsActive').checked = !!u.is_active;
    document.getElementById('passwordLabel').textContent = '{{ app()->getLocale() === "ar" ? "كلمة مرور جديدة (اختياري)" : "New Password (optional)" }}';
    document.getElementById('userModalTitle').textContent = '{{ __("pos.edit") }}';
    populateRoleSelect('userRole', u.role || (u.roles && u.roles[0]));
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function populateRoleSelect(selectId, selected) {
    const sel = document.getElementById(selectId);
    sel.innerHTML = allRoles.map(r =>
        `<option value="${escapeHtml(r.name)}" ${r.name === selected ? 'selected' : ''}>${escapeHtml(r.name)}</option>`
    ).join('');
}

async function saveUser() {
    const id       = document.getElementById('userId').value;
    const password = document.getElementById('userPassword').value;
    const passConf = document.getElementById('userPasswordConfirm').value;

    if (password && password !== passConf) {
        showToast('{{ app()->getLocale() === "ar" ? "كلمتا المرور غير متطابقتين" : "Passwords do not match" }}', 'danger');
        return;
    }

    const data = {
        full_name:        document.getElementById('userFullName').value,
        role:             document.getElementById('userRole').value,
        is_active:        document.getElementById('userIsActive').checked,
    };
    if (password) {
        data.password         = password;
        data.password_confirm = passConf;
    }
    if (!id) {
        data.username = document.getElementById('userUsername').value;
        data.password = password;
    }

    const url    = id ? `/api/users/${id}` : '{{ route("users.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, data);

    if (res.success) {
        showToast('{{ __("pos.success") }}');
        bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
        await boot();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function toggleUser(id) {
    const u = allUsers.find(x => x.id === id);
    if (u && ((u.role === 'admin') || (Array.isArray(u.roles) && u.roles.includes('admin')))) {
        showToast('{{ app()->getLocale() === "ar" ? "لا يمكن تعطيل حسابات المدير" : "Admin accounts cannot be deactivated" }}', 'danger');
        return;
    }
    const res = await apiCall(`/api/users/${id}/toggle-active`, 'POST');
    if (res.success) {
        if (u) u.is_active = res.is_active;
        renderUsers();
    } else {
        showToast(res.message, 'danger');
    }
}

async function deleteUser(id) {
    const u = allUsers.find(x => x.id === id);
    if (u && ((u.role === 'admin') || (Array.isArray(u.roles) && u.roles.includes('admin')))) {
        showToast('{{ app()->getLocale() === "ar" ? "لا يمكن حذف حسابات المدير" : "Admin accounts cannot be deleted" }}', 'danger');
        return;
    }
    if (!confirm('{{ __("pos.confirm_delete") }}')) return;
    const res = await apiCall(`/api/users/${id}`, 'DELETE');
    if (res.success) { showToast('{{ __("pos.success") }}'); await boot(); }
    else showToast(res.message, 'danger');
}

// ── ROLES ─────────────────────────────────────────────────────────────────────
function renderRolesList() {
    document.getElementById('rolesList').innerHTML = allRoles.length
        ? allRoles.map(r => `
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                        ${selectedRoleId === r.id ? 'active' : ''}"
                 class="u-cursor-pointer" data-action="select-role" data-id="${r.id}">
                <span class="fw-semibold">${escapeHtml(r.name)}</span>
                <div class="d-flex gap-1 align-items-center">
                    <span class="badge ${selectedRoleId === r.id ? 'bg-light text-dark' : 'bg-primary'}">
                        ${(r.permissions || []).length}
                    </span>
                    ${r.name !== 'admin' ? `
                    <button class="btn btn-sm btn-outline-${selectedRoleId === r.id ? 'light' : 'warning'} py-0 px-1"
                            data-action="edit-role" data-id="${r.id}" data-name="${escapeHtml(r.name)}"
                            title="{{ __('pos.edit') }}">
                        <i class="fas fa-edit fa-xs"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-${selectedRoleId === r.id ? 'light' : 'danger'} py-0 px-1"
                            data-action="delete-role" data-id="${r.id}"
                            title="{{ __('pos.delete') }}">
                        <i class="fas fa-trash fa-xs"></i>
                    </button>` : ''}
                </div>
            </div>`).join('')
        : '<div class="list-group-item text-muted text-center">{{ __("pos.no_roles") }}</div>';
}

function selectRole(id) {
    selectedRoleId = id;
    renderRolesList();
    const role = allRoles.find(r => r.id === id);
    if (!role) return;

    const isProtected = role.name === 'admin';
    const rolePerm = (role.permissions || []).map(p => p.name);

    document.getElementById('permCardTitle').innerHTML =
        `<i class="fas fa-key me-2"></i>${escapeHtml(role.name)}`;
    document.getElementById('savePermBtn').classList.toggle('d-none', isProtected);

    let html = '';
    if (isProtected) {
        html = `<div class="alert alert-warning"><i class="fas fa-lock me-2"></i>
            {{ app()->getLocale() === "ar" ? "دور المدير يملك جميع الصلاحيات تلقائياً ولا يمكن تعديلها." : "Admin role has all permissions automatically and cannot be edited." }}
        </div>`;
    } else {
        html = Object.entries(PERM_GROUPS).map(([group, perms]) => `
            <div class="mb-3">
                <div class="fw-semibold text-muted small text-uppercase mb-1">${group}</div>
                <div class="d-flex flex-wrap gap-2">
                    ${perms.map(p => `
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input perm-check" type="checkbox" id="perm_${p}"
                                   value="${p}" ${rolePerm.includes(p) ? 'checked' : ''}>
                            <label class="form-check-label small font-monospace" for="perm_${p}">${p}</label>
                        </div>`).join('')}
                </div>
            </div>`).join('');
    }
    document.getElementById('permissionsPanel').innerHTML = html;
}

async function savePermissions() {
    if (!selectedRoleId) return;
    const checked = [...document.querySelectorAll('.perm-check:checked')].map(el => el.value);
    const res = await apiCall(`/api/roles/${selectedRoleId}/permissions`, 'POST', { permissions: checked });
    if (res.success) {
        showToast('{{ __("pos.permissions_updated") }}');
        const role = allRoles.find(r => r.id === selectedRoleId);
        if (role) role.permissions = checked.map(name => ({ name }));
        renderRolesList();
    } else {
        showToast(res.message, 'danger');
    }
}

function openRoleModal(id = null, name = '') {
    document.getElementById('roleId').value   = id || '';
    document.getElementById('roleName').value = name;
    document.getElementById('roleModalTitle').textContent = id
        ? '{{ __("pos.edit_role") }}'
        : '{{ __("pos.create_role") }}';
    new bootstrap.Modal(document.getElementById('roleModal')).show();
}

async function saveRole() {
    const id   = document.getElementById('roleId').value;
    const name = document.getElementById('roleName').value.trim();
    if (!name) return;

    const url    = id ? `/api/roles/${id}` : '{{ route("roles.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, { name });

    if (res.success) {
        showToast(id ? '{{ __("pos.role_updated") }}' : '{{ __("pos.role_created") }}');
        bootstrap.Modal.getInstance(document.getElementById('roleModal')).hide();
        await boot();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function deleteRole(id) {
    if (!confirm('{{ __("pos.confirm_delete") }}')) return;
    const res = await apiCall(`/api/roles/${id}`, 'DELETE');
    if (res.success) {
        showToast('{{ __("pos.role_deleted") }}');
        if (selectedRoleId === id) {
            selectedRoleId = null;
            document.getElementById('permissionsPanel').innerHTML =
                '<p class="text-muted text-center py-4">{{ app()->getLocale() === "ar" ? "اختر دوراً لإدارة صلاحياته" : "Select a role to manage its permissions" }}</p>';
            document.getElementById('savePermBtn').classList.add('d-none');
        }
        await boot();
    } else {
        showToast(res.message, 'danger');
    }
}

document.getElementById('usersBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    if (action === 'edit-user')    editUser(allUsers[parseInt(btn.dataset.userIdx)]);
    else if (action === 'toggle-user') toggleUser(parseInt(btn.dataset.id));
    else if (action === 'delete-user') deleteUser(parseInt(btn.dataset.id));
});

document.getElementById('rolesList').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    if (action === 'select-role') {
        selectRole(parseInt(btn.dataset.id));
    } else if (action === 'edit-role') {
        e.stopPropagation();
        openRoleModal(parseInt(btn.dataset.id), btn.dataset.name || '');
    } else if (action === 'delete-role') {
        e.stopPropagation();
        deleteRole(parseInt(btn.dataset.id));
    }
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

boot();
</script>
@endpush
