USE psd_app_logs;

-- Add columns if they do not already exist.
ALTER TABLE daily_logs
    ADD COLUMN IF NOT EXISTS media_type ENUM('image', 'video') DEFAULT NULL AFTER media_url,
    ADD COLUMN IF NOT EXISTS media_mime VARCHAR(100) DEFAULT NULL AFTER media_type,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) DEFAULT NULL AFTER media_mime,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;

-- Tighten focus_level where supported. If the CHECK already exists, this may be skipped by the server.
-- legacy SQL/legacy SQL versions differ on named CHECK behavior, so this block is intentionally simple.
ALTER TABLE daily_logs
    MODIFY COLUMN focus_level TINYINT UNSIGNED DEFAULT 3;

-- Helpful indexes.
CREATE INDEX idx_log_date ON daily_logs (log_date);
CREATE INDEX idx_log_coordinates ON daily_logs (latitude, longitude);

-- Optional cleanup note:
-- If your server errors because an index already exists, remove that CREATE INDEX line and rerun.
