BEGIN;

INSERT INTO feature_flags (flag_key, label, description, is_enabled, sort_order)
VALUES (
    'candidate_comparison_enabled',
    'Candidate Comparison',
    'Compare multiple dog candidates side by side.',
    1,
    450
) ON CONFLICT (flag_key) DO UPDATE SET
    label = EXCLUDED.label,
    description = EXCLUDED.description,
    is_enabled = EXCLUDED.is_enabled,
    sort_order = EXCLUDED.sort_order;

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
SELECT
    'candidate_comparison_enabled',
    'should',
    'beta_enabled',
    NULL,
    'Beta 2',
    'Handlers can compare at least two accessible dogs side by side',
    'User can select dogs and see latest candidate assessments next to each other',
    'Adds a side-by-side candidate comparison workflow.'
WHERE NOT EXISTS (
    SELECT 1
    FROM feature_roadmap
    WHERE flag_key = 'candidate_comparison_enabled'
);

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Side-by-side candidate comparison workflow.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'candidate_comparison_enabled';

COMMIT;
