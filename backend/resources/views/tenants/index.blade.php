@extends('layouts.app')

@section('title', app()->getLocale()==='ar' ? 'لوحة إدارة الاشتراكات' : 'Subscription Management')

@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-layer-group me-2 text-primary"></i>
                {{ app()->getLocale()==='ar' ? 'لوحة إدارة الاشتراكات' : 'Subscription Management' }}
            </h4>
            <small class="text-muted">{{ __('pos.tenant_management_desc') }}</small>
        </div>
        <button class="btn btn-primary" data-action="new-tenant">
            <i class="fas fa-plus me-1"></i>{{ __('pos.new_tenant') }}
        </button>
    </div>

    {{-- Master-tenant notice --}}
    @php $masterId = config('tenancy.master_tenant'); @endphp
    @if(!$masterId)
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        {{ __('pos.master_tenant_not_set') }} <code class="ms-1">MASTER_TENANT_ID</code>
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        @php
            $planPrices = ['basic'=>49,'pro'=>99,'enterprise'=>199];
            $mrr = $tenants->where('subscription_status','active')->reduce(function($carry, $t) use ($planPrices) {
                return $carry + ($planPrices[$t->plan ?? 'basic'] ?? 49);
            }, 0);
            $expiringSoon = $tenants->filter(fn($t) =>
                $t->subscription_ends_at &&
                $t->subscription_ends_at->between(now(), now()->addDays(14))
            )->count();
        @endphp
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-primary bg-opacity-10">
                        <i class="fas fa-building fa-lg text-white"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1">{{ $stats['total'] }}</div>
                        <div class="text-muted small mt-1">{{ __('pos.total_tenants') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-success bg-opacity-10">
                        <i class="fas fa-check-circle fa-lg text-success"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1">{{ $stats['active'] }}</div>
                        <div class="text-muted small mt-1">{{ __('pos.active_subscriptions') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-warning bg-opacity-10">
                        <i class="fas fa-clock fa-lg text-warning"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1">{{ $stats['trial'] }}</div>
                        <div class="text-muted small mt-1">{{ __('pos.trial_subscriptions') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-info bg-opacity-10">
                        <i class="fas fa-dollar-sign fa-lg text-info"></i>
                    </div>
                    <div>
                        <div class="fs-3 fw-bold lh-1">${{ number_format($mrr) }}</div>
                        <div class="text-muted small mt-1">MRR</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($expiringSoon > 0)
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-exclamation-triangle"></i>
        <span>
            {{ $expiringSoon }}
            {{ app()->getLocale()==='ar'
                ? 'اشتراك/اشتراكات تنتهي خلال 14 يوماً'
                : ($expiringSoon === 1 ? 'subscription expires' : 'subscriptions expire') . ' within 14 days' }}
        </span>
    </div>
    @endif

    {{-- Tenants Table --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <span class="fw-semibold">{{ __('pos.all_tenants') }}</span>
            <div class="d-flex gap-2 align-items-center">
                <select id="filterStatus" class="form-select form-select-sm" style="width:auto">
                    <option value="">{{ app()->getLocale()==='ar' ? 'جميع الحالات' : 'All Status' }}</option>
                    <option value="trial">Trial</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <input type="text" id="searchTenants" class="form-control form-control-sm" style="width:220px"
                    placeholder="{{ __('pos.search') }}...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tenantsTable">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pos.tenant_name') }}</th>
                            <th>{{ __('pos.tenant_code') }}</th>
                            <th>{{ __('pos.plan') }}</th>
                            <th>{{ __('pos.subscription_status') }}</th>
                            <th>{{ __('pos.subscription_ends') }}</th>
                            <th>{{ __('pos.status') }}</th>
                            <th>{{ __('pos.created_at') }}</th>
                            <th class="text-center">{{ __('pos.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenants as $tenant)
                        @php
                            $subStatus   = $tenant->subscription_status ?? 'trial';
                            $badgeClass  = $tenant->subscriptionBadgeClass();
                            $subLabel    = $tenant->subscriptionLabel();
                            $endsAt      = $tenant->subscription_ends_at ?? $tenant->trial_ends_at;
                            $expiring14  = $endsAt && $endsAt->between(now(), now()->addDays(14));
                            $expired     = $endsAt && $endsAt->isPast();
                        @endphp
                        <tr id="tenant-row-{{ $tenant->id }}"
                            data-name="{{ strtolower($tenant->name . ' ' . $tenant->code) }}"
                            data-status="{{ $subStatus }}">
                            <td>
                                <div class="fw-semibold">{{ $tenant->name }}</div>
                                @if($tenant->id === $masterId)
                                    <span class="badge bg-warning text-dark">Master</span>
                                @endif
                            </td>
                            <td><code>{{ $tenant->code }}</code></td>
                            <td>
                                <span class="badge bg-{{ $tenant->plan === 'enterprise' ? 'primary' : ($tenant->plan === 'pro' ? 'info' : 'secondary') }}">
                                    {{ ucfirst($tenant->plan ?? 'basic') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $badgeClass }}">{{ $subLabel }}</span>
                            </td>
                            <td>
                                @if($endsAt)
                                    <span class="{{ $expiring14 ? 'text-danger fw-semibold' : ($expired ? 'text-muted text-decoration-line-through' : '') }}">
                                        {{ $endsAt->format('Y-m-d') }}
                                        @if($expiring14 && !$expired)
                                            <i class="fas fa-exclamation-triangle ms-1 text-warning"></i>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input tenant-toggle" type="checkbox" role="switch"
                                        id="toggle-{{ $tenant->id }}"
                                        data-id="{{ $tenant->id }}"
                                        {{ $tenant->is_active ? 'checked' : '' }}
                                        @if($tenant->id === $masterId) disabled title="{{ app()->getLocale()==='ar' ? 'لا يمكن تعطيل المتجر الرئيسي' : 'Cannot deactivate master tenant' }}" @endif>
                                </div>
                            </td>
                            <td class="text-muted small">{{ $tenant->created_at?->format('Y-m-d') }}</td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <button class="btn btn-sm btn-outline-primary"
                                        data-action="users"
                                        data-id="{{ $tenant->id }}"
                                        data-name="{{ $tenant->name }}"
                                        title="{{ app()->getLocale()==='ar' ? 'إدارة المستخدمين' : 'Manage Users' }}">
                                        <i class="fas fa-users"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success"
                                        data-action="extend"
                                        data-id="{{ $tenant->id }}"
                                        data-name="{{ $tenant->name }}"
                                        title="{{ __('pos.extend_subscription') }}">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary"
                                        data-action="edit"
                                        data-id="{{ $tenant->id }}"
                                        data-name="{{ $tenant->name }}"
                                        data-plan="{{ $tenant->plan ?? 'basic' }}"
                                        title="{{ __('pos.edit') }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split px-2"
                                            data-bs-toggle="dropdown" aria-expanded="false"></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button class="dropdown-item"
                                                    data-action="seed"
                                                    data-id="{{ $tenant->id }}"
                                                    data-name="{{ $tenant->name }}">
                                                    <i class="fas fa-database me-2 text-info"></i>{{ __('pos.seed_tenant') }}
                                                </button>
                                            </li>
                                            @if($subStatus !== 'suspended' && $tenant->id !== $masterId)
                                            <li>
                                                <button class="dropdown-item text-warning"
                                                    data-action="suspend"
                                                    data-id="{{ $tenant->id }}"
                                                    data-name="{{ $tenant->name }}">
                                                    <i class="fas fa-ban me-2"></i>{{ __('pos.suspend_subscription') }}
                                                </button>
                                            </li>
                                            @endif
                                            @if(in_array($subStatus, ['active','trial','suspended']) && $tenant->id !== $masterId)
                                            <li>
                                                <button class="dropdown-item text-secondary"
                                                    data-action="cancel"
                                                    data-id="{{ $tenant->id }}"
                                                    data-name="{{ $tenant->name }}">
                                                    <i class="fas fa-times me-2"></i>{{ __('pos.cancel_subscription') }}
                                                </button>
                                            </li>
                                            @endif
                                            @if($tenant->id !== $masterId)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger"
                                                    data-action="delete"
                                                    data-id="{{ $tenant->id }}"
                                                    data-name="{{ $tenant->name }}">
                                                    <i class="fas fa-trash me-2"></i>{{ __('pos.delete') }}
                                                </button>
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('pos.no_tenants') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Create / Edit Tenant Modal ── --}}
<div class="modal fade" id="tenantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tenantModalTitle">{{ __('pos.new_tenant') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tenantAlert" class="alert d-none"></div>
                <input type="hidden" id="tenantId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.tenant_name') }} *</label>
                    <input type="text" class="form-control" id="tenantName" placeholder="{{ __('pos.tenant_name_placeholder') }}">
                </div>
                <div class="mb-3" id="tenantCodeGroup">
                    <label class="form-label fw-semibold">{{ __('pos.tenant_code') }} *</label>
                    <input type="text" class="form-control" id="tenantCode"
                        placeholder="{{ __('pos.tenant_code_placeholder') }}" pattern="[a-z0-9_-]+" style="direction:ltr">
                    <div class="form-text">{{ __('pos.tenant_code_help') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.plan') }}</label>
                    <select class="form-select" id="tenantPlan">
                        <option value="basic">Basic — $49/mo</option>
                        <option value="pro">Pro — $99/mo</option>
                        <option value="enterprise">Enterprise — $199/mo</option>
                    </select>
                </div>
                <div class="mb-3" id="tenantTrialGroup">
                    <label class="form-label fw-semibold">{{ __('pos.trial_days') }}</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="tenantTrialDays" value="14" min="0" max="365">
                        <span class="input-group-text">{{ __('pos.days') }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="saveTenantBtn" data-action="save-tenant">
                    <span id="saveTenantText">{{ __('pos.save') }}</span>
                    <span class="spinner-border spinner-border-sm d-none ms-1" id="saveTenantSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Extend Subscription Modal ── --}}
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pos.extend_subscription') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="extendAlert" class="alert d-none"></div>
                <input type="hidden" id="extendTenantId">
                <p class="text-muted small mb-3 fw-semibold" id="extendTenantName"></p>
                <label class="form-label fw-semibold">{{ __('pos.extend_months') }}</label>
                <div class="input-group mb-3">
                    <input type="number" class="form-control" id="extendMonths" value="1" min="1" max="24">
                    <span class="input-group-text">{{ __('pos.months') }}</span>
                </div>
                <div class="d-flex gap-2">
                    @foreach([1,3,6,12] as $m)
                    <button class="btn btn-outline-primary btn-sm flex-fill" data-action="set-months" data-months="{{ $m }}">{{ $m }}m</button>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button type="button" class="btn btn-success btn-sm" data-action="confirm-extend">
                    <i class="fas fa-calendar-plus me-1"></i>{{ __('pos.extend') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Tenant Users Modal ── --}}
<div class="modal fade" id="usersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-users me-2 text-primary"></i>
                        {{ app()->getLocale()==='ar' ? 'مستخدمو المتجر' : 'Store Users' }}
                    </h5>
                    <small class="text-muted" id="usersModalTenantName"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="usersLoading" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                </div>
                <div id="usersContent" class="d-none">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('pos.name') }}</th>
                                <th>{{ app()->getLocale()==='ar' ? 'اسم المستخدم' : 'Username' }}</th>
                                <th>{{ __('pos.created_at') }}</th>
                                <th class="text-center">{{ __('pos.status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody"></tbody>
                    </table>
                </div>
                <div id="usersEmpty" class="d-none text-center text-muted py-5">
                    <i class="fas fa-users fa-2x mb-2 opacity-25"></i>
                    <p>{{ app()->getLocale()==='ar' ? 'لا يوجد مستخدمون' : 'No users found' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script @nonce>
const BASE_URL   = "{{ url('admin/tenants') }}";
const STORE_URL  = "{{ route('admin.tenants.store') }}";
const MASTER_ID  = "{{ config('tenancy.master_tenant') }}";
const isAr       = {{ app()->getLocale()==='ar' ? 'true' : 'false' }};
const MSG = {
    fillRequired:    "{{ __('pos.fill_required_fields') }}",
    serverError:     "{{ __('pos.server_error') }}",
    editTenant:      "{{ __('pos.edit_tenant') }}",
    newTenant:       "{{ __('pos.new_tenant') }}",
    seedConfirm:     "{{ __('pos.seed_tenant_confirm') }}",
    suspendConfirm:  "{{ __('pos.suspend_confirm') }}",
    cancelConfirm:   "{{ __('pos.cancel_subscription_confirm') }}",
    deleteConfirm:   "{{ __('pos.delete_tenant_confirm') }}",
    deleteWarning:   "{{ __('pos.delete_tenant_warning') }}",
    activate:        "{{ __('pos.activate') }}",
    deactivate:      "{{ __('pos.deactivate') }}",
};

let tenantModal, extendModal, usersModal;
let currentUsersTenantId = null;

document.addEventListener('DOMContentLoaded', () => {
    tenantModal = new bootstrap.Modal(document.getElementById('tenantModal'));
    extendModal = new bootstrap.Modal(document.getElementById('extendModal'));
    usersModal  = new bootstrap.Modal(document.getElementById('usersModal'));

    // ── Filter inputs ─────────────────────────────────────────────────────
    document.getElementById('filterStatus').addEventListener('change', applyFilters);
    document.getElementById('searchTenants').addEventListener('input', applyFilters);

    // ── Tenant toggle switches ────────────────────────────────────────────
    document.getElementById('tenantsTable').addEventListener('change', e => {
        const cb = e.target.closest('.tenant-toggle');
        if (cb) toggleTenant(cb.dataset.id, cb);
    });

    // ── Users table toggle (delegated on tbody — populated dynamically) ───
    document.getElementById('usersTableBody').addEventListener('change', e => {
        const cb = e.target.closest('.user-toggle');
        if (cb) toggleTenantUser(parseInt(cb.dataset.userId), cb);
    });

    // ── Click delegation ──────────────────────────────────────────────────
    document.addEventListener('click', e => {
        const el = e.target.closest('[data-action]');
        if (!el) return;

        const action = el.dataset.action;
        const id     = el.dataset.id;
        const name   = el.dataset.name;

        switch (action) {
            case 'new-tenant':      openTenantModal();                              break;
            case 'edit':            openTenantModal({ id, name, plan: el.dataset.plan }); break;
            case 'users':           openUsersModal(id, name);                       break;
            case 'extend':          openExtendModal(id, name);                      break;
            case 'seed':            seedTenant(id, name);                           break;
            case 'suspend':         suspendTenant(id, name);                        break;
            case 'cancel':          cancelSubscription(id, name);                   break;
            case 'delete':          deleteTenant(id, name);                         break;
            case 'save-tenant':     saveTenant();                                   break;
            case 'confirm-extend':  confirmExtend();                                break;
            case 'set-months':
                document.getElementById('extendMonths').value = el.dataset.months;
                break;
        }
    });
});

// ── Create / Edit Tenant ──────────────────────────────────────────────────
function openTenantModal(tenant = null) {
    document.getElementById('tenantAlert').classList.add('d-none');
    document.getElementById('tenantId').value        = tenant?.id ?? '';
    document.getElementById('tenantName').value      = tenant?.name ?? '';
    document.getElementById('tenantCode').value      = '';
    document.getElementById('tenantPlan').value      = tenant?.plan ?? 'basic';
    document.getElementById('tenantTrialDays').value = 14;
    document.getElementById('tenantModalTitle').textContent =
        tenant ? MSG.editTenant : MSG.newTenant;

    const isNew = !tenant?.id;
    document.getElementById('tenantCodeGroup').classList.toggle('d-none', !isNew);
    document.getElementById('tenantTrialGroup').classList.toggle('d-none', !isNew);
    tenantModal.show();
    setTimeout(() => document.getElementById('tenantName').focus(), 300);
}

async function saveTenant() {
    const id        = document.getElementById('tenantId').value;
    const name      = document.getElementById('tenantName').value.trim();
    const code      = document.getElementById('tenantCode').value.trim().toLowerCase();
    const plan      = document.getElementById('tenantPlan').value;
    const trialDays = document.getElementById('tenantTrialDays').value;

    if (!name || (!id && !code)) { showTenantAlert(MSG.fillRequired, 'danger'); return; }

    setSaveBusy(true);
    try {
        const url  = id ? `${BASE_URL}/${id}` : STORE_URL;
        const body = id ? { name, plan } : { name, code, plan, trial_days: trialDays };
        const res  = await apiCall(url, id ? 'PUT' : 'POST', body);
        if (!res.success) { showTenantAlert(res.message, 'danger'); return; }
        tenantModal.hide();
        setTimeout(() => location.reload(), 300);
    } catch { showTenantAlert(MSG.serverError, 'danger'); }
    finally  { setSaveBusy(false); }
}

// ── Tenant active toggle ──────────────────────────────────────────────────
async function toggleTenant(id, checkbox) {
    const orig = checkbox.checked;
    checkbox.disabled = true;
    const res = await apiCall(`${BASE_URL}/${id}/toggle`, 'PATCH');
    checkbox.disabled = false;
    if (res.success) showToast(res.message, 'success');
    else { checkbox.checked = !orig; showToast(res.message, 'danger'); }
}

// ── Extend Subscription ───────────────────────────────────────────────────
function openExtendModal(id, name) {
    document.getElementById('extendAlert').classList.add('d-none');
    document.getElementById('extendTenantId').value   = id;
    document.getElementById('extendTenantName').textContent = name;
    document.getElementById('extendMonths').value     = 1;
    extendModal.show();
}

async function confirmExtend() {
    const id     = document.getElementById('extendTenantId').value;
    const months = parseInt(document.getElementById('extendMonths').value);
    if (!months || months < 1) return;

    const res = await apiCall(`${BASE_URL}/${id}/extend`, 'POST', { months });
    if (res.success) {
        extendModal.hide();
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        const el = document.getElementById('extendAlert');
        el.className = 'alert alert-danger';
        el.textContent = res.message;
        el.classList.remove('d-none');
    }
}

// ── Suspend / Cancel / Delete ─────────────────────────────────────────────
async function suspendTenant(id, name) {
    if (!confirm(`${MSG.suspendConfirm} "${name}"?`)) return;
    const res = await apiCall(`${BASE_URL}/${id}/suspend`, 'PATCH');
    if (res.success) { showToast(res.message, 'warning'); setTimeout(() => location.reload(), 800); }
    else showToast(res.message, 'danger');
}

async function cancelSubscription(id, name) {
    if (!confirm(`${MSG.cancelConfirm} "${name}"?`)) return;
    const res = await apiCall(`${BASE_URL}/${id}/cancel`, 'PATCH');
    if (res.success) { showToast(res.message, 'success'); setTimeout(() => location.reload(), 800); }
    else showToast(res.message, 'danger');
}

async function seedTenant(id, name) {
    if (!confirm(`${MSG.seedConfirm} "${name}"?`)) return;
    showToast(isAr ? 'جارٍ البذر…' : 'Seeding…', 'info');
    const res = await apiCall(`${BASE_URL}/${id}/seed`, 'POST');
    showToast(res.message || (res.success ? 'Done' : 'Error'), res.success ? 'success' : 'danger');
}

async function deleteTenant(id, name) {
    if (id === MASTER_ID) {
        showToast(isAr ? 'لا يمكن حذف المتجر الرئيسي' : 'Cannot delete the master tenant', 'danger');
        return;
    }
    if (!confirm(`${MSG.deleteConfirm} "${name}"?\n${MSG.deleteWarning}`)) return;
    const res = await apiCall(`${BASE_URL}/${id}`, 'DELETE');
    if (res.success) { document.getElementById(`tenant-row-${id}`)?.remove(); showToast(res.message, 'success'); }
    else showToast(res.message, 'danger');
}

// ── Tenant Users ──────────────────────────────────────────────────────────
async function openUsersModal(tenantId, tenantName) {
    currentUsersTenantId = tenantId;
    document.getElementById('usersModalTenantName').textContent = tenantName;
    document.getElementById('usersLoading').classList.remove('d-none');
    document.getElementById('usersContent').classList.add('d-none');
    document.getElementById('usersEmpty').classList.add('d-none');
    usersModal.show();

    const res = await apiCall(`${BASE_URL}/${tenantId}/users`, 'GET');
    document.getElementById('usersLoading').classList.add('d-none');

    if (!res.success || !res.users?.length) {
        document.getElementById('usersEmpty').classList.remove('d-none');
        return;
    }
    renderUsers(res.users);
    document.getElementById('usersContent').classList.remove('d-none');
}

function renderUsers(users) {
    const deactivateLabel = MSG.deactivate;
    const activateLabel   = MSG.activate;
    document.getElementById('usersTableBody').innerHTML = users.map(u => `
        <tr id="user-row-${u.id}">
            <td class="text-muted small">${u.id}</td>
            <td>
                <div class="fw-semibold">${escHtml(u.name)}</div>
                ${u.email ? `<div class="text-muted small">${escHtml(u.email)}</div>` : ''}
            </td>
            <td><code>${escHtml(u.username)}</code></td>
            <td class="text-muted small">${u.created_at ? u.created_at.substring(0,10) : '—'}</td>
            <td class="text-center">
                <div class="form-check form-switch d-inline-block mb-0"
                     title="${u.is_active ? deactivateLabel : activateLabel}">
                    <input class="form-check-input user-toggle" type="checkbox" role="switch"
                        data-user-id="${u.id}"
                        ${u.is_active ? 'checked' : ''}>
                </div>
            </td>
        </tr>
    `).join('');
}

async function toggleTenantUser(userId, checkbox) {
    const orig = checkbox.checked;
    checkbox.disabled = true;
    const res = await apiCall(`${BASE_URL}/${currentUsersTenantId}/users/${userId}/toggle`, 'PATCH');
    checkbox.disabled = false;
    if (res.success) showToast(res.message, 'success');
    else { checkbox.checked = !orig; showToast(res.message, 'danger'); }
}

// ── Search / Filter ───────────────────────────────────────────────────────
function applyFilters() {
    const q      = document.getElementById('searchTenants').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    document.querySelectorAll('#tenantsTable tbody tr[data-name]').forEach(row => {
        const nameMatch   = !q || row.dataset.name.includes(q);
        const statusMatch = !status || row.dataset.status === status;
        row.style.display = (nameMatch && statusMatch) ? '' : 'none';
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showTenantAlert(msg, type) {
    const el = document.getElementById('tenantAlert');
    el.className = `alert alert-${type}`;
    el.textContent = msg;
    el.classList.remove('d-none');
}

function setSaveBusy(busy) {
    document.getElementById('saveTenantBtn').disabled = busy;
    document.getElementById('saveTenantSpinner').classList.toggle('d-none', !busy);
}
</script>
@endpush
@endsection
