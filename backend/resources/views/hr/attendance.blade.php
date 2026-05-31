@extends('layouts.app')
@section('title', __('pos.hr_attendance'))
@section('page-title', '📋 ' . __('pos.hr_attendance'))

@section('content')

{{-- HR Module Summary --}}
@include('hr._summary')

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4" id="hrAttendanceTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-attendance" data-bs-toggle="tab" data-bs-target="#pane-attendance"
            type="button" role="tab">
      <i class="fas fa-fingerprint me-1"></i>
      {{ app()->getLocale()==='ar' ? 'سجلات الحضور' : 'Attendance Records' }}
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-schedule" data-bs-toggle="tab" data-bs-target="#pane-schedule"
            type="button" role="tab">
      <i class="fas fa-calendar-week me-1"></i>
      {{ app()->getLocale()==='ar' ? 'جدول الوردية' : 'Shift Schedule' }}
    </button>
  </li>
</ul>

<div class="tab-content">

{{-- ════════════════ TAB 1: ATTENDANCE ════════════════ --}}
<div class="tab-pane fade show active" id="pane-attendance" role="tabpanel">

  {{-- Filter Bar --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <label class="form-label mb-0 fw-semibold small">{{ __('pos.work_date') }}</label>
        </div>
        <div class="col-auto">
          <input type="date" id="filterDate" class="form-control form-control-sm"
                 value="{{ date('Y-m-d') }}">
        </div>
        <div class="col-auto">
          <select id="filterBranch" class="form-select form-select-sm" style="min-width:160px">
            <option value="">{{ app()->getLocale()==='ar' ? 'كل الفروع' : 'All Branches' }}</option>
            @php /** @var \App\Models\Branch[] $branches */ @endphp
            @foreach($branches ?? [] as $b)
              <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto">
          <select id="filterStatus" class="form-select form-select-sm">
            <option value="">{{ app()->getLocale()==='ar' ? 'كل الحالات' : 'All Statuses' }}</option>
            <option value="working_now">{{ app()->getLocale()==='ar' ? 'يعمل الآن 🟢' : 'Working Now 🟢' }}</option>
            <option value="checked_out">{{ app()->getLocale()==='ar' ? 'انتهى الدوام ✓' : 'Checked Out ✓' }}</option>
            <option value="present">{{ __('pos.present') }}</option>
            <option value="absent">{{ __('pos.absent') }}</option>
            <option value="late">{{ __('pos.late') }}</option>
            <option value="half_day">{{ __('pos.half_day') }}</option>
            <option value="remote">{{ __('pos.remote') }}</option>
          </select>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="btnRefresh">
            <i class="fas fa-rotate-right me-1"></i>{{ app()->getLocale()==='ar' ? 'تحديث' : 'Refresh' }}
          </button>
          <button class="btn btn-sm btn-outline-success" id="btnManualCheckin">
            <i class="fas fa-user-plus me-1"></i>{{ app()->getLocale()==='ar' ? 'تسجيل يدوي' : 'Manual Entry' }}
          </button>
          <button class="btn btn-sm btn-outline-primary" id="btnExport">
            <i class="fas fa-file-export me-1"></i>{{ app()->getLocale()==='ar' ? 'تصدير' : 'Export' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Summary Badges --}}
  <div class="row g-3 mb-4">
    @foreach([
      ['id'=>'cntWorkingNow','icon'=>'fas fa-circle-dot','color'=>'success','label'=> app()->getLocale()==='ar' ? 'يعملون الآن' : 'Working Now'],
      ['id'=>'cntCheckedOut','icon'=>'fas fa-user-check', 'color'=>'primary','label'=> app()->getLocale()==='ar' ? 'أتمّوا الدوام' : 'Checked Out'],
      ['id'=>'cntAbsent',    'icon'=>'fas fa-user-xmark', 'color'=>'danger', 'label'=> app()->getLocale()==='ar' ? 'غائبون'  : 'Absent'],
      ['id'=>'cntLate',      'icon'=>'fas fa-user-clock', 'color'=>'warning','label'=> app()->getLocale()==='ar' ? 'متأخرون' : 'Late'],
      ['id'=>'cntRemote',    'icon'=>'fas fa-laptop',     'color'=>'info',   'label'=> app()->getLocale()==='ar' ? 'عن بُعد' : 'Remote'],
    ] as $card)
    <div class="col-sm-6 col-md-auto flex-grow-1">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-{{ $card['color'] }}" id="{{ $card['id'] }}">–</div>
          <div class="text-muted small"><i class="{{ $card['icon'] }} me-1"></i>{{ $card['label'] }}</div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Attendance Table --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}</th>
              <th>{{ __('pos.work_date') }}</th>
              <th>{{ __('pos.check_in') }}</th>
              <th>{{ __('pos.check_out') }}</th>
              <th>{{ __('pos.late_minutes') }}</th>
              <th>{{ app()->getLocale()==='ar' ? 'ساعات العمل' : 'Hours' }}</th>
              <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
              <th>{{ app()->getLocale()==='ar' ? 'إجراءات' : 'Actions' }}</th>
            </tr>
          </thead>
          <tbody id="attendanceBody">
            <tr><td colspan="8" class="text-center py-4">
              <div class="spinner-border spinner-border-sm me-2 text-primary"></div>
              {{ app()->getLocale()==='ar' ? 'جاري التحميل…' : 'Loading…' }}
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>{{-- /pane-attendance --}}

{{-- ════════════════ TAB 2: SHIFT SCHEDULE ════════════════ --}}
<div class="tab-pane fade" id="pane-schedule" role="tabpanel">

  {{-- Week Navigator --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <button class="btn btn-sm btn-outline-secondary" id="btnPrevWeek">
            <i class="fas fa-chevron-{{ app()->getLocale()==='ar' ? 'right' : 'left' }}"></i>
          </button>
        </div>
        <div class="col-auto fw-semibold" id="weekLabel">–</div>
        <div class="col-auto">
          <button class="btn btn-sm btn-outline-secondary" id="btnNextWeek">
            <i class="fas fa-chevron-{{ app()->getLocale()==='ar' ? 'left' : 'right' }}"></i>
          </button>
        </div>
        <div class="col-auto">
          <button class="btn btn-sm btn-outline-primary" id="btnThisWeek">
            {{ app()->getLocale()==='ar' ? 'هذا الأسبوع' : 'This Week' }}
          </button>
        </div>
        <div class="col-auto">
          <select id="scheduleBranch" class="form-select form-select-sm" style="min-width:160px">
            <option value="">{{ app()->getLocale()==='ar' ? 'كل الفروع' : 'All Branches' }}</option>
            @foreach($branches ?? [] as $b)
              <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto ms-auto">
          <button class="btn btn-sm btn-success" id="btnAssignShift">
            <i class="fas fa-plus me-1"></i>{{ app()->getLocale()==='ar' ? 'تعيين وردية' : 'Assign Shift' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Schedule Grid --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" id="scheduleTable">
          <thead class="table-light" id="scheduleHead"></thead>
          <tbody id="scheduleBody">
            <tr><td colspan="8" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary me-2"></div>
              {{ app()->getLocale()==='ar' ? 'جاري التحميل…' : 'Loading…' }}
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>{{-- /pane-schedule --}}

</div>{{-- /tab-content --}}

{{-- ════ Manual Check-in Modal ════ --}}
<div class="modal fade" id="modalManualEntry" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-clock me-2"></i>
          {{ app()->getLocale()==='ar' ? 'تسجيل حضور يدوي' : 'Manual Attendance Entry' }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}</label>
          <select id="manualUserId" class="form-select" required></select>
        </div>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold small">{{ __('pos.work_date') }}</label>
            <input type="date" id="manualDate" class="form-control" value="{{ date('Y-m-d') }}">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">{{ __('pos.check_in') }}</label>
            <input type="time" id="manualCheckIn" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">{{ __('pos.check_out') }}</label>
            <input type="time" id="manualCheckOut" class="form-control" placeholder="{{ app()->getLocale()==='ar' ? 'اختياري' : 'Optional' }}">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'الفرع' : 'Branch' }}</label>
            <select id="manualBranch" class="form-select">
              <option value="">{{ app()->getLocale()==='ar' ? 'الكل' : 'All' }}</option>
              @foreach($branches ?? [] as $b)
                <option value="{{ $b->id }}">{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'ملاحظة' : 'Notes' }}</label>
          <textarea id="manualNotes" class="form-control" rows="2"></textarea>
        </div>
        <div id="manualError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}</button>
        <button type="button" class="btn btn-success" id="btnSaveManual">
          <i class="fas fa-save me-1"></i>{{ app()->getLocale()==='ar' ? 'حفظ' : 'Save' }}
        </button>
      </div>
    </div>
  </div>
</div>

{{-- ════ Assign Shift Modal ════ --}}
<div class="modal fade" id="modalAssignShift" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>
          {{ app()->getLocale()==='ar' ? 'تعيين وردية' : 'Assign Shift' }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}</label>
          <select id="assignUserId" class="form-select" required></select>
        </div>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'التاريخ' : 'Date' }}</label>
            <input type="date" id="assignDate" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'الوردية' : 'Shift Template' }}</label>
            <select id="assignTemplate" class="form-select">
              <option value="">{{ app()->getLocale()==='ar' ? '— بدون قالب —' : '— No template —' }}</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">{{ app()->getLocale()==='ar' ? 'الفرع' : 'Branch' }}</label>
            <select id="assignBranch" class="form-select">
              <option value="">{{ app()->getLocale()==='ar' ? 'الكل' : 'Any' }}</option>
              @foreach($branches ?? [] as $b)
                <option value="{{ $b->id }}">{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div id="assignError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}</button>
        <button type="button" class="btn btn-success" id="btnSaveAssign">
          <i class="fas fa-check me-1"></i>{{ app()->getLocale()==='ar' ? 'تعيين' : 'Assign' }}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
// ── Constants ─────────────────────────────────────────────────────────────
const isAr = {{ app()->getLocale()==='ar' ? 'true' : 'false' }};

const statusColors = {
  present:     'success',
  absent:      'danger',
  late:        'warning',
  half_day:    'info',
  remote:      'primary',
  holiday:     'secondary',
};

const statusLabels = {
  present:  '{{ __('pos.present') }}',
  absent:   '{{ __('pos.absent') }}',
  late:     '{{ __('pos.late') }}',
  half_day: '{{ __('pos.half_day') }}',
  remote:   isAr ? 'عن بُعد' : 'Remote',
  holiday:  isAr ? 'إجازة' : 'Holiday',
};

const shiftStatusColors = {
  scheduled: 'secondary',
  active:    'success',
  completed: 'primary',
  missed:    'danger',
  excused:   'warning',
};

// ── Helpers ───────────────────────────────────────────────────────────────
function fmtTime(iso) {
  if (!iso) return '–';
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function fmtDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString(isAr ? 'ar-EG' : 'en-GB', {weekday:'short', day:'2-digit', month:'short'});
}

// ── ATTENDANCE TAB ────────────────────────────────────────────────────────
async function loadAttendance() {
  const date   = document.getElementById('filterDate').value;
  const branch = document.getElementById('filterBranch').value;
  const status = document.getElementById('filterStatus').value;

  const params = new URLSearchParams({ date });
  if (branch) params.append('branch_id', branch);
  if (status) params.append('status', status);

  const tbody = document.getElementById('attendanceBody');
  tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>${isAr?'جاري التحميل…':'Loading…'}</td></tr>`;

  const res  = await fetch('/api/hr/attendance?' + params, { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const rows = data.records ?? [];

  // Summary counters
  let cntWorkingNow = 0, cntCheckedOut = 0, cntAbsent = 0, cntLate = 0, cntRemote = 0;
  rows.forEach(r => {
    if (r.is_working_now) cntWorkingNow++;
    if (r.has_checked_out) cntCheckedOut++;
    if (r.status === 'absent')  cntAbsent++;
    if (r.status === 'late' && r.has_checked_out) cntLate++; // late + done
    if (r.status === 'remote')  cntRemote++;
  });

  document.getElementById('cntWorkingNow').textContent = cntWorkingNow;
  document.getElementById('cntCheckedOut').textContent  = cntCheckedOut;
  document.getElementById('cntAbsent').textContent      = cntAbsent;
  document.getElementById('cntLate').textContent        = cntLate;
  document.getElementById('cntRemote').textContent      = cntRemote;

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">
      <i class="fas fa-calendar-xmark fa-2x d-block mb-2 opacity-25"></i>
      ${isAr?'لا توجد سجلات':'No attendance records'}
    </td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const col   = statusColors[r.status] ?? 'secondary';
    const label = statusLabels[r.status] ?? r.status;
    const late  = r.late_minutes > 0 ? `<span class="text-danger">${r.late_minutes} ${isAr?'د':'min'}</span>` : '–';
    const hours = r.hours_worked ? `${r.hours_worked}h` : '–';
    const empUrl = r.user_id ? `/hr/employees?open=${r.user_id}` : '/hr/employees';

    // Build the cycle badge: working-now vs checked-out vs other
    let cycleBadge;
    if (r.is_working_now) {
      cycleBadge = `<span class="badge bg-success d-inline-flex align-items-center gap-1">
        <span class="pulse-dot"></span>${isAr?'يعمل الآن':'Working Now'}
      </span>`;
    } else if (r.has_checked_out) {
      cycleBadge = `<span class="badge bg-${col}">${label} <i class="fas fa-circle-check ms-1"></i></span>`;
    } else {
      cycleBadge = `<span class="badge bg-${col}">${label}</span>`;
    }

    // Check-out cell: show button if checked-in but not out
    const checkOutCell = r.check_out
      ? fmtTime(r.check_out)
      : (r.check_in
          ? `<button class="btn btn-xs btn-outline-warning py-0 px-1" onclick="doCheckout(${r.user_id},'${r.work_date}')"
               title="${isAr?'تسجيل الخروج':'Check out'}">
               <i class="fas fa-sign-out-alt"></i>
             </button>`
          : '–');

    return `<tr>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar-circle bg-${col} bg-opacity-15 text-${col}">${esc(r.user?.name?.[0] ?? '?')}</div>
          <div>
            <a href="${empUrl}" class="fw-semibold text-dark text-decoration-none">${esc(r.user?.name ?? '–')}</a>
            <div class="text-muted small">${esc(r.branch?.name ?? '')}</div>
          </div>
        </div>
      </td>
      <td>${r.work_date ?? '–'}</td>
      <td>${r.check_in ? fmtTime(r.check_in) : '–'}</td>
      <td>${checkOutCell}</td>
      <td>${late}</td>
      <td>${hours}</td>
      <td>${cycleBadge}</td>
      <td class="text-muted small">${esc(r.notes ?? '')}</td>
    </tr>`;
  }).join('');
}

// Quick checkout directly from table
async function doCheckout(userId, workDate) {
  const time = prompt(isAr ? 'أدخل وقت الخروج (HH:MM):' : 'Enter check-out time (HH:MM):', new Date().toTimeString().slice(0,5));
  if (!time) return;
  const res = await fetch('/api/hr/attendance/checkout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
    body: JSON.stringify({ user_id: userId, work_date: workDate, check_out: time }),
  });
  const d = await res.json();
  if (d.success) { loadAttendance(); }
  else { alert(d.message ?? (isAr ? 'حدث خطأ' : 'Error')); }
}

// ── Event listeners for attendance tab ───────────────────────────────────
document.getElementById('filterDate').addEventListener('change',   loadAttendance);
document.getElementById('filterBranch').addEventListener('change', loadAttendance);
document.getElementById('filterStatus').addEventListener('change', loadAttendance);
document.getElementById('btnRefresh').addEventListener('click',    loadAttendance);
document.getElementById('btnExport').addEventListener('click', () => {
  const date = document.getElementById('filterDate').value;
  window.open('/api/hr/attendance/export?date=' + date, '_blank');
});

// Manual entry modal
const modalManual  = new bootstrap.Modal(document.getElementById('modalManualEntry'));
let employeesList  = [];

async function loadEmployeesForSelect(selId) {
  if (!employeesList.length) {
    const r = await fetch('/api/hr/employees', { headers: { 'Accept': 'application/json' } });
    const d = await r.json();
    employeesList = d.employees ?? [];
  }
  const sel = document.getElementById(selId);
  const cur = sel.value;
  sel.innerHTML = `<option value="">${isAr?'-- اختر موظفاً --':'-- Select employee --'}</option>` +
    employeesList.map(e => `<option value="${e.id}" ${e.id == cur ? 'selected' : ''}>${esc(e.full_name)}</option>`).join('');
}

document.getElementById('btnManualCheckin').addEventListener('click', async () => {
  await loadEmployeesForSelect('manualUserId');
  document.getElementById('manualError').classList.add('d-none');
  modalManual.show();
});

document.getElementById('btnSaveManual').addEventListener('click', async () => {
  const userId   = document.getElementById('manualUserId').value;
  const date     = document.getElementById('manualDate').value;
  const checkIn  = document.getElementById('manualCheckIn').value;
  const checkOut = document.getElementById('manualCheckOut').value;
  const branch   = document.getElementById('manualBranch').value;
  const notes    = document.getElementById('manualNotes').value;
  const errEl    = document.getElementById('manualError');
  errEl.classList.add('d-none');

  if (!userId || !date || !checkIn) {
    errEl.textContent = isAr ? 'الموظف والتاريخ ووقت الدخول مطلوبة.' : 'Employee, date, and check-in time are required.';
    errEl.classList.remove('d-none');
    return;
  }
  const r = await fetch('/api/hr/attendance/checkin', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
    body: JSON.stringify({ user_id: userId, work_date: date, check_in: checkIn, branch_id: branch || null, notes }),
  });
  const d = await r.json();
  if (!d.success) { errEl.textContent = d.message ?? 'Error'; errEl.classList.remove('d-none'); return; }

  // If check-out also provided, save it too
  if (checkOut) {
    await fetch('/api/hr/attendance/checkout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify({ user_id: userId, work_date: date, check_out: checkOut, notes }),
    });
  }
  modalManual.hide();
  loadAttendance();
});

// ── SHIFT SCHEDULE TAB ────────────────────────────────────────────────────
let currentWeekStart = null;

function getThisWeekStart() {
  // Week starts Saturday (Middle-East standard)
  const now = new Date();
  const day = now.getDay(); // 0=Sun, 6=Sat
  const diff = (day >= 6) ? 0 : -(day + 1); // days back to Saturday
  const sat  = new Date(now);
  sat.setDate(now.getDate() + diff);
  return sat.toISOString().slice(0,10);
}

async function loadSchedule() {
  if (!currentWeekStart) currentWeekStart = getThisWeekStart();
  const branch = document.getElementById('scheduleBranch').value;
  const params = new URLSearchParams({ week_start: currentWeekStart });
  if (branch) params.append('branch_id', branch);

  document.getElementById('scheduleBody').innerHTML =
    `<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div></td></tr>`;

  const res  = await fetch('/api/hr/shifts/schedule?' + params, { headers: { 'Accept': 'application/json' } });
  const data = await res.json();

  // Update week label
  document.getElementById('weekLabel').textContent =
    fmtDate(data.week_start) + ' — ' + fmtDate(data.week_end);

  const days   = data.days   ?? [];
  const shifts = data.shifts ?? [];

  // Build employee → day map
  const empMap = {};
  shifts.forEach(s => {
    if (!empMap[s.user_id]) empMap[s.user_id] = { name: s.user_name, shifts: {} };
    empMap[s.user_id].shifts[s.shift_date] = s;
  });

  // Day names header
  const dayNames = isAr
    ? ['سبت','أحد','اثنين','ثلاثاء','أربعاء','خميس','جمعة']
    : ['Sat','Sun','Mon','Tue','Wed','Thu','Fri'];

  document.getElementById('scheduleHead').innerHTML =
    `<tr><th>${isAr?'الموظف':'Employee'}</th>` +
    days.map(d => {
      const dt   = new Date(d + 'T00:00:00');
      const name = dayNames[dt.getDay()];
      const num  = dt.getDate();
      const isToday = d === new Date().toISOString().slice(0,10);
      return `<th class="${isToday ? 'table-primary' : ''}">${name}<br><small>${num}</small></th>`;
    }).join('') + '</tr>';

  const emps = Object.entries(empMap);
  if (!emps.length) {
    document.getElementById('scheduleBody').innerHTML =
      `<tr><td colspan="${days.length + 1}" class="text-center py-5 text-muted">
        <i class="fas fa-calendar-xmark fa-2x d-block mb-2 opacity-25"></i>
        ${isAr?'لا توجد ورديات لهذا الأسبوع':'No shifts scheduled for this week'}
      </td></tr>`;
    return;
  }

  document.getElementById('scheduleBody').innerHTML = emps.map(([uid, emp]) =>
    `<tr>
      <td class="fw-semibold">${esc(emp.name)}</td>` +
    days.map(d => {
      const s = emp.shifts[d];
      if (!s) return `<td class="text-center text-muted opacity-25">–</td>`;
      const col    = shiftStatusColors[s.status] ?? 'secondary';
      const time   = s.template?.start_time
        ? `<div class="text-muted" style="font-size:.7rem">${s.template.start_time}–${s.template.end_time}</div>`
        : '';
      return `<td class="text-center">
        <span class="badge bg-${col} d-block">${esc(s.template?.name ?? s.status)}</span>${time}
      </td>`;
    }).join('') + '</tr>'
  ).join('');
}

// Schedule tab events
document.getElementById('btnPrevWeek').addEventListener('click', () => {
  const d = new Date(currentWeekStart + 'T00:00:00');
  d.setDate(d.getDate() - 7);
  currentWeekStart = d.toISOString().slice(0,10);
  loadSchedule();
});
document.getElementById('btnNextWeek').addEventListener('click', () => {
  const d = new Date(currentWeekStart + 'T00:00:00');
  d.setDate(d.getDate() + 7);
  currentWeekStart = d.toISOString().slice(0,10);
  loadSchedule();
});
document.getElementById('btnThisWeek').addEventListener('click', () => {
  currentWeekStart = getThisWeekStart();
  loadSchedule();
});
document.getElementById('scheduleBranch').addEventListener('change', loadSchedule);

// Load schedule when tab is opened
document.getElementById('tab-schedule').addEventListener('shown.bs.tab', () => {
  if (!currentWeekStart) loadSchedule();
});

// Assign shift modal
const modalAssign = new bootstrap.Modal(document.getElementById('modalAssignShift'));
let shiftTemplates = [];

document.getElementById('btnAssignShift').addEventListener('click', async () => {
  await loadEmployeesForSelect('assignUserId');
  if (!shiftTemplates.length) {
    const r = await fetch('/api/hr/shifts/templates', { headers: { 'Accept': 'application/json' } });
    const d = await r.json();
    shiftTemplates = d.templates ?? [];
    const sel = document.getElementById('assignTemplate');
    sel.innerHTML = `<option value="">${isAr?'— بدون قالب —':'— No template —'}</option>` +
      shiftTemplates.map(t => `<option value="${t.id}">${esc(t.name)} (${t.start_time}–${t.end_time})</option>`).join('');
  }
  document.getElementById('assignDate').value = currentWeekStart ?? getThisWeekStart();
  document.getElementById('assignError').classList.add('d-none');
  modalAssign.show();
});

document.getElementById('btnSaveAssign').addEventListener('click', async () => {
  const userId     = document.getElementById('assignUserId').value;
  const date       = document.getElementById('assignDate').value;
  const templateId = document.getElementById('assignTemplate').value;
  const branch     = document.getElementById('assignBranch').value;
  const errEl      = document.getElementById('assignError');
  errEl.classList.add('d-none');

  if (!userId || !date) {
    errEl.textContent = isAr ? 'الموظف والتاريخ مطلوبان.' : 'Employee and date are required.';
    errEl.classList.remove('d-none');
    return;
  }
  const r = await fetch('/api/hr/shifts/schedule', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
    body: JSON.stringify({ user_id: userId, shift_date: date, shift_template_id: templateId || null, branch_id: branch || null }),
  });
  const d = await r.json();
  if (!d.success) { errEl.textContent = d.message ?? 'Error'; errEl.classList.remove('d-none'); return; }
  modalAssign.hide();
  loadSchedule();
});

// ── Initial load ──────────────────────────────────────────────────────────
loadAttendance();
</script>

<style @nonce>
.avatar-circle {
  width: 34px; height: 34px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .8rem; flex-shrink: 0;
}
/* Pulsing green dot for "Working Now" */
.pulse-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #fff;
  animation: pulse-anim 1.4s ease-in-out infinite;
  flex-shrink: 0;
}
@keyframes pulse-anim {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: .5; transform: scale(1.3); }
}
.btn-xs { font-size: .7rem; }
#scheduleTable th, #scheduleTable td { min-width: 80px; }
</style>
@endpush
