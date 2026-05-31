@extends('layouts.app')
@section('title', __('pos.cash_register_reconciliation'))
@section('page-title', __('pos.cash_register_reconciliation'))

@section('content')

{{-- Open session panel --}}
<div id="openSessionPanel" style="display:none">
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card green text-center">
                <p class="mb-1 small opacity-75">{{ __('pos.cash_sales') }}</p>
                <h4 class="mb-0 fw-bold" id="liveCashSales">-</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card blue text-center">
                <p class="mb-1 small opacity-75">{{ __('pos.card_transfer_sales') }}</p>
                <h4 class="mb-0 fw-bold" id="liveOtherSales">-</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card orange text-center">
                <p class="mb-1 small opacity-75">{{ __('pos.total_sales') }}</p>
                <h4 class="mb-0 fw-bold" id="liveTotalSales">-</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card red text-center">
                <p class="mb-1 small opacity-75">{{ __('pos.returns') }}</p>
                <h4 class="mb-0 fw-bold" id="liveReturns">-</h4>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-cash-register me-2 text-success"></i>{{ __('pos.current_session') }}</span>
            <span class="badge bg-success fs-6" id="sessionNumber">-</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <table class="table table-bordered table-sm">
                        <tr><th class="bg-light" width="45%">{{ __('pos.cashier') }}</th><td id="sessName">-</td></tr>
                        <tr><th class="bg-light">{{ __('pos.open_time') }}</th><td id="sessOpened">-</td></tr>
                        <tr><th class="bg-light">{{ __('pos.opening_balance') }}</th><td id="sessOpening" class="fw-bold">-</td></tr>
                        <tr><th class="bg-light">{{ __('pos.invoices_count') }}</th><td id="sessInvoices">-</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <strong>{{ __('pos.expected_in_drawer') }}:</strong><br>
                        {{ __('pos.expected_formula') }}<br>
                        <span class="fs-5 fw-bold" id="expectedCash">-</span>
                    </div>
                </div>
            </div>

            {{-- Cash Drawer Movements --}}
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">
                        <i class="fas fa-exchange-alt me-2 text-primary"></i>
                        {{ app()->getLocale() === 'ar' ? 'حركات الخزينة' : 'Cash Drawer Movements' }}
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-info fs-6" id="estimatedBalanceBadge">
                            {{ app()->getLocale() === 'ar' ? 'الرصيد المقدر: ...' : 'Est. Balance: ...' }}
                        </span>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#movementModal">
                            <i class="fas fa-plus me-1"></i>{{ app()->getLocale() === 'ar' ? 'تسجيل حركة' : 'Record Movement' }}
                        </button>
                    </div>
                </div>
                <div id="movementsListPlaceholder" class="text-muted small text-center py-2">
                    {{ app()->getLocale() === 'ar' ? 'لا توجد حركات مسجلة في هذه الجلسة.' : 'No movements recorded in this session.' }}
                </div>
                <div id="movementsList" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ app()->getLocale() === 'ar' ? 'الوقت' : 'Time' }}</th>
                                    <th>{{ app()->getLocale() === 'ar' ? 'النوع' : 'Type' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'المبلغ' : 'Amount' }}</th>
                                    <th>{{ app()->getLocale() === 'ar' ? 'السبب' : 'Reason' }}</th>
                                </tr>
                            </thead>
                            <tbody id="movementsBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="lowBalanceAlert" class="alert alert-warning py-2 small d-none mt-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <span id="lowBalanceMsg"></span>
                </div>
            </div>

            <div class="border rounded p-3 bg-light">
                <h6 class="fw-bold mb-3"><i class="fas fa-lock me-2"></i>{{ __('pos.close_reconcile') }}</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ __('pos.actual_cash_label') }} *</label>
                        <input type="number" class="form-control form-control-lg" id="actualCash"
                            step="0.01" min="0" placeholder="0.00" data-on-input="calcDifference">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ __('pos.difference') }}</label>
                        <div class="form-control form-control-lg fw-bold text-center" id="diffDisplay"
                            style="background:#f8f9fa;">-</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ __('pos.notes') }}</label>
                        <input type="text" class="form-control" id="closeNotes"
                            placeholder="{{ __('pos.diff_reason_hint') }}">
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-warning" data-fn="printShiftReport">
                        <i class="fas fa-print me-1"></i>{{ __('pos.print_shift_report') }}
                    </button>
                    <button class="btn btn-danger" data-fn="closeSession">
                        <i class="fas fa-lock me-1"></i>{{ __('pos.close_register') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- No session panel --}}
