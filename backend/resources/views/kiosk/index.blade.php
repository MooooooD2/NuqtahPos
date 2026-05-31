<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1e293b">
    @php
        $isAr = app()->getLocale() === 'ar';
        $kioskTitle = __('pos.kiosk_title');
    @endphp
    <title>{{ $kioskTitle !== 'pos.kiosk_title' ? $kioskTitle : ($isAr ? 'الخدمة الذاتية' : 'Self-Service') }}</title>
    <link rel="manifest" href="/site.webmanifest">
    @vite(['resources/css/app.css'])
    <style @nonce>
        /* Kiosk — full-screen, no scrollbar, touch-optimized */
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; overflow: hidden; margin: 0; background: #0f172a; color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .kiosk-root { display: grid; grid-template-rows: auto 1fr auto; height: 100vh; }
        .kiosk-header { background: #1e293b; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #3b82f6; }
        .kiosk-body { overflow-y: auto; padding: 1.5rem; display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        .kiosk-footer { background: #1e293b; padding: 1rem 2rem; border-top: 2px solid #1e293b; }

        /* Products grid */
        .products-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; align-content: start; }
        .product-card { background: #1e293b; border-radius: 1rem; padding: 1.2rem; text-align: center; cursor: pointer; transition: transform .15s, background .15s; border: 2px solid transparent; user-select: none; }
        .product-card:hover { background: #334155; transform: scale(1.03); }
        .product-card:active { transform: scale(.97); }
        .product-card.selected { border-color: #3b82f6; }
        .product-card img { width: 80px; height: 80px; object-fit: cover; border-radius: .5rem; margin-bottom: .5rem; }
        .product-card .name { font-size: .9rem; font-weight: 600; margin-bottom: .25rem; }
        .product-card .price { font-size: 1.1rem; font-weight: 700; color: #10b981; }

        /* Cart panel */
        .cart-panel { background: #1e293b; border-radius: 1rem; padding: 1.2rem; display: flex; flex-direction: column; }
        .cart-panel h3 { margin: 0 0 1rem; font-size: 1.1rem; color: #94a3b8; border-bottom: 1px solid #334155; padding-bottom: .5rem; }
        .cart-items { flex: 1; overflow-y: auto; margin-bottom: 1rem; }
        .cart-item { display: flex; align-items: center; justify-content: space-between; padding: .6rem 0; border-bottom: 1px solid #1e293b; font-size: .9rem; }
        .cart-item .qty-ctrl { display: flex; align-items: center; gap: .5rem; }
        .qty-btn { width: 30px; height: 30px; border-radius: .5rem; border: none; background: #334155; color: #f1f5f9; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .qty-btn:hover { background: #475569; }
        .cart-total { font-size: 1.4rem; font-weight: 700; text-align: center; padding: .75rem; background: #0f172a; border-radius: .75rem; margin-bottom: 1rem; }
        .pay-btn { width: 100%; padding: 1rem; font-size: 1.2rem; font-weight: 700; background: #3b82f6; color: white; border: none; border-radius: .75rem; cursor: pointer; transition: background .15s; }
        .pay-btn:hover { background: #2563eb; }
        .pay-btn:disabled { background: #475569; cursor: not-allowed; }

        /* Payment modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.8); display: flex; align-items: center; justify-content: center; z-index: 999; }
        .modal-box { background: #1e293b; border-radius: 1.5rem; padding: 2rem; width: min(480px, 90vw); text-align: center; }
        .modal-box h2 { margin: 0 0 1.5rem; font-size: 1.5rem; }
        .payment-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .pay-method-btn { padding: 1.5rem; border-radius: 1rem; border: 2px solid #334155; background: #0f172a; color: #f1f5f9; font-size: 1.1rem; cursor: pointer; transition: all .15s; }
        .pay-method-btn:hover { border-color: #3b82f6; background: #1e3a5f; }
        .pay-method-btn.active { border-color: #3b82f6; background: #1e40af; }

        /* Attract / idle screen */
        .attract-screen { position: fixed; inset: 0; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 998; cursor: pointer; transition: opacity .5s; }
        .attract-screen h1 { font-size: 3rem; margin-bottom: 1rem; text-align: center; }
        .attract-screen p { font-size: 1.3rem; color: #94a3b8; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{ opacity:1 } 50%{ opacity:.4 } }
        .d-none { display: none !important; }
    </style>
</head>
<body>

{{-- Attract / idle screen — no inline onclick; wired via addEventListener below --}}
<div class="attract-screen" id="attractScreen">
    <div style="font-size:5rem">🏪</div>
    <h1>{{ $isAr ? 'مرحباً بك' : 'Welcome' }}</h1>
    <p>{{ $isAr ? 'المس الشاشة للبدء' : 'Touch the screen to start' }}</p>
</div>

<div class="kiosk-root" id="kioskRoot" style="display:none">

    {{-- Header --}}
    <header class="kiosk-header">
        <div style="font-size:1.4rem;font-weight:700">🏪</div>
        <div style="font-size:1rem;color:#94a3b8" id="kioskClock"></div>
        {{-- id="btnGoIdle" — wired via addEventListener --}}
        <button id="btnGoIdle" style="background:none;border:none;color:#94a3b8;font-size:1.3rem;cursor:pointer">⬅ {{ $isAr ? 'خروج' : 'Exit' }}</button>
    </header>

    {{-- Main body --}}
    <main class="kiosk-body">
        {{-- Products --}}
        <section>
            {{-- #categoryBar — delegated click via data-cat --}}
            <div id="categoryBar" style="display:flex;gap:.5rem;margin-bottom:1rem;overflow-x:auto;padding-bottom:.5rem"></div>
            {{-- #productsGrid — delegated click via data-product-id --}}
            <div class="products-grid" id="productsGrid">
                <div class="text-center py-5" style="color:#64748b;grid-column:1/-1">{{ $isAr ? 'جاري التحميل…' : 'Loading…' }}</div>
            </div>
        </section>

        {{-- Cart --}}
        <aside class="cart-panel">
            <h3>🛒 {{ $isAr ? 'سلة الطلبات' : 'Your Order' }}</h3>
            {{-- #cartItems — delegated click via data-action="qty-dec|qty-inc" --}}
            <div class="cart-items" id="cartItems">
                <p style="color:#64748b;text-align:center;margin-top:2rem">{{ $isAr ? 'السلة فارغة' : 'Cart is empty' }}</p>
            </div>
            <div class="cart-total" id="cartTotal">0.00</div>
            {{-- id="payBtn" — wired via addEventListener --}}
            <button class="pay-btn" id="payBtn" disabled>
                {{ $isAr ? 'الدفع الآن 💳' : 'Checkout 💳' }}
            </button>
        </aside>
    </main>

    {{-- Footer --}}
    <footer class="kiosk-footer" style="text-align:center;color:#475569;font-size:.85rem">
        {{ $isAr ? 'للمساعدة تواصل مع الكاشير' : 'For assistance, please contact the cashier' }}
    </footer>
</div>

{{-- Payment Modal --}}
<div class="modal-overlay d-none" id="payModal">
    <div class="modal-box">
        <h2>{{ $isAr ? 'اختر طريقة الدفع' : 'Choose Payment Method' }}</h2>
        <div class="cart-total" id="modalTotal" style="margin-bottom:1.5rem"></div>
        <div class="payment-methods">
            {{-- data-method — delegated click via #payModal listener --}}
            <button class="pay-method-btn" data-method="cash">💵 {{ $isAr ? 'نقداً' : 'Cash' }}</button>
            <button class="pay-method-btn" data-method="card">💳 {{ $isAr ? 'بطاقة' : 'Card' }}</button>
        </div>
        {{-- id="btnClosePayment" — wired via addEventListener --}}
        <button id="btnClosePayment" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:.9rem">{{ $isAr ? 'إلغاء' : 'Cancel' }}</button>
    </div>
</div>

<script @nonce>
const IS_AR = {{ $isAr ? 'true' : 'false' }};
const CSRF  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
let cart = {};
let allProducts = [];
let idleTimer;

// ── Clock ──────────────────────────────────────────────────────────────────
function updateClock() {
    document.getElementById('kioskClock').textContent =
        new Date().toLocaleTimeString(IS_AR ? 'ar-EG' : 'en-US');
}
setInterval(updateClock, 1000);
updateClock();

// ── Idle Management ───────────────────────────────────────────────────────
function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(goIdle, 90_000); // 90 s
}

function wakeUp() {
    document.getElementById('attractScreen').style.display = 'none';
    document.getElementById('kioskRoot').style.display = 'grid';
    loadProducts();
    resetIdle();
}

function goIdle() {
    cart = {};
    renderCart();
    document.getElementById('attractScreen').style.display = 'flex';
    document.getElementById('kioskRoot').style.display = 'none';
}

// ── Static button bindings ────────────────────────────────────────────────
document.getElementById('attractScreen').addEventListener('click', wakeUp);
document.getElementById('btnGoIdle')?.addEventListener('click', goIdle);
document.getElementById('payBtn')?.addEventListener('click', openPayment);
document.getElementById('btnClosePayment')?.addEventListener('click', closePayment);

// Payment method buttons — delegated on modal overlay
document.getElementById('payModal')?.addEventListener('click', e => {
    const btn = e.target.closest('[data-method]');
    if (btn) selectPayment(btn.dataset.method);
});

// Cart qty buttons — delegated on #cartItems
document.getElementById('cartItems').addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const pid = parseInt(btn.dataset.pid, 10);
    const qty = parseInt(btn.dataset.qty, 10);
    setQty(pid, qty);
});

// Product cards — delegated on #productsGrid
document.getElementById('productsGrid').addEventListener('click', e => {
    const card = e.target.closest('[data-product-id]');
    if (card) addToCart(parseInt(card.dataset.productId, 10));
});

// Category bar — delegated on #categoryBar
document.getElementById('categoryBar').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    filterCat(btn.dataset.cat || null);
});

// Reset idle on any interaction
document.addEventListener('touchstart', resetIdle);
document.addEventListener('click', resetIdle);

// ── Products ──────────────────────────────────────────────────────────────
async function loadProducts() {
    try {
        const r = await fetch('/api/kiosk/products', { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        allProducts = d.products ?? [];
        renderProducts(allProducts);
        renderCategories([...new Set(allProducts.map(p => p.category_name).filter(Boolean))]);
    } catch { /* silent fail in kiosk mode */ }
}

function renderProducts(products) {
    const grid = document.getElementById('productsGrid');
    if (!products.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:#64748b">${IS_AR ? 'لا توجد منتجات' : 'No products'}</div>`;
        return;
    }
    // data-product-id — click delegated above on #productsGrid
    grid.innerHTML = products.map(p => `
        <div class="product-card" data-product-id="${p.id}">
            ${p.image_url ? `<img src="${escHtml(p.image_url)}" alt="">` : '<div style="font-size:3rem">📦</div>'}
            <div class="name">${escHtml(p.name)}</div>
            <div class="price">${parseFloat(p.sale_price || p.price || 0).toFixed(2)}</div>
        </div>
    `).join('');
}

function renderCategories(cats) {
    const bar = document.getElementById('categoryBar');
    // data-cat="" means "All"; data-cat="Name" means that category — delegated above
    bar.innerHTML =
        `<button data-cat="" style="padding:.4rem 1rem;border-radius:2rem;border:none;background:#334155;color:#f1f5f9;cursor:pointer;white-space:nowrap">${IS_AR ? 'الكل' : 'All'}</button>` +
        cats.map(c => `<button data-cat="${escHtml(c)}" style="padding:.4rem 1rem;border-radius:2rem;border:none;background:#334155;color:#f1f5f9;cursor:pointer;white-space:nowrap">${escHtml(c)}</button>`).join('');
}

function filterCat(cat) {
    renderProducts(cat ? allProducts.filter(p => p.category_name === cat) : allProducts);
}

// ── Cart ───────────────────────────────────────────────────────────────────
function addToCart(productId) {
    const p = allProducts.find(x => x.id === productId);
    if (!p) return;
    if (!cart[productId]) cart[productId] = { product: p, qty: 0 };
    cart[productId].qty++;
    renderCart();
    resetIdle();
}

function setQty(productId, qty) {
    if (qty <= 0) { delete cart[productId]; }
    else { cart[productId].qty = qty; }
    renderCart();
}

function renderCart() {
    const items   = Object.values(cart);
    const total   = items.reduce((s, i) => s + i.qty * parseFloat(i.product.sale_price || i.product.price || 0), 0);
    const el      = document.getElementById('cartItems');
    const totalEl = document.getElementById('cartTotal');
    const payBtn  = document.getElementById('payBtn');

    if (!items.length) {
        el.innerHTML = `<p style="color:#64748b;text-align:center;margin-top:2rem">${IS_AR ? 'السلة فارغة' : 'Cart is empty'}</p>`;
        totalEl.textContent = '0.00';
        payBtn.disabled = true;
        return;
    }

    // data-action="qty-dec|qty-inc" + data-pid + data-qty — delegated above on #cartItems
    el.innerHTML = items.map(i => `
        <div class="cart-item">
            <span style="flex:1">${escHtml(i.product.name)}</span>
            <div class="qty-ctrl">
                <button class="qty-btn" data-action="qty-dec" data-pid="${i.product.id}" data-qty="${i.qty - 1}">−</button>
                <span>${i.qty}</span>
                <button class="qty-btn" data-action="qty-inc" data-pid="${i.product.id}" data-qty="${i.qty + 1}">+</button>
            </div>
            <span style="width:70px;text-align:end">${(i.qty * parseFloat(i.product.sale_price || i.product.price || 0)).toFixed(2)}</span>
        </div>
    `).join('');

    totalEl.textContent = total.toFixed(2);
    payBtn.disabled = false;
}

// ── Payment ────────────────────────────────────────────────────────────────
function openPayment() {
    document.getElementById('modalTotal').textContent = document.getElementById('cartTotal').textContent;
    document.getElementById('payModal').classList.remove('d-none');
}

function closePayment() {
    document.getElementById('payModal').classList.add('d-none');
}

async function selectPayment(method) {
    closePayment();
    const items = Object.values(cart).map(i => ({
        product_id: i.product.id,
        quantity:   i.qty,
    }));

    try {
        const r = await fetch('/api/kiosk/checkout', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ items, payment_method: method }),
        });
        const d = await r.json();

        if (d.success) {
            cart = {};
            renderCart();
            showSuccess(d.invoice_number);
        } else {
            showError(d.message ?? (IS_AR ? 'حدث خطأ' : 'Error'));
        }
    } catch {
        showError(IS_AR ? 'حدث خطأ في الاتصال' : 'Connection error');
    }
}

function showSuccess(invoiceNumber) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:#0f172a;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999';
    overlay.innerHTML = `
        <div style="font-size:5rem">✅</div>
        <h2 style="font-size:2rem;margin:1rem 0">${IS_AR ? 'شكراً لك!' : 'Thank you!'}</h2>
        <p style="color:#94a3b8">${IS_AR ? 'رقم الفاتورة:' : 'Invoice #'} ${escHtml(invoiceNumber)}</p>
        <p style="color:#64748b;margin-top:2rem">${IS_AR ? 'سيتم العودة للقائمة خلال ثوانٍ…' : 'Returning to menu…'}</p>
    `;
    document.body.appendChild(overlay);
    setTimeout(() => overlay.remove(), 5_000);
}

function showError(msg) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.8);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999';
    overlay.innerHTML = `
        <div style="background:#1e293b;border-radius:1.5rem;padding:2rem;text-align:center;max-width:360px">
            <div style="font-size:3rem">⚠️</div>
            <p style="margin:1rem 0;font-size:1.1rem">${escHtml(msg)}</p>
            <button id="errDismiss" style="padding:.6rem 2rem;background:#3b82f6;color:#fff;border:none;border-radius:.5rem;cursor:pointer;font-size:1rem">${IS_AR ? 'حسناً' : 'OK'}</button>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.querySelector('#errDismiss').addEventListener('click', () => overlay.remove());
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
</body>
</html>
