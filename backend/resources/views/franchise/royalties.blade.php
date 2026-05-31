@extends('layouts.app')
@section('title', __('pos.franchise_royalties'))
@section('page-title', '🤝 ' . __('pos.franchise_royalties'))

@section('content')

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-primary" id="cntAgreements">–</div>
        <div class="text-muted small">{{ __('pos.franchise_agreements') }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-success" id="totalRoyalties">–</div>
        <div class="text-muted small">{{ __('pos.royalty_amount') }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-warning" id="totalDue">–</div>
        <div class="text-muted small">{{ __('pos.total_due') }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-info" id="totalPaid">–</div>
        <div class="text-muted small">{{ __('pos.amount_paid') }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Filter + Generate --}}
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body py-2">
    <div class="row g-2 align-items-center">
      <div class="col-auto">
        <label class="form-label mb-0 fw-semibold small">{{ app()->getLocale()==='ar' ? 'السنة' : 'Year' }}</label>
      </div>
      <div class="col-auto">
        <select id="filterYear" class="form-select form-select-sm">
          @for($y = date('Y'); $y >= date('Y') - 2; $y--)
            <option value="{{ $y }}">{{ $y }}</option>
          @endfor
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-0 fw-semibold small">{{ app()->getLocale()==='ar' ? 'الشهر' : 'Month' }}</label>
      </div>
      <div class="col-auto">
        <select id="filterMonth" class="form-select form-select-sm">
          <option value="">{{ app()->getLocale()==='ar' ? 'كل الأشهر' : 'All Months' }}</option>
          @foreach(range(1,12) as $m)
            <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
              {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-auto">
        <select id="filterStmt" class="form-select form-select-sm">
          <option value="">{{ app()->getLocale()==='ar' ? 'كل الحالات' : 'All Statuses' }}</option>
          <option value="draft">{{ app()->getLocale()==='ar' ? 'مسودة' : 'Draft' }}</option>
          <option value="sent">{{ app()->getLocale()==='ar' ? 'مُرسل' : 'Sent' }}</option>
          <option value="paid">{{ app()->getLocale()==='ar' ? 'مدفوع' : 'Paid' }}</option>
          <option value="overdue">{{ app()->getLocale()==='ar' ? 'متأخر' : 'Overdue' }}</option>
        </select>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn btn-sm btn-outline-secondary me-1" id="btnRefreshStmts">
          <i class="fas fa-rotate-right me-1"></i>{{ app()->getLocale()==='ar' ? 'تحديث' : 'Refresh' }}
        </button>
        <button class="btn btn-sm btn-primary" id="btnGenStmts">
          <i class="fas fa-file-invoice me-1"></i>{{ __('pos.generate_statements') }}
        </button>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">

  {{-- Statements Table --}}
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-file-contract me-2 text-primary"></i>{{ __('pos.royalty_statements') }}</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>{{ __('pos.franchisee') }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'الفترة' : 'Period' }}</th>
                <th>{{ __('pos.gross_sales') }}</th>
                <th>{{ __('pos.royalty_amount') }}</th>
                <th>{{ __('pos.marketing_fee') }}</th>
                <th>{{ __('pos.total_due') }}</th>
                <th>{{ __('pos.balance_due') }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="statementsBody">
              <tr><td colspan="9" class="text-center py-4">
                <div class="spinner-border spinner-border-sm me-2 text-primary"></div>
                {{ app()->getLocale()==='ar' ? 'جاري التحميل…' : 'Loading…' }}
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  {{-- Agreements Panel --}}
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-handshake me-2 text-success"></i>{{ __('pos.franchise_agreements') }}</h6>
      </div>
      <div class="list-group list-group-flush" id="agreementsList">
        <div class="list-group-item text-center text-muted py-4">
          <div class="spinner-border spinner-border-sm"></div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Record Payment Modal --}}
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2 text-success"></i>{{ app()->getLocale()==='ar' ? 'تسجيل دفعة' : 'Record Payment' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="payStmtId">
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ __('pos.amount_paid') }}</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-coins"></i></span>
            <input type="number" id="payAmount" class="form-control" step="0.01" min="0" placeholder="0.00">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'ملاحظة' : 'Note' }}</label>
          <input type="text" id="payNote" class="form-control" placeholder="{{ app()->getLocale()==='ar' ? 'اختياري…' : 'Optional…' }}">
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}</button>
        <button type="button" class="btn btn-success" id="btnConfirmPayment">
          <i class="fas fa-check me-1"></i>{{ app()->getLocale()==='ar' ? 'تأكيد الدفع' : 'Confirm Payment' }}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
