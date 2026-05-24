# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

GuidePaw is a PHP/PostgreSQL web app for service dog training and handler management. It includes a native Android companion app that consumes the same JSON API. The stack is PostgreSQL-only — never reintroduce MySQL/MariaDB code paths.

- Local source: `/home/james/projects/gpb3/gpb3`
- Live served path: `/var/www/guidepaw`
- Local app URL: `https://10.147.18.184` (ZeroTier) or `https://10.230.194.242` (LAN)
- Database: `guidepaw` (PostgreSQL)

## Commands

```bash
# Lint a PHP file
php -l path/to/file.php

# Deploy repo to live path and run smoke checks
bash scripts/deploy_local.sh

# QA crawler (add GUIDEPAW_CHECK_API_ROUTES=yes to include API routes)
bash scripts/run_local_qa_crawler.sh

# Playwright browser tests (requires Chrome and a running local server)
npm run test:e2e
npm run test:e2e:headed    # headed mode

# Run a single Playwright test file
npx playwright test tests/browser/guidepaw-auth-crawl.spec.js

# Android debug APK
cd android/guidepaw-companion && GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon :app:assembleDebug

# DB shell / dump
sudo -u postgres psql guidepaw
sudo -u postgres pg_dump guidepaw -f /tmp/guidepaw-db.sql

# Service status
systemctl status nginx php8.5-fpm postgresql
```

At minimum, lint touched PHP files and run `deploy_local.sh` before committing. Run the Android Gradle build whenever `android/guidepaw-companion/` changes.

## Architecture

### PHP request lifecycle

Every page controller starts with `require_once 'includes/db_connect.php'`. That file:
1. Starts the session
2. Chains `app_config.php` → `error_handler.php` → `roles.php` → `paywalls.php` → `training_data.php` → `dog_age_helpers.php` → `db_core.php` → `address_helpers.php` → `handler_profile_helpers.php` → `auth_helpers.php` → `training_helpers.php` → `dog_access_helpers.php` → `dog_care_helpers.php`
3. Connects to PostgreSQL via PDO using env vars (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) with `$pdo` in `$GLOBALS`

Page controllers call `checkLogin()` (from `auth_helpers.php`) or `requireAdmin()` / `requireRole()` (from `authz.php`) near the top before doing any work.

### Auth and roles

- `checkLogin()` — verifies session, re-reads DB for live account status, sets `$_SESSION['user_role']` and `$_SESSION['is_admin']`
- Roles (highest to lowest): `master_admin`, `basic_admin`, `moderator`, `pro_trainer`, `user`
- The username `admin` is always resolved to `master_admin` regardless of the DB row value
- Admin guards must read from the DB via `gpCurrentUserIsAdmin($pdo)`, not just session flags

### API authentication

API endpoints in `api/` use Bearer token auth, not PHP sessions. The bootstrap for these files is `includes/api_auth.php` (which itself chains `includes/db_connect.php`).

- `requireApiUser($pdo)` — validates the Bearer token (or `X-Api-Token` header, or `access_token` query param), updates `last_used_at`, and returns `['id', 'username', 'token_id', 'active_dog_id']`. Calls `apiJson([...], 401)` and exits on failure.
- Tokens are stored as SHA-256 hashes in `api_tokens`; plain-text tokens are only ever shown once at issuance.
- `apiJson($payload, $status)` is the standard response helper — sets `Content-Type: application/json` and exits.
- Token TTL defaults to 90 days, configurable via `API_TOKEN_TTL_DAYS`.

### Android-to-web session handoff

The Android companion authenticates via API token but sometimes opens PHP pages in a WebView. `companion_session.php` bridges this: it accepts a POST with `access_token` and `next` (target page), validates the token, creates a PHP session for that user, and redirects to `next`. This is how the app opens web pages without re-prompting for login.

### Subscription tiers

Three tiers are defined in `includes/paywalls.php`: `free`, `plus`, `pro`. Tier aliases (`starter` → `free`, `premium` → `pro`, etc.) are normalized via `gpNormalizeUserTier()`. Use `gpTierRank()` to compare tiers numerically. Feature access is gated by checking a user's tier rank against a minimum required rank — do not compare tier strings directly.

### Feature flags

`featureEnabled($pdo, 'flag_key')` reads the `feature_flags` table (cached per request via a static). New flags are added via migrations in `sql/migrations/pgsql/`. Two non-obvious behaviors:
- `wearable_integrations_enabled` is hardcoded to `true` and bypasses the DB check.
- Unknown flags (not in the DB) default to **enabled** (`true`). This is intentional fail-open behavior.

### Audit logging

Admin-sensitive operations should call `writeAuditLog($pdo, $action, $targetType, $targetId, $details)` from `includes/audit_log.php`. The log is viewable at `admin_audit_log.php`. Currently audited action types include feature flag updates, roadmap edits, backup export/import, and training record archive/restore.

