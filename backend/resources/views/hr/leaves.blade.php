@extends('layouts.app')
@section('title', __('pos.leave_requests'))
@section('page-title', '🌴 ' . __('pos.leaves'))

@section('content')

{{-- HR Module Summary --}}
@include('hr._summary')

<div class="row g-4">

  {{-- Apply Leave Form --}}
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-plus-circle me-2 text-success"></i>{{ __('pos.apply_leave') }}</h6>
      </div>
      <div class="card-body">
        <form id="leaveForm">
          @csrf

          {{-- Employee selector (admins only) --}}
          @if(auth()->user()->hasAnyPermission(['manage_settings','manage_roles']))
          <div class="mb-3">
            <label class="form-label fw-semibold">
              <i class="fas fa-user me-1 text-primary"></i>
              {{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}
            </label>
            <select id="leaveEmpId" name="user_id" class="form-select" required>
              <option value="">{{ app()->getLocale()==='ar' ? 'اختر الموظف…' : 'Select employee…' }}</option>
            </select>
            <div class="form-text text-muted">
              <i class="fas fa-info-circle me-1"></i>
              {{ app()->getLocale()==='ar'
                  ? 'الطلبات المُقدَّمة من قِبَل المدير تُعتمَد تلقائياً'
                  : 'Requests submitted by managers are auto-approved' }}
            </div>
          </div>
          @endif

          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('pos.leave_type') }}</label>
            <select name="leave_type" id="leaveType" class="form-select" required>
              <option value="">{{ app()->getLocale()==='ar' ? 'اختر النوع' : 'Select type…' }}</option>
              <option value="annual">{{ __('pos.annual_leave') }}</option>
              <option value="sick">{{ __('pos.sick_leave') }}</option>
              <option value="unpaid">{{ __('pos.unpaid_leave') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('pos.leave_start') }}</label>
            <input type="date" name="starts_at" id="leaveStart" class="form-control" required
                   min="{{ date('Y-m-d') }}">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('pos.leave_end') }}</label>
            <input type="date" name="ends_at" id="leaveEnd" class="form-control" required
                   min="{{ date('Y-m-d') }}">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('pos.leave_days') }}</label>
            <input type="number" id="leaveDays" class="form-control bg-light" readonly placeholder="–">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ __('pos.leave_reason') }}</label>
            <textarea name="reason" class="form-control" rows="3"
                      placeholder="{{ app()->getLocale()==='ar' ? 'سبب الإجازة…' : 'Reason for leave…' }}"></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-paper-plane me-2"></i>{{ app()->getLocale()==='ar' ? 'تقديم الطلب' : 'Submit Request' }}
          </button>
        </form>
      </div>
    </div>

    {{-- Leave Balance --}}
    <div class="card border-0 shadow-sm mt-3">
      <div class="card-header bg-transparent d-flex align-items-center gap-2">
        <h6 class="mb-0 fw-semibold flex-grow-1">
          <i class="fas fa-calendar-check me-2 text-info"></i>{{ app()->getLocale()==='ar' ? 'رصيد الإجازات' : 'Leave Balance' }}
        </h6>
        <span id="balanceHeader" class="badge bg-primary bg-opacity-10 text-primary small fw-normal"></span>
      </div>
      <div class="card-body" id="leaveBalance">
        <div class="text-center text-muted py-2">
          <div class="spinner-border spinner-border-sm"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Requests Table --}}
  <div class="col-lg-8">
    {{-- Filter --}}
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <select id="filterStatus" class="form-select form-select-sm" style="width:auto">
        <option value="">{{ app()->getLocale()==='ar' ? 'كل الحالات' : 'All Statuses' }}</option>
        <option value="pending">{{ __('pos.leave_pending') }}</option>
        <option value="approved">{{ __('pos.leave_approved') }}</option>
        <option value="rejected">{{ __('pos.leave_rejected') }}</option>
        <option value="cancelled">{{ __('pos.leave_cancelled') }}</option>
      </select>
      <select id="filterType" class="form-select form-select-sm" style="width:auto">
        <option value="">{{ app()->getLocale()==='ar' ? 'كل الأنواع' : 'All Types' }}</option>
        <option value="annual">{{ __('pos.annual_leave') }}</option>
        <option value="sick">{{ __('pos.sick_leave') }}</option>
        <option value="unpaid">{{ __('pos.unpaid_leave') }}</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnRefreshLeaves">
        <i class="fas fa-rotate-right me-1"></i>{{ app()->getLocale()==='ar' ? 'تحديث' : 'Refresh' }}
      </button>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>{{ app()->getLocale()==='ar' ? 'الموظف' : 'Employee' }}</th>
                <th>{{ __('pos.leave_type') }}</th>
                <th>{{ __('pos.leave_start') }}</th>
                <th>{{ __('pos.leave_end') }}</th>
                <th>{{ __('pos.leave_days') }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'إجراءات' : 'Actions' }}</th>
              </tr>
            </thead>
            <tbody id="requestsBody">
              <tr><td colspan="7" class="text-center py-4">
                <div class="spinner-border spinner-border-sm me-2 text-primary"></div>
                {{ app()->getLocale()==='ar' ? 'جاري التحميل…' : 'Loading…' }}
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
const CSRF  = document.querySelector('meta[name=csrf-token]').content;
const JSON_H = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };

