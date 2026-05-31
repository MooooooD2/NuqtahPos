@extends('layouts.app')
@section('title',      app()->getLocale() === 'ar' ? 'الموظفون' : 'Employees')
@section('page-title', '👥 ' . (app()->getLocale() === 'ar' ? 'إدارة الموظفين' : 'Employee Management'))

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

{{-- HR Module Summary --}}
@include('hr._summary')

{{-- ─── Filters ──────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input type="text" id="empSearch" class="form-control"
                 placeholder="{{ $isAr ? 'بحث باسم أو اسم المستخدم…' : 'Search by name or username…' }}">
        </div>
      </div>
      <div class="col-md-3">
        <select id="empBranch" class="form-select form-select-sm">
          <option value="">{{ $isAr ? 'كل الفروع' : 'All Branches' }}</option>
          @foreach($branches as $b)
            <option value="{{ $b->id }}">{{ $b->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select id="empStatus" class="form-select form-select-sm">
          <option value="">{{ $isAr ? 'الكل' : 'All' }}</option>
          <option value="active">{{ $isAr ? 'نشط' : 'Active' }}</option>
          <option value="inactive">{{ $isAr ? 'غير نشط' : 'Inactive' }}</option>
        </select>
      </div>
      <div class="col-md-3 text-end">
        <button class="btn btn-sm btn-outline-secondary" id="btnRefreshEmp">
          <i class="fas fa-rotate-right me-1"></i>{{ $isAr ? 'تحديث' : 'Refresh' }}
        </button>
        <span class="badge bg-secondary ms-2" id="empCount">–</span>
      </div>
    </div>
  </div>
</div>

{{-- ─── Employees Table ──────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light sticky-top">
          <tr>
            <th>{{ $isAr ? 'الموظف' : 'Employee' }}</th>
            <th>{{ $isAr ? 'الدور' : 'Role' }}</th>
            <th>{{ $isAr ? 'الفرع' : 'Branch' }}</th>
            <th>{{ $isAr ? 'الراتب الأساسي' : 'Basic Salary' }}</th>
            <th>{{ $isAr ? 'إجمالي البدلات' : 'Total Allowances' }}</th>
            <th>{{ $isAr ? 'الحالة' : 'Status' }}</th>
            <th>{{ $isAr ? 'إجراءات' : 'Actions' }}</th>
          </tr>
        </thead>
        <tbody id="empBody">
          <tr>
            <td colspan="7" class="text-center py-5">
              <div class="spinner-border spinner-border-sm text-primary me-2"></div>
              {{ $isAr ? 'جاري التحميل…' : 'Loading…' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ─── Employee Profile Modal (Tabbed) ─────────────────────────────────── --}}
<div class="modal fade" id="empModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      {{-- Header --}}
      <div class="modal-header bg-primary text-white py-2">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar-circle-lg bg-white text-primary fw-bold" id="modalEmpInitial">?</div>
          <div>
            <h6 class="mb-0 fw-bold" id="modalEmpName">—</h6>
            <div class="small opacity-75" id="modalEmpRole">—</div>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
      </div>

      {{-- Tab navigation --}}
      <div class="modal-body p-0">
        <ul class="nav nav-tabs border-bottom px-3 pt-2 bg-light" id="empModalTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tabSalaryBtn" data-bs-toggle="tab" data-bs-target="#tabSalary" role="tab">
              <i class="fas fa-money-bill-wave me-1 text-primary"></i>{{ $isAr ? 'الراتب' : 'Salary' }}
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabLeavesBtn" data-bs-toggle="tab" data-bs-target="#tabLeaves" role="tab">
              <i class="fas fa-umbrella-beach me-1 text-warning"></i>{{ $isAr ? 'الإجازات' : 'Leaves' }}
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabAttendanceBtn" data-bs-toggle="tab" data-bs-target="#tabAttendance" role="tab">
              <i class="fas fa-calendar-check me-1 text-success"></i>{{ $isAr ? 'الحضور' : 'Attendance' }}
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tabPayrollBtn" data-bs-toggle="tab" data-bs-target="#tabPayroll" role="tab">
              <i class="fas fa-file-invoice-dollar me-1 text-info"></i>{{ $isAr ? 'مسير الراتب' : 'Payroll' }}
            </button>
          </li>
        </ul>

        <div class="tab-content p-3">

          {{-- ── Salary Tab ─────────────────────────────────────────────── --}}
          <div class="tab-pane fade show active" id="tabSalary" role="tabpanel">
            <form id="salaryForm">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold">
                    {{ $isAr ? 'الراتب الأساسي' : 'Basic Salary' }} <span class="text-danger">*</span>
                  </label>
                  <input type="number" class="form-control" id="sBasic" step="0.01" min="0" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'بدل السكن' : 'Housing Allowance' }}</label>
                  <input type="number" class="form-control" id="sHousing" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'بدل المواصلات' : 'Transport Allowance' }}</label>
                  <input type="number" class="form-control" id="sTransport" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'بدل الوجبات' : 'Meal Allowance' }}</label>
                  <input type="number" class="form-control" id="sMeal" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'بدلات أخرى' : 'Other Allowances' }}</label>
                  <input type="number" class="form-control" id="sOther" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'معامل الوقت الإضافي' : 'Overtime Multiplier' }}</label>
                  <input type="number" class="form-control" id="sOvertime" step="0.1" min="1" max="5" value="1.5">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'العملة' : 'Currency' }}</label>
                  <input type="text" class="form-control font-monospace text-uppercase" id="sCurrency"
                         maxlength="3" value="EGP" placeholder="EGP">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">{{ $isAr ? 'تاريخ السريان' : 'Effective From' }}</label>
                  <input type="date" class="form-control" id="sEffective" value="{{ date('Y-m-d') }}">
                </div>
              </div>

              <div class="alert alert-success mt-3 py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ $isAr ? 'إجمالي الراتب المتوقع:' : 'Expected Gross Salary:' }}</span>
                <span class="fs-5 fw-bold" id="grossPreview">0.00</span>
              </div>
            </form>
          </div>

          {{-- ── Leaves Tab ─────────────────────────────────────────────── --}}
          <div class="tab-pane fade" id="tabLeaves" role="tabpanel">
            {{-- Balance cards --}}
            <div id="empLeaveBalance" class="row g-2 text-center mb-3">
              <div class="col-12 text-muted small py-2">
                <div class="spinner-border spinner-border-sm"></div>
              </div>
            </div>
            {{-- History table --}}
            <h6 class="fw-semibold text-muted small mb-2">
              <i class="fas fa-history me-1"></i>{{ $isAr ? 'سجل الإجازات' : 'Leave History' }}
            </h6>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto">
              <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>{{ $isAr ? 'النوع' : 'Type' }}</th>
                    <th>{{ $isAr ? 'من' : 'From' }}</th>
                    <th>{{ $isAr ? 'إلى' : 'To' }}</th>
                    <th>{{ $isAr ? 'أيام' : 'Days' }}</th>
                    <th>{{ $isAr ? 'الحالة' : 'Status' }}</th>
                  </tr>
                </thead>
                <tbody id="empLeaveHistory">
                  <tr><td colspan="5" class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm"></div>
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>

          {{-- ── Attendance Tab ──────────────────────────────────────────── --}}
          <div class="tab-pane fade" id="tabAttendance" role="tabpanel">
            <div class="table-responsive" style="max-height:380px;overflow-y:auto">
              <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>{{ $isAr ? 'التاريخ' : 'Date' }}</th>
                    <th>{{ $isAr ? 'الدخول' : 'In' }}</th>
                    <th>{{ $isAr ? 'الخروج' : 'Out' }}</th>
                    <th>{{ $isAr ? 'ساعات' : 'Hours' }}</th>
                    <th>{{ $isAr ? 'تأخير' : 'Late' }}</th>
                    <th>{{ $isAr ? 'الحالة' : 'Status' }}</th>
                  </tr>
                </thead>
                <tbody id="empAttendBody">
                  <tr><td colspan="6" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>

          {{-- ── Payroll Tab ─────────────────────────────────────────────── --}}
          <div class="tab-pane fade" id="tabPayroll" role="tabpanel">
            <div class="table-responsive" style="max-height:380px;overflow-y:auto">
              <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>{{ $isAr ? 'الفترة' : 'Period' }}</th>
                    <th>{{ $isAr ? 'الراتب الأساسي' : 'Basic' }}</th>
                    <th>{{ $isAr ? 'الإجمالي' : 'Gross' }}</th>
                    <th>{{ $isAr ? 'الخصومات' : 'Deductions' }}</th>
                    <th>{{ $isAr ? 'الصافي' : 'Net' }}</th>
                    <th>{{ $isAr ? 'الحالة' : 'Status' }}</th>
                  </tr>
                </thead>
                <tbody id="empPayrollBody">
                  <tr><td colspan="6" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>{{-- /tab-content --}}
      </div>{{-- /modal-body --}}

      {{-- Footer --}}
      <div class="modal-footer">
        <a href="{{ route('hr.attendance') }}" class="btn btn-outline-secondary btn-sm me-auto">
          <i class="fas fa-calendar-check me-1"></i>{{ $isAr ? 'سجل الحضور' : 'Attendance Log' }}
        </a>
        <button class="btn btn-secondary" data-bs-dismiss="modal">{{ $isAr ? 'إغلاق' : 'Close' }}</button>
        <button class="btn btn-primary" id="saveSalaryBtn">
          <i class="fas fa-save me-2"></i>{{ $isAr ? 'حفظ الراتب' : 'Save Salary' }}
        </button>
      </div>

    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
