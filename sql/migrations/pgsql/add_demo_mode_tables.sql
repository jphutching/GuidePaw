-- Demo mode: session tracking and data snapshots for auto-reset after 15 minutes.

CREATE TABLE IF NOT EXISTS demo_sessions (
    user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    started_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_reset_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS demo_snapshots (
    id               SERIAL PRIMARY KEY,
    snapshot_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    table_name       TEXT    NOT NULL,
    row_data         JSONB   NOT NULL,
    captured_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_demo_snapshots_user_table
    ON demo_snapshots (snapshot_user_id, table_name);
