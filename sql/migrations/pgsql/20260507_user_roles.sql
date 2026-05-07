ALTER TABLE users ADD COLUMN IF NOT EXISTS user_role TEXT NOT NULL DEFAULT 'user';

UPDATE users
SET user_role = 'admin'
WHERE COALESCE(is_admin, 0) = 1
  AND COALESCE(NULLIF(user_role, ''), 'user') <> 'admin';

UPDATE users
SET user_role = 'user'
WHERE user_role IS NULL OR trim(user_role) = '';

ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_role_check;
ALTER TABLE users ADD CONSTRAINT users_user_role_check CHECK (user_role IN ('admin', 'moderator', 'user'));

CREATE INDEX IF NOT EXISTS idx_users_user_role ON users(user_role);
