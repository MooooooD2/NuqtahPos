{{-- FILE: resources/views/returns/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.returns'))
@section('page-title', __('pos.returns'))

@section('content')

{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-undo me-2"></i>{{ __('pos.returns') }}</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#returnModal">
        <i class="fas fa-plus me-1"></i>{{ __('pos.process_return') }}
    </button>
</div>

{{-- Search --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control" id="returnsSearch"
                placeholder="{{ app()->getLocale() === 'ar' ? 'بحث برقم المرتجع، رقم الفاتورة، اسم العميل...' : 'Search by return #, invoice #, customer...' }}">
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'رقم المرتجع' : 'Return #' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'رقم الفاتورة' : 'Invoice #' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'العميل' : 'Customer' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'طريقة الرد' : 'Refund Method' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'المبلغ' : 'Amount' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'التاريخ' : 'Date' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'العناصر' : 'Items' }}</th>
                        <th>{{ app()->getLocale() === 'ar' ? 'طباعة' : 'Print' }}</th>
                    </tr>
                </thead>
                <tbody id="returnsTableBody">
                    <tr><td colspan="10" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        {{ app()->getLocale() === 'ar' ? 'جاري التحميل...' : 'Loading...' }}
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted" id="returnsPaginationInfo"></small>
        <div id="returnsPaginationBtns" class="d-flex gap-2"></div>
    </div>
</div>

{{-- Return Modal --}}
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pos.process_return') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Step 1: Find Invoice --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'الخطوة 1: رقم الفاتورة' : 'Step 1: Invoice Number' }}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="returnInvoiceNum"
                            placeholder="INV-20240101-0001">
                        <button class="btn btn-outline-primary" data-fn="findInvoice">
                            <i class="fas fa-search"></i> {{ __('pos.search') }}
                        </button>
                    </div>
                </div>

                {{-- Invoice Info --}}
                <div id="invoiceInfo" style="display:none" class="alert alert-info mb-3">
                    <strong id="invoiceInfoText"></strong>
                </div>

                {{-- Step 2: Select Items --}}
                <div id="returnItemsSection" style="display:none">
                    <h6 class="fw-semibold mb-2">{{ app()->getLocale() === 'ar' ? 'الخطوة 2: اختر العناصر للإرجاع' : 'Step 2: Select items to return' }}</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('pos.product_name') }}</th>
                                    <th>{{ app()->getLocale() === 'ar' ? 'يمكن إرجاعه' : 'Can Return' }}</th>
                                    <th>{{ app()->getLocale() === 'ar' ? 'كمية الإرجاع' : 'Return Qty' }}</th>
                                    <th>{{ __('pos.unit_price') }}</th>
                                    <th>{{ __('pos.subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody id="returnItemsBody"></tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="4" class="text-end">{{ __('pos.total') }}</td>
                                    <td id="returnTotal">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ app()->getLocale() === 'ar' ? 'اسم العميل' : 'Customer Name' }}</label>
                            <input type="text" class="form-control" id="returnCustomer">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('pos.return_reason') }}</label>
                            <input type="text" class="form-control" id="returnReason"
                                placeholder="{{ app()->getLocale() === 'ar' ? 'تالف / غير مناسب / خطأ في الطلب...' : 'Damaged / wrong item...' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'طريقة رد المبلغ *' : 'Refund Method *' }}</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check form-check-inline border rounded p-3 flex-fill text-center"
                                    style="cursor:pointer" data-fn="setRefundMethod" data-args='["cash"]'>
                                    <input class="form-check-input d-none" type="radio" name="refundMethod"
                                        id="refundCash" value="cash" checked>
                                    <label class="form-check-label" for="refundCash" style="cursor:pointer">
                                        <i class="fas fa-money-bill-wave fa-2x d-block mb-1 text-success"></i>
                                        <strong>{{ app()->getLocale() === 'ar' ? 'نقدي' : 'Cash' }}</strong><br>
                                        <small class="text-muted">{{ app()->getLocale() === 'ar' ? 'رد المبلغ كاش من الدرج' : 'Cash refund from drawer' }}</small>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline border rounded p-3 flex-fill text-center"
                                    style="cursor:pointer" data-fn="setRefundMethod" data-args='["store_credit"]'>
                                    <input class="form-check-input d-none" type="radio" name="refundMethod"
                                        id="refundCredit" value="store_credit">
                                    <label class="form-check-label" for="refundCredit" style="cursor:pointer">
                                        <i class="fas fa-star fa-2x d-block mb-1 text-primary"></i>
                                        <strong>{{ app()->getLocale() === 'ar' ? 'رصيد في المحل' : 'Store Credit' }}</strong><br>
                                        <small class="text-muted">{{ app()->getLocale() === 'ar' ? 'يُحفظ لشراء قادم' : 'Saved for next purchase' }}</small>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline border rounded p-3 flex-fill text-center"
                                    style="cursor:pointer" data-fn="setRefundMethod" data-args='["exchange"]'>
                                    <input class="form-check-input d-none" type="radio" name="refundMethod"
                                        id="refundExchange" value="exchange">
                                    <label class="form-check-label" for="refundExchange" style="cursor:pointer">
                                        <i class="fas fa-exchange-alt fa-2x d-block mb-1 text-warning"></i>
                                        <strong>{{ app()->getLocale() === 'ar' ? 'استبدال' : 'Exchange' }}</strong><br>
                                        <small class="text-muted">{{ app()->getLocale() === 'ar' ? 'استبدال بمنتج آخر' : 'Replace with another item' }}</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" id="refundMethodAlert" style="display:none">
                            <div class="alert alert-warning py-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                <span id="refundMethodNote"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-warning" id="submitReturnBtn" style="display:none" data-fn="submitReturn">
                    <i class="fas fa-undo me-1"></i>{{ __('pos.process_return') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Items Detail Modal --}}