<div id="noSessionPanel" style="display:none">
    <div class="text-center py-5">
        <i class="fas fa-cash-register fa-4x mb-3 d-block text-muted opacity-50"></i>
        <h5>{{ __('pos.no_open_session') }}</h5>
        <p class="text-muted">{{ __('pos.open_register_hint') }}</p>
        <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#openModal">
            <i class="fas fa-play me-2"></i>{{ __('pos.open_register') }}
        </button>
    </div>
</div>

{{-- Sessions history --}}
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2"></i>{{ __('pos.session_history') }}</span>
        <button class="btn btn-danger btn-sm" data-fn="printHistory">
            <i class="fas fa-print me-1"></i>{{ __('pos.print') }}
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>{{ __('pos.session_number') }}</th>
                        <th>{{ __('pos.cashier') }}</th>
                        <th>{{ __('pos.opened') }}</th>
                        <th>{{ __('pos.closed') }}</th>
                        <th>{{ __('pos.sales') }}</th>
                        <th>{{ __('pos.expected_cash') }}</th>
                        <th>{{ __('pos.actual_cash') }}</th>
                        <th>{{ __('pos.difference') }}</th>
                        <th>{{ __('pos.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <tr><td colspan="10" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal: Record Cash Movement --}}
<div class="modal fade" id="movementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>{{ app()->getLocale() === 'ar' ? 'تسجيل حركة نقدية' : 'Record Cash Movement' }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'نوع الحركة *' : 'Movement Type *' }}</label>
                    <div class="d-flex gap-2">
                        <div class="form-check form-check-inline flex-fill">
                            <input class="form-check-input" type="radio" name="movType" id="movDeposit" value="deposit" checked>
                            <label class="form-check-label text-success fw-semibold" for="movDeposit">
                                <i class="fas fa-arrow-down me-1"></i>{{ app()->getLocale() === 'ar' ? 'إيداع (دخول)' : 'Deposit (In)' }}
                            </label>
                        </div>
                        <div class="form-check form-check-inline flex-fill">
                            <input class="form-check-input" type="radio" name="movType" id="movWithdrawal" value="withdrawal">
                            <label class="form-check-label text-danger fw-semibold" for="movWithdrawal">
                                <i class="fas fa-arrow-up me-1"></i>{{ app()->getLocale() === 'ar' ? 'سحب (خروج)' : 'Withdrawal (Out)' }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'المبلغ *' : 'Amount *' }}</label>
                    <input type="number" class="form-control form-control-lg" id="movAmount" step="0.01" min="0.01" placeholder="0.00">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'السبب / الملاحظة' : 'Reason / Note' }}</label>
                    <input type="text" class="form-control" id="movReason" maxlength="500"
                        placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: صرف فواتير، إيداع من المبيعات...' : 'e.g. Petty cash, supply purchase...' }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="recordMovement">
                    <i class="fas fa-save me-1"></i>{{ app()->getLocale() === 'ar' ? 'تسجيل' : 'Record' }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Open Register --}}