const leaveColors = { pending:'warning', approved:'success', rejected:'danger', cancelled:'secondary' };
const leaveLabels = {
  pending:   '{{ __('pos.leave_pending') }}',
  approved:  '{{ __('pos.leave_approved') }}',
  rejected:  '{{ __('pos.leave_rejected') }}',
  cancelled: '{{ __('pos.leave_cancelled') }}',
};
const typeLabels = {
  annual: '{{ __('pos.annual_leave') }}',
  sick:   '{{ __('pos.sick_leave') }}',
  unpaid: '{{ __('pos.unpaid_leave') }}',
};
const t = {
  noLeaves:  '{{ app()->getLocale()==='ar' ? 'لا توجد طلبات إجازة' : 'No leave requests' }}',
  days:      '{{ app()->getLocale()==='ar' ? 'يوم' : 'days' }}',
  cancel:    '{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}',
  submitted: '{{ app()->getLocale()==='ar' ? 'تم تقديم الطلب' : 'Request Submitted' }}',
};
const isAdmin = @json(auth()->user()->hasAnyPermission(['manage_settings','manage_roles']));

// ── Load employees into selector (admin only) ─────────────────────────────
const empSel = document.getElementById('leaveEmpId');
if (isAdmin && empSel) {
  (async () => {
    try {
      const d = await fetch('/api/hr/employees?status=active', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        credentials: 'same-origin',
      }).then(r => r.json());
      const emps = d.employees ?? [];
      emps.forEach(emp => {
        const opt = document.createElement('option');
        opt.value = emp.id;
        opt.textContent = emp.full_name + (emp.branch_name ? ' — ' + emp.branch_name : '');
        opt.dataset.name = emp.full_name;
        empSel.appendChild(opt);
      });
    } catch (_) {}
  })();

  // When employee changes → reload their leave balance card
  empSel.addEventListener('change', () => {
    const id = empSel.value;
    if (id) loadBalance(parseInt(id));
    else    loadBalance();
  });
}

// ── Auto-calc days ─────────────────────────────────────────────────────────
function calcDays() {
  const s = document.getElementById('leaveStart').value;
  const e = document.getElementById('leaveEnd').value;
  if (s && e) {
    const days = Math.round((new Date(e) - new Date(s)) / 86400000) + 1;
    document.getElementById('leaveDays').value = days > 0 ? days : '–';
  }
}

// ── Event listeners (no inline handlers) ─────────────────────────────────
document.getElementById('leaveStart').addEventListener('change', () => {
  document.getElementById('leaveEnd').min = document.getElementById('leaveStart').value;
  calcDays();
});
document.getElementById('leaveEnd').addEventListener('change', calcDays);
document.getElementById('filterStatus').addEventListener('change', loadRequests);
document.getElementById('filterType').addEventListener('change', loadRequests);
document.getElementById('btnRefreshLeaves').addEventListener('click', loadRequests);

// ── Event delegation for action buttons ───────────────────────────────────
document.getElementById('requestsBody').addEventListener('click', async e => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  await actionLeave(btn.dataset.id, btn.dataset.action);
});

// ── Submit leave ───────────────────────────────────────────────────────────
document.getElementById('leaveForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  // Validate employee selection (admin)
  if (isAdmin && empSel && !empSel.value) {
    Swal.fire({ icon: 'warning', title: '{{ app()->getLocale()==='ar' ? 'اختر الموظف' : 'Select an employee' }}' });
    empSel.focus();
    return;
  }

  const fd   = new FormData(e.target);
  const body = Object.fromEntries(fd);
  // Remove CSRF token from body (it's in headers)
  delete body['_token'];

  const submitBtn = e.target.querySelector('[type=submit]');
  submitBtn.disabled = true;
  const origHtml = submitBtn.innerHTML;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ app()->getLocale()==='ar' ? 'جاري الإرسال…' : 'Submitting…' }}';

  const res  = await fetch('/api/hr/leaves', {
    method: 'POST', headers: JSON_H,
    body: JSON.stringify(body),
  });
  const data = await res.json();
  submitBtn.disabled = false;
  submitBtn.innerHTML = origHtml;

  if (data.success) {
    const empName = (isAdmin && empSel?.selectedOptions[0]?.dataset.name)
      ? empSel.selectedOptions[0].dataset.name
      : '';
    Swal.fire({ icon: 'success', title: t.submitted, text: empName || undefined, timer: 2500, showConfirmButton: false });
    e.target.reset();
    document.getElementById('leaveDays').value = '';
    if (isAdmin && empSel) empSel.value = '';
    loadRequests();
    loadBalance();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message ?? JSON.stringify(data.errors ?? '') });
  }
});