<div class="modal fade" id="returnItemsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnItemsModalTitle">
                    {{ app()->getLocale() === 'ar' ? 'عناصر المرتجع' : 'Return Items' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pos.product_name') }}</th>
                            <th>{{ __('pos.quantity') }}</th>
                            <th>{{ __('pos.unit_price') }}</th>
                            <th>{{ __('pos.subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody id="returnItemsModalBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script @nonce>
const isAr       = LOCALE === 'ar';
const STORE_NAME = @json(\App\Models\Setting::get('store_name', config('app.name')));
let currentInvoice  = null;
let returnableItems = [];
let returnsPage     = 1;
let returnsSearch   = '';
let returnsData     = [];

// ── Refund method selector ────────────────────────────────────────────────
function setRefundMethod(value, el) {
    const radio = document.querySelector(`input[name="refundMethod"][value="${value}"]`);
    if (radio) radio.checked = true;
    document.querySelectorAll('[data-fn="setRefundMethod"]').forEach(d =>
        d.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10'));
    el.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');

    const notes = {
        cash:         isAr ? '💵 سيتم رد المبلغ نقداً من درج الكاشير فوراً.' : '💵 Cash refund from the drawer immediately.',
        store_credit: isAr ? '⭐ سيُحفظ المبلغ كرصيد للعميل لاستخدامه في عملية شراء قادمة.' : '⭐ Amount saved as store credit for next purchase.',
        exchange:     isAr ? '🔄 لا يُرد أي مبلغ — سيستبدل العميل المنتج بمنتج آخر.' : '🔄 No refund — customer exchanges for another product.',
    };
    document.getElementById('refundMethodNote').textContent = notes[value] ?? '';
    document.getElementById('refundMethodAlert').style.display = 'block';
}

// ── Find invoice ──────────────────────────────────────────────────────────
async function findInvoice() {
    const num = document.getElementById('returnInvoiceNum').value.trim();
    if (!num) return;
    const res = await apiCall(`{{ route('invoices.by-number') }}?number=${encodeURIComponent(num)}`);
    if (!res.success) { showToast(res.message, 'danger'); return; }

    currentInvoice = res.invoice;
    document.getElementById('invoiceInfo').style.display = 'block';
    document.getElementById('invoiceInfoText').textContent =
        `{{ __('pos.invoice_number') }}: ${res.invoice.invoice_number} | ${isAr ? 'الإجمالي' : 'Total'}: ${formatCurrency(res.invoice.final_total)}`;

    const itemsRes = await apiCall(`{{ url('/api/invoices') }}/${res.invoice.id}/returnable-items`);
    returnableItems = itemsRes.items || [];

    if (!returnableItems.length) {
        showToast(isAr ? 'لا توجد عناصر متاحة للإرجاع' : 'No items available for return', 'danger');
        return;
    }

    document.getElementById('returnItemsSection').style.display = 'block';
    document.getElementById('submitReturnBtn').style.display    = 'inline-block';

    document.getElementById('returnItemsBody').innerHTML = returnableItems.map((item, i) => `
        <tr>
            <td>${item.product_name}${item.unit_abbreviation ? ` <span class="badge bg-secondary ms-1">${item.unit_abbreviation}</span>` : ''}</td>
            <td class="text-center fw-semibold">${item.returnable_qty}</td>
            <td style="width:100px">
                <input type="number" class="form-control form-control-sm" id="retQty${i}"
                    value="0" min="0" max="${item.returnable_qty}" data-on-change="updateReturnTotal">
            </td>
            <td>${formatCurrency(item.price)}</td>
            <td id="retSubtotal${i}">0.00</td>
        </tr>`).join('');
}

function updateReturnTotal() {
    let total = 0;
    returnableItems.forEach((item, i) => {
        const qty = parseInt(document.getElementById(`retQty${i}`)?.value) || 0;
        const sub = qty * item.price;
        total += sub;
        document.getElementById(`retSubtotal${i}`).textContent = formatCurrency(sub);
    });
    document.getElementById('returnTotal').textContent = formatCurrency(total);
}

async function submitReturn() {
    const items = returnableItems
        .map((item, i) => ({
            product_id:   item.product_id,
            product_name: item.product_name,
            quantity:     parseInt(document.getElementById(`retQty${i}`)?.value) || 0,
            price:        item.price,
        }))
        .filter(i => i.quantity > 0);

    if (!items.length) {
        showToast(isAr ? 'اختر منتجاً واحداً على الأقل للإرجاع' : 'Select at least one item', 'danger');
        return;
    }

    const refundMethod = document.querySelector('input[name="refundMethod"]:checked')?.value || 'cash';
    const res = await apiCall('{{ route("returns.store") }}', 'POST', {
        invoice_id:    currentInvoice.id,
        customer_name: document.getElementById('returnCustomer').value,
        reason:        document.getElementById('returnReason').value,
        refund_method: refundMethod,
        items,
    });

    if (res.success) {
        const labels = { cash: isAr ? 'نقدي من الدرج' : 'Cash', store_credit: isAr ? 'رصيد للعميل' : 'Store Credit', exchange: isAr ? 'استبدال' : 'Exchange' };
        showToast(`✅ ${isAr ? 'تم المرتجع — الرد:' : 'Return processed — Refund:'} ${labels[refundMethod]}`);
        bootstrap.Modal.getInstance(document.getElementById('returnModal')).hide();
        currentInvoice = null; returnableItems = [];
        document.getElementById('returnInvoiceNum').value = '';
        document.getElementById('invoiceInfo').style.display      = 'none';
        document.getElementById('returnItemsSection').style.display = 'none';
        document.getElementById('submitReturnBtn').style.display    = 'none';
        loadReturns();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

// ── Returns table ─────────────────────────────────────────────────────────
async function loadReturns() {
    const tbody = document.getElementById('returnsTableBody');
    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>${isAr ? 'جاري التحميل...' : 'Loading...'}</td></tr>`;

    const params = new URLSearchParams({ page: returnsPage, per_page: 20 });
    if (returnsSearch) params.append('search', returnsSearch);

    const res = await apiCall(`{{ url('api/returns') }}?${params}`);
    if (!res.success) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${res.message || '{{ __("pos.error") }}'}</td></tr>`;
        return;
    }

    returnsData = res.returns || [];
    if (!returnsData.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">${isAr ? 'لا توجد مرتجعات' : 'No returns found'}</td></tr>`;
        document.getElementById('returnsPaginationInfo').textContent = '';
        document.getElementById('returnsPaginationBtns').innerHTML   = '';
        return;
    }

    const refundLabels = {
        cash:         isAr ? 'نقدي'     : 'Cash',
        store_credit: isAr ? 'رصيد محل' : 'Store Credit',
        exchange:     isAr ? 'استبدال'  : 'Exchange',
    };

    tbody.innerHTML = returnsData.map((r, idx) => {
        const rowNum      = (returnsPage - 1) * 20 + idx + 1;
        const statusClass = r.status === 'completed' ? 'bg-success' : r.status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary';
        const statusLabel = r.status === 'completed' ? (isAr ? 'مكتمل' : 'Completed')
                          : r.status === 'pending'   ? (isAr ? 'معلق'  : 'Pending')
                          :                             r.status;
        const itemCount = r.items?.length ?? 0;
        return `<tr>
            <td>${rowNum}</td>
            <td><code class="small">${r.return_number ?? '—'}</code></td>
            <td><code class="small">${r.invoice_number ?? '—'}</code></td>
            <td>${r.customer_name ?? '—'}</td>
            <td><span class="badge bg-info text-dark">${refundLabels[r.refund_method] ?? r.refund_method ?? '—'}</span></td>
            <td class="fw-semibold text-success">${formatCurrency(r.total_amount)}</td>
            <td><span class="badge ${statusClass}">${statusLabel}</span></td>
            <td class="small text-muted">${formatDate(r.created_at)}</td>
            <td>
                ${itemCount > 0
                    ? `<button class="btn btn-sm btn-outline-secondary" data-fn="showReturnItems" data-args='[${r.id}]'>
                           <i class="fas fa-list me-1"></i>${itemCount}
                       </button>`
                    : '—'}
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" data-fn="printReturn" data-args='[${r.id}]' title="${isAr ? 'طباعة' : 'Print'}">
                    <i class="fas fa-print"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    // pagination info
    document.getElementById('returnsPaginationInfo').textContent =
        isAr ? `${res.total} مرتجع — صفحة ${res.current_page} من ${res.last_page}`
              : `${res.total} returns — page ${res.current_page} of ${res.last_page}`;

    const btns = document.getElementById('returnsPaginationBtns');
    btns.innerHTML = '';
    if (res.current_page > 1) {
        const prev = document.createElement('button');
        prev.className = 'btn btn-sm btn-outline-secondary';
        prev.textContent = isAr ? '‹ السابق' : '‹ Prev';
        prev.addEventListener('click', () => { returnsPage--; loadReturns(); });
        btns.appendChild(prev);
    }
    if (res.current_page < res.last_page) {
        const next = document.createElement('button');
        next.className = 'btn btn-sm btn-outline-secondary';
        next.textContent = isAr ? 'التالي ›' : 'Next ›';
        next.addEventListener('click', () => { returnsPage++; loadReturns(); });
        btns.appendChild(next);
    }
}

// ── Items detail modal ────────────────────────────────────────────────────
function showReturnItems(returnId) {
    if (returnId instanceof Element) return;

    const ret = returnsData.find(r => r.id == returnId);
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('returnItemsModal'));
    const tbody = document.getElementById('returnItemsModalBody');

    if (!ret || !ret.items?.length) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">${isAr ? 'لا توجد بيانات' : 'No data'}</td></tr>`;
        modal.show();
        return;
    }

    document.getElementById('returnItemsModalTitle').textContent =
        `${isAr ? 'عناصر المرتجع' : 'Return Items'} — ${ret.return_number}`;

    tbody.innerHTML = ret.items.map(item => `
        <tr>
            <td>${item.product_name ?? '—'}${item.unit_abbreviation ? ` <span class="badge bg-secondary ms-1">${item.unit_abbreviation}</span>` : ''}</td>
            <td>${item.quantity}</td>
            <td>${formatCurrency(item.price)}</td>
            <td>${formatCurrency(item.subtotal ?? item.quantity * item.price)}</td>
        </tr>`).join('');

    modal.show();
}

// ── Print return receipt ──────────────────────────────────────────────────
function printReturn(returnId) {
    if (returnId instanceof Element) return;

    const ret = returnsData.find(r => r.id == returnId);
    if (!ret) { showToast(isAr ? 'لم يتم تحميل بيانات المرتجع' : 'Return data not loaded', 'danger'); return; }

    const dir = isAr ? 'rtl' : 'ltr';
    const tha = isAr ? 'right' : 'left';
    const now = new Date();
    const locale = isAr ? 'ar-EG' : 'en-US';
    const dateStr = now.toLocaleDateString(locale, { year: 'numeric', month: '2-digit', day: '2-digit' });
    const timeStr = now.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });

    const refundLabels = { cash: isAr ? 'نقدي' : 'Cash', store_credit: isAr ? 'رصيد محل' : 'Store Credit', exchange: isAr ? 'استبدال' : 'Exchange' };

    const itemRows = (ret.items || []).map(item => `
        <tr>
            <td style="padding:6px 4px;border-bottom:1px solid #ccc;text-align:${tha};">${item.product_name ?? '—'}</td>
            <td style="padding:6px 4px;border-bottom:1px solid #ccc;text-align:center;">${item.quantity}</td>
            <td style="padding:6px 4px;border-bottom:1px solid #ccc;text-align:right;">${formatCurrency(item.price)}</td>
            <td style="padding:6px 4px;border-bottom:1px solid #ccc;text-align:right;">${formatCurrency(item.subtotal ?? item.quantity * item.price)}</td>
        </tr>`).join('');

    const html = `<!DOCTYPE html>
<html dir="${dir}" lang="${isAr ? 'ar' : 'en'}">
<head>
<meta charset="utf-8">
<title>${isAr ? 'إيصال مرتجع' : 'Return Receipt'} — ${ret.return_number ?? ''}</title>
<style>
  body { font-family: ${isAr ? "'Cairo','Segoe UI',Tahoma,sans-serif" : "'Segoe UI',Tahoma,Geneva,Verdana,sans-serif"}; font-size:13px; line-height:1.4; margin:0; padding:15px; background:#fff; max-width:350px; margin:0 auto; }
  .box { border:1px solid #ddd; padding:12px; border-radius:5px; }
  .header { text-align:center; margin-bottom:15px; padding-bottom:8px; border-bottom:1px dashed #aaa; }
  .store-name { font-size:18px; font-weight:bold; margin-bottom:4px; }
  .receipt-title { font-size:14px; font-weight:bold; color:#d9534f; }
  .info-line { display:flex; justify-content:space-between; margin:4px 0; font-size:12px; }
  table { width:100%; border-collapse:collapse; margin:12px 0; }
  th { background:#f2f2f2; padding:6px 4px; font-size:12px; border-bottom:1px solid #aaa; text-align:${tha}; }
  .totals { margin-top:8px; border-top:1px solid #ccc; }
  .footer { text-align:center; margin-top:15px; font-size:11px; color:#555; border-top:1px dashed #aaa; padding-top:8px; }
  @media print { body { margin:0; padding:0; } .box { border:none; padding:0; } }
</style>
</head>
<body>
<div class="box">
  <div class="header">
    <div class="store-name">${STORE_NAME || (isAr ? 'نظام نقطة البيع' : 'POS System')}</div>
    <div class="receipt-title">🔄 ${isAr ? 'إيصال مرتجع' : 'Return Receipt'}</div>
  </div>

  <div class="info-line">
    <span>${isAr ? 'رقم المرتجع' : 'Return #'}:</span>
    <span><strong>${ret.return_number ?? '—'}</strong></span>
  </div>
  <div class="info-line">
    <span>${isAr ? 'رقم الفاتورة' : 'Invoice #'}:</span>
    <span>${ret.invoice_number ?? '—'}</span>
  </div>
  <div class="info-line">
    <span>${isAr ? 'التاريخ' : 'Date'}:</span>
    <span>${dateStr} ${timeStr}</span>
  </div>
  ${ret.customer_name ? `<div class="info-line"><span>${isAr ? 'العميل' : 'Customer'}:</span><span>${ret.customer_name}</span></div>` : ''}
  ${ret.reason ? `<div class="info-line"><span>${isAr ? 'السبب' : 'Reason'}:</span><span>${ret.reason}</span></div>` : ''}

  <table>
    <thead>
      <tr>
        <th style="text-align:${tha};">${isAr ? 'المنتج' : 'Product'}</th>
        <th style="text-align:center;">${isAr ? 'الكمية' : 'Qty'}</th>
        <th style="text-align:right;">${isAr ? 'السعر' : 'Price'}</th>
        <th style="text-align:right;">${isAr ? 'الإجمالي' : 'Total'}</th>
      </tr>
    </thead>
    <tbody>${itemRows}</tbody>
  </table>

  <table class="totals">
    <tr style="font-weight:bold; font-size:14px;">
      <td colspan="3" style="padding:8px 4px; text-align:${tha};">${isAr ? 'إجمالي المبلغ المسترد' : 'Total Refund'}</td>
      <td style="padding:8px 4px; text-align:right; color:#d9534f;">${formatCurrency(ret.total_amount)}</td>
    </tr>
    <tr>
      <td colspan="3" style="padding:4px; text-align:${tha};">${isAr ? 'طريقة الرد' : 'Refund Method'}</td>
      <td style="padding:4px; text-align:right;">${refundLabels[ret.refund_method] ?? ret.refund_method ?? '—'}</td>
    </tr>
  </table>

  <div class="footer">${isAr ? 'شكراً لتعاملكم معنا' : 'Thank you for your business'}</div>
</div>
</body>
</html>`;

    const w = window.open('', '_blank');
    w.document.write(html);
    w.document.close();
    w.focus();
    w.print();
    w.onafterprint = () => w.close();
}

// ── Search debounce ───────────────────────────────────────────────────────
let searchTimer;
document.getElementById('returnsSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        returnsSearch = this.value.trim();
        returnsPage   = 1;
        loadReturns();
    }, 400);
});

// ── Init ──────────────────────────────────────────────────────────────────
loadReturns();
</script>
@endpush
