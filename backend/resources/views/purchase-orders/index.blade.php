{{-- FILE: resources/views/purchase-orders/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.purchase_orders'))
@section('page-title', __('pos.purchase_orders'))

@push('styles')
<style @nonce>
.po-status-bar { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
.po-badge-num  { font-family:monospace; letter-spacing:.03em; }
.po-items-tbl th, .po-items-tbl td { vertical-align:middle; }
.po-items-tbl td input { min-width:70px; }
.section-divider { font-size:.78rem; font-weight:700; text-transform:uppercase;
                   letter-spacing:.08em; color:#6c757d; border-bottom:1px solid #e2e8f0;
                   padding-bottom:.35rem; margin-bottom:.75rem; }
.recv-tbl th, .recv-tbl td { vertical-align:middle; font-size:.875rem; }
</style>
@endpush

@section('content')

{{-- ── Page header ────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i>{{ __('pos.purchase_orders') }}</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPOModal">
        <i class="fas fa-plus me-1"></i>{{ __('pos.create_po') }}
    </button>
</div>

{{-- ── Filters ─────────────────────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="po-status-bar">
            <span class="text-muted small">{{ __('pos.filter') }}:</span>
            <select class="form-select form-select-sm" id="poStatusFilter" style="width:180px" data-on-change="loadPOs">
                <option value="">{{ __('pos.all') }}</option>
                <option value="draft">{{ app()->getLocale()==='ar' ? 'مسودة' : 'Draft' }}</option>
                <option value="pending">{{ __('pos.po_status_pending') }}</option>
                <option value="approved">{{ app()->getLocale()==='ar' ? 'معتمد' : 'Approved' }}</option>
                <option value="rejected">{{ app()->getLocale()==='ar' ? 'مرفوض' : 'Rejected' }}</option>
                <option value="received">{{ __('pos.po_status_received') }}</option>
                <option value="partial">{{ __('pos.po_status_partial') }}</option>
                <option value="cancelled">{{ __('pos.po_status_cancelled') }}</option>
            </select>
        </div>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>{{ __('pos.po_number') }}</th>
                        <th>{{ __('pos.suppliers') }}</th>
                        <th>{{ __('pos.total') }}</th>
                        <th>{{ __('pos.order_date') }}</th>
                        <th>{{ __('pos.expected_date') }}</th>
                        <th>{{ __('pos.status') }}</th>
                        <th>{{ __('pos.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="poBody">
                    <tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- Create PO Modal                                                        --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="createPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>{{ __('pos.create_po') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                {{-- ── Section 1: Basic info ────────────────────────────── --}}
                <div class="section-divider">{{ app()->getLocale() === 'ar' ? 'بيانات الطلب' : 'Order Details' }}</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('pos.suppliers') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="poSupplier" required>
                            <option value="">-- {{ __('pos.suppliers') }} --</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">{{ __('pos.discount') }}</label>
                        <input type="number" class="form-control" id="poDiscount" value="0" min="0" step="0.01" data-on-change="updatePOTotals">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">{{ __('pos.order_date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="poOrderDate" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">{{ __('pos.expected_date') }}</label>
                        <input type="date" class="form-control" id="poExpectedDate">
                    </div>
                </div>

                {{-- ── Section 2: Items ─────────────────────────────────── --}}
                <div class="section-divider">{{ app()->getLocale() === 'ar' ? 'المنتجات' : 'Products' }}</div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm po-items-tbl align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:200px">{{ __('pos.product_name') }}</th>
                                <th style="width:100px">{{ __('pos.quantity') }}</th>
                                <th style="width:130px">{{ __('pos.cost_price') }}</th>
                                <th style="width:130px">{{ __('pos.selling_price') }}</th>
                                <th style="width:120px">{{ __('pos.subtotal') }}</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="poItemsBody"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="p-1">
                                    <button class="btn btn-sm btn-outline-primary w-100" data-fn="addPOItemRow">
                                        <i class="fas fa-plus me-1"></i>{{ __('pos.add_product') }}
                                    </button>
                                </td>
                            </tr>
                            <tr class="table-secondary fw-bold">
                                <td colspan="4" class="text-end pe-3">{{ __('pos.total') }}</td>
                                <td id="poGrandTotal" class="text-success">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- ── Section 3: Notes ─────────────────────────────────── --}}
                <div class="section-divider">{{ app()->getLocale() === 'ar' ? 'ملاحظات' : 'Notes' }}</div>
                <textarea class="form-control" id="poNotes" rows="2"
                    placeholder="{{ app()->getLocale() === 'ar' ? 'ملاحظات اختيارية...' : 'Optional notes...' }}"></textarea>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="savePO">
                    <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- Receive PO Modal                                                       --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="receivePOModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-box-open me-2"></i>
                    {{ app()->getLocale() === 'ar' ? 'استلام بضاعة' : 'Receive Stock' }}:
                    <span id="receivePONumber" class="ms-1 fw-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3 small">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ app()->getLocale() === 'ar'
                        ? 'أدخل الكمية المستلمة فعلاً — النظام سيسجل أي فرق مع المورد تلقائياً'
                        : 'Enter the actual quantity received — the system will log any discrepancy automatically.' }}
                </div>
                <input type="hidden" id="receivePOId">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm recv-tbl">
                        <thead class="table-dark">
                            <tr>
                                <th>{{ app()->getLocale() === 'ar' ? 'المنتج' : 'Product' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'المطلوب' : 'Ordered' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'مستلم سابقاً' : 'Prev. Rcvd' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'المتبقي' : 'Remaining' }}</th>
                                <th class="text-center" style="width:110px">{{ app()->getLocale() === 'ar' ? 'المستلم الآن ✏️' : 'Receiving ✏️' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'الفرق' : 'Diff' }}</th>
                                <th>{{ app()->getLocale() === 'ar' ? 'سبب الفرق' : 'Discrepancy Note' }}</th>
                                <th style="width:100px">{{ app()->getLocale() === 'ar' ? 'سعر التكلفة' : 'Cost' }}</th>
                            </tr>
                        </thead>
                        <tbody id="receiveItemsBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-success" data-fn="submitReceivePO">
                    <i class="fas fa-check me-1"></i>
                    {{ app()->getLocale() === 'ar' ? 'تأكيد الاستلام' : 'Confirm Receipt' }}
                </button>
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script @nonce>
let poProducts  = [];
let poItemCount = 0;
let renderedPOs = [];
const isAr      = LOCALE === 'ar';

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Data loaders ──────────────────────────────────────────────────────────
async function loadSuppliers() {
    const res = await apiCall('{{ route("suppliers.all") }}');
    const sel = document.getElementById('poSupplier');
    sel.innerHTML = '<option value="">-- {{ __("pos.suppliers") }} --</option>'
        + (res.suppliers || []).map(s => `<option value="${s.id}">${s.name}</option>`).join('');
}

async function loadProductsList() {
    const res  = await apiCall('{{ route("products.all") }}');
    poProducts = res.products || [];
}

async function loadPOs() {
    const status = document.getElementById('poStatusFilter').value;
    const url    = '{{ route("purchase-orders.all") }}' + (status ? `?status=${status}` : '');
    const res    = await apiCall(url);
    const orders = res.purchase_orders?.data || [];

    const statusMap = {
        draft:     { label: isAr ? 'مسودة'  : 'Draft',     cls: 'secondary' },
        pending:   { label: '{{ __("pos.po_status_pending") }}',   cls: 'warning text-dark' },
        approved:  { label: isAr ? 'معتمد'  : 'Approved',  cls: 'success' },
        rejected:  { label: isAr ? 'مرفوض'  : 'Rejected',  cls: 'danger' },
        received:  { label: '{{ __("pos.po_status_received") }}',  cls: 'success' },
        partial:   { label: '{{ __("pos.po_status_partial") }}',   cls: 'info text-dark' },
        cancelled: { label: '{{ __("pos.po_status_cancelled") }}', cls: 'danger' },
    };

    renderedPOs = orders;

    document.getElementById('poBody').innerHTML = orders.length
        ? orders.map((po, i) => {
            const st = statusMap[po.status] || { label: po.status, cls: 'secondary' };

            let actions = '';
            if (po.status === 'draft') {
                actions = `<button class="btn btn-sm btn-outline-primary me-1" data-action="submit-po" data-po-id="${po.id}">
                    <i class="fas fa-paper-plane me-1"></i>${isAr ? 'إرسال للاعتماد' : 'Submit'}
                </button>`;
            }
            @can('approve_purchase_order')
            if (po.status === 'pending') {
                actions = `<button class="btn btn-sm btn-success me-1" data-action="approve-po" data-po-id="${po.id}">
                    <i class="fas fa-check me-1"></i>${isAr ? 'اعتماد' : 'Approve'}
                </button>
                <button class="btn btn-sm btn-outline-danger" data-action="reject-po" data-po-id="${po.id}">
                    <i class="fas fa-times me-1"></i>${isAr ? 'رفض' : 'Reject'}
                </button>`;
            }
            @endcan
            if (po.status === 'approved') {
                actions += `<button class="btn btn-sm btn-outline-success" data-action="receive-po" data-po-idx="${i}">
                    <i class="fas fa-box-open me-1"></i>${isAr ? 'استلام' : 'Receive'}
                </button>`;
            }
            if (!actions) actions = '<span class="text-muted small">—</span>';

            return `<tr>
                <td><span class="badge bg-primary po-badge-num">${escapeHtml(po.po_number)}</span></td>
                <td>${escapeHtml(po.supplier_name)}</td>
                <td class="fw-semibold">${formatCurrency(po.final_amount)}</td>
                <td class="small text-muted">${formatDate(po.order_date)}</td>
                <td class="small text-muted">${po.expected_date ? formatDate(po.expected_date) : '—'}</td>
                <td><span class="badge bg-${st.cls}">${st.label}</span></td>
                <td class="text-nowrap">${actions}</td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

// ── PO item rows ──────────────────────────────────────────────────────────
function addPOItemRow() {
    const idx  = poItemCount++;
    const opts = poProducts.map(p =>
        `<option value="${p.id}" data-cost="${p.cost_price}" data-price="${p.price}">${p.name}${p.unit_abbreviation ? ' (' + p.unit_abbreviation + ')' : ''}</option>`
    ).join('');

    const row  = document.createElement('tr');
    row.id     = `poRow${idx}`;
    row.innerHTML = `
        <td>
            <select class="form-select form-select-sm" data-action="fill-prices" data-idx="${idx}">
                <option value="">-- {{ __('pos.product_name') }} --</option>
                ${opts}
            </select>
            <input type="hidden" id="poItemProductId${idx}">
        </td>
        <td><input type="number" class="form-control form-control-sm" id="poItemQty${idx}"
                value="1" min="1" data-action="update-subtotal" data-idx="${idx}"></td>
        <td><input type="number" class="form-control form-control-sm" id="poItemCost${idx}"
                value="0" step="0.01" data-action="update-subtotal" data-idx="${idx}"></td>
        <td><input type="number" class="form-control form-control-sm" id="poItemSelling${idx}"
                value="0" step="0.01"></td>
        <td id="poRowSubtotal${idx}" class="fw-semibold text-end">0.00</td>
        <td class="text-center">
            <button class="btn btn-sm btn-outline-danger" data-action="remove-row" data-idx="${idx}">
                <i class="fas fa-trash"></i>
            </button>
        </td>`;
    document.getElementById('poItemsBody').appendChild(row);
}

function fillPORowPrices(idx, sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('poItemProductId' + idx).value = sel.value;
    document.getElementById('poItemCost' + idx).value      = opt.dataset.cost  || 0;
    document.getElementById('poItemSelling' + idx).value   = opt.dataset.price || 0;
    updatePORowSubtotal(idx);
}

function updatePORowSubtotal(idx) {
    const qty  = parseFloat(document.getElementById('poItemQty'  + idx)?.value) || 0;
    const cost = parseFloat(document.getElementById('poItemCost' + idx)?.value) || 0;
    document.getElementById('poRowSubtotal' + idx).textContent = formatCurrency(qty * cost);
    updatePOTotals();
}

function removePORow(idx) {
    document.getElementById('poRow' + idx)?.remove();
    updatePOTotals();
}

function updatePOTotals() {
    let total = 0;
    document.querySelectorAll('[id^="poItemQty"]').forEach(el => {
        const idx  = el.id.replace('poItemQty', '');
        const qty  = parseFloat(el.value) || 0;
        const cost = parseFloat(document.getElementById('poItemCost' + idx)?.value) || 0;
        total += qty * cost;
    });
    const disc = parseFloat(document.getElementById('poDiscount').value) || 0;
    document.getElementById('poGrandTotal').textContent = formatCurrency(Math.max(0, total - disc));
}

// ── Save PO ───────────────────────────────────────────────────────────────
async function savePO() {
    const items = [];
    document.querySelectorAll('[id^="poItemQty"]').forEach(el => {
        const idx       = el.id.replace('poItemQty', '');
        const productId = document.getElementById('poItemProductId' + idx)?.value;
        const selEl     = document.querySelector(`#poRow${idx} select`);
        const name      = selEl?.options[selEl?.selectedIndex]?.text || '';
        items.push({
            product_id:    productId || null,
            product_name:  name,
            quantity:      parseInt(el.value),
            cost_price:    parseFloat(document.getElementById('poItemCost'    + idx)?.value),
            selling_price: parseFloat(document.getElementById('poItemSelling' + idx)?.value) || null,
        });
    });

    if (!document.getElementById('poSupplier').value || !items.length) {
        showToast(LOCALE === 'ar' ? 'يرجى اختيار المورد وإضافة منتج واحد على الأقل' : 'Please select a supplier and add at least one product', 'danger');
        return;
    }

    const res = await apiCall('{{ route("purchase-orders.store") }}', 'POST', {
        supplier_id:   document.getElementById('poSupplier').value,
        order_date:    document.getElementById('poOrderDate').value,
        expected_date: document.getElementById('poExpectedDate').value || null,
        discount:      document.getElementById('poDiscount').value || 0,
        notes:         document.getElementById('poNotes').value,
        items,
    });

    if (res.success) {
        showToast(LOCALE === 'ar' ? 'تم إنشاء أمر الشراء بنجاح' : 'Purchase order created');
        bootstrap.Modal.getInstance(document.getElementById('createPOModal')).hide();
        document.getElementById('poItemsBody').innerHTML = '';
        poItemCount = 0;
        loadPOs();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

// ── Receive PO ────────────────────────────────────────────────────────────
function showReceivePO(po) {
    document.getElementById('receivePOId').value           = po.id;
    document.getElementById('receivePONumber').textContent = po.po_number;

    document.getElementById('receiveItemsBody').innerHTML = po.items.map(item => {
        const pending = item.quantity - (item.received_quantity || 0);
        return `<tr>
            <td class="fw-semibold">${escapeHtml(item.product_name)}</td>
            <td class="text-center">${item.quantity}</td>
            <td class="text-center text-success">${item.received_quantity || 0}</td>
            <td class="text-center fw-bold">${pending}</td>
            <td>
                <input type="number" class="form-control form-control-sm text-center" id="recv_qty_${item.id}"
                    value="${pending}" min="0"
                    data-item-id="${item.id}" data-pending="${pending}" data-on-input="calcDiscrepancyEl">
            </td>
            <td class="text-center"><span id="disc_${item.id}" class="badge bg-secondary">—</span></td>
            <td>
                <input type="text" class="form-control form-control-sm" id="recv_disc_notes_${item.id}"
                    placeholder="${LOCALE === 'ar' ? 'سبب الفرق...' : 'Reason...'}" style="display:none">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm" id="recv_cost_${item.id}"
                    value="${item.cost_price}" step="0.01">
            </td>
        </tr>`;
    }).join('');

    po.items.forEach(item => {
        const pending = item.quantity - (item.received_quantity || 0);
        calcDiscrepancy(item.id, pending);
    });

    new bootstrap.Modal(document.getElementById('receivePOModal')).show();
    window._currentPOItems = po.items;
}

function calcDiscrepancy(itemId, expected) {
    const received = parseInt(document.getElementById(`recv_qty_${itemId}`)?.value) || 0;
    const diff     = received - expected;
    const el       = document.getElementById(`disc_${itemId}`);
    const notesEl  = document.getElementById(`recv_disc_notes_${itemId}`);
    if (!el) return;
    const isAr = LOCALE === 'ar';
    if (diff === 0) {
        el.textContent        = isAr ? '✓ مطابق' : '✓ OK';
        el.className          = 'badge bg-success';
        notesEl.style.display = 'none';
    } else if (diff < 0) {
        el.textContent        = `${diff} ${isAr ? 'ناقص' : 'short'}`;
        el.className          = 'badge bg-danger';
        notesEl.style.display = '';
        notesEl.placeholder   = isAr ? 'سبب النقص...' : 'Reason for shortage...';
    } else {
        el.textContent        = `+${diff} ${isAr ? 'زيادة' : 'extra'}`;
        el.className          = 'badge bg-warning text-dark';
        notesEl.style.display = '';
        notesEl.placeholder   = isAr ? 'سبب الزيادة...' : 'Reason for surplus...';
    }
}

function calcDiscrepancyEl(el) {
    calcDiscrepancy(parseInt(el.dataset.itemId), parseInt(el.dataset.pending));
}

async function submitReceivePO() {
    const poId  = document.getElementById('receivePOId').value;
    const items = (window._currentPOItems || []).map(item => {
        const pending  = item.quantity - (item.received_quantity || 0);
        const received = parseInt(document.getElementById(`recv_qty_${item.id}`)?.value) || 0;
        const diff     = received - pending;
        return {
            item_id:           item.id,
            received_quantity: received,
            cost_price:        parseFloat(document.getElementById(`recv_cost_${item.id}`)?.value) || null,
            discrepancy_notes: diff !== 0 ? (document.getElementById(`recv_disc_notes_${item.id}`)?.value || null) : null,
        };
    });

    const withDiscrepancy = items.filter((item, i) => {
        const pending = window._currentPOItems[i].quantity - (window._currentPOItems[i].received_quantity || 0);
        return item.received_quantity !== pending;
    });

    if (withDiscrepancy.length > 0) {
        const msg = LOCALE === 'ar'
            ? `⚠️ يوجد فرق في ${withDiscrepancy.length} منتج. هل تريد المتابعة؟`
            : `⚠️ Discrepancy in ${withDiscrepancy.length} item(s). Continue?`;
        if (!confirm(msg)) return;
    }

    const res = await apiCall(`/api/purchase-orders/${poId}/receive`, 'POST', { items });
    if (res.success) {
        const hasDisc = res.purchase_order?.items?.some(i => i.discrepancy !== 0);
        showToast(
            hasDisc
                ? (LOCALE === 'ar' ? '⚠️ تم الاستلام مع وجود فروق' : '⚠️ Received with discrepancies')
                : (LOCALE === 'ar' ? '✅ تم الاستلام بنجاح' : '✅ Stock received successfully'),
            hasDisc ? 'warning' : 'success'
        );
        bootstrap.Modal.getInstance(document.getElementById('receivePOModal')).hide();
        loadPOs();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

// ── Submit / Approve / Reject ─────────────────────────────────────────────
async function submitPO(poId) {
    if (!confirm(isAr ? 'إرسال أمر الشراء للاعتماد؟' : 'Submit this PO for approval?')) return;
    const res = await apiCall(`/api/purchase-orders/${poId}/submit`, 'POST');
    if (res.success) {
        showToast(isAr ? 'تم الإرسال للاعتماد' : 'Submitted for approval', 'success');
        loadPOs();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function approvePO(poId) {
    if (!confirm(isAr ? 'اعتماد أمر الشراء هذا؟' : 'Approve this purchase order?')) return;
    const res = await apiCall(`/api/purchase-orders/${poId}/approve`, 'POST');
    if (res.success) {
        showToast(isAr ? '✅ تم الاعتماد' : '✅ Approved', 'success');
        loadPOs();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function rejectPO(poId) {
    const reason = prompt(isAr ? 'سبب الرفض:' : 'Rejection reason:');
    if (!reason) return;
    const res = await apiCall(`/api/purchase-orders/${poId}/reject`, 'POST', { reason });
    if (res.success) {
        showToast(isAr ? 'تم رفض أمر الشراء' : 'Purchase order rejected', 'warning');
        loadPOs();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

// ── Event delegation ──────────────────────────────────────────────────────
document.getElementById('poBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    if      (action === 'receive-po') showReceivePO(renderedPOs[parseInt(btn.dataset.poIdx)]);
    else if (action === 'submit-po')  submitPO(btn.dataset.poId);
    else if (action === 'approve-po') approvePO(btn.dataset.poId);
    else if (action === 'reject-po')  rejectPO(btn.dataset.poId);
});

document.getElementById('poItemsBody').addEventListener('change', function(e) {
    const el = e.target.closest('[data-action]');
    if (!el) return;
    const idx = parseInt(el.dataset.idx);
    if      (el.dataset.action === 'fill-prices')     fillPORowPrices(idx, el);
    else if (el.dataset.action === 'update-subtotal') updatePORowSubtotal(idx);
});

document.getElementById('poItemsBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="remove-row"]');
    if (btn) removePORow(parseInt(btn.dataset.idx));
});

// Reset form when modal closes
document.getElementById('createPOModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('poItemsBody').innerHTML = '';
    document.getElementById('poNotes').value    = '';
    document.getElementById('poDiscount').value = '0';
    document.getElementById('poGrandTotal').textContent = '0.00';
    poItemCount = 0;
});

// ── Init ──────────────────────────────────────────────────────────────────
loadSuppliers();
loadProductsList();
loadPOs();
</script>
@endpush
