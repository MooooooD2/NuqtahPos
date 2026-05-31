@extends('layouts.app')
@section('title', __('pos.payroll'))
@section('page-title', '💰 ' . __('pos.payroll'))

@section('content')

{{-- Salary setup reminder --}}
@php
    $isAr = app()->getLocale() === 'ar';
    $noSalaryCount = \DB::table('users')
        ->where('is_active', true)
        ->whereNull('deleted_at')
        ->whereNotIn('id', \DB::table('salary_structures')->where('is_active', true)->pluck('user_id'))
        ->count();
@endphp
@if($noSalaryCount > 0)
<div class="alert alert-warning d-flex align-items-center gap-3 mb-3">
    <i class="fas fa-triangle-exclamation fa-lg flex-shrink-0"></i>
    <div class="flex-grow-1">
        <strong>{{ $isAr ? 'تنبيه:' : 'Notice:' }}</strong>
        {{ $isAr
            ? "{$noSalaryCount} موظف لم يتم تحديد راتبه بعد — سيظهر راتبه كـ 0.00 في مسير الرواتب."
            : "{$noSalaryCount} employee(s) have no salary structure set — their pay will be 0.00." }}
    </div>
    <a href="{{ route('hr.employees') }}" class="btn btn-warning btn-sm flex-shrink-0">
        <i class="fas fa-users-gear me-1"></i>
        {{ $isAr ? 'ضبط الرواتب' : 'Set Salaries' }}
    </a>
</div>
@endif

{{-- HR Module Summary --}}
@include('hr._summary')

