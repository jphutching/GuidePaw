BEGIN;

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Bounded, safety-aware training troubleshooting assistant for handlers.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'ai_training_assistant_enabled';

COMMIT;
