@extends('layouts.app')
@section('title', __('pos.shift_management'))
@section('page-title', '👥 ' . __('pos.shift_management'))

@section('content')

{{-- Summary Cards --}}
<div class="row g-3 mb-4" id="shiftSummary">
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body">
        <div class="fs-2 fw-bold text-success" id="cntActive">–</div>
        <div class="text-muted small">{{ app()->getLocale()==='ar' ? 'ورديات نشطة الآن' : 'Active Now' }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body">
        <div class="fs-2 fw-bold text-primary" id="cntToday">–</div>
        <div class="text-muted small">{{ app()->getLocale()==='ar' ? 'إجمالي اليوم' : 'Today Total' }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body">
        <div class="fs-2 fw-bold text-warning" id="cntBreak">–</div>
        <div class="text-muted small">{{ app()->getLocale()==='ar' ? 'في استراحة' : 'On Break' }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body">
        <div class="fs-2 fw-bold text-info" id="totalHours">–</div>
        <div class="text-muted small">{{ app()->getLocale()==='ar' ? 'إجمالي ساعات العمل' : 'Total Hours Worked' }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Active Shifts Table --}}
<div class="card shadow-sm border-0 mb-4">
  <div class="card-header d-flex align-items-center justify-content-between bg-transparent">
    <h6 class="mb-0 fw-semibold"><i class="fas fa-users-clock me-2 text-primary"></i>{{ __('pos.all_shifts') }}</h6>
    <button class="btn btn-sm btn-outline-secondary" id="btnRefreshShifts">
      <i class="fas fa-rotate-right me-1"></i>{{ app()->getLocale()==='ar' ? 'تحديث' : 'Refresh' }}
    </button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'الفرع' : 'Branch' }}</th>
            <th>{{ __('pos.clock_in_at') }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'مدة الوردية' : 'Duration' }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
            <th>{{ app()->getLocale()==='ar' ? 'إجراءات' : 'Actions' }}</th>
          </tr>
        </thead>
        <tbody id="shiftsTableBody">
          <tr><td colspan="6" class="text-center py-4 text-muted">
            <div class="spinner-border spinner-border-sm me-2"></div>
            {{ app()->getLocale()==='ar' ? 'جاري التحميل…' : 'Loading…' }}
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
async function loadShifts() {
  const res  = await fetch('/api/shifts/active', { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const shifts = data.shifts ?? [];

  // Summary
  let onBreak = 0;
  let totalMins = 0;
  shifts.forEach(s => {
    if (s.breaks?.some(b => !b.ended_at)) onBreak++;
    if (s.clock_in_at) {
      totalMins += Math.floor((Date.now() - new Date(s.clock_in_at)) / 60000);
    }
  });

  document.getElementById('cntActive').textContent  = shifts.length;
  document.getElementById('cntToday').textContent   = shifts.length;
  document.getElementById('cntBreak').textContent   = onBreak;
  document.getElementById('totalHours').textContent = (totalMins / 60).toFixed(1) + 'h';

  // Table rows
  const tbody = document.getElementById('shiftsTableBody');
  if (!shifts.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">
      <i class="fas fa-moon fa-2x d-block mb-2 opacity-25"></i>
      {{ app()->getLocale()==='ar' ? 'لا توجد ورديات نشطة حالياً' : 'No active shifts right now' }}
    </td></tr>`;
    return;
  }

  tbody.innerHTML = shifts.map(s => {
    const sinceMs   = s.clock_in_at ? Date.now() - new Date(s.clock_in_at) : 0;
    const hrs       = Math.floor(sinceMs / 3600000);
    const mins      = Math.floor((sinceMs % 3600000) / 60000);
    const isBreak   = s.breaks?.some(b => !b.ended_at);
    const statusBadge = isBreak
      ? '<span class="badge bg-warning text-dark"><i class="fas fa-pause me-1"></i>{{ __('pos.on_break') }}</span>'
      : '<span class="badge bg-success"><i class="fas fa-play me-1"></i>{{ app()->getLocale()==='ar' ? 'نشط' : 'Active' }}</span>';

    return `<tr>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:34px;height:34px;font-size:.8rem">
            ${esc(s.user?.name?.[0] ?? '?')}
          </div>
          <div>
            <div class="fw-semibold">${esc(s.user?.name ?? '–')}</div>
            <div class="text-muted small">${esc(s.user?.email ?? '')}</div>
          </div>
        </div>
      </td>
      <td><span class="badge bg-secondary">${esc(s.branch?.name ?? '–')}</span></td>
      <td>${s.clock_in_at ? new Date(s.clock_in_at).toLocaleTimeString() : '–'}</td>
      <td><span class="badge bg-light text-dark border">${hrs}h ${mins}m</span></td>
      <td>${statusBadge}</td>
      <td>
        <button class="btn btn-xs btn-outline-danger" data-action="force-clockout" data-id="${s.id}" title="{{ app()->getLocale()==='ar' ? 'إنهاء الوردية' : 'End Shift' }}">
          <i class="fas fa-stop"></i>
        </button>
      </td>
    </tr>`;
  }).join('');
}

// ── Event delegation on table body ────────────────────────────────────────
document.getElementById('shiftsTableBody').addEventListener('click', async e => {
  const btn = e.target.closest('[data-action="force-clockout"]');
  if (!btn) return;
  const confirmed = await Swal.fire({
    icon: 'warning',
    title: '{{ app()->getLocale()==='ar' ? 'تأكيد إنهاء الوردية؟' : 'Confirm end shift?' }}',
    showCancelButton: true,
    confirmButtonText: '{{ app()->getLocale()==='ar' ? 'إنهاء' : 'End Shift' }}',
    confirmButtonColor: '#dc3545',
    cancelButtonText: '{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}',
  });
  if (confirmed.isConfirmed) await loadShifts();
});

// ── Static button listener ─────────────────────────────────────────────────
document.getElementById('btnRefreshShifts').addEventListener('click', loadShifts);

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

loadShifts();
// Auto-refresh every 60 seconds
setInterval(loadShifts, 60_000);
</script>
@endpush
