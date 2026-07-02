<?php
if (!function_exists('gpChangelogEntries')) {
    function gpChangelogEntries(): array
    {
        return [
            [
                'version' => '0.102',
                'date'    => 'July 2, 2026',
                'title'   => 'Recurring meeting date picker refinements',
                'items'   => [
                    'For recurring: exclude day-of-month numbers; use only weekday letters (s,m,t,w,t,f,s) + multiple times per day.',
                    'Category now has custom "what it is" field. Timezone field notes it is for the meeting\'s timezone.',
                    'Android companion v0.102.',
                ],
            ],
            [
                'version' => '0.101',
                'date'    => 'July 1, 2026',
                'title'   => 'Recurring selectors & companion release',
                'items'   => [
                    'Cleaner weekday checkboxes (Sun–Sat) and natural "On the Nth weekday of the month" flow for recurring appointments.',
                    'Android companion v0.101 with improved schedule pickers and update check.',
                ],
            ],
            [
                'version' => '0.098',
                'date'    => 'May 28, 2026',
                'title'   => 'Google Play ready — web checkout, privacy policy',
                'items'   => [
                    'Plan upgrades and add-on purchases now open securely in your web browser.',
                    'Privacy policy added at guidepaw.app/privacy.',
                ],
            ],
            [
                'version' => '0.097',
                'date'    => 'May 28, 2026',
                'title'   => 'Found dog reports, live changelog & demo fix',
                'items'   => [
                    'QR Tracking now shows found-dog location reports submitted via your dog\'s public QR profile.',
                    'What\'s New dialog pulls live release notes from the server.',
                    'Demo mode banner now appears correctly for all demo accounts.',
                    'Demo reset timer syncs with the server\'s actual interval after each reset.',
                ],
            ],
            [
                'version' => '0.096',
                'date'    => 'May 27, 2026',
                'title'   => 'Isolated demo sessions',
                'items'   => [
                    'Each demo login creates a private isolated session — simultaneous users never interfere with each other.',
                    'Demo sessions auto-delete 30 minutes after last reset, keeping the server clean.',
                ],
            ],
            [
                'version' => '0.095',
                'date'    => 'May 27, 2026',
                'title'   => 'Demo countdown & reset',
                'items'   => [
                    'Demo banner now shows a live countdown to the next data reset.',
                    'Tap "Reset Now" to restore demo data instantly at any time.',
                ],
            ],
            [
                'version' => '0.094',
                'date'    => 'May 27, 2026',
                'title'   => 'Cleaner Settings & login security',
                'items'   => [
                    'API token list removed from Settings — not relevant to end users.',
                    'Logging in now revokes all previous sessions automatically.',
                ],
            ],
            [
                'version' => '0.093',
                'date'    => 'May 27, 2026',
                'title'   => 'Update check & downgrade guard',
                'items'   => [
                    'Check for Update button in Settings shows current version and update status.',
                    'Update banner no longer suggests downgrades — server-side force flag required.',
                ],
            ],
            [
                'version' => '0.092',
                'date'    => 'May 27, 2026',
                'title'   => 'Badge & tagline update',
                'items'   => [
                    'Updated brand badge image in the header.',
                    'New tagline: TRAINING TRUST FOR THE JOURNEY.',
                ],
            ],
            [
                'version' => '0.091',
                'date'    => 'May 27, 2026',
                'title'   => 'Header redesign',
                'items'   => [
                    'Brand header is taller with a larger shield badge and centered GuidePaw title.',
                ],
            ],
            [
                'version' => '0.090',
                'date'    => 'May 27, 2026',
                'title'   => 'Bug fixes & maintenance',
                'items'   => [
                    'Under-the-hood fixes and stability improvements.',
                ],
            ],
            [
                'version' => '0.089',
                'date'    => 'May 27, 2026',
                'title'   => 'Demo mode banner',
                'items'   => [
                    'Amber banner shown on every screen while using a demo account.',
                    'Banner confirms data resets after 15 minutes so users know what to expect.',
                ],
            ],
            [
                'version' => '0.088',
                'date'    => 'May 27, 2026',
                'title'   => 'Demo accounts & ID structure',
                'items'   => [
                    'Three pre-loaded demo accounts available from the login screen (demo.sarah, demo.marcus, demo.jennifer).',
                    'Demo sessions auto-reset to original data 15 minutes after login.',
                    'Tap "Use" next to any demo account to fill credentials instantly.',
                ],
            ],
            [
                'version' => '0.087',
                'date'    => 'May 27, 2026',
                'title'   => 'Rotation fix',
                'items'   => [
                    'Rotating the phone no longer triggers a data refresh.',
                ],
            ],
            [
                'version' => '0.086',
                'date'    => 'May 27, 2026',
                'title'   => 'Native registration, log detail & UI cleanup',
                'items'   => [
                    'Create Account now opens a native sign-up form — no browser required.',
                    'Tap any training log to see full notes and a focus-level indicator.',
                    'Upgrade Plan button routes directly to the in-app Plans screen.',
                    'Removed redundant "Open web feedback" button from the Feedback form.',
                ],
            ],
            [
                'version' => '0.085',
                'date'    => 'May 27, 2026',
                'title'   => "What's New dialog",
                'items'   => [
                    "App now shows a What's New summary after each update.",
                    'Web changelog page added at /changelog.php.',
                    'Changelog JSON API available at /api/changelog.php.',
                ],
            ],
            [
                'version' => '0.084',
                'date'    => 'May 27, 2026',
                'title'   => 'Phone-first uploads',
                'items'   => [
                    'Gallery, Take Photo, and Record Video buttons on training logs.',
                    'Native health document upload — PDF and images without a browser.',
                    'Camera permission and FileProvider added for live capture.',
                ],
            ],
            [
                'version' => '0.083',
                'date'    => 'May 27, 2026',
                'title'   => 'Public Dog Profile & native billing',
                'items'   => [
                    'Public Dog Profile viewer — share your dog\'s ID, contacts, and found-dog instructions.',
                    'Native Stripe checkout for add-on services inside the app.',
                    'Removed remaining web-only action buttons throughout the app.',
                ],
            ],
            [
                'version' => '0.082',
                'date'    => 'May 27, 2026',
                'title'   => 'Brand header',
                'items'   => [
                    'GuidePaw logo and tagline shown at the top of every screen.',
                ],
            ],
            [
                'version' => '0.081',
                'date'    => 'May 27, 2026',
                'title'   => 'Navigation fix',
                'items'   => [
                    'Training Programs now opens natively instead of launching a browser.',
                ],
            ],
            [
                'version' => '0.080',
                'date'    => 'May 27, 2026',
                'title'   => 'State Access Laws restored',
                'items'   => [
                    'Full state-by-state access law requirements reinstated.',
                ],
            ],
            [
                'version' => '0.079',
                'date'    => 'May 26, 2026',
                'title'   => 'FAQ, Add Dog, Plans & Trainer Marketplace',
                'items'   => [
                    'Native FAQ section with quick-reference answers.',
                    'Add Dog wizard with breed and details form.',
                    'Plans screen with tier cards and upgrade paths.',
                    'Trainer Marketplace listings visible in-app.',
                ],
            ],
            [
                'version' => '0.078',
                'date'    => 'May 26, 2026',
                'title'   => 'Five new native screens',
                'items'   => [
                    'Dog Profile editor.',
                    'Settings screen.',
                    'AI Coach conversation.',
                    'ESA Legal info reference.',
                    'Breed Finder tool.',
                ],
            ],
            [
                'version' => '0.077',
                'date'    => 'May 26, 2026',
                'title'   => 'Find a Vet',
                'items'   => [
                    'Manual location entry with up to 250-mile route coverage.',
                    'Google Places integration for nearby vet search.',
                ],
            ],
            [
                'version' => '0.076',
                'date'    => 'May 26, 2026',
                'title'   => 'Version display',
                'items'   => [
                    'App version number shown in the header.',
                ],
            ],
            [
                'version' => '0.075',
                'date'    => 'May 26, 2026',
                'title'   => 'Challenges fix',
                'items'   => [
                    'Challenges section correctly placed in the Training menu.',
                ],
            ],
            [
                'version' => '0.073',
                'date'    => 'May 26, 2026',
                'title'   => 'Collapsible grouped menu',
                'items'   => [
                    'Navigation reorganised into collapsible sections for easier browsing.',
                ],
            ],
            [
                'version' => '0.071',
                'date'    => 'May 26, 2026',
                'title'   => 'GPS state detection',
                'items'   => [
                    'Access Laws auto-detects your state via GPS.',
                ],
            ],
            [
                'version' => '0.070',
                'date'    => 'May 26, 2026',
                'title'   => 'State Access Laws',
                'items'   => [
                    'New section with state-by-state service dog and ESA access rules.',
                ],
            ],
            [
                'version' => '0.069',
                'date'    => 'May 26, 2026',
                'title'   => 'Health Summary upgrade',
                'items'   => [
                    'Health Summary section rebuilt with richer native display.',
                ],
            ],
            [
                'version' => '0.060',
                'date'    => 'May 25, 2026',
                'title'   => 'First public release',
                'items'   => [
                    'Training logs, goal intake, behavior risk scoring, regression tracking.',
                    'Habit repair protocol lookup.',
                    'Wearable data, medications, appointments, and notifications.',
                ],
            ],
        ];
    }
}
