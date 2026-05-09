BEGIN;

UPDATE feature_flags
SET is_enabled = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'trainer_marketplace_enabled';

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Trainer marketplace directory over saved training profiles.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'trainer_marketplace_enabled';

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
SELECT
    'trainer_marketplace_enabled',
    'could',
    'beta_enabled',
    NULL,
    'Beta 2',
    'Handlers can browse trainer contacts saved on their training profiles',
    'User can open a trainer directory and review saved trainer contacts',
    'Adds a directory view over existing training profile data.'
WHERE NOT EXISTS (
    SELECT 1
    FROM feature_roadmap
    WHERE flag_key = 'trainer_marketplace_enabled'
);

COMMIT;
