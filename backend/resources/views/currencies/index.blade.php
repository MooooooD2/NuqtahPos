@extends('layouts.app')
@section('title', __('pos.currencies'))
@section('page-title', '💱 ' . __('pos.currencies'))

@section('content')

<div class="row g-4">

  {{-- Currency List --}}
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-money-bill-transfer me-2 text-primary"></i>{{ __('pos.currencies') }}</h6>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-success" id="btnUpdateRates">
            <i class="fas fa-rotate me-1"></i>{{ __('pos.update_rates') }}
          </button>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCurrencyModal">
            <i class="fas fa-plus me-1"></i>{{ app()->getLocale()==='ar' ? 'إضافة عملة' : 'Add Currency' }}
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('pos.currency_code') }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'الاسم' : 'Name' }}</th>
                <th>{{ __('pos.currency_symbol') }}</th>
                <th>{{ __('pos.exchange_rate') }}</th>
                <th>{{ __('pos.rate_updated_at') }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'الحالة' : 'Status' }}</th>
                <th>{{ app()->getLocale()==='ar' ? 'إجراءات' : 'Actions' }}</th>
              </tr>
            </thead>
            <tbody id="currencyBody">
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

  {{-- Converter + Info --}}
  <div class="col-lg-4">
    {{-- Currency Converter --}}
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-calculator me-2 text-warning"></i>{{ __('pos.convert_currency') }}</h6>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label fw-semibold small">{{ __('pos.from_currency') }}</label>
          <select id="fromCur" class="form-select"></select>
        </div>
        <div class="mb-3">
          <input type="number" id="convertAmount" class="form-control" value="1" min="0" step="any">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">{{ __('pos.to_currency') }}</label>
          <select id="toCur" class="form-select"></select>
        </div>
        <div class="p-3 rounded-3 text-center bg-light">
          <div class="fs-4 fw-bold text-primary" id="convertResult">–</div>
          <div class="text-muted small" id="convertRate">–</div>
        </div>
      </div>
    </div>

    {{-- Base Currency Card --}}
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-4">
        <div class="fs-1 mb-2">🏦</div>
        <div class="text-muted small mb-1">{{ __('pos.base_currency') }}</div>
        <div class="fs-3 fw-bold text-success" id="baseCurrencyDisplay">–</div>
        <div class="text-muted small mt-2">{{ app()->getLocale()==='ar' ? 'جميع الأسعار تُحسب بالنسبة لهذه العملة' : 'All rates calculated relative to this currency' }}</div>
      </div>
    </div>
  </div>

</div>

{{-- Add Currency Modal --}}
<div class="modal fade" id="addCurrencyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>{{ app()->getLocale()==='ar' ? 'إضافة عملة جديدة' : 'Add Currency' }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ __('pos.currency_code') }}</label>
            <input type="text" id="newCode" class="form-control text-uppercase" placeholder="USD" maxlength="3">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ __('pos.currency_symbol') }}</label>
            <input type="text" id="newSymbol" class="form-control" placeholder="$" maxlength="5">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'الاسم' : 'Name' }}</label>
            <input type="text" id="newName" class="form-control" placeholder="{{ app()->getLocale()==='ar' ? 'اسم العملة' : 'Currency Name' }}">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ __('pos.exchange_rate') }}</label>
            <input type="number" id="newRate" class="form-control" value="1" step="any" min="0">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="newIsBase">
              <label class="form-check-label" for="newIsBase">{{ __('pos.base_currency') }}</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}</button>
        <button type="button" class="btn btn-primary" id="btnAddCurrency">
          <i class="fas fa-plus me-1"></i>{{ app()->getLocale()==='ar' ? 'إضافة' : 'Add' }}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
// ── State ─────────────────────────────────────────────────────────────────
let allCurrencies = [];
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const JSON_HEADERS = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };

// ── Translations injected at render time (no inline handlers needed) ──────
const t = {
  base:       '{{ __('pos.base_currency') }}',
  active:     '{{ app()->getLocale()==='ar' ? 'نشط'      : 'Active' }}',
  inactive:   '{{ app()->getLocale()==='ar' ? 'غير نشط'  : 'Inactive' }}',
  deactivate: '{{ app()->getLocale()==='ar' ? 'تعطيل'    : 'Deactivate' }}',
  activate:   '{{ app()->getLocale()==='ar' ? 'تفعيل'    : 'Activate' }}',
  delete:     '{{ app()->getLocale()==='ar' ? 'حذف'      : 'Delete' }}',
  delConfirm: '{{ app()->getLocale()==='ar' ? 'حذف العملة؟' : 'Delete Currency?' }}',
  noData:     '{{ app()->getLocale()==='ar' ? 'لا توجد عملات' : 'No currencies configured' }}',
  updating:   '{{ app()->getLocale()==='ar' ? 'جاري التحديث…' : 'Updating…' }}',
};

