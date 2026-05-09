BEGIN;

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Behavior risk scoring page backed by incidents and candidate assessments.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'behavior_risk_scoring_enabled';

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
SELECT
    'behavior_risk_scoring_enabled',
    'should',
    'beta_enabled',
    NULL,
    'Beta 2',
    'Handlers can see a current behavior risk score for the active dog',
    'User can open a behavior risk scoring page that summarizes incidents and candidate risk',
    'Adds a guidance page for behavior risk and training planning.'
WHERE NOT EXISTS (
    SELECT 1
    FROM feature_roadmap
    WHERE flag_key = 'behavior_risk_scoring_enabled'
);

COMMIT;
