@extends('layouts.app')
@php $isAr = app()->getLocale() === 'ar'; @endphp
@section('title', __('pos.pricing_rules'))
@section('page-title', '💰 ' . __('pos.pricing_rules'))

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <p class="text-muted mb-0">
    {{ $isAr ? 'إعداد قواعد التسعير الزمنية والكمية والذكية.' : 'Configure time-based, bulk, and smart pricing rules.' }}
  </p>
  <button class="btn btn-primary" id="newRuleBtn" data-bs-toggle="modal" data-bs-target="#ruleModal">
    <i class="fas fa-plus me-1"></i> {{ __('pos.pricing_new_rule') }}
  </button>
</div>

{{-- Happy Hour Banner --}}
<div id="happyHourBanner" class="alert alert-warning d-none mb-3">
  {{ __('pos.pricing_happy_hour_active') }}
</div>

{{-- Rules Table --}}
<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ $isAr ? 'الاسم' : 'Name' }}</th>
            <th>{{ $isAr ? 'النوع' : 'Type' }}</th>
            <th>{{ $isAr ? 'الخصم' : 'Discount' }}</th>
            <th>{{ $isAr ? 'الجدول الزمني' : 'Schedule' }}</th>
            <th>{{ $isAr ? 'الأولوية' : 'Priority' }}</th>
            <th>{{ $isAr ? 'الحالة' : 'Status' }}</th>
            <th>{{ __('pos.pricing_active_now') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="rulesTable">
          <tr>
            <td colspan="8" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary me-2"></div>
              {{ $isAr ? 'جاري التحميل…' : 'Loading…' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ── Rule Modal ── --}}
<div class="modal fade" id="ruleModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ruleModalTitle">
          {{ $isAr ? 'قاعدة تسعير جديدة' : 'New Pricing Rule' }}
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ruleId">
        <div class="row g-3">

          {{-- Name --}}
          <div class="col-md-8">
            <label class="form-label fw-semibold">{{ $isAr ? 'اسم القاعدة' : 'Rule Name' }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="ruleName"
              placeholder="{{ $isAr ? 'مثال: ساعة السعادة 3–5 م' : 'e.g. Happy Hour 3–5 PM' }}">
          </div>

          {{-- Priority --}}
          <div class="col-md-4">
            <label class="form-label fw-semibold">{{ $isAr ? 'الأولوية (1–100)' : 'Priority (1–100)' }}</label>
            <input type="number" class="form-control" id="rulePriority" value="10" min="1" max="100">
          </div>

          {{-- Rule Type --}}
          <div class="col-md-4">
            <label class="form-label fw-semibold">{{ $isAr ? 'نوع القاعدة' : 'Rule Type' }}</label>
            <select class="form-select" id="ruleType">
              <option value="happy_hour">⏰ {{ __('pos.pricing_happy_hour') }}</option>
              <option value="bulk_discount">📦 {{ __('pos.pricing_bulk_discount') }}</option>
              <option value="day_of_week">📅 {{ __('pos.pricing_day_of_week') }}</option>
              <option value="category">🏷 {{ __('pos.pricing_category') }}</option>
              <option value="flat_price">💲 {{ __('pos.pricing_flat_price') }}</option>
            </select>
          </div>

          {{-- Discount Type --}}
          <div class="col-md-4">
            <label class="form-label fw-semibold">{{ $isAr ? 'نوع الخصم' : 'Discount Type' }}</label>
            <select class="form-select" id="discountType">
              <option value="percentage">{{ $isAr ? 'نسبة مئوية (%)' : 'Percentage (%)' }}</option>
              <option value="fixed_amount">{{ $isAr ? 'مبلغ ثابت' : 'Fixed Amount' }}</option>
              <option value="new_price">{{ $isAr ? 'سعر جديد' : 'New Price' }}</option>
            </select>
          </div>

          {{-- Discount Value --}}
          <div class="col-md-4">
            <label class="form-label fw-semibold">{{ $isAr ? 'قيمة الخصم' : 'Discount Value' }}</label>
            <input type="number" class="form-control" id="discountValue" step="0.01" min="0"
              placeholder="{{ $isAr ? 'مثال: 15' : 'e.g. 15' }}">
          </div>

          {{-- Time fields (happy_hour / day_of_week) --}}
          <div class="col-md-6 time-fields">
            <label class="form-label fw-semibold">{{ $isAr ? 'وقت البداية' : 'Start Time' }}</label>
            <input type="time" class="form-control" id="timeStart">
          </div>
          <div class="col-md-6 time-fields">
            <label class="form-label fw-semibold">{{ $isAr ? 'وقت النهاية' : 'End Time' }}</label>
            <input type="time" class="form-control" id="timeEnd">
          </div>

          {{-- Days of week --}}
          <div class="col-12 dow-fields">
            <label class="form-label fw-semibold">{{ $isAr ? 'أيام الأسبوع' : 'Days of Week' }}</label>
            <div class="d-flex gap-3 flex-wrap" id="dowCheckboxes">
              @php
                $days = $isAr
                  ? ['الاثنين'=>1,'الثلاثاء'=>2,'الأربعاء'=>3,'الخميس'=>4,'الجمعة'=>5,'السبت'=>6,'الأحد'=>7]
                  : ['Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6,'Sun'=>7];
              @endphp
              @foreach($days as $label => $val)
              <div class="form-check form-check-inline">
                <input class="form-check-input dow-cb" type="checkbox" value="{{ $val }}" id="dow{{ $val }}">
                <label class="form-check-label" for="dow{{ $val }}">{{ $label }}</label>
              </div>
              @endforeach
            </div>
          </div>

          {{-- Date range --}}
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ $isAr ? 'صالح من (اختياري)' : 'Valid From (optional)' }}</label>
            <input type="date" class="form-control" id="validFrom">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ $isAr ? 'صالح حتى (اختياري)' : 'Valid Until (optional)' }}</label>
            <input type="date" class="form-control" id="validUntil">
          </div>

          {{-- Bulk min qty --}}
          <div class="col-md-4 bulk-fields">
            <label class="form-label fw-semibold">{{ $isAr ? 'الحد الأدنى للكمية' : 'Min Quantity' }}</label>
            <input type="number" class="form-control" id="minQty" step="0.01" min="0"
              placeholder="{{ $isAr ? 'مثال: 5' : 'e.g. 5' }}">
          </div>

          {{-- Checkboxes --}}
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="ruleActive" checked>
              <label class="form-check-label" for="ruleActive">{{ $isAr ? 'نشطة' : 'Active' }}</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="ruleStackable">
              <label class="form-check-label" for="ruleStackable">
                {{ $isAr ? 'قابلة للتراكم (دمج القواعد)' : 'Stackable (combine rules)' }}
              </label>
            </div>
          </div>

          {{-- Description --}}
          <div class="col-12">
            <label class="form-label fw-semibold">{{ $isAr ? 'الوصف (اختياري)' : 'Description (optional)' }}</label>
            <input type="text" class="form-control" id="ruleDesc"
              placeholder="{{ $isAr ? 'ملاحظة داخلية…' : 'Internal description…' }}">
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          {{ $isAr ? 'إلغاء' : 'Cancel' }}
        </button>
        <button class="btn btn-primary" id="saveRuleBtn">
          <i class="fas fa-save me-1"></i>{{ $isAr ? 'حفظ القاعدة' : 'Save Rule' }}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
