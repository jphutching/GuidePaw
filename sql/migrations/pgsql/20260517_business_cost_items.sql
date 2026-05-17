CREATE TABLE IF NOT EXISTS business_cost_items (
    slug TEXT PRIMARY KEY,
    category TEXT NOT NULL DEFAULT 'current',
    label TEXT NOT NULL,
    summary TEXT NOT NULL DEFAULT '',
    billing_cycle TEXT NOT NULL DEFAULT 'monthly',
    unit_cost_cents INTEGER NOT NULL DEFAULT 0,
    quantity NUMERIC(12,2) NOT NULL DEFAULT 1,
    currency TEXT NOT NULL DEFAULT 'USD',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS business_cost_items_category_idx ON business_cost_items (category);
CREATE INDEX IF NOT EXISTS business_cost_items_active_idx ON business_cost_items (is_active);
