<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>{{ $table->table_name }} — Menu</title>
<style @nonce>
  :root {
    --primary: #f59e0b;
    --dark: #1e293b;
    --card-bg: #fff;
    --text: #1e293b;
    --muted: #64748b;
    --success: #16a34a;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f8fafc; font-family: 'Segoe UI', Arial, sans-serif; color: var(--text); }

  .header {
    background: var(--dark);
    color: #fff;
    padding: 16px 20px;
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .header h1 { font-size: 1.2rem; }
  .cart-badge {
    background: var(--primary);
    color: #000;
    border-radius: 50%;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700;
  }
  .cart-btn {
    background: var(--primary);
    color: #000;
    border: none;
    border-radius: 20px;
    padding: 8px 16px;
    font-weight: 700;
    cursor: pointer;
    font-size: .85rem;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Category tabs */
  .cat-tabs {
    display: flex;
    overflow-x: auto;
    gap: 8px;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    scrollbar-width: none;
  }
  .cat-tabs::-webkit-scrollbar { display: none; }
  .cat-btn {
    white-space: nowrap;
    padding: 6px 14px;
    border-radius: 20px;
    border: 1.5px solid #cbd5e1;
    background: transparent;
    cursor: pointer;
    font-size: .85rem;
    font-weight: 500;
    transition: all .15s;
  }
  .cat-btn.active { background: var(--dark); color: #fff; border-color: var(--dark); }

  /* Products grid */
  .products { padding: 16px; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }

  .product-card {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
    position: relative;
  }
  .product-card:active { transform: scale(.97); }
  .product-card.in-cart::after {
    content: attr(data-qty);
    position: absolute;
    top: 8px; right: 8px;
    background: var(--primary);
    color: #000;
    border-radius: 50%;
    width: 22px; height: 22px;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700;
  }

  .product-img {
    width: 100%; aspect-ratio: 1; object-fit: cover;
    background: #f1f5f9;
  }
  .product-img-placeholder {
    width: 100%; aspect-ratio: 1;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
  }
  .product-info { padding: 10px; }
  .product-name { font-weight: 600; font-size: .9rem; margin-bottom: 4px; }
  .product-desc { font-size: .75rem; color: var(--muted); margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .product-price { font-weight: 700; color: var(--primary); }

  /* Cart modal overlay */
  .cart-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 200;
    display: none;
    align-items: flex-end;
  }
  .cart-overlay.open { display: flex; }
  .cart-sheet {
    background: #fff;
    border-radius: 20px 20px 0 0;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    padding: 20px;
    animation: slideUp .25s ease;
  }
  @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
  .cart-sheet h2 { font-size: 1.1rem; margin-bottom: 16px; }

  .cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
    gap: 8px;
  }
  .cart-item-name { flex: 1; font-weight: 500; }
  .qty-controls { display: flex; align-items: center; gap: 6px; }
  .qty-btn {
    width: 28px; height: 28px;
    border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    background: transparent;
    font-size: 1rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
  }
  .qty-minus { color: #dc2626; }
  .qty-plus  { color: #16a34a; }
  .cart-item-price { font-weight: 600; min-width: 60px; text-align: right; }

  .cart-total { font-size: 1.1rem; font-weight: 700; text-align: right; margin: 16px 0; }
  .cart-note { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 8px; margin-bottom: 12px; font-size: .9rem; resize: none; }
  .checkout-btn {
    width: 100%;
    background: var(--dark);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 14px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
  }
  .checkout-btn:disabled { opacity: .6; }

  /* Customer info */
  .customer-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
  .customer-fields input {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: .85rem;
    width: 100%;
  }

  /* Success */
  .success-overlay {
    display: none;
    position: fixed; inset: 0;
    background: #fff;
    z-index: 300;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    text-align: center;
    padding: 32px;
  }
  .success-overlay.show { display: flex; }
  .success-icon { font-size: 5rem; }
  .success-overlay h2 { font-size: 1.5rem; }
  .track-btn { background: var(--dark); color: #fff; border: none; border-radius: 12px; padding: 12px 28px; font-size: 1rem; cursor: pointer; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div style="font-size:.75rem;color:#94a3b8;">📍 {{ $table->table_name }}</div>
    <h1>Our Menu</h1>
  </div>
  <button class="cart-btn" id="openCartBtn">
    🛒 Cart <span class="cart-badge" id="cartCount">0</span>
  </button>
</div>

<div class="cat-tabs" id="catTabs"></div>

<div class="products" id="productsGrid"></div>

{{-- Cart overlay --}}
<div class="cart-overlay" id="cartOverlay">
  <div class="cart-sheet" id="cartSheet">
    <h2>🛒 Your Order</h2>
    <div id="cartItemsList"></div>
    <div class="cart-total" id="cartTotal"></div>
    <div class="customer-fields">
      <input type="text" id="custName" placeholder="Your name (optional)">
      <input type="tel" id="custPhone" placeholder="Phone (optional)">
    </div>
    <textarea class="cart-note" id="orderNote" rows="2" placeholder="Special instructions…"></textarea>
    <button class="checkout-btn" id="placeOrderBtn">Place Order</button>
  </div>
</div>

{{-- Success overlay --}}
<div class="success-overlay" id="successOverlay">
  <div class="success-icon">🎉</div>
  <h2>Order Placed!</h2>
  <p id="successMsg">We've received your order and the kitchen is preparing it.</p>
  <p style="font-size:.85rem;color:var(--muted);">Order #<span id="orderId"></span></p>
  <button class="track-btn" id="backToMenuBtn">Back to Menu</button>
</div>

<script @nonce>
const PRODUCTS = @json($products);
const TABLE_TOKEN = "{{ $table->token }}";
const API_ORDER   = `/qr/${TABLE_TOKEN}/order`;
const CSRF        = null; // public route uses web CSRF-less via stateless

let cart = {};    // { product_id: { product, qty } }
let allCategories = [];

/* ─── Init ─── */
function init() {
  // Build categories
  const catSet = new Set(['All']);
  PRODUCTS.forEach(p => { if (p.category) catSet.add(p.category); });
  allCategories = [...catSet];

  renderCatTabs('All');
  renderProducts('All');
}

function renderCatTabs(active) {
  const tabs = document.getElementById('catTabs');
  tabs.innerHTML = allCategories.map(c =>
    `<button class="cat-btn${c === active ? ' active' : ''}" data-cat="${escHtml(c)}">${escHtml(c)}</button>`
  ).join('');
}

function filterBy(cat) {
  renderCatTabs(cat);
  renderProducts(cat);
}

function renderProducts(cat) {
  const list = cat === 'All' ? PRODUCTS : PRODUCTS.filter(p => p.category === cat);
  document.getElementById('productsGrid').innerHTML = list.map(p => {
    const inCart = cart[p.id];
    const qty    = inCart ? inCart.qty : 0;
    return `<div class="product-card${qty ? ' in-cart' : ''}" data-qty="${qty}" data-id="${p.id}">
      ${p.image
        ? `<img class="product-img" src="${escHtml(p.image)}" alt="${escHtml(p.name)}" loading="lazy">`
        : `<div class="product-img-placeholder">🍽</div>`}
      <div class="product-info">
        <div class="product-name">${escHtml(p.name)}</div>
        ${p.description ? `<div class="product-desc">${escHtml(p.description)}</div>` : ''}
        <div class="product-price">${formatMoney(p.price)}</div>
      </div>
    </div>`;
  }).join('');
}

function addToCart(id) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) return;
  if (cart[id]) cart[id].qty++;
  else cart[id] = { product: p, qty: 1 };
  updateCartBadge();
  // refresh current cat
  const activeCat = document.querySelector('.cat-btn.active')?.textContent ?? 'All';
  renderProducts(activeCat);
}

function updateQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) delete cart[id];
  updateCartBadge();
  renderCartItems();
  const activeCat = document.querySelector('.cat-btn.active')?.textContent ?? 'All';
  renderProducts(activeCat);
}

function updateCartBadge() {
  const count = Object.values(cart).reduce((s, x) => s + x.qty, 0);
  document.getElementById('cartCount').textContent = count;
}

function openCart() {
  renderCartItems();
  document.getElementById('cartOverlay').classList.add('open');
}

function closeCart() {
  document.getElementById('cartOverlay').classList.remove('open');
}

function closeCartOnBg(e) {
  if (e.target === document.getElementById('cartOverlay')) closeCart();
}

function renderCartItems() {
  const items = Object.values(cart);
  if (!items.length) {
    document.getElementById('cartItemsList').innerHTML = '<p style="color:#94a3b8;text-align:center;padding:16px;">Cart is empty</p>';
    document.getElementById('cartTotal').textContent = '';
    return;
  }
  let total = 0;
  document.getElementById('cartItemsList').innerHTML = items.map(({ product: p, qty }) => {
    const sub = p.price * qty;
    total += sub;
    return `<div class="cart-item">
      <span class="cart-item-name">${escHtml(p.name)}</span>
      <div class="qty-controls">
        <button class="qty-btn qty-minus" data-id="${p.id}" data-delta="-1">−</button>
        <span>${qty}</span>
        <button class="qty-btn qty-plus" data-id="${p.id}" data-delta="1">+</button>
      </div>
      <span class="cart-item-price">${formatMoney(sub)}</span>
    </div>`;
  }).join('');
  document.getElementById('cartTotal').innerHTML = `Total: <strong>${formatMoney(total)}</strong>`;
}

async function placeOrder() {
  const items = Object.values(cart);
  if (!items.length) { alert('Cart is empty!'); return; }

  const btn = document.getElementById('placeOrderBtn');
  btn.disabled = true;
  btn.textContent = 'Sending…';

  const payload = {
    customer_name:  document.getElementById('custName').value.trim() || null,
    customer_phone: document.getElementById('custPhone').value.trim() || null,
    notes:          document.getElementById('orderNote').value.trim() || null,
    items: items.map(({ product: p, qty }) => ({
      product_id: p.id,
      quantity:   qty,
    })),
  };

  try {
    const res = await fetch(API_ORDER, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (res.ok) {
      cart = {};
      updateCartBadge();
      closeCart();
      document.getElementById('orderId').textContent  = data.order_id;
      document.getElementById('successOverlay').classList.add('show');
    } else {
      alert(data.message ?? 'Failed to place order. Please try again.');
    }
  } catch (e) {
    alert('Network error. Please try again.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Place Order';
  }
}

function backToMenu() {
  document.getElementById('successOverlay').classList.remove('show');
  renderProducts('All');
}

function formatMoney(n) { return parseFloat(n).toFixed(2); }
function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

init();

/* ─── Static HTML listeners ─── */
document.getElementById('openCartBtn').addEventListener('click', openCart);
document.getElementById('cartOverlay').addEventListener('click', closeCartOnBg);
document.getElementById('placeOrderBtn').addEventListener('click', placeOrder);
document.getElementById('backToMenuBtn').addEventListener('click', backToMenu);

/* ─── Event delegation: category tabs ─── */
document.getElementById('catTabs').addEventListener('click', function(e) {
  const btn = e.target.closest('[data-cat]');
  if (!btn) return;
  filterBy(btn.dataset.cat);
});

/* ─── Event delegation: product cards ─── */
document.getElementById('productsGrid').addEventListener('click', function(e) {
  const card = e.target.closest('.product-card[data-id]');
  if (!card) return;
  addToCart(parseInt(card.dataset.id));
});

/* ─── Event delegation: cart qty buttons ─── */
document.getElementById('cartItemsList').addEventListener('click', function(e) {
  const btn = e.target.closest('[data-delta]');
  if (!btn) return;
  updateQty(parseInt(btn.dataset.id), parseInt(btn.dataset.delta));
});
</script>

{{-- Need CSRF for the POST --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
</body>
</html>