const CSRF   = document.querySelector('meta[name=csrf-token]').content;
const JSON_H = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };

const stmtColors  = { draft:'secondary', sent:'info', paid:'success', overdue:'danger' };
const stmtLabels  = {
  draft:   '{{ app()->getLocale()==='ar' ? 'مسودة' : 'Draft' }}',
  sent:    '{{ app()->getLocale()==='ar' ? 'مُرسل' : 'Sent' }}',
  paid:    '{{ app()->getLocale()==='ar' ? 'مدفوع' : 'Paid' }}',
  overdue: '{{ app()->getLocale()==='ar' ? 'متأخر' : 'Overdue' }}',
};
const royaltyTypeLabels = {
  percentage: '{{ app()->getLocale()==='ar' ? 'نسبة مئوية' : 'Percentage' }}',
  fixed:      '{{ app()->getLocale()==='ar' ? 'ثابت' : 'Fixed' }}',
  tiered:     '{{ app()->getLocale()==='ar' ? 'متدرج' : 'Tiered' }}',
};
const t = {
  noStmts:    '{{ app()->getLocale()==='ar' ? 'لا توجد بيانات لهذه الفترة' : 'No statements for this period' }}',
  noAgreements: '{{ app()->getLocale()==='ar' ? 'لا توجد اتفاقيات' : 'No agreements yet' }}',
  payBtn:     '{{ app()->getLocale()==='ar' ? 'تسجيل دفعة' : 'Record Payment' }}',
  pdfBtn:     '{{ app()->getLocale()==='ar' ? 'تحميل PDF' : 'Download PDF' }}',
  genConfirm: '{{ __('pos.generate_statements') }}',
  stmtsFor:   '{{ app()->getLocale()==='ar' ? 'إنشاء كشوفات' : 'Generate statements for' }}',
  genLabel:   '{{ app()->getLocale()==='ar' ? 'إنشاء' : 'Generate' }}',
  cancel:     '{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}',
  generated:  '{{ app()->getLocale()==='ar' ? 'تم الإنشاء' : 'Generated' }}',
  payRecorded:'{{ app()->getLocale()==='ar' ? 'تم تسجيل الدفعة' : 'Payment Recorded' }}',
  monthly:    'monthly',
};

// ── Event listeners (no inline handlers) ─────────────────────────────────
document.getElementById('filterYear').addEventListener('change',  loadStatements);
document.getElementById('filterMonth').addEventListener('change', loadStatements);
document.getElementById('filterStmt').addEventListener('change',  loadStatements);
document.getElementById('btnRefreshStmts').addEventListener('click', loadStatements);
document.getElementById('btnGenStmts').addEventListener('click',  generateStatements);
document.getElementById('btnConfirmPayment').addEventListener('click', submitPayment);

// ── Event delegation on statements table ──────────────────────────────────
document.getElementById('statementsBody').addEventListener('click', e => {
  const pay = e.target.closest('[data-action="pay"]');
  if (pay) { openPayment(pay.dataset.id, parseFloat(pay.dataset.bal)); return; }

  const pdf = e.target.closest('[data-action="pdf"]');
  if (pdf) { window.open(`/api/franchise/statements/${pdf.dataset.id}/pdf`, '_blank'); }
});