### Directory layout

| Path | Purpose |
|------|---------|
| `*.php` (root) | Page controllers and public/admin screens |
| `includes/` | Shared PHP helpers — domain-named (e.g. `includes/feedback_submission.php`) |
| `api/*.php` | JSON endpoints consumed by the Android app; must return `{"success": bool, "message": "..."}` |
| `sql/migrations/pgsql/` | PostgreSQL-only migrations; numbered or date-prefixed |
| `android/guidepaw-companion/` | Native Android companion (Kotlin, `com.guidepaw.companion`) |
| `tests/browser/` | Playwright specs targeting the local HTTPS server |
| `scripts/` | Deploy, crawl, smoke-test, and network utilities |
| `assets/`, `uploads/`, `storage/` | Public assets, user uploads, app storage |

### Android companion

The Android app (`com.guidepaw.companion`) uses Jetpack Compose. Source lives under `android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/`. Key files: `GuidePawApiClient.kt` (API calls), `GuidePawWebActivity.kt` (WebView pages), `GuidePawNavigation.kt` (bottom nav).

When the Android code changes, four files must move together:
1. `versionCode` and `versionName` in `app/build.gradle`
2. `CompanionAppVersion.kt` constants
3. `GUIDEPAW_COMPANION_VERSION_CODE` / `GUIDEPAW_COMPANION_VERSION_NAME` server env vars (read by `includes/companion_release.php` for the in-app update check)
4. The published APK filename in `downloads/`

### Brand header requirement

All visual PHP pages (those with a `<body>` tag) must include `includes/brand_header.php` and call `guidepawBrandHeader(...)`. `deploy_local.sh` enforces this — exceptions are: `login.php`, `public_dog_profile.php`, `report_found_dog.php`.

### Database migrations

Apply manually with `psql`. The schema baseline is `"latest postgres sql.txt"` in the repo root. Incremental migrations live in `sql/migrations/pgsql/` and are applied in order. There is no automated migration runner — `APP_ALLOW_DB_MIGRATIONS` env var gates in-app migration logic.

### Input validation and uploads

`includes/validation.php` provides the standard helpers: `cleanText()`, `cleanTextarea()`, `handleTrainingMediaUpload()` (images ≤8MB, video ≤50MB, audio ≤25MB), `handleDogDocumentUpload()` (PDF/image ≤12MB), and coordinate validators. Use these rather than rolling ad-hoc sanitization.

### Environment configuration

Config is read via `appEnv($key, $default)` in `includes/app_config.php`, which checks `$_SERVER`, `$_ENV`, then `getenv()`. Key vars: `APP_ENV`, `APP_URL`, `APP_STORAGE_PATH`, `APP_MODE`, `DB_*`. See `.env.render.example` for the documented Render deployment config.

### PostgreSQL insert pattern

Never use `lastInsertId()` — it doesn't work reliably with PDO/pgsql. Use `insertAndGetId($pdo, $sql, $params)` from `includes/db_core.php`, which appends `RETURNING id` automatically. Other useful `db_core.php` helpers: `dbDateAdd()` / `dbDateSub()` for portable interval arithmetic, `tableExists()` for schema guards.

### CSRF protection

All state-changing HTML forms must include a CSRF token. Use `generateCsrfToken()` to emit the hidden field and `verifyCsrfToken($_POST['csrf_token'])` at the top of the POST handler. Both are in `includes/auth_helpers.php`.

### In-app notifications

`gpCreateNotification($pdo, $userId, $title, ...)` from `includes/notifications.php` creates a notification row. The nav badge reads `gpUnreadNotificationCount()`. The `user_notifications` table is created lazily if it doesn't exist.

### Email delivery

Outbound email goes through ZeptoMail via `gpSendViaZeptoMail($to, $subject, $body)` in `includes/smtp_mailer.php`. Requires env var `ZEPTO_SEND_MAIL_TOKEN`. Falls back to PHP `mail()` if the token is absent.

### Billing / Stripe

`includes/stripe_checkout.php` builds Stripe Checkout sessions; `includes/stripe_webhook.php` and the root `stripe_webhook.php` handle incoming events. Uses raw HTTP calls (no Stripe PHP SDK). Key env vars: `GUIDEPAW_STRIPE_SECRET_KEY`, `GUIDEPAW_STRIPE_WEBHOOK_SECRET`.

### Coding conventions

- Procedural PHP matching the existing style; no frameworks
- Shared logic belongs in a domain-named helper in `includes/`, not duplicated across pages
- API endpoints return JSON only — no HTML body tags
- Feedback attachments are untrusted input; treat accordingly
- `e($value)` (from `auth_helpers.php`) is the standard HTML-escape helper
- Use `gpEnv()` (alias for `appEnv()`) when inside helper files that may be loaded before `app_config.php` is on the include path
