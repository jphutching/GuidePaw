BEGIN;

INSERT INTO feature_flags (flag_key, label, description, is_enabled, sort_order)
VALUES (
    'goal_builder_enabled',
    'Goal Builder',
    'Guided training-goal drafting for measurable behavior plans.',
    1,
    215
) ON CONFLICT (flag_key) DO UPDATE SET
    label = EXCLUDED.label,
    description = EXCLUDED.description,
    is_enabled = EXCLUDED.is_enabled,
    sort_order = EXCLUDED.sort_order,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
SELECT
    'goal_builder_enabled',
    'should',
    'beta_enabled',
    NULL,
    'Beta 2',
    'Handlers can turn a vague issue into a measurable training goal',
    'User can draft, preview, and save a structured goal from the builder',
    'Adds a guided goal-builder workflow on top of training goal intake.'
WHERE NOT EXISTS (
    SELECT 1
    FROM feature_roadmap
    WHERE flag_key = 'goal_builder_enabled'
);

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Guided goal-builder workflow for measurable training plans.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'goal_builder_enabled';

COMMIT;
