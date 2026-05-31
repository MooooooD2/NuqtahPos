@extends('layouts.app')
@section('title', 'Kitchen Display System')
@section('page-title', 'Kitchen Display System')

@section('content')
<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card text-center border-warning">
      <div class="card-body">
        <div class="fs-3 fw-bold text-warning" id="statPending">-</div>
        <div class="text-muted small">⏳ Pending</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-info">
      <div class="card-body">
        <div class="fs-3 fw-bold text-info" id="statPreparing">-</div>
        <div class="text-muted small">🔥 Cooking</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-success">
      <div class="card-body">
        <div class="fs-3 fw-bold text-success" id="statReady">-</div>
        <div class="text-muted small">✅ Ready</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-secondary">
      <div class="card-body">
        <div class="fs-3 fw-bold" id="statAvg">-</div>
        <div class="text-muted small">⏱ Avg Prep (min)</div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Active Orders</h5>
  <div class="d-flex gap-2">
    <a href="{{ route('kitchen.display') }}" target="_blank" class="btn btn-dark">
      <i class="fas fa-tv me-1"></i> Open KDS Screen
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOrderModal">
      <i class="fas fa-plus me-1"></i> New Order
    </button>
  </div>
</div>

<div id="ordersContainer" class="row g-3"></div>

{{-- New Order Modal --}}
<div class="modal fade" id="newOrderModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Kitchen Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Table #</label>
            <input type="text" class="form-control" id="tableNumber" placeholder="e.g. T-01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Order Type</label>
            <select class="form-select" id="orderType">
              <option value="dine_in">Dine In</option>
              <option value="takeaway">Takeaway</option>
              <option value="delivery">Delivery</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Notes</label>
            <input type="text" class="form-control" id="orderNotes" placeholder="Special instructions…">
          </div>
        </div>
        <h6>Items</h6>
        <div id="itemsContainer">
          <div class="row g-2 mb-2 item-row-form">
            <div class="col-5"><input type="text" class="form-control item-name" placeholder="Item name" required></div>
            <div class="col-2"><input type="number" class="form-control item-qty" placeholder="Qty" value="1" min="0.1" step="0.1"></div>
            <div class="col-3"><input type="text" class="form-control item-note" placeholder="Note"></div>
            <div class="col-2"><button class="btn btn-outline-danger w-100 remove-item" type="button">✕</button></div>
          </div>
        </div>
        <button class="btn btn-outline-secondary btn-sm mt-2" id="addItemRow">+ Add Item</button>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="submitOrder">Send to Kitchen</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const API   = '{{ route("api.kitchen.orders") }}';
const BASE  = '{{ url("api/kitchen") }}';
const CSRF  = document.querySelector('meta[name="csrf-token"]').content;

/* ─── Helpers ─── */
async function apiFetch(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' } };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(url, opts);
  return res.json();
}

function statusBadge(status) {
  const map = { pending: 'warning', preparing: 'info', ready: 'success', served: 'secondary', cancelled: 'danger' };
  return `<span class="badge bg-${map[status] ?? 'secondary'}">${status}</span>`;
}

