ALTER TABLE users ADD COLUMN IF NOT EXISTS backup_contact_name TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS backup_contact_phone TEXT;

UPDATE users
SET
    backup_contact_name = COALESCE(NULLIF(TRIM(backup_contact_name), ''), 'Optional backup contact'),
    backup_contact_phone = COALESCE(NULLIF(TRIM(backup_contact_phone), ''), 'Optional backup phone')
WHERE COALESCE(NULLIF(TRIM(backup_contact_name), ''), '') = ''
   OR COALESCE(NULLIF(TRIM(backup_contact_phone), ''), '') = '';

ALTER TABLE users ALTER COLUMN backup_contact_name SET DEFAULT 'Optional backup contact';
ALTER TABLE users ALTER COLUMN backup_contact_phone SET DEFAULT 'Optional backup phone';