const IS_AR = {{ $isAr ? 'true' : 'false' }};
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;
const API   = '/api/pricing-rules';

// Translated strings injected at render time
const T = {
  newRule:        IS_AR ? 'قاعدة تسعير جديدة'      : 'New Pricing Rule',
  editRule:       IS_AR ? 'تعديل القاعدة'           : 'Edit Rule',
  active:         IS_AR ? 'نشطة'                    : 'Active',
  inactive:       IS_AR ? 'غير نشطة'                : 'Inactive',
  activeNow:      IS_AR ? '✓ الآن'                  : '✓ Now',
  edit:           IS_AR ? 'تعديل'                   : 'Edit',
  disable:        IS_AR ? 'تعطيل'                   : 'Disable',
  enable:         IS_AR ? 'تفعيل'                   : 'Enable',
  del:            IS_AR ? 'حذف'                     : 'Del',
  noRules:        IS_AR ? 'لا توجد قواعد بعد'       : 'No rules yet',
  deleteConfirm:  IS_AR ? 'هل تريد حذف هذه القاعدة؟' : 'Delete this rule?',
  deleteTitle:    IS_AR ? 'تأكيد الحذف'             : 'Confirm Delete',
  cannotUndo:     IS_AR ? 'لا يمكن التراجع عن هذا الإجراء.' : 'This action cannot be undone.',
  yesDelete:      IS_AR ? 'نعم، احذف'               : 'Yes, delete',
  cancel:         IS_AR ? 'إلغاء'                   : 'Cancel',
  saveError:      IS_AR ? 'حدث خطأ عند الحفظ'      : 'Error saving rule',
  off:            IS_AR ? ' خصم'                    : ' off',
  newPrice:       IS_AR ? ' (سعر جديد)'             : ' (new)',
  days:           IS_AR ? 'الأيام: '                : 'Days: ',
  happyHour:      IS_AR ? 'ساعة السعادة'            : 'Happy Hour',
  bulkDiscount:   IS_AR ? 'خصم الكمية'              : 'Bulk Discount',
  dayOfWeek:      IS_AR ? 'يوم الأسبوع'             : 'Day of Week',
  category:       IS_AR ? 'الفئة'                   : 'Category',
  flatPrice:      IS_AR ? 'سعر ثابت'                : 'Flat Price',
  loyaltyTier:    IS_AR ? 'مستوى الولاء'            : 'Loyalty Tier',
};

