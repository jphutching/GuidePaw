BEGIN;

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Goal intake foundation for measurable behavior goals.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'goal_intake_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Training progression foundation for session and module tracking.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'training_progression_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Regression detection foundation for training relapse handling.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'regression_engine_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Quick repair protocol foundation for common problem behaviors.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'habit_repair_enabled';

COMMIT;
