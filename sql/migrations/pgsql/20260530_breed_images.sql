-- Breed photo cache: stores Dog CEO API URLs so each breed is only fetched once.
-- color_variant is '' for the primary photo; future AKC color variants use a non-empty string.
CREATE TABLE IF NOT EXISTS breed_images (
    id            SERIAL PRIMARY KEY,
    breed_name    VARCHAR(120) NOT NULL,
    color_variant VARCHAR(80)  NOT NULL DEFAULT '',
    image_url     TEXT         NOT NULL DEFAULT '',
    source        VARCHAR(40)  NOT NULL DEFAULT 'dog_ceo',
    fetched_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (breed_name, color_variant)
);

INSERT INTO feature_flags (flag_key, label, description, is_enabled, sort_order)
VALUES (
    'breed_photos_enabled',
    'Breed Photos',
    'Show representative breed photos on questionnaire results. Images from Dog CEO API (CC0, no key required). Disable to revert all breed pages to text-only instantly.',
    1,
    55
)
ON CONFLICT (flag_key) DO NOTHING;
