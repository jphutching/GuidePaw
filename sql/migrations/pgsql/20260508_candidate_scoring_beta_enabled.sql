BEGIN;

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Candidate scoring dashboard support for the active dog.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'candidate_scoring_enabled';

COMMIT;
