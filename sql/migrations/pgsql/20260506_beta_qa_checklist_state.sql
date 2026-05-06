CREATE TABLE IF NOT EXISTS beta_qa_checklist_state (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    checklist_key TEXT NOT NULL DEFAULT 'guidepaw_beta_qa_checklist_v1',
    checked_items JSONB NOT NULL DEFAULT '{}'::jsonb,
    notes TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, checklist_key)
);

CREATE INDEX IF NOT EXISTS idx_beta_qa_checklist_state_user_key ON beta_qa_checklist_state(user_id, checklist_key);