const _isAr = LOCALE === 'ar';
let _currentEmpId = null;
let _allEmployees  = [];

// ── Status label helpers ──────────────────────────────────────────────────
const attColors  = { present:'success', absent:'danger', late:'warning', half_day:'info', remote:'primary' };
const attLabels  = {
  present:'{{ __('pos.present') }}', absent:'{{ __('pos.absent') }}',
  late:'{{ __('pos.late') }}', half_day:'{{ __('pos.half_day') }}', remote:'{{ __('pos.remote') }}'
};
const leaveColors = { pending:'warning', approved:'success', rejected:'danger', cancelled:'secondary' };
const leaveLabels = {
  pending:  '{{ __('pos.leave_pending') }}',  approved: '{{ __('pos.leave_approved') }}',
  rejected: '{{ __('pos.leave_rejected') }}', cancelled:'{{ __('pos.leave_cancelled') }}'
};
const typeLabels = { annual:'{{ __('pos.annual_leave') }}', sick:'{{ __('pos.sick_leave') }}', unpaid:'{{ __('pos.unpaid_leave') }}' };

// ── Gross preview ─────────────────────────────────────────────────────────
function updateGross() {
    const sum = ['sBasic','sHousing','sTransport','sMeal','sOther']
        .reduce((t, id) => t + (parseFloat(document.getElementById(id).value) || 0), 0);
    document.getElementById('grossPreview').textContent = sum.toLocaleString('en', {minimumFractionDigits:2, maximumFractionDigits:2});
}
['sBasic','sHousing','sTransport','sMeal','sOther'].forEach(id =>
    document.getElementById(id).addEventListener('input', updateGross)
);

