# 🤝 GuidePaw Handoff Document
**Last updated:** May 28, 2026
**Prepared by:** Claude (Senior Developer)
**For:** Codex CLI or next AI session

---

## ✅ CURRENT STATE — FULLY SYNCED

| Item | Value |
|------|-------|
| App version | **0.098 (versionCode 98)** |
| Git branch | `main` |
| Git HEAD | `7999467` |
| Local server | Deployed, serving v0.098 |
| Render env vars | `GUIDEPAW_COMPANION_VERSION_CODE=98`, `GUIDEPAW_COMPANION_VERSION_NAME=0.098` |
| APK path env var | `GUIDEPAW_COMPANION_APK_PATH=downloads/GuidePaw_Companion_v0.098_release.apk` |
| DB backup | `storage/guidepaw-backup-20260528.sql` (1.8MB, May 28 2026) |
| Working tree | **Clean** (HANDOFF.md and SESSION_STATE.json only have auto-generated changes) |

All four version files are in sync:
1. `android/guidepaw-companion/app/build.gradle` → `versionCode 98 / versionName '0.098'`
2. `CompanionAppVersion.kt` → `VERSION_CODE = 98 / VERSION_NAME = "0.098"`
3. `master.env` → `GUIDEPAW_COMPANION_VERSION_CODE=98 / VERSION_NAME=0.098`
4. `includes/changelog.php` → top entry is v0.098

---

## 🎯 IMMEDIATE NEXT TASK

**Submit to Google Play Store.**
Everything is built and ready. James just needs to follow the step-by-step guide:

```
open: play-store/SUBMIT.md
```

Files needed (all present):
- `play-store/GuidePaw_Companion_v0.098_release.aab` ← upload this to Play Console
- `play-store/store-listing.txt` ← copy/paste for app name, short/full description
- `play-store/data-safety-answers.txt` ← answers to Data Safety form
- `play-store/graphics/app_icon_512.png` ← drag to App icon field
- `play-store/graphics/feature_graphic_1024x500.png` ← drag to Feature graphic field
- Privacy policy live at: `https://guidepaw.app/privacy`
- Demo accounts for App Access declaration: `demo.sarah / demo.marcus / demo.jennifer`, password `Demo1234!`

**Keystore (never commit, never lose):**
- File: `/home/james/keys/guidepaw-release.jks`
- Password + alias: `/home/james/keys/guidepaw-keystore-credentials.txt`

---

## 📋 WHAT WAS BUILT (v0.095 → v0.098)

### v0.098 — Google Play Ready
- `privacy.php` added at `/privacy` (required by Play Console)
- `play-store/` submission kit: SUBMIT.md guide, store listing, data safety answers, graphics
- Signed release AAB/APK built with keystore

### v0.097 — Found Dog Reports + Live Changelog
- QR Tracking screen now shows found-dog location reports from `api/found_dog_reports.php`
- What's New dialog pulls live release notes from `api/changelog.php`
- Demo mode banner fixed for all demo accounts
- Demo reset timer syncs with server interval

### v0.096 — Isolated Demo Sessions
- Each demo login (demo.one/two/three) creates an ephemeral private sandbox
- Sandbox auto-deletes 30 min after last reset
- PHP false/true properly coerced to PostgreSQL 'f'/'t' booleans

### v0.095 — Demo Countdown + Reset
- Demo banner shows live countdown to next auto-reset
- Reset endpoint: `api/demo_reset.php`
- Demo smoke tests added to Playwright suite

---

## 🏗️ ARCHITECTURE QUICK REFERENCE

### Stack
- **Backend:** PHP 8.5 + PostgreSQL (PDO, no MySQL, no ORM)
- **Android:** Kotlin + Jetpack Compose, `com.guidepaw.companion`
- **Hosting:** Render (Docker, no autoDeploy — push to GitHub then manually trigger or use deploy hook)
- **Local dev:** nginx + php8.5-fpm on 10.147.18.184, deployed via `bash scripts/deploy_local.sh`

### Android Bottom Nav (5 tabs)
`OVERVIEW` → `TRAINING` → `DOGS` → `NOTIFICATIONS` → `MORE`

