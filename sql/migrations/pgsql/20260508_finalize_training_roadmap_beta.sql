BEGIN;

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Coach review workflow for regression follow-up.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'coach_review_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Lightweight media review workflow for training logs.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'media_reviews_enabled';

COMMIT;
