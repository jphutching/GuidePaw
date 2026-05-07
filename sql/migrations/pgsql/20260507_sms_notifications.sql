ALTER TABLE users ADD COLUMN IF NOT EXISTS sms_phone TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS sms_notifications_enabled BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_users_sms_notifications_enabled ON users(sms_notifications_enabled);