// ── Load requests ──────────────────────────────────────────────────────────
async function loadRequests() {
  const status = document.getElementById('filterStatus').value;
  const type   = document.getElementById('filterType').value;
  const params = new URLSearchParams();
  if (status) params.append('status', status);
  if (type)   params.append('type', type);

  const res  = await fetch('/api/hr/leaves?' + params, { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const rows = data.requests ?? [];
  const tbody = document.getElementById('requestsBody');

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
      <i class="fas fa-umbrella-beach fa-2x d-block mb-2 opacity-25"></i>${t.noLeaves}
    </td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const col   = leaveColors[r.status] ?? 'secondary';
    const label = leaveLabels[r.status] ?? r.status;
    const type  = typeLabels[r.leave_type] ?? r.leave_type;

    let actions = '–';
    if (isAdmin && r.status === 'pending') {
      actions = `<button class="btn btn-xs btn-success me-1" data-action="approve" data-id="${r.id}"><i class="fas fa-check"></i></button>
                 <button class="btn btn-xs btn-danger" data-action="reject" data-id="${r.id}"><i class="fas fa-times"></i></button>`;
    } else if (!isAdmin && r.status === 'pending') {
      actions = `<button class="btn btn-xs btn-outline-secondary" data-action="cancel" data-id="${r.id}">${t.cancel}</button>`;
    }

    const empUrl = r.user_id ? `/hr/employees?open=${r.user_id}` : '/hr/employees';
    return `<tr>
      <td><a href="${empUrl}" class="fw-semibold text-dark text-decoration-none">${esc(r.user?.name ?? '–')}</a></td>
      <td>${type}</td>
      <td>${r.starts_at ?? '–'}</td>
      <td>${r.ends_at ?? '–'}</td>
      <td><span class="badge bg-light text-dark border">${r.days_count ?? '–'} ${t.days}</span></td>
      <td><span class="badge bg-${col}">${label}</span></td>
      <td>${actions}</td>
    </tr>`;
  }).join('');
}

async function actionLeave(id, action) {
  const res  = await fetch(`/api/hr/leaves/${id}/${action}`, {
    method: 'POST', headers: JSON_H,
  });
  const data = await res.json();
  if (data.success) { loadRequests(); loadBalance(); }
  else Swal.fire({ icon: 'error', title: 'Error', text: data.message });
}

// ── Leave balance (optional empId for admin viewing other employees) ────────
async function loadBalance(empId = null) {
  const el = document.getElementById('leaveBalance');
  const headerEl = document.getElementById('balanceHeader');
  el.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div></div>';

  let bal = {};
  try {
    if (isAdmin && empId) {
      // Admin viewing another employee — use the employee-specific endpoint
      const d = await fetch(`/api/hr/employees/${empId}/leaves`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        credentials: 'same-origin',
      }).then(r => r.json());
      const b = d.balance ?? {};
      bal = {
        annual_remaining: (b.annual_allowed ?? 21) - (b.annual_taken ?? 0),
        sick_remaining:   (b.sick_allowed   ?? 10) - (b.sick_taken  ?? 0),
        total_taken:      (b.annual_taken ?? 0) + (b.sick_taken ?? 0),
      };
      if (headerEl && empSel?.selectedOptions[0]) {
        headerEl.textContent = empSel.selectedOptions[0].dataset.name ?? '';
      }
    } else {
      const d = await fetch('/api/hr/leaves/balance', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(r => r.json());
      bal = d.balance ?? {};
      if (headerEl) headerEl.textContent = '';
    }
  } catch (_) {}

  el.innerHTML = `
    <div class="row g-2 text-center">
      <div class="col-4">
        <div class="fs-4 fw-bold text-success">${bal.annual_remaining ?? '–'}</div>
        <div class="text-muted small">{{ __('pos.annual_leave') }}</div>
      </div>
      <div class="col-4">
        <div class="fs-4 fw-bold text-warning">${bal.sick_remaining ?? '–'}</div>
        <div class="text-muted small">{{ __('pos.sick_leave') }}</div>
      </div>
      <div class="col-4">
        <div class="fs-4 fw-bold text-info">${bal.total_taken ?? 0}</div>
        <div class="text-muted small">{{ app()->getLocale()==='ar' ? 'مستخدم' : 'Used' }}</div>
      </div>
    </div>`;
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

loadRequests();
loadBalance();
</script>
@endpush
