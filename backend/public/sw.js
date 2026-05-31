/**
 * Phase 11 — Enhanced PWA Service Worker v3
 * Features: Cache-first assets, Background Sync, Push Notifications,
 *           Offline fallback, Periodic sync, IndexedDB queue.
 */
const SW_VERSION = 'pos-v4';
const CACHE  = SW_VERSION;
const ASSETS = [
    '/css/styles.css',
    '/js/app.js',
    '/js/pos-offline.js',
];

const CACHE_PAGES = ['/pos', '/dashboard', '/kitchen'];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE)
            .then(c => c.addAll([...ASSETS, ...CACHE_PAGES]))
            .then(() => self.skipWaiting())
    );
});

// ── Activate (cleanup old caches) ────────────────────────────────────────────
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// ── Fetch Strategy ────────────────────────────────────────────────────────────
self.addEventListener('fetch', e => {
    const { request } = e;
    const url = new URL(request.url);

    if (url.origin !== location.origin) return;

    // Offline write queue for mutating API calls
    if (['POST','PUT','PATCH','DELETE'].includes(request.method) && url.pathname.startsWith('/api/')) {
        e.respondWith(handleOfflineWrite(request));
        return;
    }
    if (request.method !== 'GET') return;

    // Static assets → cache-first
    if (isStaticAsset(url.pathname)) {
        e.respondWith(caches.match(request).then(cached => cached || fetch(request).then(res => {
            const cloned = res.clone(); // clone BEFORE any async op
            caches.open(CACHE).then(c => c.put(request, cloned));
            return res;
        })));
        return;
    }

    // API → network-only (never cache)
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(fetch(request).catch(() =>
            new Response(JSON.stringify({ error: 'offline' }), { status: 503, headers: { 'Content-Type': 'application/json' } })
        ));
        return;
    }

    // Navigation (HTML pages) → network-first, cache fallback for offline only.
    // HTML pages carry unique CSP nonces — serving a stale cached copy would
    // misfire inline-handler violations (old onclick= attributes) and break
    // script-src nonce checks. Always fetch fresh; only use cache when offline.
    if (request.mode === 'navigate') {
        e.respondWith(networkFirstNav(request));
        return;
    }

    // Default → network-first with cache fallback
    e.respondWith(fetch(request)
        .then(res => { const cloned = res.clone(); caches.open(CACHE).then(c => c.put(request, cloned)); return res; })
        .catch(() => caches.match(request) || new Response('Offline', { status: 503 }))
    );
});

// ── Background Sync ───────────────────────────────────────────────────────────
self.addEventListener('sync', e => {
    if (e.tag === 'offline-invoices' || e.tag === 'offline-queue') {
        e.waitUntil(drainOfflineQueue());
    }
});

// ── Push Notifications ────────────────────────────────────────────────────────
self.addEventListener('push', e => {
    if (!e.data) return;
    let payload;
    try { payload = e.data.json(); } catch { payload = { title: 'POS', body: e.data.text() }; }

    e.waitUntil(self.registration.showNotification(payload.title ?? 'POS System', {
        body:    payload.body ?? '',
        icon:    '/icons/icon-192.png',
        badge:   '/icons/badge-72.png',
        tag:     payload.tag  ?? 'pos-notification',
        data:    payload.data ?? {},
        vibrate: [200, 100, 200],
        requireInteraction: payload.requireInteraction ?? false,
    }));
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    const url = e.notification.data?.url ?? '/dashboard';
    e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
        const existing = list.find(c => c.url.includes(location.origin));
        if (existing) { existing.postMessage({ type: 'navigate', url }); return existing.focus(); }
        return clients.openWindow(url);
    }));
});

// ── Periodic Sync ─────────────────────────────────────────────────────────────
self.addEventListener('periodicsync', e => {
    if (e.tag === 'stock-check') e.waitUntil(drainOfflineQueue());
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function isStaticAsset(p) {
    return p.startsWith('/css/') || p.startsWith('/js/') || p.startsWith('/fonts/') ||
           p.startsWith('/icons/') || /\.(woff2?|ttf|png|jpg|svg|ico|webp)$/.test(p);
}

// Network-first for HTML navigation — always serve fresh page.
// Falls back to cache only when the network is completely unreachable.
// This prevents stale CSP nonces and stale inline-handler violations.
async function networkFirstNav(request) {
    try {
        const res = await fetch(request);
        if (res.ok) {
            // Update cache so offline fallback stays reasonably fresh
            const cloned = res.clone();
            caches.open(CACHE).then(c => c.put(request, cloned));
        }
        return res;
    } catch {
        // Offline: serve cached page if available, else minimal offline message
        const cached = await caches.match(request);
        return cached ?? new Response(
            '<!DOCTYPE html><html><body><h2 style="font-family:sans-serif;text-align:center;margin-top:20vh">Offline — no cached version available</h2></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html' } }
        );
    }
}

async function handleOfflineWrite(request) {
    try { return await fetch(request.clone()); }
    catch {
        const item = { url: request.url, method: request.method, body: await request.clone().text(),
                        headers: [...request.headers.entries()] };
        await queuePush(item);
        if ('SyncManager' in self) await self.registration.sync.register('offline-queue');
        return new Response(JSON.stringify({ queued: true }), { status: 202, headers: { 'Content-Type': 'application/json' } });
    }
}

// ── IndexedDB Queue ──────────────────────────────────────────────────────────
const DB_NAME = 'pos-offline-queue', DB_VERSION = 1, STORE = 'queue';
function openDB() {
    return new Promise((res, rej) => {
        const r = indexedDB.open(DB_NAME, DB_VERSION);
        r.onupgradeneeded = e => e.target.result.createObjectStore(STORE, { autoIncrement: true });
        r.onsuccess = e => res(e.target.result);
        r.onerror   = rej;
    });
}
function queuePush(item) {
    return openDB().then(db => new Promise((res, rej) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).add(item).onsuccess = res;
        tx.onerror = rej;
    }));
}
async function drainOfflineQueue() {
    const db = await openDB();
    const items = await new Promise((res, rej) => {
        const result = [], tx = db.transaction(STORE, 'readonly');
        const cur = tx.objectStore(STORE).openCursor();
        cur.onsuccess = e => { const c = e.target.result; if (c) { result.push([c.key, c.value]); c.continue(); } else res(result); };
        cur.onerror = rej;
    });
    for (const [key, item] of items) {
        try {
            await fetch(item.url, { method: item.method, body: item.body, headers: Object.fromEntries(item.headers) });
            await new Promise((res, rej) => {
                const tx = db.transaction(STORE, 'readwrite');
                tx.objectStore(STORE).delete(key).onsuccess = res;
                tx.onerror = rej;
            });
        } catch { /* retry next sync */ }
    }
}
