<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Kitchen Display</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style @nonce>
  html,body{height:100%;overflow:hidden;background:#f8f9fa;}
  body{display:flex;flex-direction:column;}
  .scroll-area{flex:1;overflow-y:auto;}
  .kds-clock{font-size:1.6rem;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:.03em;}
  .card-top-bar{height:4px;border-radius:0;}
  .urgent-card{animation:urgentPulse 2s ease-in-out infinite;}
  @keyframes urgentPulse{0%,100%{box-shadow:0 0 0 0 rgba(220,53,69,.4);}50%{box-shadow:0 0 0 8px rgba(220,53,69,0);}}
  @keyframes fadeInUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
  .order-card{animation:fadeInUp .2s ease;}
  .conn-live{width:9px;height:9px;border-radius:50%;background:#198754;display:inline-block;animation:livePulse 2s infinite;}
  .conn-dead{width:9px;height:9px;border-radius:50%;background:#dc3545;display:inline-block;}
  @keyframes livePulse{0%{box-shadow:0 0 0 0 rgba(25,135,84,.5);}70%{box-shadow:0 0 0 6px rgba(25,135,84,0);}100%{box-shadow:0 0 0 0 rgba(25,135,84,0);}}
  .elapsed-danger{animation:blinkBadge 1s step-end infinite;}
  @keyframes blinkBadge{0%,100%{opacity:1;}50%{opacity:.3;}}
</style>
</head>
<body>

{{-- ═══ NAVBAR ═══ --}}
<nav class="navbar bg-white border-bottom shadow-sm px-3 py-2" style="flex-shrink:0">
  <div class="d-flex align-items-center gap-2">
    <span class="bg-warning rounded-2 d-flex align-items-center justify-content-center text-white fw-bold"
          style="width:36px;height:36px;font-size:1.1rem">🍳</span>
    <div>
      <div class="fw-bold text-dark lh-1" style="font-size:.95rem">Kitchen Display</div>
      <div class="text-muted lh-1" style="font-size:.6rem;letter-spacing:.07em;text-transform:uppercase">Live Orders</div>
    </div>
    <span class="conn-live ms-1" id="connDot"></span>
  </div>

  <div class="mx-auto text-center d-none d-md-block">
    <div class="kds-clock text-dark" id="clock">00:00:00</div>
    <div class="text-muted" id="clockDate" style="font-size:.62rem;text-transform:uppercase;letter-spacing:.05em"></div>
  </div>

  <div class="d-flex align-items-center gap-2">
    <span class="badge rounded-pill text-bg-warning d-flex align-items-center gap-1 px-3 py-2">
      <i class="fas fa-hourglass-half fa-xs"></i>
      <span id="statPending" class="fw-bold fs-6">0</span>
      <span class="d-none d-sm-inline fw-normal">Pending</span>
    </span>
    <span class="badge rounded-pill text-bg-primary d-flex align-items-center gap-1 px-3 py-2">
      <i class="fas fa-fire fa-xs"></i>
      <span id="statCooking" class="fw-bold fs-6">0</span>
      <span class="d-none d-sm-inline fw-normal">Cooking</span>
    </span>
    <span class="badge rounded-pill text-bg-success d-flex align-items-center gap-1 px-3 py-2">
      <i class="fas fa-bell fa-xs"></i>
      <span id="statReady" class="fw-bold fs-6">0</span>
      <span class="d-none d-sm-inline fw-normal">Ready</span>
    </span>
    <button class="btn btn-sm btn-outline-secondary" id="soundToggle" title="Toggle sound">
      <i class="fas fa-volume-high" id="soundIcon"></i>
    </button>
  </div>
</nav>

{{-- ═══ FILTER TABS ═══ --}}
<div class="bg-white border-bottom px-3" style="flex-shrink:0">
  <ul class="nav nav-tabs border-0" id="filterTabs">
    <li class="nav-item">
      <button class="nav-link active fw-semibold" data-filter="all">
        <i class="fas fa-table-cells-large fa-xs me-1"></i>All Orders
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link fw-semibold text-warning" data-filter="pending">
        <i class="fas fa-hourglass-half fa-xs me-1"></i>Pending
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link fw-semibold text-primary" data-filter="preparing">
        <i class="fas fa-fire fa-xs me-1"></i>Cooking
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link fw-semibold text-success" data-filter="ready">
        <i class="fas fa-check-circle fa-xs me-1"></i>Ready
      </button>
    </li>
  </ul>
</div>

{{-- ═══ ORDERS GRID ═══ --}}
<div class="scroll-area p-3" id="ordersGrid">
  <div class="d-flex align-items-center justify-content-center" style="height:60vh">
    <div class="text-center text-muted">
      <div class="spinner-border text-secondary mb-3"></div>
      <div class="small">Loading…</div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script @nonce>
const API  = '{{ route("api.kitchen.orders") }}';
const BASE = '{{ url("api/kitchen") }}';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

let knownIds = new Set(), soundOn = true, currentFilter = 'all', allOrders = [];

/* Clock */
function tick() {
  const n = new Date();
  document.getElementById('clock').textContent     = n.toLocaleTimeString('en-GB');
  document.getElementById('clockDate').textContent = n.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long'});
}
setInterval(tick, 1000); tick();

/* Sound */
document.getElementById('soundToggle').addEventListener('click', function () {
  soundOn = !soundOn;
  document.getElementById('soundIcon').className = soundOn ? 'fas fa-volume-high' : 'fas fa-volume-xmark';
  this.classList.toggle('btn-outline-danger',   !soundOn);
  this.classList.toggle('btn-outline-secondary', soundOn);
});
function playAlert() {
  if (!soundOn) return;
  try {
    const c = new (window.AudioContext||window.webkitAudioContext)();
    [[880,0],[1100,.14],[880,.28]].forEach(([f,w])=>{
      const o=c.createOscillator(),g=c.createGain();
      o.connect(g);g.connect(c.destination);o.frequency.value=f;
      g.gain.setValueAtTime(.35,c.currentTime+w);
      g.gain.exponentialRampToValueAtTime(.001,c.currentTime+w+.11);
      o.start(c.currentTime+w);o.stop(c.currentTime+w+.11);
    });
  } catch(e){}
}

/* Filter tabs */
document.querySelectorAll('#filterTabs .nav-link').forEach(t => {
  t.addEventListener('click', function(){
    document.querySelectorAll('#filterTabs .nav-link').forEach(x=>x.classList.remove('active'));
    this.classList.add('active');
    currentFilter = this.dataset.filter;
    renderGrid(allOrders);
  });
});

/* API */
async function req(url, method='GET') {
  const r = await fetch(url,{method,credentials:'same-origin',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}});
  return r.json();
}

/* Helpers */
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

function elapsedBadge(min, urgent) {
  const cls = (urgent||min>=20) ? 'badge text-bg-danger elapsed-danger'
            : (min>=10)         ? 'badge text-bg-warning'
            : 'badge text-bg-secondary';
  return `<span class="${cls}"><i class="fas fa-clock fa-xs me-1"></i>${min}m</span>`;
}

const TYPE = {dine_in:'fa-utensils',takeaway:'fa-bag-shopping',delivery:'fa-motorcycle',qr_order:'fa-qrcode'};
const STATUS = {
  pending:   {color:'warning', label:'Pending',  icon:'fa-hourglass-half'},
  preparing: {color:'primary', label:'Cooking',  icon:'fa-fire'},
  ready:     {color:'success', label:'Ready',    icon:'fa-check-circle'},
};

/* Render card */
function renderCard(o) {
  const urgent = o.is_urgent_val;
  const elapsed = o.elapsed_minutes_val ?? 0;
  const sm = STATUS[o.status] ?? STATUS.pending;
  const tIcon = TYPE[o.order_type] ?? 'fa-circle-dot';
  const tLabel = (o.order_type??'').replace('_',' ');

  const items = (o.items??[]).map(item=>`
    <li class="list-group-item px-3 py-2 d-flex align-items-start gap-2 border-0 border-bottom${item.status==='done'?' text-muted':''}">
      <span class="badge text-bg-warning rounded-2 fw-bold flex-shrink-0" style="font-size:.8rem;min-width:26px">
        ${parseFloat(item.quantity)}
      </span>
      <div class="flex-grow-1">
        <div class="small fw-medium${item.status==='done'?' text-decoration-line-through':''}">${esc(item.product_name)}</div>
        ${item.notes?`<div class="badge text-bg-warning text-wrap text-start mt-1 fw-normal" style="font-size:.68rem"><i class="fas fa-triangle-exclamation fa-xs me-1"></i>${esc(item.notes)}</div>`:''}
      </div>
      ${item.status==='done'?`<span class="badge text-bg-success flex-shrink-0"><i class="fas fa-check fa-xs"></i></span>`:''}
    </li>`).join('');

  let actions = '';
  if (o.status==='pending')
    actions=`<button class="btn btn-primary flex-fill" data-action="accept" data-id="${o.id}"><i class="fas fa-check me-1"></i>Accept</button>
             <button class="btn btn-outline-danger" style="width:44px" data-action="cancel" data-id="${o.id}" title="Cancel"><i class="fas fa-xmark"></i></button>`;
  else if (o.status==='preparing')
    actions=`<button class="btn btn-success flex-fill" data-action="ready" data-id="${o.id}"><i class="fas fa-bell me-1"></i>Mark Ready</button>
             <button class="btn btn-outline-danger" style="width:44px" data-action="cancel" data-id="${o.id}" title="Cancel"><i class="fas fa-xmark"></i></button>`;
  else if (o.status==='ready')
    actions=`<button class="btn btn-purple flex-fill btn-outline-secondary" data-action="serve" data-id="${o.id}"><i class="fas fa-concierge-bell me-1"></i>Mark Served</button>`;

  return `
<div class="card order-card h-100 shadow-sm border-${sm.color}${urgent?' urgent-card border-danger':''}" id="order-${o.id}" data-id="${o.id}">
  <div class="card-top-bar bg-${urgent?'danger':sm.color}"></div>
  <div class="card-header bg-${sm.color}-subtle border-bottom border-${sm.color} border-opacity-25 py-2 px-3">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div>
        <div class="fw-black text-dark lh-1 mb-1" style="font-size:1.3rem">#${esc(o.order_number)}</div>
        <div class="d-flex flex-wrap gap-1">
          <span class="badge text-bg-secondary rounded-pill fw-normal" style="font-size:.68rem">
            <i class="fas ${tIcon} fa-xs me-1"></i>${tLabel||'Order'}
          </span>
          <span class="badge text-bg-${sm.color} rounded-pill fw-normal" style="font-size:.68rem">
            <i class="fas ${sm.icon} fa-xs me-1"></i>${sm.label}
          </span>
        </div>
      </div>
      <div class="d-flex flex-column align-items-end gap-1">
        ${o.table_number?`<span class="badge text-bg-light border fw-normal" style="font-size:.68rem"><i class="fas fa-chair fa-xs me-1"></i>Table ${esc(o.table_number)}</span>`:''}
        ${elapsedBadge(elapsed,urgent)}
      </div>
    </div>
  </div>

  <ul class="list-group list-group-flush flex-grow-1">${items}</ul>

  ${o.notes?`<div class="card-body py-2 px-3 border-top bg-warning-subtle">
    <small class="text-warning-emphasis"><i class="fas fa-note-sticky me-1"></i>${esc(o.notes)}</small>
  </div>`:''}

  ${actions?`<div class="card-footer bg-transparent pt-2 pb-2 border-top d-flex gap-2">${actions}</div>`:''}
</div>`;
}

/* Render grid */
function renderGrid(orders) {
  const grid = document.getElementById('ordersGrid');
  const list = (currentFilter==='all' ? [...orders] : orders.filter(o=>o.status===currentFilter))
    .sort((a,b)=>{
      if(a.is_urgent_val!==b.is_urgent_val) return a.is_urgent_val?-1:1;
      return (b.elapsed_minutes_val??0)-(a.elapsed_minutes_val??0);
    });

  if (!list.length) {
    const msg = currentFilter==='all' ? 'No active orders' : 'No '+currentFilter+' orders';
    grid.innerHTML=`
      <div class="d-flex flex-column align-items-center justify-content-center gap-3 text-center py-5">
        <div class="display-1">🍽️</div>
        <div class="fw-semibold text-muted fs-5">${msg}</div>
        <div class="text-muted small">Waiting for new orders…</div>
      </div>`;
    return;
  }

  grid.innerHTML=`<div class="row g-3">${
    list.map(o=>`<div class="col-12 col-sm-6 col-lg-4 col-xxl-3">${renderCard(o)}</div>`).join('')
  }</div>`;
}

/* Event delegation */
document.getElementById('ordersGrid').addEventListener('click', async function(e){
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const {action,id} = btn.dataset;
  const orig = btn.innerHTML;
  btn.disabled=true;
  btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
  try {
    if(action==='accept') await req(`${BASE}/${id}/accept`,'POST');
    if(action==='ready')  await req(`${BASE}/${id}/ready`, 'POST');
    if(action==='serve')  await req(`${BASE}/${id}/served`,'POST');
    if(action==='cancel'){
      if(!confirm('Cancel this order?')){btn.disabled=false;btn.innerHTML=orig;return;}
      await req(`${BASE}/${id}/cancel`,'POST');
    }
    await poll();
  } catch(err){ console.error(err); btn.disabled=false; btn.innerHTML=orig; }
});

/* Poll */
async function poll(){
  try {
    const {orders=[],stats={}} = await req(API);
    orders.forEach(o=>{ if(!knownIds.has(o.id)) playAlert(); knownIds.add(o.id); });
    document.getElementById('statPending').textContent = stats.pending??0;
    document.getElementById('statCooking').textContent = stats.preparing??0;
    document.getElementById('statReady').textContent   = stats.ready??0;
    allOrders=orders; renderGrid(orders);
    const d=document.getElementById('connDot');
    d.className='conn-live ms-1';
  } catch(e){
    console.error(e);
    document.getElementById('connDot').className='conn-dead ms-1';
  }
}
poll();
setInterval(poll,8000);
</script>
</body>
</html>
