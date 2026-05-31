@extends('layouts.app')
@section('title', app()->getLocale()==='ar' ? 'الخطط والأسعار' : 'Plans & Pricing')

@push('styles')
<style @nonce>
.plan-card { border-radius: 1rem; border: 2px solid #e5e7eb; transition: all .2s; }
.plan-card.inactive { opacity: .55; }
.plan-card-header { border-radius: .875rem .875rem 0 0; padding: 1.25rem; }
.plan-price { font-size: 2.4rem; font-weight: 800; line-height: 1; }
.plan-price sup { font-size: 1rem; font-weight: 600; vertical-align: super; }
.plan-price sub { font-size: .85rem; font-weight: 400; color: #6b7280; }
.plan-feature { display: flex; align-items: center; gap: .5rem; padding: .3rem 0; font-size: .82rem; border-bottom: 1px solid #f3f4f6; }
.plan-feature:last-child { border-bottom: none; }
.plan-badge { font-size: .7rem; font-weight: 700; padding: .2rem .6rem; border-radius: 1rem; }
.annual-save { font-size: .72rem; font-weight: 600; color: #059669; }

/* Module chips on plan card */
.module-chip { display: inline-flex; align-items: center; gap: .3rem;
    background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;
    border-radius: .5rem; padding: .18rem .5rem; font-size: .72rem; font-weight: 600; margin: .15rem; }
.module-chip i { font-size: .65rem; }

/* Module group header in modal */
.mod-group-header { font-size: .68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: #6b7280; margin: .75rem 0 .4rem;
    padding-bottom: .3rem; border-bottom: 1px solid #e5e7eb; }
.mod-check-label { display: flex; align-items: center; gap: .4rem;
    cursor: pointer; padding: .3rem .5rem; border-radius: .4rem;
    font-size: .83rem; transition: background .15s; user-select: none; }
.mod-check-label:hover { background: #f0f9ff; }
.mod-check-label input { cursor: pointer; width: 15px; height: 15px; flex-shrink: 0; }
.mod-check-label i { color: #0369a1; width: 14px; text-align: center; }
</style>
@endpush

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="fas fa-tags me-2 text-primary"></i>
            {{ $isAr ? 'الخطط والأسعار' : 'Plans & Pricing' }}
        </h4>
        <small class="text-muted">{{ $isAr ? 'إدارة خطط الاشتراك وأسعارها' : 'Manage subscription plans and pricing' }}</small>
    </div>
    <button class="btn btn-primary" data-action="new-plan">
        <i class="fas fa-plus me-1"></i>
        {{ $isAr ? 'خطة جديدة' : 'New Plan' }}
    </button>
</div>

<div id="pageAlert" class="alert d-none mb-3"></div>

<div class="row g-3" id="plansGrid">
    @forelse($plans as $plan)
    @php
        $colors = [
            'basic'      => ['#6b7280','#f9fafb'],
            'pro'        => ['#0ea5e9','#eff6ff'],
            'enterprise' => ['#8b5cf6','#faf5ff'],
        ];
        $c           = $colors[$plan->id] ?? ['#374151','#f9fafb'];
        $tenantCount = $tenantCounts[$plan->id] ?? 0;
        $annualSave  = $plan->annualSavings();
        $planModules = $plan->feature_flags ?? [];
    @endphp
    <div class="col-md-6 col-xl-4" id="plan-col-{{ $plan->id }}">
        <div class="plan-card card shadow-sm h-100 {{ $plan->is_active ? '' : 'inactive' }}">
            <div class="plan-card-header" style="background:{{ $c[1] }}">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="badge mb-2" style="background:{{ $c[0] }};color:#fff;font-size:.72rem">
                            {{ strtoupper($plan->id) }}
                        </span>
                        <div class="fw-bold fs-5">{{ $plan->name }}</div>
                    </div>
                    <div class="d-flex gap-1">
                        @if(!$plan->is_active)
                            <span class="badge bg-secondary plan-badge">{{ $isAr?'معطل':'Inactive' }}</span>
                        @endif
                        <span class="badge bg-light text-dark plan-badge">
                            {{ $tenantCount }} {{ $isAr?'متجر':'store'.($tenantCount==1?'':'s') }}
                        </span>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="plan-price" style="color:{{ $c[0] }}">
                        <sup>$</sup>{{ number_format($plan->monthly_price, 0) }}<sub>/{{ $isAr?'شهر':'mo' }}</sub>
                    </div>
                    @if($plan->annual_price)
                    <div class="mt-1">
                        <span class="text-muted small">${{ number_format($plan->annual_price, 0) }}/{{ $isAr?'سنة':'yr' }}</span>
                        @if($annualSave > 0)
                            <span class="annual-save ms-2">
                                <i class="fas fa-tag fa-xs"></i>
                                {{ $isAr?'وفّر':'Save' }} ${{ number_format($annualSave, 0) }}
                            </span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <div class="card-body py-3">
                {{-- Limits --}}
                <div class="mb-3">
                    <div class="plan-feature">
                        <i class="fas fa-users fa-sm text-muted"></i>
                        <span>{{ $isAr?'المستخدمون':'Users' }}:
                            <strong>{{ $plan->max_users ?? ($isAr?'غير محدود':'Unlimited') }}</strong>
                        </span>
                    </div>
                    <div class="plan-feature">
                        <i class="fas fa-boxes fa-sm text-muted"></i>
                        <span>{{ $isAr?'المنتجات':'Products' }}:
                            <strong>{{ $plan->max_products ?? ($isAr?'غير محدود':'Unlimited') }}</strong>
                        </span>
                    </div>
                    <div class="plan-feature">
                        <i class="fas fa-clock fa-sm text-muted"></i>
                        <span>{{ $isAr?'التجربة':'Trial' }}:
                            <strong>{{ $plan->trial_days }} {{ $isAr?'يوم':'days' }}</strong>
                        </span>
                    </div>
                </div>

                {{-- Modules (feature_flags) --}}
                @if(count($planModules))
                <div class="mb-3">
                    <div class="text-muted" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem">
                        <i class="fas fa-puzzle-piece me-1"></i>{{ $isAr?'الوحدات المتاحة':'Included Modules' }}
                        <span class="badge bg-primary ms-1" style="font-size:.65rem">{{ count($planModules) }}</span>
                    </div>
                    <div>
                        @foreach($planModules as $mk)
                            @php $md = $allModules[$mk] ?? null; @endphp
                            @if($md)
                            <span class="module-chip">
                                <i class="fas {{ $md['icon'] }}"></i>
                                {{ $isAr ? $md['ar'] : $md['en'] }}
                            </span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Marketing feature bullets --}}
                @if($plan->features && count($plan->features))
                <div class="mb-2">
                    <div class="text-muted" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem">
                        <i class="fas fa-star me-1"></i>{{ $isAr?'مميزات إضافية':'Highlights' }}
                    </div>
                    @foreach($plan->features as $feature)
                    <div class="plan-feature">
                        <i class="fas fa-check-circle fa-sm text-success"></i>
                        <span>{{ is_array($feature) ? ($isAr ? ($feature['ar'] ?? $feature['en'] ?? '') : ($feature['en'] ?? $feature['ar'] ?? '')) : $feature }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="card-footer bg-white border-top d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary flex-fill"
                        data-action="edit-plan"
                        data-plan="{{ json_encode($plan) }}">
                    <i class="fas fa-edit me-1"></i>{{ $isAr?'تعديل':'Edit' }}
                </button>
                <button class="btn btn-sm {{ $plan->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                        data-action="toggle-plan"
                        data-id="{{ $plan->id }}"
                        data-active="{{ $plan->is_active ? '1' : '0' }}"
                        title="{{ $plan->is_active ? ($isAr?'تعطيل':'Deactivate') : ($isAr?'تفعيل':'Activate') }}">
                    <i class="fas fa-{{ $plan->is_active ? 'ban' : 'check' }}"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger"
                        data-action="delete-plan"
                        data-id="{{ $plan->id }}"
                        data-tenants="{{ $tenantCount }}"
                        title="{{ $isAr?'حذف':'Delete' }}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12 text-center py-5 text-muted">
        <i class="fas fa-tags fa-3x mb-3 opacity-25 d-block"></i>
        {{ $isAr ? 'لا توجد خطط بعد. أنشئ خطتك الأولى.' : 'No plans yet. Create your first plan.' }}
    </div>
    @endforelse
</div>

{{-- ═══ Plan Modal ═══ --}}
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="planModalTitle">{{ $isAr?'خطة جديدة':'New Plan' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlert" class="alert d-none mb-3"></div>

                {{-- Basic fields --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">{{ $isAr?'معرف الخطة':'Plan ID' }} *
                            <span class="text-muted fw-normal">({{ $isAr?'لا يمكن تغييره':'cannot change' }})</span>
                        </label>
                        <input type="text" id="pId" class="form-control form-control-sm" placeholder="e.g. basic" style="direction:ltr">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small">{{ $isAr?'اسم الخطة':'Plan Name' }} *</label>
                        <input type="text" id="pName" class="form-control form-control-sm" placeholder="{{ $isAr?'مثال: الخطة الأساسية':'e.g. Basic Plan' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">{{ $isAr?'السعر الشهري ($)':'Monthly Price ($)' }} *</label>
                        <input type="number" id="pMonthly" class="form-control form-control-sm" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">{{ $isAr?'السعر السنوي ($)':'Annual Price ($)' }}
                            <span class="text-muted fw-normal">({{ $isAr?'اختياري':'optional' }})</span>
                        </label>
                        <input type="number" id="pAnnual" class="form-control form-control-sm" min="0" step="0.01" placeholder="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">{{ $isAr?'أيام التجربة':'Trial Days' }} *</label>
                        <input type="number" id="pTrial" class="form-control form-control-sm" min="0" max="365" value="14">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">{{ $isAr?'أقصى مستخدمين':'Max Users' }}
                            <span class="text-muted fw-normal">({{ $isAr?'فارغ=∞':'blank=∞' }})</span>
                        </label>
                        <input type="number" id="pMaxUsers" class="form-control form-control-sm" min="1" placeholder="{{ $isAr?'غير محدود':'Unlimited' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">{{ $isAr?'أقصى منتجات':'Max Products' }}
                            <span class="text-muted fw-normal">({{ $isAr?'فارغ=∞':'blank=∞' }})</span>
                        </label>
                        <input type="number" id="pMaxProducts" class="form-control form-control-sm" min="1" placeholder="{{ $isAr?'غير محدود':'Unlimited' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">{{ $isAr?'الترتيب':'Sort Order' }}</label>
                        <input type="number" id="pOrder" class="form-control form-control-sm" min="0" value="0">
                    </div>
                </div>

                <hr class="my-3">

                {{-- ═══ Module Selector ═══ --}}
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <span class="fw-bold small"><i class="fas fa-puzzle-piece me-1 text-primary"></i>{{ $isAr?'الوحدات المتاحة':'Included Modules' }}</span>
                        <span class="text-muted small ms-2">— {{ $isAr?'اختر الوحدات التي يتضمنها هذا الاشتراك':'Choose which modules are available on this plan' }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-xs btn-outline-success" style="font-size:.72rem;padding:.2rem .6rem" id="checkAllModules">
                            <i class="fas fa-check-double me-1"></i>{{ $isAr?'تحديد الكل':'All' }}
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.2rem .6rem" id="uncheckAllModules">
                            <i class="fas fa-xmark me-1"></i>{{ $isAr?'إلغاء الكل':'None' }}
                        </button>
                        <span class="badge bg-primary align-self-center" id="selectedCount" style="font-size:.72rem">0</span>
                    </div>
                </div>

                <div class="row g-0" id="modulesGrid">
                @php
                    $grouped = [];
                    foreach($allModules as $key => $mod) {
                        $grouped[$mod['group']][$key] = $mod;
                    }
                @endphp
                @foreach($moduleGroups as $gKey => $gLabel)
                    @if(isset($grouped[$gKey]))
                    <div class="col-md-6 col-lg-4 px-2">
                        <div class="mod-group-header">
                            {{ $isAr ? $gLabel['ar'] : $gLabel['en'] }}
                        </div>
                        @foreach($grouped[$gKey] as $mKey => $mod)
                        <label class="mod-check-label w-100" for="mod_{{ $mKey }}">
                            <input type="checkbox" id="mod_{{ $mKey }}" name="feature_flags[]" value="{{ $mKey }}" class="module-checkbox">
                            <i class="fas {{ $mod['icon'] }}"></i>
                            <span>{{ $isAr ? $mod['ar'] : $mod['en'] }}</span>
                        </label>
                        @endforeach
                    </div>
                    @endif
                @endforeach
                </div>

                <hr class="my-3">

                {{-- Marketing highlights (freeform) --}}
                <div>
                    <label class="form-label fw-semibold small">
                        <i class="fas fa-star me-1 text-warning"></i>
                        {{ $isAr?'نقاط تسويقية (اختياري — سطر لكل نقطة)':'Marketing Highlights (optional — one per line)' }}
                    </label>
                    <textarea id="pFeatures" class="form-control form-control-sm" rows="3"
                        placeholder="{{ $isAr?'مثال:\nدعم فني 24/7\nشهادة SSL\nنسخ احتياطي يومي':'e.g.\n24/7 Support\nSSL Certificate\nDaily Backups' }}"></textarea>
                    <small class="text-muted">{{ $isAr?'هذه النقاط تظهر في بطاقة الخطة للمستخدم (مثل: دعم فني، ضمان استرداد...). الوحدات أعلاه تتحكم في الوصول الفعلي.':'These bullets appear on the public plan card (e.g. support, guarantees). Modules above control actual access.' }}</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ $isAr?'إلغاء':'Cancel' }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="planSaveBtn" data-action="save-plan">
                    <span id="planSaveText">{{ $isAr?'حفظ':'Save' }}</span>
                    <span class="spinner-border spinner-border-sm d-none ms-1" id="planSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script @nonce>
const isAr      = {{ $isAr ? 'true' : 'false' }};
const PLANS_URL = "{{ route('admin.plans.store') }}";
let planModal, editingId = null;

document.addEventListener('DOMContentLoaded', () => {
    planModal = new bootstrap.Modal(document.getElementById('planModal'));
    updateSelectedCount();
});

// ── Module checkbox counter ───────────────────────────────────────────────────
function updateSelectedCount() {
    const n = document.querySelectorAll('.module-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = n;
}
document.getElementById('modulesGrid').addEventListener('change', updateSelectedCount);

document.getElementById('checkAllModules').addEventListener('click', () => {
    document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = true);
    updateSelectedCount();
});
document.getElementById('uncheckAllModules').addEventListener('click', () => {
    document.querySelectorAll('.module-checkbox').forEach(cb => cb.checked = false);
    updateSelectedCount();
});

// ── Event delegation ──────────────────────────────────────────────────────────
document.addEventListener('click', e => {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    const action = el.dataset.action;
    if (action === 'new-plan')    openPlanModal();
    if (action === 'edit-plan')   editPlan(JSON.parse(el.dataset.plan));
    if (action === 'toggle-plan') togglePlan(el.dataset.id, el.dataset.active === '1');
    if (action === 'delete-plan') deletePlan(el.dataset.id, parseInt(el.dataset.tenants));
    if (action === 'save-plan')   savePlan();
});

function setModules(flags) {
    document.querySelectorAll('.module-checkbox').forEach(cb => {
        cb.checked = Array.isArray(flags) && flags.includes(cb.value);
    });
    updateSelectedCount();
}

function getSelectedModules() {
    return [...document.querySelectorAll('.module-checkbox:checked')].map(cb => cb.value);
}

function openPlanModal() {
    editingId = null;
    document.getElementById('planModalTitle').textContent = isAr ? 'خطة جديدة' : 'New Plan';
    document.getElementById('pId').value         = '';
    document.getElementById('pId').disabled      = false;
    document.getElementById('pName').value       = '';
    document.getElementById('pMonthly').value    = '0';
    document.getElementById('pAnnual').value     = '';
    document.getElementById('pTrial').value      = '14';
    document.getElementById('pMaxUsers').value   = '';
    document.getElementById('pMaxProducts').value= '';
    document.getElementById('pOrder').value      = '0';
    document.getElementById('pFeatures').value   = '';
    setModules([]);
    clearModalAlert();
    planModal.show();
    setTimeout(() => document.getElementById('pId').focus(), 300);
}

function editPlan(plan) {
    editingId = plan.id;
    document.getElementById('planModalTitle').textContent = isAr ? 'تعديل الخطة' : 'Edit Plan';
    document.getElementById('pId').value         = plan.id;
    document.getElementById('pId').disabled      = true;
    document.getElementById('pName').value       = plan.name;
    document.getElementById('pMonthly').value    = plan.monthly_price;
    document.getElementById('pAnnual').value     = plan.annual_price ?? '';
    document.getElementById('pTrial').value      = plan.trial_days;
    document.getElementById('pMaxUsers').value   = plan.max_users ?? '';
    document.getElementById('pMaxProducts').value= plan.max_products ?? '';
    document.getElementById('pOrder').value      = plan.sort_order;
    document.getElementById('pFeatures').value   = Array.isArray(plan.features)
        ? plan.features.map(f => typeof f === 'object' ? (isAr ? (f.ar||f.en||'') : (f.en||f.ar||'')) : f).join('\n')
        : '';
    setModules(plan.feature_flags ?? []);
    clearModalAlert();
    planModal.show();
}

async function savePlan() {
    const id   = document.getElementById('pId').value.trim().toLowerCase();
    const name = document.getElementById('pName').value.trim();
    if (!name || (!editingId && !id)) {
        showModalAlert(isAr ? 'يرجى ملء الحقول المطلوبة' : 'Please fill required fields', 'danger');
        return;
    }

    const featuresRaw  = document.getElementById('pFeatures').value.trim();
    const features     = featuresRaw ? featuresRaw.split('\n').map(s => s.trim()).filter(Boolean) : [];
    const feature_flags = getSelectedModules();

    const payload = {
        name,
        monthly_price:  parseFloat(document.getElementById('pMonthly').value) || 0,
        annual_price:   document.getElementById('pAnnual').value !== ''
                          ? parseFloat(document.getElementById('pAnnual').value) : null,
        trial_days:     parseInt(document.getElementById('pTrial').value) || 14,
        max_users:      document.getElementById('pMaxUsers').value !== ''
                          ? parseInt(document.getElementById('pMaxUsers').value) : null,
        max_products:   document.getElementById('pMaxProducts').value !== ''
                          ? parseInt(document.getElementById('pMaxProducts').value) : null,
        sort_order:     parseInt(document.getElementById('pOrder').value) || 0,
        features,
        feature_flags,
    };
    if (!editingId) payload.id = id;

    setBusy(true);
    try {
        const url    = editingId ? "{{ url('admin/plans') }}/" + editingId : PLANS_URL;
        const method = editingId ? 'PUT' : 'POST';
        const res    = await apiCall(url, method, payload);
        if (!res.success) { showModalAlert(res.message, 'danger'); return; }
        planModal.hide();
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch {
        showModalAlert(isAr ? 'حدث خطأ في الخادم' : 'Server error', 'danger');
    } finally {
        setBusy(false);
    }
}

async function togglePlan(id, isActive) {
    try {
        const res = await apiCall("{{ url('admin/plans') }}/" + id + '/toggle', 'PATCH');
        if (!res.success) { showPageAlert(res.message, 'danger'); return; }
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch { showPageAlert(isAr ? 'حدث خطأ' : 'Error', 'danger'); }
}

async function deletePlan(id, tenantCount) {
    if (tenantCount > 0) {
        showPageAlert(
            (isAr ? 'لا يمكن حذف الخطة — يوجد ' : 'Cannot delete — ')
            + tenantCount + (isAr ? ' متجر مرتبط بها' : ' store(s) using this plan'),
            'warning'
        );
        return;
    }
    if (!confirm(isAr ? 'هل أنت متأكد من حذف هذه الخطة؟' : 'Delete this plan?')) return;
    try {
        const res = await apiCall("{{ url('admin/plans') }}/" + id, 'DELETE');
        if (!res.success) { showPageAlert(res.message, 'danger'); return; }
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch { showPageAlert(isAr ? 'حدث خطأ' : 'Error', 'danger'); }
}

function setBusy(on) {
    document.getElementById('planSaveBtn').disabled = on;
    document.getElementById('planSpinner').classList.toggle('d-none', !on);
}
function showModalAlert(msg, type) {
    const el = document.getElementById('modalAlert');
    el.className = `alert alert-${type} py-2 small`;
    el.textContent = msg;
    el.classList.remove('d-none');
}
function clearModalAlert() { document.getElementById('modalAlert').classList.add('d-none'); }
function showPageAlert(msg, type) {
    const el = document.getElementById('pageAlert');
    el.className = `alert alert-${type}`;
    el.textContent = msg;
    el.classList.remove('d-none');
    window.scrollTo(0, 0);
}
</script>
@endpush
@endsection