{{-- Generate Payroll Card --}}
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
    <h6 class="mb-0 fw-semibold"><i class="fas fa-play-circle me-2 text-primary"></i>{{ __('pos.payroll_run') }}</h6>
  </div>
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'السنة' : 'Year' }}</label>
        <select id="payYear" class="form-select">
          @for($y = date('Y'); $y >= date('Y') - 2; $y--)
            <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
          @endfor
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'الشهر' : 'Month' }}</label>
        <select id="payMonth" class="form-select">
          @foreach(range(1,12) as $m)
            <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
              {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'الفرع' : 'Branch' }}</label>
        <select id="payBranch" class="form-select">
          <option value="">{{ app()->getLocale()==='ar' ? 'كل الفروع' : 'All Branches' }}</option>
          @php /** @var \App\Models\Branch[] $branches */ @endphp
          @foreach($branches ?? [] as $b)
            <option value="{{ $b->id }}">{{ $b->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100" id="btnGenPayroll">
          <i class="fas fa-cogs me-2"></i>{{ __('pos.payroll_run') }}
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Payroll Runs List --}}
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
    <h6 class="mb-0 fw-semibold"><i class="fas fa-list-check me-2 text-success"></i>{{ app()->getLocale()==='ar' ? 'دورات الرواتب' : 'Payroll Runs' }}</h6>
    <button class="btn btn-sm btn-outline-secondary" id="btnRefreshRuns">
      <i class="fas fa-rotate-right me-1"></i>{{ app()->getLocale()==='ar' ? 'تحديث' : 'Refresh' }}
    </button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ app()->getLocale()==='ar' ? 'الفترة' : 'Period' }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'الفرع' : 'Branch' }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'عدد الموظفين' : 'Employees' }}</th>
            <th>{{ __('pos.gross_salary') }}</th>
            <th>{{ __('pos.deductions') }}</th>
            <th>{{ __('pos.net_salary') }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'إجراءات' : 'Actions' }}</th>
          </tr>
        </thead>
        <tbody id="runsBody">
          <tr><td colspan="8" class="text-center py-4 text-muted">
            <div class="spinner-border spinner-border-sm me-2 text-primary"></div>
            {{ app()->getLocale()==='ar' ? 'جاري التحميل…' : 'Loading…' }}
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Pay Slips Modal --}}
<div class="modal fade" id="slipsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>{{ __('pos.payroll_slips') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>{{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}</th>
                <th>{{ __('pos.basic_salary') }}</th>
                <th>{{ __('pos.allowances') }}</th>
                <th>{{ __('pos.overtime_pay') }}</th>
                <th>{{ __('pos.gross_salary') }}</th>
                <th>{{ __('pos.income_tax') }}</th>
                <th>{{ __('pos.social_insurance') }}</th>
                <th>{{ __('pos.absence_deduction') }}</th>
                <th>{{ __('pos.net_salary') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="slipsBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const JSON_H = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };

const runColors = { draft:'secondary', approved:'primary', paid:'success' };
const runLabels = {
  draft:    '{{ app()->getLocale()==='ar' ? 'مسودة' : 'Draft' }}',
  approved: '{{ app()->getLocale()==='ar' ? 'معتمد' : 'Approved' }}',
  paid:     '{{ app()->getLocale()==='ar' ? 'مدفوع' : 'Paid' }}',
};
const t = {
  approveTitle: '{{ app()->getLocale()==='ar' ? 'اعتماد' : 'Approve' }}',
  paidTitle:    '{{ app()->getLocale()==='ar' ? 'تأكيد الدفع' : 'Mark Paid' }}',
  slipsTitle:   '{{ __('pos.payroll_slips') }}',
  noRuns:       '{{ app()->getLocale()==='ar' ? 'لا توجد دورات رواتب بعد' : 'No payroll runs yet' }}',
  allBranches:  '{{ app()->getLocale()==='ar' ? 'الكل' : 'All' }}',
  confirmGen:   '{{ app()->getLocale()==='ar' ? 'إنشاء مسير الرواتب؟' : 'Generate Payroll?' }}',
  period:       '{{ app()->getLocale()==='ar' ? 'الفترة:' : 'Period:' }}',
  genBtn:       '{{ __('pos.payroll_run') }}',
  cancel:       '{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}',
  generated:    '{{ app()->getLocale()==='ar' ? 'تم إنشاء مسير الرواتب' : 'Payroll Run Created' }}',
};

// ── Load runs ──────────────────────────────────────────────────────────────
async function loadRuns() {
  const res  = await fetch('/api/hr/payroll/runs', { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const runs = data.runs ?? [];
  const tbody = document.getElementById('runsBody');

  if (!runs.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">
      <i class="fas fa-coins fa-2x d-block mb-2 opacity-25"></i>${t.noRuns}
    </td></tr>`;
    return;
  }

  tbody.innerHTML = runs.map(r => {
    const col   = runColors[r.status] ?? 'secondary';
    const label = runLabels[r.status] ?? r.status;
    const approveBtn = r.status === 'draft'
      ? `<button class="btn btn-outline-success" data-action="approve" data-id="${r.id}" title="${t.approveTitle}"><i class="fas fa-check"></i></button>`
      : '';
    const paidBtn = r.status === 'approved'
      ? `<button class="btn btn-outline-info" data-action="mark-paid" data-id="${r.id}" title="${t.paidTitle}"><i class="fas fa-money-bill-wave"></i></button>`
      : '';
    return `<tr>
      <td><span class="fw-semibold">${r.year}/${String(r.month).padStart(2,'0')}</span></td>
      <td>${esc(r.branch?.name ?? t.allBranches)}</td>
      <td><span class="badge bg-light text-dark border">${r.employee_count ?? '–'}</span></td>
      <td>${fmt(r.total_gross)}</td>
      <td class="text-danger">${fmt(r.total_deductions)}</td>
      <td class="fw-bold text-success">${fmt(r.total_net)}</td>
      <td><span class="badge bg-${col}">${label}</span></td>
      <td>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary" data-action="view-slips" data-id="${r.id}" title="${t.slipsTitle}"><i class="fas fa-eye"></i></button>
          ${approveBtn}${paidBtn}
        </div>
      </td>
    </tr>`;
  }).join('');
}

// ── Event delegation on runs table ────────────────────────────────────────
document.getElementById('runsBody').addEventListener('click', async e => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const id = btn.dataset.id;
  if (btn.dataset.action === 'view-slips')  { await viewSlips(id); return; }
  if (btn.dataset.action === 'approve')     { await approveRun(id); return; }
  if (btn.dataset.action === 'mark-paid')   { await markPaid(id); return; }
});

document.getElementById('slipsBody').addEventListener('click', e => {
  const btn = e.target.closest('[data-action="print-slip"]');
  if (btn) printSlip(btn.dataset.id);
});

// ── Static button listeners ────────────────────────────────────────────────
document.getElementById('btnRefreshRuns').addEventListener('click', loadRuns);
document.getElementById('btnGenPayroll').addEventListener('click', generatePayroll);

// ── Actions ────────────────────────────────────────────────────────────────
async function generatePayroll() {
  const year   = document.getElementById('payYear').value;
  const month  = document.getElementById('payMonth').value;
  const branch = document.getElementById('payBranch').value;

  const confirmed = await Swal.fire({
    icon: 'question',
    title: t.confirmGen,
    text: `${t.period} ${year}/${String(month).padStart(2,'0')}`,
    showCancelButton: true,
    confirmButtonText: t.genBtn,
    cancelButtonText: t.cancel,
  });
  if (!confirmed.isConfirmed) return;

  const res  = await fetch('/api/hr/payroll/generate', {
    method: 'POST', headers: JSON_H,
    body: JSON.stringify({ year, month, branch_id: branch || null }),
  });
  const data = await res.json();

  if (data.success) {
    Swal.fire({ icon: 'success', title: t.generated, timer: 2000, showConfirmButton: false });
    loadRuns();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
  }
}

async function viewSlips(runId) {
  const res  = await fetch(`/api/hr/payroll/runs/${runId}/slips`, { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const slips = data.slips ?? [];

  document.getElementById('slipsBody').innerHTML = slips.map(s => {
    const empUrl = s.user_id ? `/hr/employees?open=${s.user_id}` : '/hr/employees';
    return `<tr>
    <td><a href="${empUrl}" class="fw-semibold text-dark text-decoration-none">${esc(s.user?.name ?? '–')}</a></td>
    <td>${fmt(s.basic_salary)}</td>
    <td>${fmt(s.total_allowances)}</td>
    <td>${fmt(s.overtime_pay)}</td>
    <td class="fw-bold">${fmt(s.gross_salary)}</td>
    <td class="text-danger">${fmt(s.income_tax)}</td>
    <td class="text-danger">${fmt(s.social_insurance)}</td>
    <td class="text-danger">${fmt(s.absence_deduction)}</td>
    <td class="fw-bold text-success">${fmt(s.net_salary)}</td>
    <td><button class="btn btn-xs btn-outline-secondary" data-action="print-slip" data-id="${s.id}"><i class="fas fa-print"></i></button></td>
  </tr>`;
  }).join('');

  new bootstrap.Modal(document.getElementById('slipsModal')).show();
}

async function approveRun(id) {
  await fetch(`/api/hr/payroll/runs/${id}/approve`, { method: 'POST', headers: JSON_H });
  loadRuns();
}

async function markPaid(id) {
  await fetch(`/api/hr/payroll/runs/${id}/mark-paid`, { method: 'POST', headers: JSON_H });
  loadRuns();
}

function printSlip(id) { window.open(`/api/hr/payroll/slips/${id}/print`, '_blank'); }

function fmt(n) { return n != null ? Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : '–'; }
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

loadRuns();
</script>
@endpush