let editId  = null;
let ruleMap = new Map();

// ── Rule type → show/hide field groups ─────────────────────────────────────
function toggleTypeFields() {
  const type = document.getElementById('ruleType').value;
  document.querySelectorAll('.time-fields').forEach(el =>
    el.style.display = ['happy_hour', 'day_of_week'].includes(type) ? '' : 'none');
  document.querySelectorAll('.dow-fields').forEach(el =>
    el.style.display = ['happy_hour', 'day_of_week'].includes(type) ? '' : 'none');
  document.querySelectorAll('.bulk-fields').forEach(el =>
    el.style.display = type === 'bulk_discount' ? '' : 'none');
}

// ── Open create modal ───────────────────────────────────────────────────────
function openCreate() {
  editId = null;
  document.getElementById('ruleModalTitle').textContent = T.newRule;
  document.getElementById('ruleId').value = '';
  ['ruleName','discountValue','ruleDesc','timeStart','timeEnd','validFrom','validUntil','minQty']
    .forEach(id => document.getElementById(id).value = '');
  document.getElementById('ruleType').value      = 'happy_hour';
  document.getElementById('discountType').value  = 'percentage';
  document.getElementById('rulePriority').value  = '10';
  document.getElementById('ruleActive').checked  = true;
  document.getElementById('ruleStackable').checked = false;
  document.querySelectorAll('.dow-cb').forEach(cb => cb.checked = false);
  toggleTypeFields();
}

// ── Open edit modal ─────────────────────────────────────────────────────────
function openEdit(rule) {
  editId = rule.id;
  document.getElementById('ruleModalTitle').textContent  = T.editRule;
  document.getElementById('ruleName').value              = rule.name;
  document.getElementById('ruleType').value              = rule.rule_type;
  document.getElementById('discountType').value          = rule.discount_type;
  document.getElementById('discountValue').value         = rule.discount_value;
  document.getElementById('rulePriority').value          = rule.priority;
  document.getElementById('ruleActive').checked          = !!rule.is_active;
  document.getElementById('ruleStackable').checked       = !!rule.stackable;
  document.getElementById('ruleDesc').value              = rule.description ?? '';
  document.getElementById('timeStart').value             = rule.time_start ?? '';
  document.getElementById('timeEnd').value               = rule.time_end ?? '';
  document.getElementById('validFrom').value             = rule.valid_from ?? '';
  document.getElementById('validUntil').value            = rule.valid_until ?? '';
  document.getElementById('minQty').value                = rule.min_quantity ?? '';
  document.querySelectorAll('.dow-cb').forEach(cb => {
    cb.checked = (rule.days_of_week ?? []).includes(parseInt(cb.value));
  });
  toggleTypeFields();
  new bootstrap.Modal(document.getElementById('ruleModal')).show();
}

// ── Save rule (create or update) ────────────────────────────────────────────
document.getElementById('saveRuleBtn').addEventListener('click', async () => {
  const dows = [...document.querySelectorAll('.dow-cb:checked')].map(cb => parseInt(cb.value));
  const payload = {
    name:           document.getElementById('ruleName').value.trim(),
    rule_type:      document.getElementById('ruleType').value,
    discount_type:  document.getElementById('discountType').value,
    discount_value: parseFloat(document.getElementById('discountValue').value) || 0,
    priority:       parseInt(document.getElementById('rulePriority').value) || 10,
    is_active:      document.getElementById('ruleActive').checked,
    stackable:      document.getElementById('ruleStackable').checked,
    description:    document.getElementById('ruleDesc').value.trim() || null,
    time_start:     document.getElementById('timeStart').value || null,
    time_end:       document.getElementById('timeEnd').value || null,
    days_of_week:   dows.length ? dows : null,
    valid_from:     document.getElementById('validFrom').value || null,
    valid_until:    document.getElementById('validUntil').value || null,
    min_quantity:   parseFloat(document.getElementById('minQty').value) || null,
  };

  const url    = editId ? `${API}/${editId}` : API;
  const method = editId ? 'PUT' : 'POST';

  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    body: JSON.stringify(payload),
  });

  if (res.ok) {
    bootstrap.Modal.getInstance(document.getElementById('ruleModal'))?.hide();
    loadRules();
  } else {
    const d = await res.json().catch(() => ({}));
    Swal.fire({ icon: 'error', title: T.saveError, text: d.message ?? '' });
  }
});

