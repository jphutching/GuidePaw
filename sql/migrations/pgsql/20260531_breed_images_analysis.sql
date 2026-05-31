-- Add AI analysis columns to breed_images
ALTER TABLE breed_images
    ADD COLUMN IF NOT EXISTS full_dog_score  SMALLINT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS analyzed_at     TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL;
