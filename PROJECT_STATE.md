# PROJECT_STATE.md
# Persistent source of truth for GuidePaw.
# Maintained by Claude. Never auto-overwritten by the middleware.
# Codex: read this file. Do not edit it. If something here is wrong, tell James.

---

## CURRENT VERSION

| Item | Value |
|------|-------|
| App version | **0.098 (versionCode 98)** |
| Git branch | `main` |
| Local server | nginx + php8.5-fpm, deployed at `/var/www/guidepaw` |
| Render app | https://guidepaw-ch3y.onrender.com |
| Render autoDeploy | **OFF** — push to GitHub, then manually trigger or use deploy hook |

### All 4 version files must stay in sync:
1. `android/guidepaw-companion/app/build.gradle` → `versionCode` / `versionName`
2. `CompanionAppVersion.kt` → `VERSION_CODE` / `VERSION_NAME`
3. `master.env` → `GUIDEPAW_COMPANION_VERSION_CODE` / `GUIDEPAW_COMPANION_VERSION_NAME`
4. `includes/changelog.php` → top entry version

---

## STACK

| Layer | Tech |
|-------|------|
| Backend | PHP 8.5, procedural, no frameworks |
| Database | PostgreSQL only — no MySQL, no ORM |
| PDO connection | `includes/db_connect.php` → `$GLOBALS['pdo']` |
| Android | Kotlin + Jetpack Compose, `com.guidepaw.companion` |
| Hosting | Render (Docker) |
| Local dev | nginx + php8.5-fpm on 10.147.18.184 |

---

## ANDROID APP STRUCTURE

**Bottom nav (5 tabs):** OVERVIEW → TRAINING → DOGS → NOTIFICATIONS → MORE

All sections are in the `NavSection` enum at `MainActivity.kt:166`. There are 45+ sections total. The MORE tab opens a full-screen section menu.

| File | Lines | Purpose |
|------|-------|---------|
| `MainActivity.kt` | 10,654 | All composable screens + all state |
| `GuidePawApiClient.kt` | 2,718 | All API calls |
| `GuidePawNavigation.kt` | 46 | URL routing: WebView vs native |
| `GuidePawWebActivity.kt` | 168 | WebView wrapper |
| `CompanionAppVersion.kt` | 6 | Version constants only |

---

## API ENDPOINTS (`api/*.php`)

40+ endpoints. Key ones:
`login.php` `me.php` `dogs.php` `logs.php` `qr_tracking.php`
`found_dog_reports.php` `demo_reset.php` `demo_status.php`
`app_release.php` `changelog.php` `billing.php` `notifications.php`

All API endpoints: Bearer token auth via `includes/api_auth.php` → `requireApiUser($pdo)`.
All responses: `apiJson($payload, $status)` — JSON only, no HTML.

---

## DATABASE RULES

- Insert: always `insertAndGetId($pdo, $sql, $params)` — never `lastInsertId()`
- Booleans in raw SQL: `'t'` / `'f'` strings — not PHP `true`/`false`
- Tier column: `user_tier` (not `subscription_tier`, not `tier`)
- Schema baseline: `"latest postgres sql.txt"` in repo root
- Migrations: `sql/migrations/pgsql/` — applied manually with psql

---

## DEMO ACCOUNTS

| Username | Password | Tier |
|----------|----------|------|
| demo.sarah | Demo1234! | free |
| demo.marcus | Demo1234! | plus |
| demo.jennifer | Demo1234! | pro |

`demo.one / demo.two / demo.three` also work (aliases).
Each login spawns an isolated ephemeral sandbox (`demo_*` in users table), auto-deleted 30 min after last reset.
Reset endpoint: `api/demo_reset.php`. Status: `api/demo_status.php`.

---

## FEATURE FLAGS

All 31 feature flags are currently **enabled**. Unknown flags default to `true` (fail-open).
Table: `feature_flags`. Helper: `featureEnabled($pdo, 'flag_key')`.
Exception: `wearable_integrations_enabled` is hardcoded `true`, bypasses DB.

---

## PLAY STORE STATUS (as of May 28, 2026)

- **Not yet submitted** — submission kit is ready
- AAB: `play-store/GuidePaw_Companion_v0.098_release.aab`
- Guide: `play-store/SUBMIT.md` (7-step drag-and-drop)
- Privacy policy: live at `https://guidepaw.app/privacy`
- Keystore: `/home/james/keys/guidepaw-release.jks` (never commit, never lose)

---

## LOAD-BEARING FILES — DO NOT TOUCH WITHOUT READING FULLY

| File | Risk |
|------|------|
| `includes/db_connect.php` | Bootstrap chain — include order matters |
| `includes/auth_helpers.php` | Session auth, CSRF, HTML escaping |
| `includes/paywalls.php` | Tier logic — use `gpTierRank()`, never compare strings |
| `scripts/render-set-env.sh` | Was rewritten to fix damage — do not simplify |
| `GuidePawApiClient.kt` | Changing method signatures breaks MainActivity |
| `GuidePawNavigation.kt` | Changing this affects every in-app link |

---

## DEPLOYMENT

```bash
# After any PHP change:
bash scripts/deploy_local.sh

# After any Android change:
cd android/guidepaw-companion
GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon clean :app:assembleDebug

# Push Render env var:
bash scripts/render-set-env.sh KEY=VALUE

# DB backup:
sudo -u postgres pg_dump guidepaw -f /tmp/guidepaw-backup-$(date +%Y%m%d).sql
```

---

## KNOWN TECHNICAL DEBT / FUTURE WORK

- Training Log list screen is still WebView-only (not natively built)
- Public Dog Profile viewer is WebView-only
- Community Challenges detail is WebView-only
- `api/billing.php` exists but Plans section may need additional endpoints
- Screenshots still needed for Play Store submission (take on physical device)

---

*Last updated: May 28, 2026 by Claude. Update this file when architecture changes.*