// ── Load currencies ────────────────────────────────────────────────────────
async function loadCurrencies() {
  const res  = await fetch('/api/currencies', { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  allCurrencies = data.currencies ?? [];

  const base = allCurrencies.find(c => c.is_base);
  if (base) document.getElementById('baseCurrencyDisplay').textContent = base.code + ' — ' + (base.symbol ?? '');

  // Populate converter selects (preserve current selection)
  ['fromCur','toCur'].forEach(id => {
    const sel = document.getElementById(id);
    const cur = sel.value;
    sel.innerHTML = allCurrencies
      .filter(c => c.is_active || c.is_base)
      .map(c => `<option value="${esc(c.code)}">${esc(c.code)} — ${esc(c.name ?? '')}</option>`)
      .join('');
    if (cur) sel.value = cur;
  });

  const tbody = document.getElementById('currencyBody');
  if (!allCurrencies.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
      <i class="fas fa-coins fa-2x d-block mb-2 opacity-25"></i>${t.noData}
    </td></tr>`;
    convert();
    return;
  }

  tbody.innerHTML = allCurrencies.map(c => `<tr>
    <td><span class="fw-bold font-monospace">${esc(c.code)}</span></td>
    <td>${esc(c.name ?? '–')}</td>
    <td class="fw-semibold">${esc(c.symbol ?? '–')}</td>
    <td>
      ${c.is_base
        ? `<span class="fw-semibold">${parseFloat(c.exchange_rate).toFixed(4)}</span>`
        : `<input type="number" class="form-control form-control-sm d-inline-block"
                  style="width:110px" value="${parseFloat(c.exchange_rate).toFixed(4)}"
                  step="any" min="0"
                  data-action="update-rate" data-code="${esc(c.code)}">`
      }
    </td>
    <td class="text-muted small">${c.rate_updated_at ? new Date(c.rate_updated_at).toLocaleDateString() : '–'}</td>
    <td>
      ${c.is_base
        ? `<span class="badge bg-success"><i class="fas fa-star me-1"></i>${t.base}</span>`
        : `<span class="badge bg-${c.is_active ? 'primary' : 'secondary'}">${c.is_active ? t.active : t.inactive}</span>`
      }
    </td>
    <td>
      ${!c.is_base ? `
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-${c.is_active ? 'warning' : 'success'}"
                  data-action="toggle-currency" data-code="${esc(c.code)}" data-active="${c.is_active ? '0' : '1'}"
                  title="${c.is_active ? t.deactivate : t.activate}">
            <i class="fas fa-${c.is_active ? 'toggle-off' : 'toggle-on'}"></i>
          </button>
          <button class="btn btn-outline-danger"
                  data-action="delete-currency" data-code="${esc(c.code)}"
                  title="${t.delete}">
            <i class="fas fa-trash"></i>
          </button>
        </div>` : '–'}
    </td>
  </tr>`).join('');

  convert();
}

// ── Event delegation for dynamic table elements ───────────────────────────
document.getElementById('currencyBody').addEventListener('change', e => {
  const input = e.target.closest('[data-action="update-rate"]');
  if (input) updateRate(input.dataset.code, input.value);
});

document.getElementById('currencyBody').addEventListener('click', async e => {
  const toggle = e.target.closest('[data-action="toggle-currency"]');
  if (toggle) { await toggleCurrency(toggle.dataset.code, toggle.dataset.active === '1'); return; }

  const del = e.target.closest('[data-action="delete-currency"]');
  if (del) await deleteCurrency(del.dataset.code);
});

// ── Converter (event listeners, no inline handlers) ───────────────────────
document.getElementById('fromCur').addEventListener('change', convert);
document.getElementById('toCur').addEventListener('change', convert);
document.getElementById('convertAmount').addEventListener('input', convert);

function convert() {
  const from   = document.getElementById('fromCur').value;
  const to     = document.getElementById('toCur').value;
  const amount = parseFloat(document.getElementById('convertAmount').value) || 1;

  const fromC  = allCurrencies.find(c => c.code === from);
  const toC    = allCurrencies.find(c => c.code === to);
  if (!fromC || !toC) return;

  const fromRate = parseFloat(fromC.exchange_rate) || 1;
  const toRate   = parseFloat(toC.exchange_rate)   || 1;
  const result   = (amount / fromRate) * toRate;

  document.getElementById('convertResult').textContent = (toC.symbol ?? '') + ' ' + result.toFixed(4);
  document.getElementById('convertRate').textContent   = `1 ${from} = ${(toRate / fromRate).toFixed(6)} ${to}`;
}

// ── Update exchange rate ───────────────────────────────────────────────────
async function updateRate(code, rate) {
  await fetch(`/api/currencies/${code}`, {
    method: 'PUT',
    headers: JSON_HEADERS,
    body: JSON.stringify({ exchange_rate: parseFloat(rate) }),
  });
  // Refresh local state without full reload
  const cur = allCurrencies.find(c => c.code === code);
  if (cur) { cur.exchange_rate = parseFloat(rate); convert(); }
}

// ── Toggle active state ────────────────────────────────────────────────────
async function toggleCurrency(code, active) {
  await fetch(`/api/currencies/${code}/toggle`, {
    method: 'PATCH',
    headers: JSON_HEADERS,
    body: JSON.stringify({ is_active: active }),
  });
  loadCurrencies();
}

// ── Delete currency ────────────────────────────────────────────────────────
async function deleteCurrency(code) {
  const c = await Swal.fire({
    icon: 'warning',
    title: t.delConfirm,
    showCancelButton: true,
    confirmButtonText: t.delete,
    confirmButtonColor: '#dc3545',
    cancelButtonText: '{{ app()->getLocale()==='ar' ? 'إلغاء' : 'Cancel' }}',
  });
  if (!c.isConfirmed) return;

  try {
    const res  = await fetch(`/api/currencies/${code}`, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    const data = await res.json();
    if (data.success) {
      Swal.fire({ icon: 'success', title: '{{ app()->getLocale()==='ar' ? 'تم الحذف' : 'Deleted' }}', timer: 1200, showConfirmButton: false });
      loadCurrencies();
    } else {
      Swal.fire({ icon: 'error', title: '{{ app()->getLocale()==='ar' ? 'لا يمكن الحذف' : 'Cannot Delete' }}', text: data.message });
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
  }
}

// ── Update rates from API ──────────────────────────────────────────────────
document.getElementById('btnUpdateRates').addEventListener('click', async function () {
  this.disabled = true;
  this.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${t.updating}`;

  const res  = await fetch('/api/currencies/update-rates', {
    method: 'POST',
    headers: JSON_HEADERS,
  });
  const data = await res.json();

  if (data.success) {
    Swal.fire({ icon: 'success', title: '{{ __('pos.update_rates') }}', text: data.message, timer: 2000, showConfirmButton: false });
    loadCurrencies();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
  }
  this.disabled = false;
  this.innerHTML = `<i class="fas fa-rotate me-1"></i>{{ __('pos.update_rates') }}`;
});

// ── Add currency ───────────────────────────────────────────────────────────
document.getElementById('btnAddCurrency').addEventListener('click', async function () {
  const body = {
    code:          document.getElementById('newCode').value.toUpperCase().trim(),
    name:          document.getElementById('newName').value.trim(),
    symbol:        document.getElementById('newSymbol').value.trim(),
    exchange_rate: parseFloat(document.getElementById('newRate').value),
    is_base:       document.getElementById('newIsBase').checked,
  };
  const res  = await fetch('/api/currencies', {
    method: 'POST',
    headers: JSON_HEADERS,
    body: JSON.stringify(body),
  });
  const data = await res.json();
  if (data.success) {
    bootstrap.Modal.getInstance(document.getElementById('addCurrencyModal')).hide();
    // Reset form
    ['newCode','newName','newSymbol'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('newRate').value   = '1';
    document.getElementById('newIsBase').checked = false;
    loadCurrencies();
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message ?? JSON.stringify(data.errors ?? '') });
  }
});

// ── Utility ────────────────────────────────────────────────────────────────
function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Init ───────────────────────────────────────────────────────────────────
loadCurrencies();
</script>
@endpush