// ── Toggle rule active / inactive ───────────────────────────────────────────
async function toggleRule(id) {
  await fetch(`${API}/${id}/toggle`, {
    method: 'PATCH',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
  });
  loadRules();
}

// ── Delete rule ─────────────────────────────────────────────────────────────
async function deleteRule(id) {
  const result = await Swal.fire({
    icon: 'warning',
    title: T.deleteTitle,
    text: T.cannotUndo,
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: T.yesDelete,
    cancelButtonText: T.cancel,
    reverseButtons: !IS_AR,
  });
  if (!result.isConfirmed) return;

  const res = await fetch(`${API}/${id}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
  });
  if (res.ok) {
    loadRules();
  } else {
    const d = await res.json().catch(() => ({}));
    Swal.fire({ icon: 'error', title: T.saveError, text: d.message ?? '' });
  }
}

// ── Event delegation on rules table ────────────────────────────────────────
document.getElementById('rulesTable').addEventListener('click', function(e) {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const { action, id } = btn.dataset;
  if (action === 'edit')   openEdit(ruleMap.get(parseInt(id)));
  if (action === 'toggle') toggleRule(id);
  if (action === 'delete') deleteRule(id);
});

// ── New Rule button ─────────────────────────────────────────────────────────
document.getElementById('newRuleBtn').addEventListener('click', openCreate);

// ── Rule Type select change ─────────────────────────────────────────────────
document.getElementById('ruleType').addEventListener('change', toggleTypeFields);

// ── Rule type label map ─────────────────────────────────────────────────────
const typeLabel = {
  happy_hour:    '⏰ ' + T.happyHour,
  bulk_discount: '📦 ' + T.bulkDiscount,
  day_of_week:   '📅 ' + T.dayOfWeek,
  category:      '🏷 ' + T.category,
  flat_price:    '💲 ' + T.flatPrice,
  loyalty_tier:  '⭐ ' + T.loyaltyTier,
};

// ── Load & render rules ─────────────────────────────────────────────────────
async function loadRules() {
  const data = await fetch(API, { headers: { 'Accept': 'application/json' } }).then(r => r.json());

  // Rebuild lookup map for edit
  ruleMap.clear();
  data.forEach(r => ruleMap.set(r.id, r));

  // Happy Hour banner
  const happyHourActive = data.some(r => r.rule_type === 'happy_hour' && r.is_currently_active);
  document.getElementById('happyHourBanner').classList.toggle('d-none', !happyHourActive);

  const tbody = document.getElementById('rulesTable');

  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">
      <i class="fas fa-tags fa-2x d-block mb-2 opacity-25"></i>${T.noRules}
    </td></tr>`;
    return;
  }

  tbody.innerHTML = data.map(r => {
    const discountStr = r.discount_type === 'percentage'
      ? `${r.discount_value}%`
      : r.discount_type === 'fixed_amount'
        ? `${r.discount_value}${T.off}`
        : `${r.discount_value}${T.newPrice}`;

    const scheduleStr = r.time_start
      ? `${r.time_start} – ${r.time_end}${r.days_of_week?.length ? `<br><small class="text-muted">${T.days}${r.days_of_week.join(',')}</small>` : ''}`
      : (r.days_of_week?.length ? `<small class="text-muted">${T.days}${r.days_of_week.join(',')}</small>` : '—');

    return `<tr>
      <td>
        <div class="fw-semibold">${esc(r.name)}</div>
        ${r.description ? `<small class="text-muted">${esc(r.description)}</small>` : ''}
      </td>
      <td><span class="badge bg-secondary bg-opacity-25 text-body">${typeLabel[r.rule_type] ?? r.rule_type}</span></td>
      <td class="fw-semibold text-primary">${discountStr}</td>
      <td>${scheduleStr}</td>
      <td><span class="badge bg-light text-dark border">${r.priority}</span></td>
      <td>
        <span class="badge bg-${r.is_active ? 'success' : 'secondary'}">
          ${r.is_active ? T.active : T.inactive}
        </span>
      </td>
      <td>
        ${r.is_currently_active
          ? `<span class="badge bg-warning text-dark">${T.activeNow}</span>`
          : '<span class="text-muted">–</span>'}
      </td>
      <td>
        <div class="d-flex gap-1 flex-wrap">
          <button class="btn btn-sm btn-outline-secondary" data-action="edit"   data-id="${r.id}">
            <i class="fas fa-edit me-1"></i>${T.edit}
          </button>
          <button class="btn btn-sm btn-outline-${r.is_active ? 'warning' : 'success'}" data-action="toggle" data-id="${r.id}">
            <i class="fas fa-${r.is_active ? 'pause' : 'play'} me-1"></i>${r.is_active ? T.disable : T.enable}
          </button>
          <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${r.id}">
            <i class="fas fa-trash me-1"></i>${T.del}
          </button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

loadRules();
</script>
@endpush
