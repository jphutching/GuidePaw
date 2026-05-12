CREATE TABLE IF NOT EXISTS paywall_catalog_items (
    slug TEXT PRIMARY KEY,
    item_type TEXT NOT NULL DEFAULT 'feature',
    label TEXT NOT NULL,
    summary TEXT NOT NULL DEFAULT '',
    included_text TEXT NOT NULL DEFAULT '',
    locked_text TEXT NOT NULL DEFAULT '',
    billing_model TEXT NOT NULL DEFAULT 'plan',
    required_tier TEXT NOT NULL DEFAULT 'free',
    scope TEXT NOT NULL DEFAULT 'user',
    price_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'USD',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_service_entitlements (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_slug TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    source TEXT NOT NULL DEFAULT 'admin',
    purchased_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP WITHOUT TIME ZONE NULL,
    notes TEXT
);

CREATE TABLE IF NOT EXISTS dog_service_entitlements (
    id SERIAL PRIMARY KEY,
    dog_id INTEGER NOT NULL REFERENCES dogs(id) ON DELETE CASCADE,
    service_slug TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    source TEXT NOT NULL DEFAULT 'admin',
    purchased_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP WITHOUT TIME ZONE NULL,
    notes TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_user_service_entitlements_active
    ON user_service_entitlements (user_id, service_slug)
    WHERE status = 'active';

CREATE UNIQUE INDEX IF NOT EXISTS ux_dog_service_entitlements_active
    ON dog_service_entitlements (dog_id, service_slug)
    WHERE status = 'active';

INSERT INTO paywall_catalog_items (
    slug, item_type, label, summary, included_text, locked_text,
    billing_model, required_tier, scope, price_cents, currency,
    sort_order, is_active, notes, updated_at
) VALUES
('free', 'plan', 'Free', 'Core handler tools for a single dog account.', 'Dashboard
Dogs
Logs
Training
Care
ADA tools
Notifications
Community', 'Trainer Marketplace
AI Training Assistant
Extra dog slots
QR Tracking add-ons', 'plan', 'free', 'user', 0, 'USD', 10, TRUE, 'The first dog stays free with the handler account.', CURRENT_TIMESTAMP),
('plus', 'plan', 'Plus', 'Adds the trainer directory and other premium planning surfaces.', 'Trainer Marketplace
Everything in Free', 'AI Training Assistant', 'plan', 'plus', 'user', 0, 'USD', 20, TRUE, 'Paid monthly plan tier.', CURRENT_TIMESTAMP),
('pro', 'plan', 'Pro', 'Premium tier for the AI training assistant and deeper planning tools.', 'Trainer Marketplace
AI Training Assistant
Everything in Plus', '', 'plan', 'pro', 'user', 0, 'USD', 30, TRUE, 'Highest monthly plan tier.', CURRENT_TIMESTAMP),
('trainer_marketplace', 'feature', 'Trainer Marketplace', 'Trainer directory and saved trainer contacts.', 'Browse saved trainer contacts
Call, email, and website buttons
Search trainer profiles saved on the dog', '', 'plan', 'plus', 'user', 0, 'USD', 40, TRUE, 'Gate this at Plus unless admin overrides.', CURRENT_TIMESTAMP),
('ai_training_assistant', 'feature', 'AI Training Assistant', 'Bounded training help for current problems and next steps.', 'Narrow next-step plan
Safety-aware suggestions
Follow-up questions', '', 'plan', 'pro', 'user', 0, 'USD', 50, TRUE, 'Gate this at Pro unless admin overrides.', CURRENT_TIMESTAMP),
('qr_tracking', 'service', 'QR Tracking', 'Public QR profile and scan history for a dog.', 'Public profile opens
Scan logging
Found-dog test alert
Lifetime access on one dog', '', 'lifetime_dog', 'free', 'dog', 2500, 'USD', 60, TRUE, 'First dog is free; extra dogs need a QR Tracking entitlement.', CURRENT_TIMESTAMP),
('extra_dog_slot', 'service', 'Extra Dog Slot', 'Add another dog beyond the first free dog.', 'One additional dog slot
Tied to the handler account', '', 'lifetime_user', 'free', 'user', 1500, 'USD', 70, TRUE, 'Used to keep the first dog free while allowing add-on dogs.', CURRENT_TIMESTAMP)
ON CONFLICT (slug) DO UPDATE SET
    item_type = EXCLUDED.item_type,
    label = EXCLUDED.label,
    summary = EXCLUDED.summary,
    included_text = EXCLUDED.included_text,
    locked_text = EXCLUDED.locked_text,
    billing_model = EXCLUDED.billing_model,
    required_tier = EXCLUDED.required_tier,
    scope = EXCLUDED.scope,
    price_cents = EXCLUDED.price_cents,
    currency = EXCLUDED.currency,
    sort_order = EXCLUDED.sort_order,
    is_active = EXCLUDED.is_active,
    notes = EXCLUDED.notes,
    updated_at = CURRENT_TIMESTAMP;