<div class="modal fade" id="openModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-play me-2"></i>{{ __('pos.open_register') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.opening_balance_drawer_label') }} *</label>
                    <input type="number" class="form-control form-control-lg" id="openingAmount"
                        step="0.01" min="0" placeholder="0.00">
                    <div class="form-text">{{ __('pos.opening_balance_drawer_hint') }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.notes') }}</label>
                    <input type="text" class="form-control" id="openNotes">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-success" data-fn="openSession">
                    <i class="fas fa-play me-1"></i>{{ __('pos.start_session') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
let currentSession   = null;
let liveStats        = null;
let renderedSessions = [];

const CASH_SESSION_BASE_URL = '{{ url("api/cash-session") }}';

const _t = {
    enterOpeningBalance:  '{{ __('pos.enter_opening_balance') }}',
    registerOpened:       '{{ __('pos.register_opened_success') }}',
    enterActualCash:      '{{ __('pos.enter_actual_cash_msg') }}',
    confirmClose:         '{{ __('pos.confirm_close_register') }}',
    registerClosed:       '{{ __('pos.register_closed_success') }}',
    error:                '{{ __('pos.error') }}',
    noSessions:           '{{ __('pos.no_previous_sessions') }}',
    sessionOpen:          '{{ __('pos.session_open_status') }}',
    sessionClosed:        '{{ __('pos.session_closed_status') }}',
    shiftCloseReport:     '{{ __('pos.shift_close_report') }}',
    cashier:              '{{ __('pos.cashier') }}',
    openTime:             '{{ __('pos.open_time') }}',
    shiftOpenBalance:     '{{ __('pos.shift_open_balance') }}',
    cashSales:            '{{ __('pos.cash_sales') }}',
    cardTransferSales:    '{{ __('pos.card_transfer_sales') }}',
    totalSales:           '{{ __('pos.total_sales') }}',
    returns:              '{{ __('pos.returns') }}',
    invoicesCount:        '{{ __('pos.invoices_count') }}',
    expectedInDrawer:     '{{ __('pos.expected_in_drawer') }}',
    actualFound:          '{{ __('pos.actual_found') }}',
    difference:           '{{ __('pos.difference') }}',
    cashierSignature:     '{{ __('pos.cashier_signature') }}',
    managerSignature:     '{{ __('pos.manager_signature') }}',
    printDate:            '{{ __('pos.print_date') }}',
};

async function loadCurrentSession() {
    const res = await apiCall('{{ route("cash-session.current") }}');
    if (res.session) {
        currentSession = res.session;
        liveStats      = res.session;
        showOpenPanel();
    } else {
        showNoSessionPanel();
    }
    loadHistory();
}

function showOpenPanel() {
    document.getElementById('openSessionPanel').style.display = 'block';
    document.getElementById('noSessionPanel').style.display   = 'none';
    const s = currentSession;
    document.getElementById('sessionNumber').textContent  = s.session_number;
    document.getElementById('sessName').textContent       = s.cashier_name;
    document.getElementById('sessOpened').textContent     = formatDate(s.opened_at);
    document.getElementById('sessOpening').textContent    = formatCurrency(s.opening_amount);
    document.getElementById('sessInvoices').textContent   = s.invoices_count || 0;
    document.getElementById('liveCashSales').textContent  = formatCurrency(s.cash_sales || 0);
    document.getElementById('liveOtherSales').textContent = formatCurrency((s.card_sales||0) + (s.transfer_sales||0));
    document.getElementById('liveTotalSales').textContent = formatCurrency(s.total_sales || 0);
    document.getElementById('liveReturns').textContent    = formatCurrency(s.total_returns || 0);

    const expected = (s.opening_amount||0) + (s.cash_sales||0) - (s.cash_returns||0);
    document.getElementById('expectedCash').textContent = formatCurrency(expected);
    calcDifference();
    loadSessionMovements();
}

function showNoSessionPanel() {
    document.getElementById('openSessionPanel').style.display = 'none';
    document.getElementById('noSessionPanel').style.display   = 'block';
}

function calcDifference() {
    if (!currentSession) return;
    const s        = currentSession;
    const expected = (s.opening_amount||0) + (s.cash_sales||0) - (s.cash_returns||0);
    const actual   = parseFloat(document.getElementById('actualCash').value) || 0;
    const diff     = actual - expected;
    const el       = document.getElementById('diffDisplay');
    if (!document.getElementById('actualCash').value) { el.textContent = '-'; el.style.color = ''; return; }
    el.textContent = (diff >= 0 ? '+' : '') + formatCurrency(diff);
    el.style.color = diff > 5 ? '#198754' : diff < -5 ? '#dc3545' : '#856404';
}

async function openSession() {
    const amount = document.getElementById('openingAmount').value;
    if (!amount) { showToast(_t.enterOpeningBalance, 'danger'); return; }
    const res = await apiCall('{{ route("cash-session.open") }}', 'POST', {
        opening_amount: amount,
        notes: document.getElementById('openNotes').value,
    });
    if (res.success) {
        showToast(_t.registerOpened);
        bootstrap.Modal.getInstance(document.getElementById('openModal')).hide();
        loadCurrentSession();
    } else showToast(res.message || _t.error, 'danger');
}

async function closeSession() {
    const actual = document.getElementById('actualCash').value;
    if (!actual) { showToast(_t.enterActualCash, 'danger'); return; }
    if (!confirm(_t.confirmClose)) return;
    const res = await apiCall(`${CASH_SESSION_BASE_URL}/${currentSession.id}/close`, 'POST', {
        actual_cash: actual,
        notes: document.getElementById('closeNotes').value,
    });
    if (res.success) {
        showToast(_t.registerClosed);
        printShiftReport(res.session);
        loadCurrentSession();
    } else showToast(res.message || _t.error, 'danger');
}

let sessionMovements = [];

async function loadSessionMovements() {
    if (!currentSession) return;
    // Fetch movements from the session movements endpoint — no direct API yet, so track locally
    renderMovements();
}

function renderMovements() {
    const tbody      = document.getElementById('movementsBody');
    const list       = document.getElementById('movementsList');
    const placeholder = document.getElementById('movementsListPlaceholder');
    const isAr       = LOCALE === 'ar';

    if (!sessionMovements.length) {
        list.classList.add('d-none');
        placeholder.classList.remove('d-none');
    } else {
        list.classList.remove('d-none');
        placeholder.classList.add('d-none');
        tbody.innerHTML = sessionMovements.map(m => `
            <tr>
                <td class="small">${new Date(m.created_at).toLocaleTimeString()}</td>
                <td><span class="badge ${m.type === 'deposit' ? 'bg-success' : 'bg-danger'}">
                    ${m.type === 'deposit' ? (isAr ? 'إيداع' : 'Deposit') : (isAr ? 'سحب' : 'Withdrawal')}
                </span></td>
                <td class="text-end fw-semibold ${m.type === 'deposit' ? 'text-success' : 'text-danger'}">
                    ${m.type === 'deposit' ? '+' : '-'}${formatCurrency(m.amount)}
                </td>
                <td class="small text-muted">${m.reason || '—'}</td>
            </tr>`).join('');
    }

    // Update estimated balance badge
    if (currentSession) {
        const deposits    = sessionMovements.filter(m => m.type === 'deposit').reduce((s, m) => s + m.amount, 0);
        const withdrawals = sessionMovements.filter(m => m.type === 'withdrawal').reduce((s, m) => s + m.amount, 0);
        const base        = (currentSession.opening_amount||0) + (currentSession.cash_sales||0) - (currentSession.cash_returns||0);
        const estimated   = base + deposits - withdrawals;
        const badge       = document.getElementById('estimatedBalanceBadge');
        badge.textContent = (isAr ? 'الرصيد المقدر: ' : 'Est. Balance: ') + formatCurrency(estimated);
        badge.className   = `badge fs-6 ${estimated < 0 ? 'bg-danger' : 'bg-info'}`;
    }
}

async function recordMovement() {
    if (!currentSession) return;
    const type   = document.querySelector('input[name="movType"]:checked')?.value;
    const amount = parseFloat(document.getElementById('movAmount').value);
    const reason = document.getElementById('movReason').value.trim();

    if (!amount || amount <= 0) {
        showToast('{{ app()->getLocale() === "ar" ? "أدخل مبلغاً صحيحاً" : "Enter a valid amount" }}', 'danger');
        return;
    }

    const res = await apiCall(`${CASH_SESSION_BASE_URL}/${currentSession.id}/movements`, 'POST', { type, amount, reason });

    if (res.success) {
        const movement = res.movement;
        sessionMovements.push(movement);
        renderMovements();

        // Show low-balance warnings if returned
        const warnings = res.warnings || [];
        const alertEl  = document.getElementById('lowBalanceAlert');
        const msgEl    = document.getElementById('lowBalanceMsg');
        if (warnings.length) {
            msgEl.textContent = warnings.join(' ');
            alertEl.classList.remove('d-none');
        } else {
            alertEl.classList.add('d-none');
        }

        // Reset form and close modal
        document.getElementById('movAmount').value = '';
        document.getElementById('movReason').value = '';
        bootstrap.Modal.getInstance(document.getElementById('movementModal')).hide();
        showToast('{{ app()->getLocale() === "ar" ? "تم تسجيل الحركة" : "Movement recorded" }}');
    } else {
        showToast(res.message || _t.error, 'danger');
    }
}

async function loadHistory() {
    const res      = await apiCall('{{ route("cash-session.history") }}');
    const sessions = res.sessions?.data || [];
    renderedSessions = sessions;
    document.getElementById('historyBody').innerHTML = sessions.length
        ? sessions.map((s, i) => {
            const diff     = s.difference;
            const diffHtml = diff !== null
                ? `<span class="fw-bold ${diff > 5 ? 'text-success' : diff < -5 ? 'text-danger' : 'text-warning'}">
                    ${diff >= 0 ? '+' : ''}${formatCurrency(diff)}</span>`
                : '-';
            return `<tr>
                <td><code>${s.session_number}</code></td>
                <td>${s.cashier_name}</td>
                <td class="small">${formatDate(s.opened_at)}</td>
                <td class="small">${s.closed_at ? formatDate(s.closed_at) : '-'}</td>
                <td class="text-success fw-semibold">${formatCurrency(s.total_sales)}</td>
                <td>${s.expected_cash !== null ? formatCurrency(s.expected_cash) : '-'}</td>
                <td>${s.actual_cash   !== null ? formatCurrency(s.actual_cash)   : '-'}</td>
                <td>${diffHtml}</td>
                <td><span class="badge ${s.status==='open' ? 'bg-success' : 'bg-secondary'}">
                    ${s.status==='open' ? _t.sessionOpen : _t.sessionClosed}</span></td>
                <td><button class="btn btn-sm btn-outline-primary" data-action="print-session" data-session-idx="${i}">
                    <i class="fas fa-print"></i></button></td>
            </tr>`;
        }).join('')
        : `<tr><td colspan="10" class="text-center text-muted py-3">${_t.noSessions}</td></tr>`;
}

function printShiftReport(session) {
    const s = session || currentSession;
    if (!s) return;

    // Normalize field names: open session uses cash_sales/card_sales/transfer_sales (merged stats);
    // closed session from DB uses total_card/total_transfer — derive the rest.
    const cardSales     = s.card_sales     ?? (s.total_card     || 0);
    const transferSales = s.transfer_sales ?? (s.total_transfer || 0);
    const totalSales    = s.total_sales    || 0;
    const cashSales     = s.cash_sales     ?? (totalSales - cardSales - transferSales);
    const cashReturns   = s.cash_returns   ?? (s.total_returns  || 0);

    // Use stored expected_cash when available; recalculate only for live open sessions.
    const expected = s.expected_cash ?? ((s.opening_amount || 0) + cashSales - cashReturns);
    const actual   = s.actual_cash   ?? parseFloat(document.getElementById('actualCash')?.value || 0);
    const diff     = actual - expected;
    const locale   = '{{ app()->getLocale() }}';
    const w = window.open('','_blank');
    w.document.write(`<!DOCTYPE html><html dir="${locale==='ar'?'rtl':'ltr'}" lang="${locale}"><head><meta charset="utf-8">
        <title>${_t.shiftCloseReport} — ${s.session_number}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap${locale==='ar'?'.rtl':''}.min.css" rel="stylesheet">
        <style>body{padding:20px;font-family:'Cairo',sans-serif;font-size:13px;max-width:500px;margin:auto;}</style>
        </head><body>
        <h4 class="text-center fw-bold mb-1">${_t.shiftCloseReport}</h4>
        <p class="text-center text-muted mb-3">${s.session_number} — ${new Date().toLocaleString(locale+'-EG')}</p>
        <table class="table table-bordered table-sm">
            <tr><th class="bg-light" width="55%">${_t.cashier}</th><td>${s.cashier_name}</td></tr>
            <tr><th class="bg-light">${_t.openTime}</th><td>${formatDate(s.opened_at)}</td></tr>
            <tr><th class="bg-light">${_t.shiftOpenBalance}</th><td class="fw-bold">${formatCurrency(s.opening_amount||0)}</td></tr>
            <tr class="table-success"><th>${_t.cashSales}</th><td class="fw-bold">${formatCurrency(cashSales)}</td></tr>
            <tr class="table-primary"><th>${_t.cardTransferSales}</th><td class="fw-bold">${formatCurrency(cardSales + transferSales)}</td></tr>
            <tr class="table-warning"><th>${_t.totalSales}</th><td class="fw-bold fs-6">${formatCurrency(totalSales)}</td></tr>
            <tr class="table-danger"><th>${_t.returns}</th><td>${formatCurrency(s.total_returns||0)}</td></tr>
            <tr><th>${_t.invoicesCount}</th><td>${s.invoices_count||0}</td></tr>
            <tr class="table-info"><th>${_t.expectedInDrawer}</th><td class="fw-bold">${formatCurrency(expected)}</td></tr>
            <tr class="table-warning"><th>${_t.actualFound}</th><td class="fw-bold fs-5">${formatCurrency(actual)}</td></tr>
            <tr class="${diff > 5 ? 'table-success' : diff < -5 ? 'table-danger' : 'table-warning'}">
                <th>${_t.difference}</th><td class="fw-bold fs-5">${diff >= 0 ? '+' : ''}${formatCurrency(diff)}</td></tr>
        </table>
        <div class="row mt-4 text-center">
            <div class="col-6"><p class="mb-5 small">${_t.cashierSignature}</p><p class="border-top pt-2">${s.cashier_name}</p></div>
            <div class="col-6"><p class="mb-5 small">${_t.managerSignature}</p><p class="border-top pt-2">___________</p></div>
        </div>
        </body></html>`);
    w.document.close(); w.focus(); w.print(); w.onafterprint = () => w.close();
}

function printSessionReport(s) { printShiftReport(s); }

function printHistory() {
    window.print();
}

document.getElementById('historyBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="print-session"]');
    if (!btn) return;
    const s = renderedSessions[parseInt(btn.dataset.sessionIdx)];
    if (s) printSessionReport(s);
});

loadCurrentSession();
setInterval(loadCurrentSession, 60000);
</script>
@endpush
