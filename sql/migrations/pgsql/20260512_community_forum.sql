ALTER TABLE users ADD COLUMN IF NOT EXISTS facebook_url TEXT;

CREATE TABLE IF NOT EXISTS community_forum_threads (
    id BIGSERIAL PRIMARY KEY,
    created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    category TEXT NOT NULL DEFAULT 'general',
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
    is_locked BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS community_forum_posts (
    id BIGSERIAL PRIMARY KEY,
    thread_id BIGINT NOT NULL REFERENCES community_forum_threads(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_community_forum_threads_created_at ON community_forum_threads (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_community_forum_posts_thread_id ON community_forum_posts (thread_id, created_at ASC);