/* ─── Load orders ─── */
async function loadOrders() {
  const { orders, stats } = await apiFetch(API);
  document.getElementById('statPending').textContent   = stats.pending;
  document.getElementById('statPreparing').textContent = stats.preparing;
  document.getElementById('statReady').textContent     = stats.ready;
  document.getElementById('statAvg').textContent       = stats.avg_prep_min + ' min';

  const container = document.getElementById('ordersContainer');
  if (!orders.length) {
    container.innerHTML = '<div class="col-12 text-center text-muted py-5">No active orders</div>';
    return;
  }

  container.innerHTML = orders.map(o => `
    <div class="col-md-4 col-lg-3">
      <div class="card h-100 border-${o.is_urgent_val ? 'danger' : 'secondary'}">
        <div class="card-header d-flex justify-content-between">
          <strong>#${o.order_number}</strong>
          ${statusBadge(o.status)}
        </div>
        <div class="card-body p-2">
          <div class="small text-muted mb-2">${o.order_type?.replace('_',' ')}${o.table_number ? ' · Table ' + o.table_number : ''} · ${o.elapsed_minutes_val ?? 0}m ago</div>
          <ul class="list-unstyled mb-2">
            ${(o.items ?? []).map(i => `<li class="border-bottom py-1"><strong>×${parseFloat(i.quantity)}</strong> ${i.product_name}${i.notes ? ' <small class="text-muted">('+i.notes+')</small>' : ''}</li>`).join('')}
          </ul>
          ${o.notes ? `<div class="small text-muted">📝 ${o.notes}</div>` : ''}
        </div>
        <div class="card-footer p-2 d-flex gap-1">
          ${o.status === 'pending'   ? `<button class="btn btn-sm btn-primary flex-fill" data-action="accept" data-id="${o.id}">Accept</button>` : ''}
          ${o.status === 'preparing' ? `<button class="btn btn-sm btn-success flex-fill" data-action="ready"  data-id="${o.id}">Ready</button>` : ''}
          ${o.status === 'ready'     ? `<button class="btn btn-sm btn-secondary flex-fill"                    data-action="serve"  data-id="${o.id}">Served</button>` : ''}
          ${['pending','preparing'].includes(o.status) ? `<button class="btn btn-sm btn-outline-danger" data-action="cancel" data-id="${o.id}">✕</button>` : ''}
        </div>
      </div>
    </div>`).join('');
}

/* ─── Event delegation for order action buttons ─── */
document.getElementById('ordersContainer').addEventListener('click', async function(e) {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const { action, id } = btn.dataset;
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  try {
    if (action === 'accept') await apiFetch(`${BASE}/${id}/accept`, 'POST');
    if (action === 'ready')  await apiFetch(`${BASE}/${id}/ready`,  'POST');
    if (action === 'serve')  await apiFetch(`${BASE}/${id}/served`, 'POST');
    if (action === 'cancel') {
      if (!confirm('Cancel?')) { btn.disabled = false; btn.innerHTML = orig; return; }
      await apiFetch(`${BASE}/${id}/cancel`, 'POST');
    }
    loadOrders();
  } catch(err) { console.error(err); btn.disabled = false; btn.innerHTML = orig; }
});

/* ─── New order modal ─── */
document.getElementById('addItemRow').addEventListener('click', () => {
  const row = document.createElement('div');
  row.className = 'row g-2 mb-2 item-row-form';
  row.innerHTML = `
    <div class="col-5"><input type="text" class="form-control item-name" placeholder="Item name" required></div>
    <div class="col-2"><input type="number" class="form-control item-qty" placeholder="Qty" value="1" min="0.1" step="0.1"></div>
    <div class="col-3"><input type="text" class="form-control item-note" placeholder="Note"></div>
    <div class="col-2"><button class="btn btn-outline-danger w-100 remove-item" type="button">✕</button></div>`;
  document.getElementById('itemsContainer').appendChild(row);
});

document.getElementById('itemsContainer').addEventListener('click', e => {
  if (e.target.classList.contains('remove-item')) {
    const rows = document.querySelectorAll('.item-row-form');
    if (rows.length > 1) e.target.closest('.item-row-form').remove();
  }
});

document.getElementById('submitOrder').addEventListener('click', async () => {
  const items = [];
  document.querySelectorAll('.item-row-form').forEach(row => {
    const name = row.querySelector('.item-name').value.trim();
    const qty  = parseFloat(row.querySelector('.item-qty').value) || 1;
    const note = row.querySelector('.item-note').value.trim();
    if (name) items.push({ product_name: name, quantity: qty, notes: note || null });
  });
  if (!items.length) { alert('Add at least one item'); return; }

  const data = {
    table_number: document.getElementById('tableNumber').value.trim() || null,
    order_type:   document.getElementById('orderType').value,
    notes:        document.getElementById('orderNotes').value.trim() || null,
    items,
  };

  const btn = document.getElementById('submitOrder');
  btn.disabled = true;
  const res = await apiFetch(API, 'POST', data);
  btn.disabled = false;

  if (res.order) {
    bootstrap.Modal.getInstance(document.getElementById('newOrderModal')).hide();
    loadOrders();
  } else {
    alert(res.message ?? 'Error sending order');
  }
});

loadOrders();
setInterval(loadOrders, 10000);
</script>
@endpush
