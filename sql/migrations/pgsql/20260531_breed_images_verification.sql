-- Add AKC verification columns to breed_images
ALTER TABLE breed_images
    ADD COLUMN IF NOT EXISTS verified_at          TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS verification_score   SMALLINT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS verification_notes   TEXT DEFAULT NULL;
