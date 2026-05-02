-- GuidePaw beta access request workflow
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS beta_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO beta_settings (setting_key, setting_value) VALUES
    ('beta_access_enabled', 'true'),
    ('public_registration_enabled', 'false'),
    ('beta_auto_email_enabled', 'false')
ON CONFLICT (setting_key) DO NOTHING;

CREATE TABLE IF NOT EXISTS beta_access_requests (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(40),
    reason TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    token_hash CHAR(64),
    token_preview VARCHAR(16),
    approved_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP WITHOUT TIME ZONE,
    denied_at TIMESTAMP WITHOUT TIME ZONE,
    redeemed_at TIMESTAMP WITHOUT TIME ZONE,
    linked_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    email_sent_at TIMESTAMP WITHOUT TIME ZONE,
    admin_notes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT beta_access_requests_status_check CHECK (
        status IN ('pending', 'approved', 'denied', 'redeemed', 'expired')
    ),
    CONSTRAINT beta_access_requests_email_unique UNIQUE (email)
);

CREATE INDEX IF NOT EXISTS idx_beta_access_requests_status ON beta_access_requests(status);
CREATE INDEX IF NOT EXISTS idx_beta_access_requests_email ON beta_access_requests(email);
CREATE INDEX IF NOT EXISTS idx_beta_access_requests_token_hash ON beta_access_requests(token_hash);

ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(254);
ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(160);
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(40);
ALTER TABLE users ADD COLUMN IF NOT EXISTS beta_request_id BIGINT REFERENCES beta_access_requests(id) ON DELETE SET NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique_not_null
ON users (lower(email))
WHERE email IS NOT NULL AND email <> '';