// ── Load statements ────────────────────────────────────────────────────────
async function loadStatements() {
  const year   = document.getElementById('filterYear').value;
  const month  = document.getElementById('filterMonth').value;
  const status = document.getElementById('filterStmt').value;
  const params = new URLSearchParams({ year });
  if (month)  params.append('month', month);
  if (status) params.append('status', status);

  const res  = await fetch('/api/franchise/statements?' + params, { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const stmts = data.statements ?? [];

  // Summary
  let totalRoy = 0, totalDue = 0, totalPaid = 0;
  stmts.forEach(s => {
    totalRoy  += parseFloat(s.royalty_amount ?? 0);
    totalDue  += parseFloat(s.total_due      ?? 0);
    totalPaid += parseFloat(s.amount_paid    ?? 0);
  });
  document.getElementById('cntAgreements').textContent  = data.agreements_count ?? '–';
  document.getElementById('totalRoyalties').textContent = fmt(totalRoy);
  document.getElementById('totalDue').textContent       = fmt(totalDue);
  document.getElementById('totalPaid').textContent      = fmt(totalPaid);

  const tbody = document.getElementById('statementsBody');
  if (!stmts.length) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
      <i class="fas fa-file-circle-xmark fa-2x d-block mb-2 opacity-25"></i>${t.noStmts}
    </td></tr>`;
    return;
  }

  tbody.innerHTML = stmts.map(s => {
    const col   = stmtColors[s.status] ?? 'secondary';
    const label = stmtLabels[s.status] ?? s.status;
    const bal   = parseFloat(s.balance_due ?? 0);
    const payBtn = s.status !== 'paid'
      ? `<button class="btn btn-outline-success btn-sm" data-action="pay" data-id="${s.id}" data-bal="${bal}" title="${t.payBtn}"><i class="fas fa-money-bill"></i></button>`
      : '';
    return `<tr>
      <td class="fw-semibold">${esc(s.franchisee_name ?? 'Tenant #' + s.franchisee_tenant_id)}</td>
      <td>${s.period_year ?? '–'}/${String(s.period_month ?? '').padStart(2,'0')}</td>
      <td>${fmt(s.gross_sales)}</td>
      <td class="text-primary">${fmt(s.royalty_amount)}</td>
      <td class="text-warning">${fmt(s.marketing_fee)}</td>
      <td class="fw-bold">${fmt(s.total_due)}</td>
      <td class="fw-bold ${bal > 0 ? 'text-danger' : 'text-success'}">${fmt(bal)}</td>
      <td><span class="badge bg-${col}">${label}</span></td>
      <td>
        <div class="btn-group btn-group-sm">
          ${payBtn}
          <button class="btn btn-outline-secondary btn-sm" data-action="pdf" data-id="${s.id}" title="${t.pdfBtn}"><i class="fas fa-file-pdf"></i></button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── Load agreements ────────────────────────────────────────────────────────
async function loadAgreements() {
  const res  = await fetch('/api/franchise/agreements', { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const agreements = data.agreements ?? [];
  const list = document.getElementById('agreementsList');

  if (!agreements.length) {
    list.innerHTML = `<div class="list-group-item text-center text-muted py-4">
      <i class="fas fa-handshake-slash d-block mb-1 opacity-25 fs-3"></i>${t.noAgreements}
    </div>`;
    return;
  }

  list.innerHTML = agreements.map(a => {
    const typeLabel = royaltyTypeLabels[a.royalty_type] ?? a.royalty_type;
    const rate = a.royalty_type === 'percentage'
      ? `${a.royalty_rate}%`
      : (a.royalty_type === 'fixed' ? fmt(a.royalty_rate) : t.tiered ?? 'Tiered');
    return `<div class="list-group-item px-3 py-2">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="fw-semibold small">${esc(a.franchisee_name ?? 'Tenant #' + a.franchisee_tenant_id)}</div>
          <div class="text-muted" style="font-size:.75rem">${typeLabel} · ${rate}</div>
        </div>
        <span class="badge bg-light text-dark border small">${esc(a.billing_cycle ?? t.monthly)}</span>
      </div>
    </div>`;
  }).join('');
}

// ── Generate statements ────────────────────────────────────────────────────
async function generateStatements() {
  const year  = document.getElementById('filterYear').value;
  const month = document.getElementById('filterMonth').value || (new Date().getMonth() + 1);

  const c = await Swal.fire({
    icon: 'question',
    title: t.genConfirm,
    text: `${t.stmtsFor} ${year}/${String(month).padStart(2,'0')}?`,
    showCancelButton: true,
    confirmButtonText: t.genLabel,
    cancelButtonText: t.cancel,
  });
  if (!c.isConfirmed) return;

  const res  = await fetch('/api/franchise/statements/generate', {
    method: 'POST', headers: JSON_H,
    body: JSON.stringify({ year, month }),
  });
  const data = await res.json();
  if (data.success) {
    Swal.fire({ icon: 'success', title: data.message ?? t.generated, timer: 2000, showConfirmButton: false });
    loadStatements();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
  }
}

// ── Payment ────────────────────────────────────────────────────────────────
function openPayment(id, balance) {
  document.getElementById('payStmtId').value = id;
  document.getElementById('payAmount').value = balance.toFixed(2);
  document.getElementById('payNote').value   = '';
  new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

async function submitPayment() {
  const id     = document.getElementById('payStmtId').value;
  const amount = parseFloat(document.getElementById('payAmount').value);
  const note   = document.getElementById('payNote').value;

  const res  = await fetch(`/api/franchise/statements/${id}/payment`, {
    method: 'POST', headers: JSON_H,
    body: JSON.stringify({ amount, note }),
  });
  const data = await res.json();
  if (data.success) {
    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
    Swal.fire({ icon: 'success', title: t.payRecorded, timer: 1800, showConfirmButton: false });
    loadStatements();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
  }
}

function fmt(n) { return n != null ? Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '–'; }
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

loadStatements();
loadAgreements();
</script>
@endpush
