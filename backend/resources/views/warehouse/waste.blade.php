@extends('layouts.app')
@section('title', __('pos.waste_recording'))
@section('page-title', __('pos.waste_recording'))

@section('content')
<div class="row g-3">
    {{-- نموذج تسجيل الهالك --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-trash-alt me-2 text-danger"></i>{{ __('pos.waste_recording') }}
            </div>
            <div class="card-body">
                <form id="wasteForm">
                    <div class="mb-3">
                        <label class="form-label">{{ __('pos.product') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="wasteProductId" required>
                            <option value="">— {{ __('pos.select_product') }} —</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pos.waste_quantity') }} <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="wasteQty" min="0.001" step="0.001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pos.waste_reason') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="wasteReason" required>
                            <option value="expired">{{ __('pos.waste_reason_expired') }}</option>
                            <option value="damaged">{{ __('pos.waste_reason_damaged') }}</option>
                            <option value="theft">{{ __('pos.waste_reason_theft') }}</option>
                            <option value="other">{{ __('pos.waste_reason_other') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pos.notes') }}</label>
                        <textarea class="form-control" id="wasteNotes" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="mb-3 p-3 rounded bg-light" id="wasteValueDisplay" style="display:none">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">{{ __('pos.waste_value') }}</span>
                            <strong class="text-danger" id="wasteValueAmount">—</strong>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100" id="saveWasteBtn">
                        <i class="fas fa-save me-2"></i>{{ __('pos.save') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- سجل الهالك --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2"></i>{{ __('pos.waste_history') }}</span>
                <div class="d-flex gap-2 align-items-center">
                    <input type="date" class="form-control form-control-sm" id="wasteFrom" style="width:140px">
                    <input type="date" class="form-control form-control-sm" id="wasteTo" style="width:140px">
                    <button class="btn btn-sm btn-outline-primary" onclick="loadHistory()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pos.date') }}</th>
                                <th>{{ __('pos.product') }}</th>
                                <th class="text-end">{{ __('pos.waste_quantity') }}</th>
                                <th>{{ __('pos.waste_reason') }}</th>
                                <th class="text-end">{{ __('pos.waste_value') }}</th>
                                <th>{{ __('pos.recorded_by') }}</th>
                            </tr>
                        </thead>
                        <tbody id="wasteHistoryBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('pos.loading') }}...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="wasteHistoryPager" class="p-3"></div>
            </div>
            <div class="card-footer text-end">
                <strong class="text-danger">{{ __('pos.waste_total_value') }}: <span id="wasteTotalValue">—</span></strong>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const CURRENCY = '{{ \App\Models\Setting::get('currency_symbol', 'ج.م') }}';
const REASONS  = {
    expired: '{{ __('pos.waste_reason_expired') }}',
    damaged: '{{ __('pos.waste_reason_damaged') }}',
    theft:   '{{ __('pos.waste_reason_theft') }}',
    other:   '{{ __('pos.waste_reason_other') }}',
};

// Load products into select
async function loadProducts() {
    const res  = await fetch('{{ route('products.all') }}?all=true');
    const data = await res.json();
    const list = Array.isArray(data.products) ? data.products : (data.products?.data ?? []);
    const sel  = document.getElementById('wasteProductId');
    // Clear except first placeholder option
    while (sel.options.length > 1) sel.remove(1);
    list.forEach(p => {
        const unit = p.unit_abbreviation || p.unit_name || '';
        const opt  = new Option(`${p.name}${unit ? ' / ' + unit : ''} — ${p.quantity}`, p.id);
        opt.dataset.cost = p.avg_cost ?? p.cost_price ?? 0;
        sel.appendChild(opt);
    });
}

document.getElementById('wasteProductId').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    updateValueDisplay(opt?.dataset?.cost);
});

document.getElementById('wasteQty').addEventListener('input', function() {
    const opt = document.getElementById('wasteProductId').options[document.getElementById('wasteProductId').selectedIndex];
    updateValueDisplay(opt?.dataset?.cost);
});

function updateValueDisplay(cost) {
    const qty = parseFloat(document.getElementById('wasteQty').value) || 0;
    const c   = parseFloat(cost) || 0;
    const display = document.getElementById('wasteValueDisplay');
    if (qty > 0 && c > 0) {
        document.getElementById('wasteValueAmount').textContent = `${CURRENCY} ${(qty * c).toFixed(2)}`;
        display.style.display = '';
    } else {
        display.style.display = 'none';
    }
}

document.getElementById('wasteForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveWasteBtn');
    btn.disabled = true;

    try {
        const res = await fetch('{{ route('waste.store') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({
                product_id: document.getElementById('wasteProductId').value,
                quantity:   document.getElementById('wasteQty').value,
                reason:     document.getElementById('wasteReason').value,
                notes:      document.getElementById('wasteNotes').value,
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || '{{ __('pos.waste_recorded') }}', 'success');
            this.reset();
            document.getElementById('wasteValueDisplay').style.display = 'none';
            loadHistory();
            loadProducts();
        } else {
            showToast(data.message || '{{ __('pos.error') }}', 'danger');
        }
    } catch {
        showToast('{{ __('pos.error') }}', 'danger');
    } finally {
        btn.disabled = false;
    }
});

async function loadHistory(page = 1) {
    const from = document.getElementById('wasteFrom').value;
    const to   = document.getElementById('wasteTo').value;
    let url = `{{ route('waste.history') }}?page=${page}`;
    if (from) url += `&start_date=${from}`;
    if (to)   url += `&end_date=${to}`;

    const res  = await fetch(url);
    const data = await res.json();
    const rows = data.data?.data || [];
    const tbody = document.getElementById('wasteHistoryBody');

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">{{ __('pos.no_data') }}</td></tr>`;
        document.getElementById('wasteTotalValue').textContent = `${CURRENCY} 0.00`;
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="small">${new Date(r.created_at).toLocaleDateString()}</td>
            <td>${r.product?.name ?? '—'}</td>
            <td class="text-end">${r.quantity}</td>
            <td><span class="badge bg-secondary">${REASONS[r.reason] ?? r.reason}</span></td>
            <td class="text-end text-danger fw-semibold">${CURRENCY} ${parseFloat(r.total_value).toFixed(2)}</td>
            <td class="small">${r.recorder?.full_name ?? '—'}</td>
        </tr>
    `).join('');

    const total = rows.reduce((s, r) => s + parseFloat(r.total_value), 0);
    document.getElementById('wasteTotalValue').textContent = `${CURRENCY} ${total.toFixed(2)}`;
}

loadProducts();
loadHistory();
</script>
@endpush
