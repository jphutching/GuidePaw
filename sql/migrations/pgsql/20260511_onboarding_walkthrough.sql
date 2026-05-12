ALTER TABLE users ADD COLUMN IF NOT EXISTS onboarding_completed_at TIMESTAMP;
UPDATE users
SET onboarding_completed_at = CURRENT_TIMESTAMP
WHERE onboarding_completed_at IS NULL;
