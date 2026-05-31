@extends('layouts.app')
@section('title', app()->getLocale() === 'ar' ? 'تجديد الاشتراك' : 'Renew Subscription')

@push('styles')
<style @nonce>
    .sub-hero {
        background: linear-gradient(135deg, #1a3a6b 0%, #10284d 100%);
        border-radius: 1.25rem; padding: 2.5rem; color: #fff;
        margin-bottom: 2rem; position: relative; overflow: hidden;
    }
    .sub-hero::before {
        content: ''; position: absolute; inset: 0;
        background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    .sub-hero-content { position: relative; z-index: 1; }
    .status-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .35rem .9rem; border-radius: 2rem; font-size: .8rem; font-weight: 700; margin-bottom: 1rem;
    }
    .chip-expired   { background: rgba(239,68,68,.2);  color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }
    .chip-trial     { background: rgba(245,158,11,.2); color: #fcd34d; border: 1px solid rgba(245,158,11,.3); }
    .chip-suspended { background: rgba(156,163,175,.2);color: #d1d5db; border: 1px solid rgba(156,163,175,.3); }
    .chip-cancelled { background: rgba(107,114,128,.2);color: #9ca3af; border: 1px solid rgba(107,114,128,.3); }

    /* Plan cards */
    .plan-card {
        border: 2px solid #e5e7eb; border-radius: 1.25rem;
        padding: 2rem; height: 100%; position: relative;
        transition: all .25s; background: #fff;
    }
    .plan-card:hover   { border-color: #2563eb; transform: translateY(-4px); box-shadow: 0 20px 50px rgba(37,99,235,.12); }
    .plan-card.popular { border-color: #2563eb; background: #f0f7ff; box-shadow: 0 12px 40px rgba(37,99,235,.15); }
    .popular-tag {
        position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
        background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff;
        padding: .3rem 1.25rem; border-radius: 2rem; font-size: .75rem; font-weight: 800;
        white-space: nowrap; box-shadow: 0 4px 12px rgba(37,99,235,.4);
    }
    .plan-price { font-size: 2.8rem; font-weight: 900; line-height: 1; letter-spacing: -.03em; }
    .plan-feature { display: flex; align-items: center; gap: .5rem; padding: .4rem 0; font-size: .875rem; border-bottom: 1px solid #f1f5f9; }
    .plan-feature:last-child { border-bottom: none; }

    /* Billing toggle */
    .billing-btn { background: #f1f5f9; border: 1.5px solid #e2e8f0; color: #475569; }
    .billing-btn.active { background: #eff6ff; border-color: #2563eb; color: #1d4ed8; }

    /* Payment method grid */
    .methods-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; margin-bottom: .85rem; }
    .method-btn {
        display: flex; flex-direction: column; align-items: center; gap: .25rem;
        padding: .6rem .3rem; border-radius: .65rem; border: 1.5px solid #e2e8f0;
        background: #f8fafc; cursor: pointer; font-size: .68rem; font-weight: 700;
        color: #64748b; transition: all .18s; line-height: 1.2; text-align: center;
    }
    .method-btn i { font-size: 1.15rem; }
    .method-btn:hover { border-color: #94a3b8; background: #f1f5f9; }
    .method-btn.selected { border-width: 2px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.08); }

    .pay-btn { transition: opacity .2s, transform .2s, box-shadow .2s; }
    .pay-btn:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(0,0,0,.18); }
    .pay-btn:disabled { opacity: .55; cursor: not-allowed; }

    /* Contact */
    .contact-card { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1.5px solid #bbf7d0; border-radius: 1.25rem; padding: 2rem; }
    .contact-method { display: flex; align-items: center; gap: 1rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .875rem; padding: 1rem 1.25rem; margin-bottom: .75rem; text-decoration: none; color: inherit; transition: all .2s; }
    .contact-method:hover { border-color: #2563eb; box-shadow: 0 4px 16px rgba(37,99,235,.1); color: inherit; }
    .contact-icon { width: 44px; height: 44px; border-radius: .75rem; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .steps-bar { display: flex; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 1rem; overflow: hidden; margin-bottom: 2rem; }
    .step-item { flex: 1; padding: .875rem 1rem; text-align: center; font-size: .8rem; font-weight: 600; color: #94a3b8; position: relative; }
    .step-item.active { background: #eff6ff; color: #2563eb; }
    .step-item.done   { background: #f0fdf4; color: #059669; }
    .step-item::after { content: '›'; position: absolute; left: 0; top: 50%; transform: translateY(-50%); color: #cbd5e1; font-size: 1.2rem; }
    .step-item:first-child::after { display: none; }

    /* Pay modal */
    .pay-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 1055; align-items: center; justify-content: center; }
    .pay-modal-backdrop.open { display: flex; }
    .pay-modal { background: #fff; border-radius: 1.5rem; padding: 2rem 1.75rem; max-width: 440px; width: 93%; box-shadow: 0 30px 70px rgba(0,0,0,.22); text-align: center; }
    .modal-method-icon { font-size: 2.75rem; margin-bottom: .75rem; }
    .account-box {
        background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: .875rem;
        padding: 1rem 1.25rem; margin: .85rem 0; font-size: 1.5rem; font-weight: 900;
        letter-spacing: .08em; font-family: monospace;
    }
    .info-row { display: flex; gap: .5rem; margin-bottom: .75rem; }
    .info-chip { flex: 1; background: #f1f5f9; border-radius: .65rem; padding: .5rem .75rem; text-align: center; }
    .info-chip .chip-label { font-size: .68rem; color: #94a3b8; display: block; }
    .info-chip .chip-value { font-size: .88rem; font-weight: 700; }

    /* No methods notice */
    .no-methods { background: #fffbeb; border: 1.5px solid #fcd34d; border-radius: 1rem; padding: 1.25rem; margin-bottom: 1rem; display: flex; align-items: flex-start; gap: .75rem; }
</style>
@endpush

@section('content')
@php
    $isAr      = app()->getLocale() === 'ar';
    $status    = $tenant->subscription_status ?? 'expired';
    $plans     ??= collect();
    $methods   ??= collect();
    $popularId = 'pro';
    $whatsapp  ??= '201000000000';
@endphp

{{-- Steps bar --}}
<div class="steps-bar">
    <div class="step-item done"><i class="fas fa-check-circle me-1"></i>{{ $isAr ? 'تسجيل الدخول' : 'Logged In' }}</div>
    <div class="step-item active"><i class="fas fa-tags me-1"></i>{{ $isAr ? 'اختر خطتك' : 'Choose Plan' }}</div>
    <div class="step-item"><i class="fas fa-wallet me-1"></i>{{ $isAr ? 'الدفع' : 'Payment' }}</div>
    <div class="step-item"><i class="fas fa-rocket me-1"></i>{{ $isAr ? 'استمر في العمل' : 'Get Back to Work' }}</div>
</div>

{{-- Hero --}}
<div class="sub-hero mb-4">
    <div class="sub-hero-content">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                @if($status === 'expired')
                    <span class="status-chip chip-expired"><i class="fas fa-clock"></i>{{ $isAr ? 'انتهى الاشتراك' : 'Subscription Expired' }}</span>
                @elseif($status === 'trial')
                    <span class="status-chip chip-trial"><i class="fas fa-hourglass-half"></i>{{ $isAr ? 'في فترة التجربة' : 'Trial Period' }}</span>
                @elseif($status === 'suspended')
                    <span class="status-chip chip-suspended"><i class="fas fa-pause-circle"></i>{{ $isAr ? 'الحساب موقوف' : 'Account Suspended' }}</span>
                @else
                    <span class="status-chip chip-cancelled"><i class="fas fa-ban"></i>{{ $isAr ? 'الاشتراك ملغى' : 'Subscription Cancelled' }}</span>
                @endif
                <h2 class="mb-1" style="font-size:1.75rem;font-weight:900">
                    {{ $isAr ? 'جدّد اشتراكك لمتابعة العمل' : 'Renew Your Subscription to Continue' }}
                </h2>
                <p class="mb-0" style="opacity:.75;font-size:.95rem">
                    {{ $isAr
                        ? 'اختر خطتك، ادفع بأي وسيلة متاحة، ثم تواصل معنا لتفعيل الاشتراك.'
                        : 'Choose your plan, pay with any available method, then contact us to activate.' }}
                </p>
            </div>
            <div class="text-end text-white-50" style="font-size:.82rem;min-width:160px">
                <div class="fw-bold text-white" style="font-size:1rem">{{ $tenant->name }}</div>
                <div>{{ $isAr ? 'الخطة الحالية:' : 'Current plan:' }} <strong class="text-white">{{ strtoupper($tenant->plan ?? 'basic') }}</strong></div>
                @if($tenant->subscription_ends_at ?? $tenant->trial_ends_at)
                <div class="mt-1">{{ $isAr ? 'انتهت في:' : 'Ended:' }}
                    <strong class="text-warning">{{ ($tenant->subscription_ends_at ?? $tenant->trial_ends_at)?->format('d M Y') }}</strong>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Plans --}}
<h5 class="fw-bold mb-3"><i class="fas fa-tags me-2 text-primary"></i>{{ $isAr ? 'الخطط المتاحة' : 'Available Plans' }}</h5>
<div class="row g-4 mb-4">
@forelse($plans as $plan)
@php
    $isPopular = $plan->id === $popularId;
    $clrs      = ['basic'=>'#6b7280','pro'=>'#2563eb','enterprise'=>'#7c3aed'];
    $clr       = $clrs[$plan->id] ?? '#374151';
    $feats = [
        'basic'      => ['نقطة بيع','إدارة المخزون','تقارير أساسية','دعم بريد إلكتروني'],
        'pro'        => ['كل مميزات Basic','مستودعات متعددة','محاسبة متكاملة','تقارير متقدمة','إدارة عملاء','العروض والترقيات','دعم أولوية'],
        'enterprise' => ['كل مميزات Pro','مستخدمون غير محدودون','API كامل','تقارير مخصصة','مدير حساب مخصص','SLA 99.9%'],
    ];
    $planFeats = $feats[$plan->id] ?? ($plan->features ?? []);
@endphp
<div class="col-md-6 col-lg-4">
<div class="plan-card {{ $isPopular ? 'popular' : '' }}" data-plan="{{ $plan->id }}">
    @if($isPopular)<span class="popular-tag">⭐ {{ $isAr ? 'الأكثر شيوعاً' : 'Most Popular' }}</span>@endif

    {{-- Header --}}
    <div class="mb-3">
        <span class="badge mb-2" style="background:{{ $clr }};color:#fff;font-size:.72rem;padding:.35rem .8rem;border-radius:.5rem">{{ strtoupper($plan->id) }}</span>
        <div class="fw-bold fs-6 mb-1">{{ $plan->name }}</div>
        <div class="d-flex align-items-end gap-1">
            <span class="plan-price" style="color:{{ $clr }}">{{ number_format($plan->monthly_price, 0) }}</span>
            <span class="text-muted mb-2" style="font-size:.85rem"> {{ $isAr ? 'ج.م / شهر' : 'EGP / mo' }}</span>
        </div>
        @if($plan->annual_price)
        <div class="text-muted small">
            {{ $isAr ? 'أو' : 'or' }} {{ number_format($plan->annual_price, 0) }} {{ $isAr ? 'ج.م / سنة' : 'EGP / yr' }}
            @php $save = $plan->annualSavings(); @endphp
            @if($save > 0)<span class="badge bg-success ms-1" style="font-size:.65rem">{{ $isAr ? 'وفّر' : 'Save' }} {{ number_format($save,0) }}</span>@endif
        </div>
        @endif
        <div class="text-muted small mt-1">
            @if($plan->max_users) {{ $isAr ? 'حتى' : 'Up to' }} {{ $plan->max_users }} {{ $isAr ? 'مستخدم' : 'users' }}
            @else {{ $isAr ? 'مستخدمون غير محدودون' : 'Unlimited users' }}@endif
        </div>
    </div>

    {{-- Features --}}
    <div class="mb-3">
        @foreach($planFeats as $feat)
        <div class="plan-feature">
            <i class="fas fa-check-circle fa-sm text-success"></i><span>{{ $feat }}</span>
        </div>
        @endforeach
    </div>

    {{-- Billing toggle --}}
    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-sm flex-fill fw-bold billing-btn active"
                data-action="billing-toggle" data-plan="{{ $plan->id }}" data-period="monthly"
                style="border-radius:.5rem;font-size:.8rem">
            {{ $isAr ? 'شهري' : 'Monthly' }}
            <span class="ms-1 fw-bold" style="color:{{ $clr }}">{{ number_format($plan->monthly_price,0) }}</span>
        </button>
        @if($plan->annual_price)
        <button class="btn btn-sm flex-fill fw-bold billing-btn"
                data-action="billing-toggle" data-plan="{{ $plan->id }}" data-period="annual"
                style="border-radius:.5rem;font-size:.8rem">
            {{ $isAr ? 'سنوي' : 'Annual' }}
            <span class="ms-1 fw-bold" style="color:{{ $clr }}">{{ number_format($plan->annual_price,0) }}</span>
            @php $save = $plan->annualSavings(); @endphp
            @if($save > 0)<span class="badge bg-success ms-1" style="font-size:.6rem">-{{ round($save/($plan->monthly_price*12)*100) }}%</span>@endif
        </button>
        @endif
    </div>

    {{-- Payment methods --}}
    @if($methods->isEmpty())
    <div class="no-methods">
        <i class="fas fa-exclamation-triangle text-warning mt-1"></i>
        <div class="small">{{ $isAr ? 'لم تُضف وسائل دفع بعد. تواصل مع الدعم لإتمام الاشتراك.' : 'No payment methods configured yet. Contact support to subscribe.' }}</div>
    </div>
    @else
    <div class="methods-grid" data-plan-methods="{{ $plan->id }}">
        @foreach($methods as $method)
        <button class="method-btn {{ $loop->first ? 'selected' : '' }}"
                data-method="{{ $method->method }}"
                data-account="{{ $method->account_number }}"
                data-name="{{ $method->account_name }}"
                data-notes="{{ $method->notes }}"
                data-label-ar="{{ $method->label_ar }}"
                data-label-en="{{ $method->label_en }}"
                data-icon="{{ $method->icon }}"
                data-color="{{ $method->color }}"
                style="{{ $loop->first ? "border-color:{$method->color};color:{$method->color}" : '' }}">
            <i class="{{ $method->icon }}" style="{{ $loop->first ? "color:{$method->color}" : '' }}"></i>
            <span>{{ $isAr ? $method->label_ar : $method->label_en }}</span>
        </button>
        @endforeach
    </div>

    {{-- Pay button --}}
    @php $first = $methods->first(); @endphp
    <button class="btn w-100 fw-bold py-2 pay-btn"
            data-action="pay-now"
            data-plan-id="{{ $plan->id }}"
            data-plan-name="{{ $plan->name }}"
            data-monthly="{{ $plan->monthly_price }}"
            data-annual="{{ $plan->annual_price ?? 0 }}"
            data-period="monthly"
            data-method="{{ $first?->method }}"
            data-account="{{ $first?->account_number }}"
            data-account-name="{{ $first?->account_name }}"
            data-notes="{{ $first?->notes }}"
            data-label-ar="{{ $first?->label_ar }}"
            data-label-en="{{ $first?->label_en }}"
            data-icon="{{ $first?->icon }}"
            data-color="{{ $first?->color }}"
            style="background:{{ $first?->color ?? '#374151' }};color:#fff;border:none;border-radius:.75rem;font-size:.92rem">
        <i class="{{ $first?->icon ?? 'fas fa-wallet' }} me-2"></i>
        {{ $isAr ? ('ادفع عبر ' . ($first?->label_ar ?? '')) : ('Pay with ' . ($first?->label_en ?? '')) }}
    </button>
    @endif

    {{-- Contact fallback --}}
    <a href="#contact" class="btn w-100 fw-bold py-2 mt-2"
       style="background:#f8fafc;color:#374151;border:1.5px solid #e5e7eb;border-radius:.75rem;font-size:.85rem"
       data-action="select-plan" data-id="{{ $plan->id }}" data-name="{{ $plan->name }}" data-price="{{ $plan->monthly_price }}">
        <i class="fas fa-headset me-1"></i>{{ $isAr ? 'تواصل مع الدعم' : 'Contact Support' }}
    </a>
</div>
</div>
@empty
<div class="col-12 text-center text-muted py-4">
    <i class="fas fa-tags fa-2x mb-2 d-block opacity-25"></i>
    {{ $isAr ? 'لا توجد خطط متاحة حالياً' : 'No plans available at the moment' }}
</div>
@endforelse
</div>

{{-- Contact section --}}
<div id="contact" class="contact-card mt-2">
    <div class="row align-items-center g-4">
        <div class="col-md-5">
            <h5 class="fw-bold mb-1"><i class="fas fa-headset me-2 text-success"></i>{{ $isAr ? 'تواصل معنا' : 'Contact Us' }}</h5>
            <p class="text-muted small mb-3">
                {{ $isAr ? 'بعد تحويل المبلغ، أرسل لقطة شاشة وسنفعّل اشتراكك فوراً.' : 'After transferring, send a screenshot and we will activate your subscription immediately.' }}
            </p>
            <div id="selectedPlanAlert" class="alert alert-primary py-2 small d-none">
                <i class="fas fa-check-circle me-1"></i><span id="selectedPlanText"></span>
            </div>
        </div>
        <div class="col-md-7">
            <a href="https://wa.me/{{ $whatsapp }}?text={{ urlencode($isAr ? "مرحباً، أريد تجديد اشتراك متجر {$tenant->name}" : "Hello, I want to renew subscription for {$tenant->name}") }}"
               class="contact-method" target="_blank">
                <div class="contact-icon" style="background:#dcfce7;color:#16a34a"><i class="fab fa-whatsapp"></i></div>
                <div>
                    <div class="fw-bold small">{{ $isAr ? 'واتساب' : 'WhatsApp' }}</div>
                    <div class="text-muted" style="font-size:.78rem">{{ $isAr ? 'أرسل صورة التحويل وسنفعّل اشتراكك' : 'Send transfer screenshot to activate' }}</div>
                </div>
                <i class="fas fa-arrow-left text-muted ms-auto" style="font-size:.8rem"></i>
            </a>
        </div>
    </div>
</div>

<div class="text-center mt-4">
    <form action="{{ route('logout') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-link text-muted small">
            <i class="fas fa-sign-out-alt me-1"></i>{{ $isAr ? 'تسجيل الخروج' : 'Sign Out' }}
        </button>
    </form>
</div>

{{-- ── Pay Modal ───────────────────────────────────────────────────────── --}}
<div class="pay-modal-backdrop" id="payModal">
    <div class="pay-modal">
        <div class="modal-method-icon" id="mdIcon">💳</div>
        <h5 class="fw-bold mb-1" id="mdTitle">—</h5>
        <p class="text-muted small mb-1" id="mdSub">{{ $isAr ? 'حوّل المبلغ إلى:' : 'Transfer the amount to:' }}</p>
        <div class="account-box" id="mdAccount">—</div>
        <div class="info-row">
            <div class="info-chip">
                <span class="chip-label">{{ $isAr ? 'الاسم' : 'Name' }}</span>
                <span class="chip-value" id="mdName">—</span>
            </div>
            <div class="info-chip">
                <span class="chip-label">{{ $isAr ? 'المبلغ' : 'Amount' }}</span>
                <span class="chip-value" id="mdAmount">—</span>
            </div>
            <div class="info-chip">
                <span class="chip-label">{{ $isAr ? 'المرجع' : 'Ref' }}</span>
                <span class="chip-value" id="mdRef" style="font-size:.68rem;font-family:monospace">—</span>
            </div>
        </div>
        <p class="text-muted small mb-0" id="mdNotes"></p>
        <p class="text-muted small mt-2 mb-3">
            <i class="fas fa-info-circle text-primary me-1"></i>
            {{ $isAr
                ? 'بعد التحويل، تواصل معنا عبر واتساب مع صورة التحويل وسيُفعَّل اشتراكك خلال ساعات.'
                : 'After transfer, contact us via WhatsApp with a screenshot and your subscription activates within hours.' }}
        </p>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary flex-fill fw-bold" id="mdCopyBtn">
                <i class="fas fa-copy me-1"></i>{{ $isAr ? 'نسخ الرقم' : 'Copy Number' }}
            </button>
            <a href="#" class="btn btn-success flex-fill fw-bold" id="mdWhatsappBtn" target="_blank">
                <i class="fab fa-whatsapp me-1"></i>{{ $isAr ? 'واتساب' : 'WhatsApp' }}
            </a>
        </div>
        <button class="btn btn-link text-muted small mt-2 w-100"
                onclick="document.getElementById('payModal').classList.remove('open')">
            {{ $isAr ? 'إغلاق' : 'Close' }}
        </button>
    </div>
</div>

@push('scripts')
<script @nonce>
const isAr       = {{ $isAr ? 'true' : 'false' }};
const WA_NUMBER  = "{{ $whatsapp }}";
const TENANT_NAME = "{{ addslashes($tenant->name) }}";

// ── Billing toggle ─────────────────────────────────────────────────────────
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action="billing-toggle"]');
    if (!btn) return;
    const { plan, period } = btn.dataset;
    btn.closest('.plan-card').querySelectorAll('[data-action="billing-toggle"]')
       .forEach(b => b.classList.toggle('active', b.dataset.period === period));
    const payBtn = btn.closest('.plan-card').querySelector('[data-action="pay-now"]');
    if (payBtn) payBtn.dataset.period = period;
});

// ── Method selector ────────────────────────────────────────────────────────
document.addEventListener('click', e => {
    const btn = e.target.closest('.method-btn');
    if (!btn) return;
    const card = btn.closest('.plan-card');

    // Update selected state
    card.querySelectorAll('.method-btn').forEach(b => {
        b.classList.remove('selected');
        b.style.borderColor = '';
        b.style.color       = '';
        b.querySelector('i').style.color = '';
    });
    btn.classList.add('selected');
    btn.style.borderColor = btn.dataset.color;
    btn.style.color       = btn.dataset.color;
    btn.querySelector('i').style.color = btn.dataset.color;

    // Update pay button
    const payBtn = card.querySelector('[data-action="pay-now"]');
    if (!payBtn) return;
    payBtn.dataset.method      = btn.dataset.method;
    payBtn.dataset.account     = btn.dataset.account;
    payBtn.dataset.accountName = btn.dataset.name;
    payBtn.dataset.notes       = btn.dataset.notes;
    payBtn.dataset.labelAr     = btn.dataset.labelAr;
    payBtn.dataset.labelEn     = btn.dataset.labelEn;
    payBtn.dataset.icon        = btn.dataset.icon;
    payBtn.dataset.color       = btn.dataset.color;
    payBtn.style.background    = btn.dataset.color;
    payBtn.innerHTML = `<i class="${btn.dataset.icon} me-2"></i>${isAr ? 'ادفع عبر ' + btn.dataset.labelAr : 'Pay with ' + btn.dataset.labelEn}`;
});

// ── Pay now → open modal ───────────────────────────────────────────────────
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action="pay-now"]');
    if (!btn) return;

    const period  = btn.dataset.period  || 'monthly';
    const planId  = btn.dataset.planId;
    const planName = btn.dataset.planName;
    const amount  = period === 'annual' ? parseFloat(btn.dataset.annual) : parseFloat(btn.dataset.monthly);
    const label   = isAr ? btn.dataset.labelAr : btn.dataset.labelEn;
    const icon    = btn.dataset.icon    || 'fas fa-wallet';
    const color   = btn.dataset.color   || '#374151';
    const account = btn.dataset.account || '—';
    const name    = btn.dataset.accountName || '—';
    const notes   = btn.dataset.notes   || '';

    // Generate reference
    const ref = planId.toUpperCase() + '-' + (period === 'annual' ? '12M' : '1M') + '-' + Date.now().toString(36).toUpperCase().slice(-5);

    // Build WhatsApp message
    const waMsg = isAr
        ? `مرحباً، أريد تجديد اشتراك متجر ${TENANT_NAME}\nالخطة: ${planName} (${period === 'annual' ? 'سنوي' : 'شهري'})\nالمبلغ: ${amount.toLocaleString()} ج.م\nالمرجع: ${ref}`
        : `Hello, I want to renew ${TENANT_NAME} subscription\nPlan: ${planName} (${period})\nAmount: ${amount.toLocaleString()} EGP\nRef: ${ref}`;

    // Populate modal
    const iconMap = {
        'fas fa-bolt':       '⚡',
        'fas fa-mobile-alt': '📱',
        'fas fa-store-alt':  '🏪',
        'fas fa-university': '🏦',
        'fas fa-wallet':     '💳',
        'fas fa-credit-card':'💳',
    };
    document.getElementById('mdIcon').textContent    = iconMap[icon] || '💳';
    document.getElementById('mdTitle').textContent   = isAr ? 'ادفع عبر ' + label : 'Pay with ' + label;
    document.getElementById('mdAccount').textContent = account;
    document.getElementById('mdAccount').style.color = color;
    document.getElementById('mdName').textContent    = name;
    document.getElementById('mdAmount').textContent  = amount.toLocaleString() + (isAr ? ' ج.م' : ' EGP');
    document.getElementById('mdRef').textContent     = ref;
    document.getElementById('mdNotes').textContent   = notes;
    document.getElementById('mdNotes').style.display = notes ? 'block' : 'none';

    document.getElementById('mdCopyBtn').onclick = () => {
        navigator.clipboard.writeText(account).then(() =>
            showToast(isAr ? 'تم نسخ الرقم ✓' : 'Copied ✓', 'success'));
    };
    document.getElementById('mdWhatsappBtn').href =
        `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(waMsg)}`;

    document.getElementById('payModal').classList.add('open');
});

// Close modal on backdrop click
document.getElementById('payModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});

// ── Contact shortcut ───────────────────────────────────────────────────────
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action="select-plan"]');
    if (!btn) return;
    document.querySelectorAll('.plan-card').forEach(c => c.style.outline = '');
    btn.closest('.plan-card').style.outline = '3px solid #2563eb';
    const alert = document.getElementById('selectedPlanAlert');
    const text  = document.getElementById('selectedPlanText');
    text.textContent = isAr
        ? `اخترت خطة ${btn.dataset.name} — تواصل معنا وسنفعّل اشتراكك فوراً.`
        : `You selected ${btn.dataset.name} — contact us and we'll activate your subscription immediately.`;
    alert.classList.remove('d-none');
    document.getElementById('contact').scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
@endpush
@endsection