// ── Load employees list ───────────────────────────────────────────────────
async function loadEmployees() {
    const params = new URLSearchParams();
    const search = document.getElementById('empSearch').value.trim();
    const branch = document.getElementById('empBranch').value;
    const status = document.getElementById('empStatus').value;
    if (search) params.append('search', search);
    if (branch) params.append('branch_id', branch);
    if (status) params.append('status', status);

    const tbody = document.getElementById('empBody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4">
        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
        ${_isAr ? 'جاري التحميل…' : 'Loading…'}
    </td></tr>`;

    try {
        const res  = await fetch('/api/hr/employees?' + params, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
        });
        const data = await res.json();
        _allEmployees = data.employees ?? [];

        document.getElementById('empCount').textContent = _allEmployees.length;

        if (!_allEmployees.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
                <i class="fas fa-users fa-2x d-block mb-2 opacity-25"></i>
                ${_isAr ? 'لا يوجد موظفون' : 'No employees found'}
            </td></tr>`;
            return;
        }

        tbody.innerHTML = _allEmployees.map(e => {
            const allowances = (parseFloat(e.housing_allowance)||0)
                + (parseFloat(e.transport_allowance)||0)
                + (parseFloat(e.meal_allowance)||0)
                + (parseFloat(e.other_allowances)||0);
            const hasSalary  = e.basic_salary !== null;
            const statusBadge = e.is_active
                ? `<span class="badge bg-success">${_isAr ? 'نشط' : 'Active'}</span>`
                : `<span class="badge bg-secondary">${_isAr ? 'غير نشط' : 'Inactive'}</span>`;

            return `<tr>
                <td>
                    <a class="fw-semibold text-dark text-decoration-none" href="#"
                       data-action="open-profile" data-id="${e.id}">
                        <div>${esc(e.full_name)}</div>
                        <div class="text-muted small">${esc(e.username)}</div>
                    </a>
                </td>
                <td><span class="badge bg-light text-dark border">${esc(e.role ?? '–')}</span></td>
                <td>${esc(e.branch_name ?? '–')}</td>
                <td>${hasSalary
                    ? `<span class="fw-semibold text-success">${parseFloat(e.basic_salary).toLocaleString('en',{minimumFractionDigits:2})}</span>`
                    : `<span class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>${_isAr ? 'غير محدد' : 'Not set'}</span>`}
                </td>
                <td>${hasSalary ? allowances.toLocaleString('en',{minimumFractionDigits:2}) : '–'}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1"
                            data-action="open-profile" data-id="${e.id}">
                        <i class="fas fa-user-circle me-1"></i>${_isAr ? 'ملف الموظف' : 'Profile'}
                    </button>
                    <button class="btn btn-sm ${e.is_active ? 'btn-outline-warning' : 'btn-outline-success'}"
                            data-action="toggle-status" data-id="${e.id}" data-active="${e.is_active ? '1' : '0'}">
                        <i class="fas fa-${e.is_active ? 'ban' : 'check'} me-1"></i>
                        ${e.is_active ? (_isAr ? 'تعطيل' : 'Deactivate') : (_isAr ? 'تفعيل' : 'Activate')}
                    </button>
                </td>
            </tr>`;
        }).join('');

        // Auto-open if ?open=ID in URL
        const openId = new URLSearchParams(location.search).get('open');
        if (openId) {
            const emp = _allEmployees.find(e => String(e.id) === openId);
            if (emp) openEmpModal(emp);
        }

    } catch (_) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">
            ${_isAr ? 'فشل التحميل' : 'Failed to load'}
        </td></tr>`;
    }
}

// ── Event delegation (table buttons) ─────────────────────────────────────
document.getElementById('empBody').addEventListener('click', async function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    if (btn.dataset.action === 'open-profile') {
        e.preventDefault();
        const emp = _allEmployees.find(x => String(x.id) === btn.dataset.id);
        if (emp) openEmpModal(emp);
        return;
    }

    if (btn.dataset.action === 'toggle-status') {
        const id     = parseInt(btn.dataset.id);
        const active = btn.dataset.active === '1';
        const msg    = active
            ? (_isAr ? 'تعطيل هذا الموظف؟' : 'Deactivate this employee?')
            : (_isAr ? 'تفعيل هذا الموظف؟' : 'Activate this employee?');
        if (!confirm(msg)) return;
        btn.disabled = true;
        const res = await fetch(`/api/hr/employees/${id}/toggle`, {
            method: 'PATCH',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
        });
        if ((await res.json()).success) loadEmployees();
        else btn.disabled = false;
    }
});

// ── Open employee profile modal ────────────────────────────────────────────
function openEmpModal(emp) {
    _currentEmpId = emp.id;
    const initials = (emp.full_name ?? '?').split(/\s+/).slice(0,2).map(w => w[0]).join('').toUpperCase();
    document.getElementById('modalEmpInitial').textContent = initials;
    document.getElementById('modalEmpName').textContent    = emp.full_name ?? '—';
    document.getElementById('modalEmpRole').textContent    = [emp.role, emp.branch_name].filter(Boolean).join(' · ') || '—';

    // Fill salary form
    document.getElementById('sBasic').value     = emp.basic_salary    ?? '';
    document.getElementById('sHousing').value   = emp.housing_allowance   ?? 0;
    document.getElementById('sTransport').value = emp.transport_allowance ?? 0;
    document.getElementById('sMeal').value      = emp.meal_allowance      ?? 0;
    document.getElementById('sOther').value     = emp.other_allowances    ?? 0;
    document.getElementById('sOvertime').value  = emp.overtime_rate_multiplier ?? 1.5;
    document.getElementById('sCurrency').value  = emp.currency_code  ?? 'EGP';
    document.getElementById('sEffective').value = emp.effective_from ?? '{{ date('Y-m-d') }}';
    document.getElementById('saveSalaryBtn').disabled = false;
    document.getElementById('saveSalaryBtn').innerHTML = '<i class="fas fa-save me-2"></i>' +
        (_isAr ? 'حفظ الراتب' : 'Save Salary');
    updateGross();

    // Reset leave/attendance/payroll panels
    document.getElementById('empLeaveBalance').innerHTML  = spinnerHtml();
    document.getElementById('empLeaveHistory').innerHTML  = `<tr><td colspan="5" class="text-center py-3">${spinnerHtml()}</td></tr>`;
    document.getElementById('empAttendBody').innerHTML    = `<tr><td colspan="6" class="text-center py-4">${spinnerHtml()}</td></tr>`;
    document.getElementById('empPayrollBody').innerHTML   = `<tr><td colspan="6" class="text-center py-4">${spinnerHtml()}</td></tr>`;

    // Switch to salary tab
    bootstrap.Tab.getOrCreateInstance(document.getElementById('tabSalaryBtn')).show();

    // Load all tab data
    loadEmpLeaves(emp.id);
    loadEmpAttendance(emp.id);
    loadEmpPayroll(emp.id);

    new bootstrap.Modal(document.getElementById('empModal')).show();
}

function spinnerHtml() {
    return '<div class="spinner-border spinner-border-sm text-primary"></div>';
}

// ── Show/hide Save button based on active tab ─────────────────────────────
document.querySelectorAll('#empModalTabs button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
        document.getElementById('saveSalaryBtn').classList.toggle('d-none', e.target.id !== 'tabSalaryBtn');
    });
});

// ── Load leave tab ────────────────────────────────────────────────────────
async function loadEmpLeaves(id) {
    try {
        const res  = await fetch(`/api/hr/employees/${id}/leaves`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
        });
        const data = await res.json();
        const b    = data.balance ?? {};
        const annualRemain = (b.annual_allowed ?? 21) - (b.annual_taken ?? 0);
        const sickRemain   = (b.sick_allowed   ?? 10) - (b.sick_taken  ?? 0);

        document.getElementById('empLeaveBalance').innerHTML = `
            <div class="col-4">
                <div class="fs-4 fw-bold text-success">${annualRemain}</div>
                <div class="text-muted small">${_isAr ? 'سنوي متبقي' : 'Annual Left'}</div>
                <div class="text-muted" style="font-size:.72rem">${_isAr ? 'مستخدم:' : 'Used:'} ${b.annual_taken??0}/${b.annual_allowed??21}</div>
            </div>
            <div class="col-4">
                <div class="fs-4 fw-bold text-warning">${sickRemain}</div>
                <div class="text-muted small">${_isAr ? 'مرضي متبقي' : 'Sick Left'}</div>
                <div class="text-muted" style="font-size:.72rem">${_isAr ? 'مستخدم:' : 'Used:'} ${b.sick_taken??0}/${b.sick_allowed??10}</div>
            </div>
            <div class="col-4">
                <div class="fs-4 fw-bold text-danger">${(b.annual_taken??0)+(b.sick_taken??0)}</div>
                <div class="text-muted small">${_isAr ? 'إجمالي مستخدم' : 'Total Used'}</div>
                <div class="text-muted" style="font-size:.72rem">${new Date().getFullYear()}</div>
            </div>`;

        const reqs  = data.requests ?? [];
        const tbody = document.getElementById('empLeaveHistory');
        if (!reqs.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">
                <i class="fas fa-umbrella-beach opacity-25 me-1"></i>${_isAr ? 'لا توجد طلبات' : 'No requests'}
            </td></tr>`;
            return;
        }
        tbody.innerHTML = reqs.map(r => `<tr>
            <td>${typeLabels[r.leave_type] ?? r.leave_type}</td>
            <td>${r.starts_at ?? '–'}</td>
            <td>${r.ends_at ?? '–'}</td>
            <td><span class="badge bg-light text-dark border">${r.days_count ?? '–'}</span></td>
            <td><span class="badge bg-${leaveColors[r.status]??'secondary'}">${leaveLabels[r.status]??r.status}</span></td>
        </tr>`).join('');

    } catch (_) {
        document.getElementById('empLeaveBalance').innerHTML =
            `<div class="col-12 text-muted small py-1">${_isAr ? 'تعذر تحميل البيانات' : 'Failed to load'}</div>`;
    }
}

