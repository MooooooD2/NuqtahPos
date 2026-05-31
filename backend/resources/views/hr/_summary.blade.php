{{-- HR Module Summary Widget — included at the top of every HR page --}}
@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="card border-0 shadow-sm mb-4" id="hrSummaryCard">
  <div class="card-body py-2 px-3">
    <div class="row g-0 text-center divide-x">

      <div class="col-6 col-md-3">
        <a href="{{ route('hr.employees') }}"
           class="d-block text-decoration-none py-2 px-1 rounded {{ request()->routeIs('hr.employees') ? 'bg-primary bg-opacity-10' : '' }} hr-sum-link">
          <div class="fs-4 fw-bold text-primary lh-1 mb-1" id="hsSumEmp">
            <span class="spinner-border spinner-border-sm opacity-50"></span>
          </div>
          <div class="small text-muted">
            <i class="fas fa-users me-1"></i>{{ $isAr ? 'الموظفون' : 'Staff' }}
          </div>
        </a>
      </div>

      <div class="col-6 col-md-3">
        <a href="{{ route('hr.attendance') }}"
           class="d-block text-decoration-none py-2 px-1 rounded {{ request()->routeIs('hr.attendance') ? 'bg-success bg-opacity-10' : '' }} hr-sum-link">
          <div class="fs-4 fw-bold text-success lh-1 mb-1" id="hsSumPresent">
            <span class="spinner-border spinner-border-sm opacity-50"></span>
          </div>
          <div class="small text-muted">
            <i class="fas fa-user-check me-1"></i>{{ $isAr ? 'حاضرون اليوم' : 'Present Today' }}
          </div>
        </a>
      </div>

      <div class="col-6 col-md-3">
        <a href="{{ route('hr.leaves') }}"
           class="d-block text-decoration-none py-2 px-1 rounded {{ request()->routeIs('hr.leaves') ? 'bg-warning bg-opacity-10' : '' }} hr-sum-link">
          <div class="fs-4 fw-bold text-warning lh-1 mb-1" id="hsSumLeaves">
            <span class="spinner-border spinner-border-sm opacity-50"></span>
          </div>
          <div class="small text-muted">
            <i class="fas fa-umbrella-beach me-1"></i>{{ $isAr ? 'إجازات معلقة' : 'Pending Leaves' }}
          </div>
        </a>
      </div>

      <div class="col-6 col-md-3">
        <a href="{{ route('hr.payroll') }}"
           class="d-block text-decoration-none py-2 px-1 rounded {{ request()->routeIs('hr.payroll') ? 'bg-info bg-opacity-10' : '' }} hr-sum-link">
          <div class="fs-4 fw-bold text-info lh-1 mb-1" id="hsSumPayroll">
            <span class="spinner-border spinner-border-sm opacity-50"></span>
          </div>
          <div class="small text-muted">
            <i class="fas fa-money-bill-wave me-1"></i>{{ $isAr ? 'آخر مسير' : 'Last Payroll' }}
          </div>
        </a>
      </div>

    </div>
  </div>
</div>

@push('scripts')
<script @nonce>
(async function loadHrSummary() {
  try {
    const d = await fetch('/api/hr/summary', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json());
    const el = id => document.getElementById(id);
    if (el('hsSumEmp'))     el('hsSumEmp').textContent    = d.total_employees ?? '–';
    if (el('hsSumPresent')) el('hsSumPresent').textContent = d.today_present != null
      ? (d.today_total ? `${d.today_present}/${d.today_total}` : d.today_present)
      : '–';
    if (el('hsSumLeaves'))  el('hsSumLeaves').textContent  = d.pending_leaves ?? '–';
    if (el('hsSumPayroll')) el('hsSumPayroll').textContent = d.last_payroll
      ? `${d.last_payroll.year}/${String(d.last_payroll.month).padStart(2,'0')}`
      : '–';
  } catch (_) {
    ['hsSumEmp','hsSumPresent','hsSumLeaves','hsSumPayroll'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = '–';
    });
  }
})();
</script>
@endpush

@once
@push('styles')
<style>
.hr-sum-link:hover { background: rgba(0,0,0,.04) !important; }
.divide-x > div + div { border-left: 1px solid rgba(0,0,0,.08); }
</style>
@endpush
@endonce
