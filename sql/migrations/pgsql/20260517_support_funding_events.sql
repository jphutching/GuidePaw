CREATE TABLE IF NOT EXISTS support_funding_events (
    id BIGSERIAL PRIMARY KEY,
    stripe_event_id TEXT NOT NULL UNIQUE,
    stripe_event_type TEXT NOT NULL,
    stripe_checkout_session_id TEXT NOT NULL UNIQUE,
    support_type TEXT NOT NULL DEFAULT 'one_time',
    support_mode TEXT NOT NULL DEFAULT 'payment',
    user_id BIGINT NULL,
    client_reference_id TEXT NOT NULL DEFAULT '',
    customer_email TEXT NOT NULL DEFAULT '',
    amount_total_cents INTEGER NOT NULL DEFAULT 0,
    amount_subtotal_cents INTEGER NOT NULL DEFAULT 0,
    currency TEXT NOT NULL DEFAULT 'usd',
    payment_status TEXT NOT NULL DEFAULT '',
    payment_intent_id TEXT NOT NULL DEFAULT '',
    subscription_id TEXT NOT NULL DEFAULT '',
    livemode BOOLEAN NOT NULL DEFAULT FALSE,
    raw_event JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS support_funding_events_updated_at_idx ON support_funding_events (updated_at DESC);
CREATE INDEX IF NOT EXISTS support_funding_events_support_type_idx ON support_funding_events (support_type);
