// POS Offline – IndexedDB product cache + invoice queue
const PosDB = {
    DB_NAME: 'pos_offline_v1',
    DB_VER:  1,
    _db:     null,

    open() {
        if (this._db) return Promise.resolve(this._db);
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(this.DB_NAME, this.DB_VER);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('products')) {
                    const s = db.createObjectStore('products', { keyPath: 'id' });
                    s.createIndex('barcode', 'barcode', { unique: false });
                }
                if (!db.objectStoreNames.contains('offline_queue')) {
                    db.createObjectStore('offline_queue', { keyPath: 'id', autoIncrement: true });
                }
            };
            req.onsuccess  = e => { this._db = e.target.result; resolve(this._db); };
            req.onerror    = e => reject(e.target.error);
        });
    },

    async cacheProducts(products) {
        const db = await this.open();
        await new Promise((resolve, reject) => {
            const tx    = db.transaction('products', 'readwrite');
            const store = tx.objectStore('products');
            products.forEach(p => store.put(p));
            tx.oncomplete = () => resolve();
            tx.onerror    = e => reject(e.target.error);
        });
    },

    async searchProducts(query) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const req = db.transaction('products', 'readonly')
                          .objectStore('products').getAll();
            req.onsuccess = () => {
                const q = query.toLowerCase().trim();
                resolve(req.result.filter(p =>
                    (p.name    && p.name.toLowerCase().includes(q))    ||
                    (p.barcode && p.barcode.toLowerCase().includes(q))
                ));
            };
            req.onerror = e => reject(e.target.error);
        });
    },

    // Find a single product by exact barcode match, else by name if only one result
    async findExact(query) {
        const results = await this.searchProducts(query);
        const q = query.toLowerCase().trim();
        const byBarcode = results.find(p => p.barcode && p.barcode.toLowerCase() === q);
        if (byBarcode) return byBarcode;
        return results.length === 1 ? results[0] : null;
    },

    async queueInvoice(data) {
        const offline_uuid = (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : (Date.now().toString(36) + Math.random().toString(36).slice(2));

        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction('offline_queue', 'readwrite');
            const req = tx.objectStore('offline_queue').add({
                offline_uuid,
                payload: { ...data.payload, offline_uuid },
                queued_at: new Date().toISOString(),
            });
            // Resolve with the UUID so callers can reference it
            req.onsuccess = () => resolve({ idb_id: req.result, offline_uuid });
            tx.onerror    = e => reject(e.target.error);
        });
    },

    async getQueue() {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const req = db.transaction('offline_queue', 'readonly')
                          .objectStore('offline_queue').getAll();
            req.onsuccess = () => resolve(req.result);
            req.onerror   = e => reject(e.target.error);
        });
    },

    async removeFromQueue(id) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('offline_queue', 'readwrite');
            tx.objectStore('offline_queue').delete(id);
            tx.oncomplete = () => resolve();
            tx.onerror    = e => reject(e.target.error);
        });
    },
};