The MORE tab opens a full-screen menu with all other sections. All 45+ NavSections are defined in the `NavSection` enum in `MainActivity.kt:166`.

### Key Kotlin Files
| File | Purpose |
|------|---------|
| `MainActivity.kt` | ALL composable screens (10,654 lines) — all state lives here |
| `GuidePawApiClient.kt` | All API calls (2,718 lines) |
| `GuidePawNavigation.kt` | URL routing, WebView vs native decision |
| `GuidePawWebActivity.kt` | WebView wrapper for web pages |
| `CompanionAppVersion.kt` | Version constants (6 lines) |

### API Endpoints (`api/*.php`)
40+ endpoints. Key ones: `login.php`, `me.php`, `dogs.php`, `logs.php`, `qr_tracking.php`, `found_dog_reports.php`, `demo_reset.php`, `demo_status.php`, `app_release.php`, `changelog.php`

### Demo Accounts (for testing)
| Username | Password | Tier |
|----------|----------|------|
| demo.sarah | Demo1234! | free |
| demo.marcus | Demo1234! | plus |
| demo.jennifer | Demo1234! | pro |

The `demo.one / demo.two / demo.three` aliases are also valid — they map to the three above. Each login to a demo account spawns an isolated ephemeral sandbox (prefixed `demo_*` in the users table). The sandbox is destroyed 30 min after the last reset.

### Database
- Engine: PostgreSQL only
- Connection: `includes/db_connect.php` → `$pdo` in `$GLOBALS`
- Insert pattern: `insertAndGetId($pdo, $sql, $params)` — never `lastInsertId()`
- Boolean values in PostgreSQL: use `'t'`/`'f'` strings in raw SQL, not `true`/`false` PHP booleans
- Migrations: `sql/migrations/pgsql/` — apply manually with `psql`

### Feature Flags
All 31 feature flags are currently **enabled** in the DB. Unknown flags default to `true` (fail-open). Do not add DB entries for new flags unless you need them disabled.

---

## 🚀 DEPLOYMENT

### Local deploy (always do this after PHP changes)
```bash
bash scripts/deploy_local.sh
```

### Render deploy (after git push)
Render autoDeploy is OFF. After `git push origin main`, either:
- Use the deploy hook (in `middleware/.env` as `RENDER_DEPLOY_HOOK`)
- Or go to render.com dashboard → Manual Deploy

### Android build (after ANY Kotlin/Gradle changes)
```bash
cd android/guidepaw-companion
GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon clean :app:assembleDebug
```

### Signed release build (for Play Store / sideloading)
```bash
cd android/guidepaw-companion
GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon clean :app:bundleRelease
# Output: app/build/outputs/bundle/release/app-release.aab
# Copy to: downloads/GuidePaw_Companion_vX.XXX_release.aab
```

---

## ⚠️ DO NOT TOUCH (without explicit instruction)

- `master.env` — secrets file, gitignored, never commit
- `/home/james/keys/guidepaw-release.jks` — Play Store signing key, losing it = can never update the app
- `scripts/render-set-env.sh` — was rewritten to fix Codex damage; do not simplify it
- `includes/db_connect.php` — bootstrap chain; changing include order breaks everything
- `includes/paywalls.php` — tier logic; use `gpTierRank()` not string comparison
- `includes/auth_helpers.php` — CSRF + session auth; do not refactor

---

## 📌 MIDDLEWARE COMMANDS

```bash
# Milestone
curl -s -X POST $MIDDLEWARE_URL/milestone \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","title":"TITLE","description":"WHAT_YOU_DID","files_changed":["file"]}'

# Token warning (~15k tokens remaining)
curl -s -X POST $MIDDLEWARE_URL/token-warning \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","tokens_used":ESTIMATE,"last_completed_task":"TASK","files_changed":["file"]}'

# Session end
curl -s -X POST $MIDDLEWARE_URL/session/end \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","summary":"WHAT_YOU_DID","files_changed":["file"],"next_task":"SPECIFIC_NEXT_TASK"}'
```
