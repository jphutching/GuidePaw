BEGIN;

CREATE TABLE IF NOT EXISTS daily_log_media_reviews (
    id SERIAL PRIMARY KEY,
    daily_log_id INTEGER NOT NULL,
    reviewer_user_id INTEGER NOT NULL,
    rating_camera_stability SMALLINT NOT NULL DEFAULT 3 CHECK (rating_camera_stability BETWEEN 1 AND 5),
    rating_audio_clarity SMALLINT NOT NULL DEFAULT 3 CHECK (rating_audio_clarity BETWEEN 1 AND 5),
    rating_training_value SMALLINT NOT NULL DEFAULT 3 CHECK (rating_training_value BETWEEN 1 AND 5),
    review_notes TEXT,
    next_step TEXT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_review_log FOREIGN KEY (daily_log_id) REFERENCES daily_logs(id) ON DELETE CASCADE,
    CONSTRAINT fk_media_review_user FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT uq_media_review_per_user UNIQUE (daily_log_id, reviewer_user_id)
);

INSERT INTO feature_flags (flag_key, label, description, is_enabled, sort_order)
VALUES (
    'media_reviews_enabled',
    'Media Reviews',
    'Review camera and audio quality on training logs with attached media.',
    1,
    280
) ON CONFLICT (flag_key) DO NOTHING;

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
VALUES (
    'media_reviews_enabled',
    'should',
    'feature_flag_created',
    NULL,
    'Beta 2',
    'Handlers can review attached media on at least one training log',
    'User can rate camera stability, audio clarity, and training value for a log with media',
    'Adds a lightweight media review workflow for training logs.'
) ON CONFLICT DO NOTHING;

COMMIT;
