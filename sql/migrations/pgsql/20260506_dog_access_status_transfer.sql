ALTER TABLE dogs ADD COLUMN IF NOT EXISTS lifecycle_status TEXT NOT NULL DEFAULT 'active';
ALTER TABLE dogs ADD COLUMN IF NOT EXISTS lifecycle_note TEXT;
ALTER TABLE dogs ADD COLUMN IF NOT EXISTS retired_at TIMESTAMP NULL;

UPDATE dogs
SET lifecycle_status = 'active'
WHERE lifecycle_status IS NULL OR TRIM(lifecycle_status) = '';

ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS access_starts_at DATE NULL;
ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS access_ends_at DATE NULL;
ALTER TABLE dog_handlers ADD COLUMN IF NOT EXISTS revoked_at TIMESTAMP NULL;

CREATE TABLE IF NOT EXISTS dog_transfer_requests (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
    from_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    keep_previous_owner_access BOOLEAN NOT NULL DEFAULT TRUE,
    note TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_dogs_lifecycle_status ON dogs(lifecycle_status);
CREATE INDEX IF NOT EXISTS idx_dog_handlers_access_ends_at ON dog_handlers(access_ends_at);
CREATE INDEX IF NOT EXISTS idx_dog_transfer_requests_to_status ON dog_transfer_requests(to_user_id, status);
CREATE INDEX IF NOT EXISTS idx_dog_transfer_requests_dog_status ON dog_transfer_requests(dog_id, status);
