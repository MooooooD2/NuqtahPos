@extends('layouts.app')
@section('title', __('pos.whatsapp'))
@section('page-title', __('pos.whatsapp'))

@push('styles')
<style @nonce>
    .stat-card { text-align: center; padding: 1.25rem; }
    .stat-card .stat-num { font-size: 2rem; font-weight: 700; }
    .msg-direction-in  { background: #e8f5e9; }
    .msg-direction-out { background: #f3f4f6; }
    [data-theme="dark"] .msg-direction-in  { background: #1a3a2a; }
    [data-theme="dark"] .msg-direction-out { background: #1e293b; }
    .wa-enabled-badge { font-size: .85rem; padding: .4em .8em; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- Status Banner --}}
    @php $waEnabled = config('whatsapp.enabled') && config('whatsapp.phone_number_id'); @endphp
    @if($waEnabled)
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
            <i class="fab fa-whatsapp fa-lg"></i>
            <span>{{ app()->getLocale() === 'ar' ? 'واتساب مفعّل ويعمل' : 'WhatsApp is enabled and active' }}</span>
            <span class="badge bg-success wa-enabled-badge ms-auto">
                {{ config('whatsapp.phone_number_id') }}
            </span>
        </div>
    @else
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="fab fa-whatsapp fa-lg"></i>
            <div>
                <strong>{{ app()->getLocale() === 'ar' ? 'واتساب غير مفعّل' : 'WhatsApp not enabled' }}</strong><br>
                <small>
                    {{ app()->getLocale() === 'ar'
                        ? 'اضبط WHATSAPP_ENABLED=true و WHATSAPP_PHONE_NUMBER_ID في ملف .env لتفعيل الخدمة.'
                        : 'Set WHATSAPP_ENABLED=true and WHATSAPP_PHONE_NUMBER_ID in your .env file to activate.' }}
                </small>
            </div>
        </div>
    @endif

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabStats">
                <i class="fas fa-chart-bar me-1"></i>{{ app()->getLocale() === 'ar' ? 'الإحصائيات' : 'Stats' }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabLogs" data-fn="loadLogs">
                <i class="fas fa-list me-1"></i>{{ app()->getLocale() === 'ar' ? 'السجلات' : 'Logs' }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabPromo">
                <i class="fas fa-bullhorn me-1"></i>{{ app()->getLocale() === 'ar' ? 'رسائل ترويجية' : 'Promotions' }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabReminders">
                <i class="fas fa-bell me-1"></i>{{ app()->getLocale() === 'ar' ? 'تذكيرات الديون' : 'Debt Reminders' }}
            </a>
        </li>
    </ul>

    <div class="tab-content pt-2">

        {{-- ── Stats ── --}}
        <div class="tab-pane fade show active" id="tabStats">
            <div class="row g-3 mb-4" id="statsRow">
                <div class="col-12 text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
        </div>

        {{-- ── Logs ── --}}
        <div class="tab-pane fade" id="tabLogs">
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <select class="form-select form-select-sm" style="max-width:150px" id="logStatusFilter"
                    data-on-change="loadLogs">
                    <option value="">{{ app()->getLocale() === 'ar' ? 'كل الحالات' : 'All statuses' }}</option>
                    <option value="queued">Queued</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="read">Read</option>
                    <option value="failed">Failed</option>
                </select>
                <select class="form-select form-select-sm" style="max-width:150px" id="logTypeFilter"
                    data-on-change="loadLogs">
                    <option value="">{{ app()->getLocale() === 'ar' ? 'كل الأنواع' : 'All types' }}</option>
                    <option value="invoice">Invoice</option>
                    <option value="debt_reminder">Debt Reminder</option>
                    <option value="promotion">Promotion</option>
                    <option value="daily_summary">Daily Summary</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="large_invoice">Large Invoice</option>
                </select>
                <input type="text" class="form-control form-control-sm" style="max-width:160px"
                    id="logPhoneFilter"
                    placeholder="{{ app()->getLocale() === 'ar' ? 'رقم الهاتف' : 'Phone number' }}"
                    data-on-change="loadLogs">
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ app()->getLocale() === 'ar' ? 'الرقم' : 'To' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'النوع' : 'Type' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الاتجاه' : 'Dir' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'التاريخ' : 'Date' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الخطأ' : 'Error' }}</th>
                        </tr>
                    </thead>
                    <tbody id="logsTbody">
                        <tr><td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-spinner fa-spin"></i>
                        </td></tr>
                    </tbody>
                </table>
            </div>
            <div id="logsPagination" class="d-flex justify-content-center mt-2 gap-2"></div>
        </div>

        {{-- ── Promotions ── --}}
        <div class="tab-pane fade" id="tabPromo">
            <div class="card" style="max-width:600px">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-bullhorn text-success me-2"></i>
                        {{ app()->getLocale() === 'ar' ? 'إرسال رسالة ترويجية' : 'Send Promotional Message' }}
                    </h6>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ app()->getLocale() === 'ar' ? 'نص الرسالة' : 'Message' }}
                            <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="promoMsg" rows="4" maxlength="1000"
                            placeholder="{{ app()->getLocale() === 'ar' ? 'اكتب نص الرسالة هنا...' : 'Write your message here...' }}"
                            data-on-input="updatePromoCount"></textarea>
                        <small class="text-muted"><span id="promoCount">0</span>/1000</small>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="promoVipOnly">
                        <label class="form-check-label" for="promoVipOnly">
                            {{ app()->getLocale() === 'ar' ? 'VIP فقط' : 'VIP customers only' }}
                        </label>
                    </div>
                    <button class="btn btn-success" id="promoBtn" data-fn="sendPromotion">
                        <i class="fab fa-whatsapp me-2"></i>
                        {{ app()->getLocale() === 'ar' ? 'إرسال للجميع' : 'Send to All' }}
                        <span id="promoSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                    </button>
                    <div id="promoResult" class="mt-3"></div>
                </div>
            </div>
        </div>

        {{-- ── Debt Reminders ── --}}
        <div class="tab-pane fade" id="tabReminders">
            <div class="card" style="max-width:500px">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-bell text-warning me-2"></i>
                        {{ app()->getLocale() === 'ar' ? 'تذكيرات الديون' : 'Debt Reminders' }}
                    </h6>
                    <p class="text-muted small mb-3">
                        {{ app()->getLocale() === 'ar'
                            ? 'إرسال رسائل تذكير تلقائية لجميع العملاء الذين لديهم رصيد مستحق ولديهم رقم هاتف.'
                            : 'Send automated reminder messages to all customers with an outstanding balance and a phone number.' }}
                    </p>
                    <button class="btn btn-warning text-dark" id="remindersBtn" data-fn="sendBulkReminders">
                        <i class="fas fa-paper-plane me-2"></i>
                        {{ app()->getLocale() === 'ar' ? 'إرسال تذكيرات للجميع' : 'Send All Reminders' }}
                        <span id="remindersSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                    </button>
                    <div id="remindersResult" class="mt-3"></div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
const WA_API = '{{ url("/api/whatsapp") }}';
let logsLoaded = false;

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── STATS ─────────────────────────────────────────────────────
async function loadStats() {
    const res  = await apiCall(`${WA_API}/stats`);
    const data = res.success ? (res.data ?? res) : {};
    const today = data.today || {};

    const cards = [
        { label: isAr ? 'إجمالي الرسائل' : 'Total Messages', num: data.total   ?? 0, color: 'primary' },
        { label: isAr ? 'مُرسَل اليوم'   : 'Sent Today',     num: today.sent   ?? 0, color: 'success' },
        { label: isAr ? 'مُستلَم اليوم'  : 'Inbound Today',  num: data.inbound ?? 0, color: 'info'    },
        { label: isAr ? 'فشل اليوم'      : 'Failed Today',   num: data.failed  ?? 0, color: 'danger'  },
    ];

    document.getElementById('statsRow').innerHTML = cards.map(c => `
    <div class="col-6 col-md-3">
      <div class="card stat-card border-${c.color} border-2">
        <div class="stat-num text-${c.color}">${c.num}</div>
        <div class="text-muted small">${c.label}</div>
      </div>
    </div>`).join('');
}

// ── LOGS ──────────────────────────────────────────────────────
window.loadLogs = async function(page) {
    if (typeof page !== 'number') page = 1;
    logsLoaded = true;

    const status = document.getElementById('logStatusFilter').value;
    const type   = document.getElementById('logTypeFilter').value;
    const phone  = document.getElementById('logPhoneFilter').value;

    const params = new URLSearchParams({ page });
    if (status) params.set('status', status);
    if (type)   params.set('type', type);
    if (phone)  params.set('phone', phone);

    const tbody = document.getElementById('logsTbody');
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i></td></tr>`;

    const res  = await apiCall(`${WA_API}/logs?${params.toString()}`);
    if (!res.success) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${res.message || 'Error'}</td></tr>`;
        return;
    }

    const data = res.data ?? {};
    renderLogs(data.data ?? []);
    renderLogsPagination(data);
};

const statusBadge = {
    queued:'secondary', sent:'primary', delivered:'success',
    read:'info', failed:'danger', pending:'warning text-dark'
};

function renderLogs(list) {
    const tbody = document.getElementById('logsTbody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">${isAr ? 'لا توجد سجلات' : 'No logs'}</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(m => `
    <tr class="${m.direction === 'inbound' ? 'msg-direction-in' : 'msg-direction-out'}">
      <td class="small">${esc(m.to_number)}</td>
      <td class="small">${esc(m.message_type ?? '-')}</td>
      <td><span class="badge bg-${statusBadge[m.status] ?? 'secondary'}">${m.status}</span></td>
      <td class="small">${m.direction === 'inbound'
          ? '<i class="fas fa-arrow-down text-success"></i>'
          : '<i class="fas fa-arrow-up text-primary"></i>'}</td>
      <td class="small">${m.created_at ? m.created_at.slice(0,16).replace('T',' ') : '-'}</td>
      <td class="small text-danger">${m.error_message ? esc(m.error_message.slice(0,60)) : ''}</td>
    </tr>`).join('');
}

function renderLogsPagination(meta) {
    const el = document.getElementById('logsPagination');
    if (!meta.last_page || meta.last_page <= 1) { el.innerHTML = ''; return; }

    let html = '';
    for (let i = Math.max(1, meta.current_page - 2); i <= Math.min(meta.last_page, meta.current_page + 2); i++) {
        html += `<button class="btn btn-sm ${i === meta.current_page ? 'btn-primary' : 'btn-outline-secondary'}"
            data-fn="loadLogs" data-args="[${i}]">${i}</button>`;
    }
    el.innerHTML = html;
}

// ── PROMOTIONS ────────────────────────────────────────────────
window.updatePromoCount = function(el) {
    document.getElementById('promoCount').textContent = el.value.length;
};

window.sendPromotion = async function() {
    const message = document.getElementById('promoMsg').value.trim();
    const vipOnly = document.getElementById('promoVipOnly').checked;

    if (!message) {
        showToast(isAr ? 'الرسالة مطلوبة' : 'Message is required', 'error');
        return;
    }

    const btn = document.getElementById('promoBtn');
    btn.disabled = true;
    document.getElementById('promoSpinner').classList.remove('d-none');
    document.getElementById('promoResult').innerHTML = '';

    const res = await apiCall(`${WA_API}/promotions`, 'POST', { message, vip_only: vipOnly });
    btn.disabled = false;
    document.getElementById('promoSpinner').classList.add('d-none');

    if (!res.success) {
        document.getElementById('promoResult').innerHTML =
            `<div class="alert alert-danger"><i class="fas fa-exclamation me-2"></i>${esc(res.message || 'Error')}</div>`;
        return;
    }

    document.getElementById('promoResult').innerHTML =
        `<div class="alert alert-success"><i class="fas fa-check me-2"></i>${esc(res.message || (isAr?'تم الإرسال':'Sent'))}</div>`;
    document.getElementById('promoMsg').value = '';
    document.getElementById('promoCount').textContent = '0';
};

// ── BULK REMINDERS ────────────────────────────────────────────
window.sendBulkReminders = async function() {
    if (!confirm(isAr
        ? 'إرسال تذكيرات لجميع العملاء المدينين؟'
        : 'Send reminders to all customers with outstanding balance?')) return;

    const btn = document.getElementById('remindersBtn');
    btn.disabled = true;
    document.getElementById('remindersSpinner').classList.remove('d-none');
    document.getElementById('remindersResult').innerHTML = '';

    const res = await apiCall(`${WA_API}/customers/bulk-reminders`, 'POST');
    btn.disabled = false;
    document.getElementById('remindersSpinner').classList.add('d-none');

    if (!res.success) {
        document.getElementById('remindersResult').innerHTML =
            `<div class="alert alert-danger"><i class="fas fa-exclamation me-2"></i>${esc(res.message || 'Error')}</div>`;
        return;
    }

    document.getElementById('remindersResult').innerHTML =
        `<div class="alert alert-success"><i class="fas fa-check me-2"></i>${esc(res.message || (isAr?'تم الإرسال':'Sent'))}</div>`;
};

loadStats();
</script>
@endpush
