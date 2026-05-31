-- Offline SQLite schema for POS Enterprise desktop

CREATE TABLE IF NOT EXISTS offline_invoices (
    id          TEXT PRIMARY KEY,
    data        TEXT NOT NULL,        -- JSON payload
    status      TEXT DEFAULT 'pending', -- pending|synced|failed
    created_at  INTEGER NOT NULL,
    synced_at   INTEGER
);

CREATE TABLE IF NOT EXISTS offline_products (
    id          INTEGER PRIMARY KEY,
    data        TEXT NOT NULL,        -- full product JSON
    updated_at  INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS offline_customers (
    id          INTEGER PRIMARY KEY,
    data        TEXT NOT NULL,
    updated_at  INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS sync_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    type        TEXT NOT NULL,
    ref_id      TEXT,
    status      TEXT NOT NULL,
    message     TEXT,
    created_at  INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_offline_invoices_status ON offline_invoices(status);
CREATE INDEX IF NOT EXISTS idx_offline_products_updated ON offline_products(updated_at);
