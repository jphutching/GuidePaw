CREATE TABLE IF NOT EXISTS user_notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    related_dog_id INTEGER NULL REFERENCES dogs(id) ON DELETE SET NULL,
    notification_type TEXT NOT NULL DEFAULT 'info',
    priority TEXT NOT NULL DEFAULT 'normal',
    title TEXT NOT NULL,
    body TEXT NULL,
    action_url TEXT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSONB NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_user_notifications_user_created ON user_notifications(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_user_notifications_user_unread ON user_notifications(user_id, is_read, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_user_notifications_dog_created ON user_notifications(related_dog_id, created_at DESC);
