@extends('layouts.app')
@section('title', __('pos.my_shift'))
@section('page-title', '🕐 ' . __('pos.my_shift'))

@section('content')

<div class="row g-4">

  {{-- Active Shift Card --}}
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100" id="shiftCard">

      {{-- No shift --}}
      <div id="noShiftPanel" class="{{ $shift ? 'd-none' : '' }}">
        <div class="card-body text-center py-5">
          <div style="font-size:4rem" class="mb-3">🌙</div>
          <h5 class="fw-bold">{{ __('pos.no_active_shift') }}</h5>
          <p class="text-muted mb-4">{{ app()->getLocale()==='ar' ? 'لست مسجلاً في وردية حالياً.' : 'You are not clocked in.' }}</p>
          <button class="btn btn-success btn-lg px-5" id="btnClockIn">
            <i class="fas fa-sign-in-alt me-2"></i>{{ __('pos.clock_in') }}
          </button>
        </div>
      </div>

      {{-- Active shift --}}
      <div id="activeShiftPanel" class="{{ $shift ? '' : 'd-none' }}">
        <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
          <span><i class="fas fa-circle-play me-2"></i>{{ __('pos.active_shift') }}</span>
          <span class="badge bg-white text-success fw-bold" id="shiftTimer">00:00:00</span>
        </div>
        <div class="card-body">

          {{-- Clock-in time --}}
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px">
              <i class="fas fa-door-open text-success"></i>
            </div>
            <div>
              <div class="text-muted small">{{ __('pos.clock_in_at') }}</div>
              <div class="fw-semibold" id="clockInTime">{{ $shift ? $shift->clock_in_at?->format('H:i:s') : '–' }}</div>
            </div>
          </div>

          {{-- Break status --}}
          <div class="d-flex align-items-center gap-3 mb-4" id="breakStatus" style="display:none!important">
            <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px">
              <i class="fas fa-pause text-warning"></i>
            </div>
            <div>
              <div class="text-muted small">{{ __('pos.on_break') }}</div>
              <div class="fw-semibold" id="breakTimer">–</div>
            </div>
          </div>

          {{-- Action buttons --}}
          <div class="d-grid gap-2">
            <button id="btnBreak" class="btn btn-warning">
              <i class="fas fa-pause me-2"></i>{{ __('pos.break_start') }}
            </button>
            <button class="btn btn-danger" id="btnClockOut">
              <i class="fas fa-sign-out-alt me-2"></i>{{ __('pos.clock_out') }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- History --}}
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-history me-2 text-primary"></i>{{ __('pos.shift_history') }}</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>{{ __('pos.shift_date') }}</th>
                <th>{{ __('pos.clock_in_at') }}</th>
                <th>{{ __('pos.clock_out_at') }}</th>
                <th>{{ __('pos.hours_worked') }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($history as $h)
              <tr>
                <td>{{ $h->shift_date->format('Y-m-d') }}</td>
                <td>{{ $h->clock_in_at?->format('H:i') ?? '–' }}</td>
                <td>{{ $h->clock_out_at?->format('H:i') ?? '–' }}</td>
                <td>
                  @if($h->hours_worked)
                    <span class="badge bg-light text-dark border">{{ number_format($h->hours_worked, 1) }}h</span>
                  @else –
                  @endif
                </td>
                <td>
                  @php $statusColors = ['active'=>'success','completed'=>'secondary','missed'=>'danger','scheduled'=>'info','excused'=>'warning'] @endphp
                  <span class="badge bg-{{ $statusColors[$h->status] ?? 'secondary' }}">
                    {{ __('pos.' . $h->status) !== 'pos.' . $h->status ? __('pos.' . $h->status) : ucfirst($h->status) }}
                  </span>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center py-5 text-muted">
                  <i class="fas fa-clock fa-2x d-block mb-2 opacity-25"></i>
                  {{ app()->getLocale()==='ar' ? 'لا يوجد سجل ورديات' : 'No shift history' }}
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Clock-out modal --}}
<div class="modal fade" id="clockOutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="fas fa-sign-out-alt me-2 text-danger"></i>{{ __('pos.clock_out') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ __('pos.cash_collected') }}</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-money-bill"></i></span>
            <input type="number" id="cashCollected" class="form-control" min="0" step="0.01" placeholder="0.00">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ __('pos.cashier_note') }}</label>
          <textarea id="cashierNote" class="form-control" rows="3" placeholder="{{ app()->getLocale()==='ar' ? 'ملاحظة اختيارية…' : 'Optional note…' }}"></textarea>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}</button>
        <button type="button" class="btn btn-danger" id="btnSubmitClockOut">
          <i class="fas fa-sign-out-alt me-1"></i>{{ __('pos.clock_out') }}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
let shiftStartTime = @json($shift?->clock_in_at?->toIso8601String());
let breakStartTime = null;
let onBreak = false;

