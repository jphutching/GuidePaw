ALTER TABLE users ADD COLUMN IF NOT EXISTS user_tier TEXT NOT NULL DEFAULT 'free';
UPDATE users SET user_tier = 'free' WHERE user_tier IS NULL OR trim(user_tier) = '';

