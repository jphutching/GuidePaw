ALTER TABLE users ADD COLUMN IF NOT EXISTS account_status VARCHAR(24) NOT NULL DEFAULT 'active';
ALTER TABLE users ADD COLUMN IF NOT EXISTS deactivated_at TIMESTAMP WITHOUT TIME ZONE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deactivated_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deletion_note TEXT;

CREATE INDEX IF NOT EXISTS idx_users_account_status ON users(account_status);

ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_status_check;
ALTER TABLE users ADD CONSTRAINT users_account_status_check
CHECK (account_status IN ('active', 'deactivated', 'pending_delete'));
