@extends('layouts.app')

@section('title', app()->getLocale()==='ar' ? 'لوحة تحكم المالك' : 'Owner cPanel')

@push('styles')
<style @nonce>
    .cpanel-stat-card { border: none; border-radius: 1rem; transition: transform .2s; }
    .cpanel-stat-card:hover { transform: translateY(-3px); }
    .stat-icon { width: 52px; height: 52px; border-radius: .75rem; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
    .stat-value { font-size: 1.85rem; font-weight: 800; line-height: 1; }
    .stat-label { font-size: .78rem; color: #6b7280; margin-top: .25rem; }
    .stat-delta { font-size: .75rem; font-weight: 600; }

    .quick-btn { border-radius: .75rem; padding: .75rem 1rem; font-weight: 600; font-size: .85rem; border: 2px solid transparent; transition: all .2s; display: flex; align-items: center; gap: .5rem; }
    .quick-btn:hover { transform: translateY(-2px); }

    .tenant-row-mini { border-radius: .5rem; padding: .5rem .75rem; transition: background .15s; }
    .tenant-row-mini:hover { background: #f9fafb; }

    .chart-card { border: none; border-radius: 1rem; }
    .section-head { font-size: .7rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #9ca3af; margin-bottom: .75rem; }

    .expire-item { display: flex; align-items: center; justify-content: space-between; padding: .55rem 0; border-bottom: 1px solid #f3f4f6; gap: .5rem; }
    .expire-item:last-child { border-bottom: none; }

    .status-pill { font-size: .7rem; font-weight: 700; padding: .2rem .6rem; border-radius: 1rem; }
</style>
@endpush

@section('content')
@php
    $isAr = app()->getLocale() === 'ar';
    $total = $tenants->count();
    // Colors cycle for dynamic plans
    $colorPalette = ['#6b7280','#0ea5e9','#8b5cf6','#f59e0b','#10b981','#ef4444'];
    $planColors   = [];
    foreach ($planModels->values() as $i => $p) {
        $planColors[$p->id] = $colorPalette[$i % count($colorPalette)];
    }
    $statusColors = ['trial'=>'#f59e0b','active'=>'#10b981','expired'=>'#ef4444','suspended'=>'#1f2937','cancelled'=>'#d1d5db'];
    $statusLabels = ['trial'=>($isAr?'تجريبي':'Trial'),'active'=>($isAr?'نشط':'Active'),'expired'=>($isAr?'منتهي':'Expired'),'suspended'=>($isAr?'معلق':'Suspended'),'cancelled'=>($isAr?'ملغي':'Cancelled')];
@endphp

<div class="container-fluid py-4">

    {{-- ── Header ── --}}
    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-gauge-high me-2 text-primary"></i>
                {{ $isAr ? 'لوحة تحكم المالك' : 'Owner cPanel' }}
            </h4>
            <small class="text-muted">
                {{ $isAr ? 'نظرة شاملة على النظام والاشتراكات' : 'Full system and subscription overview' }}
                &nbsp;·&nbsp; {{ now()->format('d M Y') }}
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.tenants') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-layer-group me-1"></i>
                {{ $isAr ? 'إدارة الاشتراكات' : 'Manage Subscriptions' }}
            </a>
            <button class="btn btn-outline-secondary btn-sm" data-action="refresh-page">
                <i class="fas fa-rotate-right me-1"></i>
                {{ $isAr ? 'تحديث' : 'Refresh' }}
            </button>
        </div>
    </div>

    {{-- ── KPI row ── --}}
    <div class="row g-3 mb-4">
        @php
            $kpis = [
                ['icon'=>'fa-building','bg'=>'#eff6ff','color'=>'#2563eb','value'=>$total,'label'=>($isAr?'إجمالي المتاجر':'Total Stores'),'sub'=>null],
                ['icon'=>'fa-check-circle','bg'=>'#f0fdf4','color'=>'#059669','value'=>$statusCounts['active'],'label'=>($isAr?'اشتراكات نشطة':'Active'),'sub'=>$total>0?round($statusCounts['active']/$total*100).'%':null],
                ['icon'=>'fa-clock','bg'=>'#fffbeb','color'=>'#d97706','value'=>$statusCounts['trial'],'label'=>($isAr?'في التجربة':'On Trial'),'sub'=>null],
                ['icon'=>'fa-dollar-sign','bg'=>'#f0f9ff','color'=>'#0284c7','value'=>'$'.number_format($mrr),'label'=>($isAr?'الإيرادات الشهرية MRR':'Monthly Revenue (MRR)'),'sub'=>null],
                ['icon'=>'fa-chart-line','bg'=>'#fdf4ff','color'=>'#7c3aed','value'=>'$'.number_format($arr),'label'=>($isAr?'الإيرادات السنوية ARR':'Annual Revenue (ARR)'),'sub'=>null],
                ['icon'=>'fa-ban','bg'=>'#fff1f2','color'=>'#e11d48','value'=>$statusCounts['suspended']+$statusCounts['cancelled']+$statusCounts['expired'],'label'=>($isAr?'غير نشطة':'Inactive'),'sub'=>null],
            ];
        @endphp
        @foreach($kpis as $k)
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card cpanel-stat-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="stat-icon" style="background:{{ $k['bg'] }};color:{{ $k['color'] }}">
                        <i class="fas {{ $k['icon'] }}"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="stat-value">{{ $k['value'] }}</div>
                        <div class="stat-label text-truncate">{{ $k['label'] }}</div>
                        @if($k['sub'])
                        <div class="stat-delta text-success">{{ $k['sub'] }} {{ $isAr?'من الكل':'of total' }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Charts row ── --}}
    <div class="row g-3 mb-4">

        {{-- Monthly growth bar chart --}}
        <div class="col-lg-7">
            <div class="card chart-card shadow-sm h-100">
                <div class="card-body">
                    <div class="section-head">{{ $isAr ? 'نمو المتاجر — آخر 12 شهراً' : 'Store Growth — Last 12 Months' }}</div>
                    <canvas id="growthChart" height="120"></canvas>
                </div>
            </div>
        </div>

        {{-- Status donut --}}
        <div class="col-lg-5">
            <div class="card chart-card shadow-sm h-100">
                <div class="card-body">
                    <div class="section-head">{{ $isAr ? 'توزيع حالات الاشتراك' : 'Subscription Status Breakdown' }}</div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="flex:0 0 160px;height:160px">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="flex-fill">
                            @foreach($statusCounts as $status => $count)
                            @if($count > 0)
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $statusColors[$status] }};display:inline-block;flex-shrink:0"></span>
                                    <span class="small">{{ $statusLabels[$status] }}</span>
                                </div>
                                <span class="fw-bold small">{{ $count }}</span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Bottom row ── --}}
    <div class="row g-3">

        {{-- Plan distribution + MRR breakdown --}}
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="section-head">{{ $isAr ? 'توزيع الخطط' : 'Plan Distribution' }}</div>
                    @foreach($planModels as $plan)
                    @php $cnt = $tenants->where('plan',$plan->id)->count(); $pct = $total>0?round($cnt/$total*100):0; @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold small text-capitalize">{{ $plan->name }}</span>
                            <span class="small text-muted">{{ $cnt }} &middot; ${{ number_format(($activeByPlan[$plan->id] ?? 0) * $plan->monthly_price) }}/mo</span>
                        </div>
                        <div class="progress" style="height:6px;border-radius:3px">
                            <div class="progress-bar" style="width:{{ $pct }}%;background:{{ $planColors[$plan->id] ?? '#6b7280' }}"></div>
                        </div>
                    </div>
                    @endforeach

                    <hr class="my-3">
                    <div class="section-head">{{ $isAr ? 'إجراءات سريعة' : 'Quick Actions' }}</div>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('admin.tenants') }}" class="quick-btn btn btn-outline-primary">
                            <i class="fas fa-layer-group"></i>
                            {{ $isAr ? 'كل الاشتراكات' : 'All Subscriptions' }}
                        </a>
                        <a href="{{ route('admin.plans') }}" class="quick-btn btn btn-outline-info">
                            <i class="fas fa-tags"></i>
                            {{ $isAr ? 'الخطط والأسعار' : 'Plans & Pricing' }}
                        </a>
                        <button class="quick-btn btn btn-outline-success" data-action="open-new-tenant">
                            <i class="fas fa-plus"></i>
                            {{ $isAr ? 'متجر جديد' : 'New Store' }}
                        </button>
                        <a href="{{ route('welcome') }}" target="_blank" class="quick-btn btn btn-outline-secondary">
                            <i class="fas fa-globe"></i>
                            {{ $isAr ? 'الصفحة الرئيسية' : 'Landing Page' }}
                        </a>
                        <a href="{{ route('roles') }}" class="quick-btn btn btn-outline-secondary">
                            <i class="fas fa-user-shield"></i>
                            {{ $isAr ? 'الأدوار والصلاحيات' : 'Roles & Permissions' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Expiring soon --}}
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    {{ $isAr ? 'اشتراكات تنتهي قريباً' : 'Expiring Soon (30 days)' }}
                    @if($expiringSoon->count() > 0)
                        <span class="badge bg-warning text-dark ms-auto">{{ $expiringSoon->count() }}</span>
                    @endif
                </div>
                <div class="card-body py-2 px-3" style="overflow-y:auto;max-height:340px">
                    @forelse($expiringSoon as $t)
                    @php
                        $endDate = $t->subscription_ends_at ?? $t->trial_ends_at;
                        $daysLeft = (int) now()->diffInDays($endDate, false);
                        $urgency  = $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'secondary');
                    @endphp
                    <div class="expire-item">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate small">{{ $t->name }}</div>
                            <div class="text-muted" style="font-size:.72rem">
                                <code>{{ $t->code }}</code> &middot;
                                <span class="badge bg-{{ $t->plan==='enterprise'?'primary':($t->plan==='pro'?'info':'secondary') }} status-pill">{{ ucfirst($t->plan) }}</span>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="badge bg-{{ $urgency }} status-pill">
                                {{ $daysLeft }}d
                            </div>
                            <div class="text-muted" style="font-size:.7rem;margin-top:.1rem">{{ $endDate->format('m/d') }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-2x text-success opacity-50 mb-2 d-block"></i>
                        <small>{{ $isAr ? 'لا اشتراكات تنتهي قريباً' : 'No subscriptions expiring soon' }}</small>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent signups --}}
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
                    <i class="fas fa-star text-primary"></i>
                    {{ $isAr ? 'أحدث المتاجر' : 'Recent Sign-ups' }}
                </div>
                <div class="card-body py-2 px-3">
                    @foreach($recentTenants as $t)
                    @php
                        $sub = $t->subscription_status ?? 'trial';
                        $bc  = $t->subscriptionBadgeClass();
                    @endphp
                    <div class="tenant-row-mini d-flex align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold text-white"
                                style="width:34px;height:34px;font-size:.8rem;background:{{ $planColors[$t->plan??'basic'] }}">
                                {{ strtoupper(substr($t->name,0,2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate small">{{ $t->name }}</div>
                                <div class="text-muted" style="font-size:.7rem"><code>{{ $t->code }}</code></div>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <span class="badge bg-{{ $bc }} status-pill">{{ $t->subscriptionLabel() }}</span>
                            <div class="text-muted" style="font-size:.7rem;margin-top:.15rem">
                                {{ $t->created_at?->format('d M') }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="card-footer bg-white border-0 pt-0">
                    <a href="{{ route('admin.tenants') }}" class="btn btn-sm btn-outline-primary w-100">
                        {{ $isAr ? 'عرض الكل' : 'View All' }}
                        <i class="fas fa-arrow-left ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── New Tenant Quick Modal ── --}}
<div class="modal fade" id="cpanelTenantModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $isAr ? 'متجر جديد' : 'New Store' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="cpAlertBox" class="alert d-none"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">{{ __('pos.tenant_name') }} *</label>
                    <input type="text" class="form-control form-control-sm" id="cpName" placeholder="{{ __('pos.tenant_name_placeholder') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">{{ __('pos.tenant_code') }} *</label>
                    <input type="text" class="form-control form-control-sm" id="cpCode"
                        placeholder="{{ __('pos.tenant_code_placeholder') }}" style="direction:ltr">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">{{ __('pos.plan') }}</label>
                    <select class="form-select form-select-sm" id="cpPlan">
                        <option value="basic">Basic — $49/mo</option>
                        <option value="pro">Pro — $99/mo</option>
                        <option value="enterprise">Enterprise — $199/mo</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold small">{{ __('pos.trial_days') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" id="cpTrialDays" value="14" min="0" max="365">
                        <span class="input-group-text">{{ __('pos.days') }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="cpSaveBtn" data-action="save-cp-tenant">
                    <span id="cpSaveText">{{ $isAr ? 'إنشاء' : 'Create' }}</span>
                    <span class="spinner-border spinner-border-sm d-none ms-1" id="cpSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script @nonce>
const STORE_URL = "{{ route('admin.tenants.store') }}";
const isAr      = {{ $isAr ? 'true' : 'false' }};
let cpModal;

document.addEventListener('DOMContentLoaded', () => {
    cpModal = new bootstrap.Modal(document.getElementById('cpanelTenantModal'));
    buildGrowthChart();
    buildStatusChart();
});

document.addEventListener('click', e => {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    const action = el.dataset.action;
    if (action === 'refresh-page')    location.reload();
    if (action === 'open-new-tenant') openNewTenantModal();
    if (action === 'save-cp-tenant')  saveCpTenant();
});

// ── Charts ───────────────────────────────────────────────────────────────
function buildGrowthChart() {
    const labels = {!! json_encode(array_column($monthlyGrowth, 'label')) !!};
    const data   = {!! json_encode(array_column($monthlyGrowth, 'count')) !!};

    new Chart(document.getElementById('growthChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: isAr ? 'متاجر جديدة' : 'New Stores',
                data,
                backgroundColor: '#2563eb',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f3f4f6' } },
                x: { grid: { display: false } }
            }
        }
    });
}

function buildStatusChart() {
    const statusCounts = @json($statusCounts);
    const labels       = @json($statusLabels);
    const colors       = @json($statusColors);

    const filtered = Object.entries(statusCounts).filter(([,v]) => v > 0);
    if (!filtered.length) return;

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels:   filtered.map(([k]) => labels[k]),
            datasets: [{ data: filtered.map(([,v]) => v), backgroundColor: filtered.map(([k]) => colors[k]), borderWidth: 0, hoverOffset: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: true, cutout: '68%',
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}` } } }
        }
    });
}

// ── Quick create tenant ───────────────────────────────────────────────────
function openNewTenantModal() { cpModal.show(); setTimeout(() => document.getElementById('cpName').focus(), 300); }

async function saveCpTenant() {
    const name      = document.getElementById('cpName').value.trim();
    const code      = document.getElementById('cpCode').value.trim().toLowerCase();
    const plan      = document.getElementById('cpPlan').value;
    const trialDays = document.getElementById('cpTrialDays').value;

    if (!name || !code) {
        showCpAlert("{{ __('pos.fill_required_fields') }}", 'danger'); return;
    }

    document.getElementById('cpSaveBtn').disabled = true;
    document.getElementById('cpSpinner').classList.remove('d-none');

    try {
        const res = await apiCall(STORE_URL, 'POST', { name, code, plan, trial_days: trialDays });
        if (!res.success) { showCpAlert(res.message, 'danger'); return; }
        cpModal.hide();
        showToast(res.message, 'success');
        setTimeout(() => location.reload(), 800);
    } catch { showCpAlert("{{ __('pos.server_error') }}", 'danger'); }
    finally {
        document.getElementById('cpSaveBtn').disabled = false;
        document.getElementById('cpSpinner').classList.add('d-none');
    }
}

function showCpAlert(msg, type) {
    const el = document.getElementById('cpAlertBox');
    el.className = `alert alert-${type} py-2 small`; el.textContent = msg; el.classList.remove('d-none');
}

</script>
@endpush
@endsection
