@extends('layouts.app')
@section('title', app()->getLocale() === 'ar' ? 'وسائل الدفع' : 'Payment Methods')
@section('page-title', app()->getLocale() === 'ar' ? 'وسائل الدفع — أرقام التحويل' : 'Payment Methods — Transfer Numbers')

@push('styles')
<style @nonce>
    .pa-card {
        border: 1.5px solid #e5e7eb;
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 1.25rem;
        background: #fff;
        transition: box-shadow .2s;
    }
    .pa-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.07); }

    .pa-card-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: .85rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .pa-method-icon {
        width: 40px; height: 40px; border-radius: .65rem;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; flex-shrink: 0;
    }
    .pa-body { padding: 1.25rem; }

    .field-label {
        font-size: .78rem; font-weight: 700; color: #64748b;
        margin-bottom: .3rem; display: block;
    }
    .save-bar {
        position: sticky; bottom: 0; z-index: 50;
        background: rgba(255,255,255,.95);
        backdrop-filter: blur(8px);
        border-top: 1px solid #e5e7eb;
        padding: .9rem 1.25rem;
        margin: 0 -1.5rem;
    }
    .preview-tag {
        font-size: .7rem; font-weight: 700; padding: .2rem .55rem;
        border-radius: .4rem; border: 1px solid currentColor; opacity: .8;
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <p class="text-muted small mb-0">
            {{ $isAr
                ? 'أدخل أرقام المحافظ والحسابات التي تريد أن يحوّل إليها عملاؤك عند تجديد الاشتراك.'
                : 'Enter the wallet and account numbers customers will use when renewing their subscription.' }}
            <br>
            <i class="fas fa-eye-slash me-1 text-warning"></i>
            <span class="text-warning fw-bold" style="font-size:.8rem">
                {{ $isAr ? 'الوسائل بدون رقم لن تظهر للعملاء.' : 'Methods without a number will not be shown to customers.' }}
            </span>
        </p>
    </div>
    <a href="{{ route('admin.cpanel') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-right me-1"></i>{{ $isAr ? 'لوحة التحكم' : 'cPanel' }}
    </a>
</div>

{{-- Skeleton shown until JS loads --}}
<div id="pa-skeleton">
    @foreach(range(1,4) as $_)
    <div class="pa-card">
        <div class="pa-card-header">
            <div class="d-flex align-items-center gap-2">
                <div style="width:40px;height:40px;border-radius:.65rem;background:#f1f5f9"></div>
                <div style="width:120px;height:14px;border-radius:.4rem;background:#f1f5f9"></div>
            </div>
        </div>
        <div class="pa-body">
            <div style="height:38px;border-radius:.5rem;background:#f8fafc"></div>
        </div>
    </div>
    @endforeach
</div>

{{-- Actual content rendered by JS --}}
<div id="pa-container" class="d-none"></div>

{{-- Save bar --}}
<div class="save-bar d-flex align-items-center justify-content-between gap-3 mt-4">
    <span class="text-muted small" id="pa-status-msg"></span>
    <button class="btn btn-success fw-bold px-4" id="pa-save-btn" disabled>
        <i class="fas fa-save me-2"></i>{{ $isAr ? 'حفظ التغييرات' : 'Save Changes' }}
    </button>
</div>

@push('scripts')
<script @nonce>
const isAr       = {{ $isAr ? 'true' : 'false' }};
const INDEX_URL  = "{{ route('admin.payment-accounts.index') }}";
const UPDATE_URL = id => `/admin/payment-accounts/${id}`;
const CSRF       = "{{ csrf_token() }}";

let accounts = [];
let dirty    = false;

// ── Load ───────────────────────────────────────────────────────────────────
async function loadAccounts() {
    try {
        const res  = await fetch(INDEX_URL, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        accounts   = data.accounts || [];
        render();
    } catch {
        document.getElementById('pa-skeleton').innerHTML =
            `<div class="alert alert-danger">${isAr ? 'تعذّر التحميل' : 'Failed to load'}</div>`;
    }
}

// ── Render ─────────────────────────────────────────────────────────────────
function render() {
    const skeleton  = document.getElementById('pa-skeleton');
    const container = document.getElementById('pa-container');

    const wa    = accounts.find(a => a.method === 'whatsapp');
    const other = accounts.filter(a => a.method !== 'whatsapp');

    const waHtml = wa ? `
    <div class="pa-card" data-pa-id="${wa.id}">
        <div class="pa-card-header" style="background:#f0fdf4;border-bottom-color:#bbf7d0">
            <div class="d-flex align-items-center gap-2">
                <div class="pa-method-icon" style="background:#dcfce7;color:#16a34a">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <div class="fw-bold" style="color:#16a34a">
                        ${isAr ? 'رقم واتساب للتواصل' : 'WhatsApp Contact Number'}
                    </div>
                    <div class="text-muted" style="font-size:.73rem">
                        ${isAr ? 'يظهر لعملاء صفحة تجديد الاشتراك للتواصل بعد الدفع' : 'Shown on renewal page so customers can contact you after payment'}
                    </div>
                </div>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input pa-active" type="checkbox" id="wa_active" ${wa.is_active ? 'checked' : ''}>
                <label class="form-check-label small fw-bold text-success" for="wa_active">
                    ${isAr ? 'مفعّل' : 'Active'}
                </label>
            </div>
        </div>
        <div class="pa-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="field-label">
                        <i class="fas fa-hashtag me-1"></i>
                        ${isAr ? 'رقم الواتساب (مع كود الدولة، بدون +)' : 'WhatsApp number (with country code, no +)'}
                    </label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold" style="background:#dcfce7;border-color:#86efac;color:#16a34a">
                            <i class="fab fa-whatsapp me-1"></i>+
                        </span>
                        <input type="text" class="form-control pa-account"
                               value="${wa.account_number || ''}"
                               placeholder="${isAr ? 'مثال: 201012345678' : 'e.g. 201012345678'}"
                               style="font-family:monospace;letter-spacing:.05em;border-color:#86efac">
                    </div>
                    <div class="form-text">${isAr ? 'مثال: 201012345678 (مصر) أو 966501234567 (السعودية)' : 'e.g. 201012345678 (Egypt) or 966501234567 (Saudi)'}</div>
                </div>
                <div class="col-md-4">
                    <label class="field-label">
                        <i class="fas fa-user me-1"></i>${isAr ? 'الاسم / الوصف' : 'Name / Label'}
                    </label>
                    <input type="text" class="form-control pa-name"
                           value="${wa.account_name || ''}"
                           placeholder="${isAr ? 'مثال: خدمة العملاء' : 'e.g. Customer Support'}">
                </div>
                <div class="col-md-3">
                    <label class="field-label">&nbsp;</label>
                    <div class="alert alert-success py-2 small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        ${isAr ? 'سيُفتح واتساب تلقائياً مع رسالة تفاصيل الدفع' : 'WhatsApp opens automatically with payment details message'}
                    </div>
                </div>
            </div>
            <input type="hidden" class="pa-notes" value="${wa.notes || ''}">
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 my-4">
        <hr class="flex-fill m-0">
        <span class="text-muted small fw-bold px-2">
            <i class="fas fa-wallet me-1"></i>${isAr ? 'حسابات التحويل' : 'Transfer Accounts'}
        </span>
        <hr class="flex-fill m-0">
    </div>
    ` : '';

    const cardsHtml = other.map(acc => `
    <div class="pa-card" data-pa-id="${acc.id}">
        <div class="pa-card-header">
            <div class="d-flex align-items-center gap-2">
                <div class="pa-method-icon" style="background:${acc.color}18;color:${acc.color}">
                    <i class="${acc.icon}"></i>
                </div>
                <div>
                    <span class="fw-bold" style="color:${acc.color}">${isAr ? acc.label_ar : acc.label_en}</span>
                    <span class="text-muted ms-2" style="font-size:.73rem">${isAr ? acc.label_en : acc.label_ar}</span>
                    ${acc.account_number
                        ? `<span class="preview-tag ms-2" style="color:${acc.color}">${isAr ? 'مُضاف ✓' : 'Set ✓'}</span>`
                        : `<span class="preview-tag ms-2" style="color:#94a3b8">${isAr ? 'غير مُضاف — لن يظهر' : 'Not set — hidden'}</span>`}
                </div>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input pa-active" type="checkbox"
                       id="pa_active_${acc.id}" ${acc.is_active ? 'checked' : ''}>
                <label class="form-check-label small text-muted" for="pa_active_${acc.id}">
                    ${isAr ? 'إظهار للعملاء' : 'Show to customers'}
                </label>
            </div>
        </div>
        <div class="pa-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="field-label">
                        <i class="fas fa-hashtag me-1"></i>
                        ${isAr ? 'رقم الحساب / المحفظة' : 'Account / Wallet Number'}
                        <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control pa-account"
                           value="${acc.account_number || ''}"
                           placeholder="${isAr ? 'أدخل الرقم هنا' : 'Enter number here'}"
                           style="font-family:monospace;letter-spacing:.05em;border-color:${acc.color}50">
                </div>
                <div class="col-md-4">
                    <label class="field-label">
                        <i class="fas fa-user me-1"></i>${isAr ? 'الاسم على الحساب' : 'Account Holder Name'}
                    </label>
                    <input type="text" class="form-control pa-name"
                           value="${acc.account_name || ''}"
                           placeholder="${isAr ? 'الاسم كما يظهر للعميل' : 'Name shown to customer'}"
                           style="border-color:${acc.color}50">
                </div>
                <div class="col-md-3">
                    <label class="field-label">
                        <i class="fas fa-sticky-note me-1"></i>${isAr ? 'ملاحظة / تعليمات إضافية' : 'Notes / Extra Instructions'}
                    </label>
                    <input type="text" class="form-control pa-notes"
                           value="${acc.notes || ''}"
                           placeholder="${isAr ? 'مثال: IBAN أو رقم فرع' : 'e.g. IBAN, branch number'}"
                           style="border-color:${acc.color}50">
                </div>
            </div>
        </div>
    </div>
    `).join('');

    container.innerHTML = waHtml + cardsHtml;
    skeleton.classList.add('d-none');
    container.classList.remove('d-none');

    // Mark dirty on any input change
    container.addEventListener('input',  markDirty);
    container.addEventListener('change', markDirty);
}

function markDirty() {
    if (dirty) return;
    dirty = true;
    document.getElementById('pa-save-btn').disabled = false;
    document.getElementById('pa-status-msg').textContent =
        isAr ? 'توجد تغييرات غير محفوظة' : 'Unsaved changes';
    document.getElementById('pa-status-msg').className = 'text-warning small fw-bold';
}

// ── Save ───────────────────────────────────────────────────────────────────
document.getElementById('pa-save-btn').addEventListener('click', async () => {
    const btn = document.getElementById('pa-save-btn');
    btn.disabled = true;
    const origHtml = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${isAr ? 'جارٍ الحفظ...' : 'Saving...'}`;

    const rows = document.querySelectorAll('[data-pa-id]');
    const promises = Array.from(rows).map(row => {
        const id      = row.dataset.paId;
        const account = row.querySelector('.pa-account').value.trim();
        const name    = row.querySelector('.pa-name').value.trim();
        const notes   = row.querySelector('.pa-notes').value.trim();
        const active  = row.querySelector('.pa-active').checked;

        return fetch(UPDATE_URL(id), {
            method : 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
            body   : JSON.stringify({
                account_number: account || null,
                account_name  : name   || null,
                notes         : notes  || null,
                is_active     : active,
            }),
        });
    });

    try {
        await Promise.all(promises);
        dirty = false;
        document.getElementById('pa-status-msg').textContent =
            isAr ? '✓ تم الحفظ بنجاح' : '✓ Saved successfully';
        document.getElementById('pa-status-msg').className = 'text-success small fw-bold';
        showToast(isAr ? 'تم حفظ وسائل الدفع بنجاح' : 'Payment methods saved', 'success');
        // Refresh to update the "Set ✓ / Not set" badges
        setTimeout(() => loadAccounts(), 600);
    } catch {
        showToast(isAr ? 'حدث خطأ أثناء الحفظ' : 'Error saving', 'danger');
        btn.disabled  = false;
        btn.innerHTML = origHtml;
    }
});

// Warn before leaving with unsaved changes
window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
});

loadAccounts();
</script>
@endpush
@endsection
