BEGIN;

UPDATE feature_flags
SET is_enabled = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'trucking_mode_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Session-backed trucking-day planner for travel, weather, and low-energy days.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'trucking_mode_enabled';

COMMIT;