// ── Load attendance tab ───────────────────────────────────────────────────
async function loadEmpAttendance(id) {
    const tbody = document.getElementById('empAttendBody');
    try {
        const res  = await fetch(`/api/hr/employees/${id}/attendance`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
        });
        const data = await res.json();
        const rows = data.records ?? [];
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">
                <i class="fas fa-calendar-xmark fa-2x d-block mb-2 opacity-25"></i>
                ${_isAr ? 'لا توجد سجلات' : 'No records'}
            </td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const col   = attColors[r.status] ?? 'secondary';
            const label = attLabels[r.status] ?? r.status;
            const late  = r.late_minutes > 0 ? `<span class="text-danger">${r.late_minutes}m</span>` : '–';
            const hours = r.hours_worked ? `${r.hours_worked}h` : '–';
            const cin   = r.check_in ? fmtTime(r.check_in) : '–';
            const cout  = r.check_out ? fmtTime(r.check_out) : '–';
            return `<tr>
                <td class="fw-semibold">${r.work_date ?? '–'}</td>
                <td>${cin}</td><td>${cout}</td>
                <td>${hours}</td><td>${late}</td>
                <td><span class="badge bg-${col}">${label}</span></td>
            </tr>`;
        }).join('');
    } catch (_) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${_isAr ? 'فشل التحميل' : 'Failed to load'}</td></tr>`;
    }
}

// ── Load payroll tab ──────────────────────────────────────────────────────
async function loadEmpPayroll(id) {
    const tbody = document.getElementById('empPayrollBody');
    const runColors = { draft:'secondary', approved:'primary', paid:'success' };
    const runLabels = { draft:'{{ app()->getLocale()==='ar' ? 'مسودة' : 'Draft' }}', approved:'{{ app()->getLocale()==='ar' ? 'معتمد' : 'Approved' }}', paid:'{{ app()->getLocale()==='ar' ? 'مدفوع' : 'Paid' }}' };
    try {
        const res  = await fetch(`/api/hr/employees/${id}/payroll`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
        });
        const data = await res.json();
        const slips = data.slips ?? [];
        if (!slips.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">
                <i class="fas fa-file-invoice-dollar fa-2x d-block mb-2 opacity-25"></i>
                ${_isAr ? 'لا توجد مسيرات بعد' : 'No payroll runs yet'}
            </td></tr>`;
            return;
        }
        tbody.innerHTML = slips.map(s => {
            const col   = runColors[s.run_status] ?? 'secondary';
            const label = runLabels[s.run_status] ?? s.run_status;
            const deductions = (parseFloat(s.income_tax)||0) + (parseFloat(s.social_insurance)||0) + (parseFloat(s.absence_deduction)||0);
            return `<tr>
                <td class="fw-semibold">${s.year}/${String(s.month).padStart(2,'0')}</td>
                <td>${fmt(s.basic_salary)}</td>
                <td class="fw-bold">${fmt(s.gross_salary)}</td>
                <td class="text-danger">${fmt(deductions)}</td>
                <td class="fw-bold text-success">${fmt(s.net_salary)}</td>
                <td><span class="badge bg-${col}">${label}</span></td>
            </tr>`;
        }).join('');
    } catch (_) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${_isAr ? 'فشل التحميل' : 'Failed to load'}</td></tr>`;
    }
}

// ── Save salary ───────────────────────────────────────────────────────────
document.getElementById('saveSalaryBtn').addEventListener('click', async function () {
    if (!_currentEmpId) return;
    const basic = parseFloat(document.getElementById('sBasic').value);
    if (!basic || basic <= 0) {
        showToast(_isAr ? 'الراتب الأساسي مطلوب' : 'Basic salary is required', 'danger');
        document.getElementById('sBasic').focus();
        return;
    }
    this.disabled = true;
    this.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${_isAr ? 'جاري الحفظ…' : 'Saving…'}`;

    try {
        const res = await fetch(`/api/hr/employees/${_currentEmpId}/salary`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            credentials: 'same-origin',
            body: JSON.stringify({
                basic_salary:             basic,
                housing_allowance:        parseFloat(document.getElementById('sHousing').value)   || 0,
                transport_allowance:      parseFloat(document.getElementById('sTransport').value)  || 0,
                meal_allowance:           parseFloat(document.getElementById('sMeal').value)       || 0,
                other_allowances:         parseFloat(document.getElementById('sOther').value)      || 0,
                overtime_rate_multiplier: parseFloat(document.getElementById('sOvertime').value)   || 1.5,
                currency_code:            document.getElementById('sCurrency').value.trim().toUpperCase() || 'EGP',
                effective_from:           document.getElementById('sEffective').value || null,
            }),
        });
        const data = await res.json();
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('empModal')).hide();
            showToast(_isAr ? '✅ تم حفظ الراتب' : '✅ Salary saved', 'success');
            loadEmployees();
        } else {
            showToast(data.message ?? (_isAr ? 'فشل الحفظ' : 'Save failed'), 'danger');
        }
    } catch (_) {
        showToast(_isAr ? 'خطأ في الاتصال' : 'Connection error', 'danger');
    } finally {
        this.disabled = false;
        this.innerHTML = `<i class="fas fa-save me-2"></i>${_isAr ? 'حفظ الراتب' : 'Save Salary'}`;
    }
});

// ── Filter listeners ──────────────────────────────────────────────────────
let _searchTimer;
document.getElementById('empSearch').addEventListener('input', function () {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(loadEmployees, 400);
});
document.getElementById('empBranch').addEventListener('change', loadEmployees);
document.getElementById('empStatus').addEventListener('change', loadEmployees);
document.getElementById('btnRefreshEmp').addEventListener('click', loadEmployees);

// ── Helpers ───────────────────────────────────────────────────────────────
function fmtTime(iso) { return new Date(iso).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }); }
function fmt(n)       { return n != null ? Number(n).toLocaleString('en', {minimumFractionDigits:2, maximumFractionDigits:2}) : '–'; }
function esc(s)       { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

loadEmployees();
</script>

<style>
.avatar-circle-lg {
  width: 42px; height: 42px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
}
</style>
@endpush
