CREATE TABLE IF NOT EXISTS dog_qr_scan_events (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
    viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash TEXT,
    user_agent TEXT,
    referrer TEXT,
    path TEXT
);

CREATE INDEX IF NOT EXISTS idx_dog_qr_scan_events_dog_time
    ON dog_qr_scan_events (dog_id, viewed_at DESC);
