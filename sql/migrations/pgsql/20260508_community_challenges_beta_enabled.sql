BEGIN;

UPDATE feature_flags
SET is_enabled = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'community_challenges_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Session-backed community challenge planner for training momentum.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'community_challenges_enabled';

COMMIT;
