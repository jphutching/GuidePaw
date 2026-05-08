BEGIN;

UPDATE feature_flags
SET is_enabled = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'video_reviews_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Video checkpoint review workflow for training clips.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'video_reviews_enabled';

COMMIT;