// ── Timer ──────────────────────────────────────────────────────────────────
function updateTimer() {
  if (!shiftStartTime) return;
  const elapsed = Date.now() - new Date(shiftStartTime);
  const h = Math.floor(elapsed / 3600000);
  const m = Math.floor((elapsed % 3600000) / 60000);
  const s = Math.floor((elapsed % 60000) / 1000);
  document.getElementById('shiftTimer').textContent =
    [h, m, s].map(n => String(n).padStart(2, '0')).join(':');

  if (onBreak && breakStartTime) {
    const bElapsed = Date.now() - new Date(breakStartTime);
    const bm = Math.floor(bElapsed / 60000);
    const bs = Math.floor((bElapsed % 60000) / 1000);
    document.getElementById('breakTimer').textContent = `${bm}m ${bs}s`;
  }
}
setInterval(updateTimer, 1000);
updateTimer();

// ── Button event listeners (no inline handlers) ───────────────────────────
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const JSON_H = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };

document.getElementById('btnClockIn')?.addEventListener('click',        clockIn);
document.getElementById('btnBreak')?.addEventListener('click',          toggleBreak);
document.getElementById('btnClockOut')?.addEventListener('click',       () => new bootstrap.Modal(document.getElementById('clockOutModal')).show());
document.getElementById('btnSubmitClockOut')?.addEventListener('click', submitClockOut);

// ── Clock In ───────────────────────────────────────────────────────────────
async function clockIn() {
  const btn = document.getElementById('btnClockIn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ app()->getLocale()==='ar' ? 'جاري…' : 'Working…' }}';

  const res  = await fetch('/api/shifts/clock-in', {
    method: 'POST', headers: JSON_H, body: JSON.stringify({}),
  });
  const data = await res.json();

  if (data.success) {
    shiftStartTime = data.shift.clock_in_at;
    document.getElementById('clockInTime').textContent = new Date(shiftStartTime).toLocaleTimeString();
    document.getElementById('noShiftPanel').classList.add('d-none');
    document.getElementById('activeShiftPanel').classList.remove('d-none');
    Swal.fire({ icon: 'success', title: '{{ app()->getLocale()==='ar' ? 'تم تسجيل حضورك' : 'Clocked In!' }}', timer: 1800, showConfirmButton: false });
  } else {
    Swal.fire({ icon: 'error', title: '{{ app()->getLocale()==='ar' ? 'خطأ' : 'Error' }}', text: data.message });
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>{{ __('pos.clock_in') }}';
  }
}

// ── Break ──────────────────────────────────────────────────────────────────
async function toggleBreak() {
  const url = onBreak ? '/api/shifts/break/end' : '/api/shifts/break/start';
  const res  = await fetch(url, {
    method: 'POST', headers: JSON_H, body: JSON.stringify({ type: 'rest' }),
  });
  const data = await res.json();

  if (data.success) {
    onBreak = !onBreak;
    breakStartTime = onBreak ? data.break?.started_at : null;

    const breakStatus = document.getElementById('breakStatus');
    const btnBreak    = document.getElementById('btnBreak');

    if (onBreak) {
      breakStatus.style.removeProperty('display');
      btnBreak.innerHTML = '<i class="fas fa-play me-2"></i>{{ __('pos.break_end') }}';
      btnBreak.classList.replace('btn-warning', 'btn-success');
    } else {
      breakStatus.style.display = 'none !important';
      btnBreak.innerHTML = '<i class="fas fa-pause me-2"></i>{{ __('pos.break_start') }}';
      btnBreak.classList.replace('btn-success', 'btn-warning');
    }
  }
}

// ── Clock Out ──────────────────────────────────────────────────────────────
async function submitClockOut() {
  const res  = await fetch('/api/shifts/clock-out', {
    method: 'POST', headers: JSON_H,
    body: JSON.stringify({
      cash_collected: parseFloat(document.getElementById('cashCollected').value || 0),
      cashier_note:   document.getElementById('cashierNote').value,
    }),
  });
  const data = await res.json();

  if (data.success) {
    bootstrap.Modal.getInstance(document.getElementById('clockOutModal')).hide();
    shiftStartTime = null;
    document.getElementById('noShiftPanel').classList.remove('d-none');
    document.getElementById('activeShiftPanel').classList.add('d-none');

    const diff = data.shift.cash_difference ?? 0;
    const icon = Math.abs(diff) < 0.01 ? 'success' : (diff < 0 ? 'warning' : 'info');
    Swal.fire({
      icon,
      title: '{{ app()->getLocale()==='ar' ? 'تم الانصراف' : 'Clocked Out' }}',
      html: `{{ app()->getLocale()==='ar' ? 'ساعات العمل:' : 'Hours worked:' }} <b>${data.shift.hours_worked ?? '–'}h</b><br>
             {{ app()->getLocale()==='ar' ? 'فرق النقدية:' : 'Cash diff:' }} <b class="${diff < 0 ? 'text-danger' : 'text-success'}">${diff.toFixed(2)}</b>`,
    }).then(() => location.reload());
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
  }
}
</script>
@endpush
