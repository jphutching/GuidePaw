CREATE TABLE IF NOT EXISTS user_training_preferences (
    user_id INTEGER NOT NULL,
    preference_key VARCHAR(80) NOT NULL,
    preference_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, preference_key)
);
