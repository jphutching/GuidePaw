BEGIN;

CREATE TABLE IF NOT EXISTS wearable_sync_events (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    dog_id INTEGER,
    source VARCHAR(80) NOT NULL DEFAULT 'manual',
    device_name VARCHAR(120),
    recorded_for_date DATE,
    steps INTEGER,
    active_minutes INTEGER,
    distance_miles NUMERIC(8,2),
    avg_heart_rate INTEGER,
    sleep_hours NUMERIC(4,1),
    summary_text TEXT,
    notes TEXT,
    raw_payload TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

UPDATE feature_roadmap
SET lifecycle_status = 'beta_enabled',
    release_notes = 'Wearable sync hub for manually entered or pasted device summaries.',
    updated_at = CURRENT_TIMESTAMP
WHERE flag_key = 'wearable_integrations_enabled';

INSERT INTO feature_roadmap
(flag_key, priority_level, lifecycle_status, owner_name, milestone, success_metric, acceptance_criteria, release_notes)
SELECT
    'wearable_integrations_enabled',
    'could',
    'beta_enabled',
    NULL,
    'Beta 2',
    'Handlers can record a wearable summary for the active dog',
    'User can save a structured wearable snapshot and review recent syncs',
    'Adds a local wearable sync hub without vendor API dependencies.'
WHERE NOT EXISTS (
    SELECT 1
    FROM feature_roadmap
    WHERE flag_key = 'wearable_integrations_enabled'
);

COMMIT;
