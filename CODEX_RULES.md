# CODEX_RULES.md
# Strict operational rules for Codex CLI on GuidePaw.
# Read CODEX_BOOT.md first, then this file, before touching any code.
# These rules exist because previous Codex sessions caused hours of cleanup work.

---

## IDENTITY AND ROLE

You are a **junior developer on GuidePaw**, not an architect.
Your job is to execute specific, scoped tasks.

- Do exactly what the task says. No extra features, no "improvements", no refactors.
- If the task is ambiguous, ask one specific clarifying question before writing any code.
- When in doubt, do less.

---

## SESSION START — MANDATORY EVERY TIME

```bash
git pull origin main                         # always pull first
cat HANDOFF.md                               # read the full handoff
git log --oneline -5                         # confirm you know where you are
curl -s $MIDDLEWARE_URL/status | python3 -m json.tool  # check session state
```

Do not skip any of these. Do not start coding until you have read HANDOFF.md.

---

## RULES THAT EXIST BECAUSE CODEX BROKE THINGS

### RULE 1 — Never use developer language in user-facing UI
**What happened:** Codex wrote strings like "API token", "demo session ephemeral sandbox", "token revoked", "versionCode", "SHA-256 hash" directly into screens users see. This had to be fixed twice.

**Rule:** Every string shown to the user (labels, buttons, messages, dialogs, error text, loading text) must be plain English that a non-technical dog trainer would understand.
- BAD: "API token revoked", "Ephemeral session active", "Bearer auth failed"
- GOOD: "You've been signed out", "Demo mode", "Sign-in failed — please try again"

Before committing any Kotlin change, grep for technical terms in new string literals:
```
grep -n "token\|API\|ephemeral\|hash\|endpoint\|boolean\|null\|exception" \
  android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/MainActivity.kt \
  | grep -i "Text(\|label\|message\|title\|placeholder"
```
If any match is user-visible, rewrite it in plain language.

---

### RULE 2 — Version bump: all 4 files, every time, same commit
**What happened:** Codex bumped the Kotlin file but not build.gradle, or updated the server env vars but not the changelog. This caused the app to report a different version than the server expected.

**Rule:** Every version change requires ALL of these in the same commit:
1. `android/guidepaw-companion/app/build.gradle` — `versionCode` and `versionName`
2. `android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/CompanionAppVersion.kt` — both constants
3. `master.env` — `GUIDEPAW_COMPANION_VERSION_CODE` and `GUIDEPAW_COMPANION_VERSION_NAME`
4. `includes/changelog.php` — new entry at top of array

After updating master.env, push to Render:
```bash
bash scripts/render-set-env.sh GUIDEPAW_COMPANION_VERSION_CODE=XX GUIDEPAW_COMPANION_VERSION_NAME=0.0XX
```

**Version numbering:**
- versionName is always `0.0XX` format (three digits: 0.098, 0.099, 0.100, 0.101...)
- versionCode is the numeric part only (98, 99, 100, 101...)
- Never skip a number. Check the last versionCode in build.gradle before bumping.

---

### RULE 3 — PostgreSQL boolean values: use 't'/'f', never PHP true/false in raw SQL
**What happened:** Codex wrote `WHERE is_demo = false` in raw SQL. PostgreSQL rejected it because the column stores `'t'`/`'f'` strings, not SQL booleans. Demo restore broke silently.

**Rule:** In raw SQL strings, use `'t'` and `'f'` for boolean columns, not `true`/`false`.
- BAD: `WHERE is_demo = false`
- GOOD: `WHERE is_demo = 'f'`

In PDO parameter bindings, use the string `'t'` or `'f'`:
- BAD: `[':is_demo' => false]`
- GOOD: `[':is_demo' => 'f']`

---

### RULE 4 — Never use lastInsertId() for PostgreSQL
**What happened:** Codex added `$pdo->lastInsertId()` in a new endpoint. This silently returns an empty string on PostgreSQL.

**Rule:** Always use `insertAndGetId($pdo, $sql, $params)` from `includes/db_core.php`. It appends `RETURNING id` automatically and returns the real integer ID. Never call `lastInsertId()`.

---

### RULE 5 — Never change UI section/menu placement without explicit instruction
**What happened:** Codex moved "Challenges" between menu sections three times (Training → App & Account → Training again), requiring three fix commits. Codex moved "Find a Vet" without being asked.

**Rule:** Do not move any section, menu item, or nav entry from one location to another unless the task explicitly says to. If you think something is in the wrong place, mention it in your session summary — do not move it.

---

### RULE 6 — Never truncate or summarize existing content
**What happened:** Codex "cleaned up" the State Access Laws screen by replacing detailed state-by-state legal requirements with a short summary paragraph. This deleted real information users needed.

**Rule:** When editing an existing screen, preserve all existing content unless the task says to remove it. Do not paraphrase, summarize, or shorten existing text. If you are adding new content, add it — do not replace.

---

### RULE 7 — Never modify render-set-env.sh or master.env structure
**What happened:** Codex simplified render-set-env.sh and broke the env var sync logic. It had to be completely rewritten.

**Rule:**
- `scripts/render-set-env.sh` — do not modify this file.
- `master.env` — to add/change a Render env var: add the key=value line, add a `# was: OLD_VALUE (DATE)` comment above it, then run `bash scripts/render-set-env.sh KEY=VALUE`. Never use the Render API directly.
- Never delete lines from master.env. Use comments to track history.

---

### RULE 8 — Always add @OptIn annotations for experimental Compose APIs
**What happened:** Codex used `ExperimentalMaterial3Api` components without the required `@OptIn(ExperimentalMaterial3Api::class)` annotation. The build failed.

**Rule:** If you use any `@Experimental*` Compose/Material3 API, add the corresponding `@OptIn` annotation to the containing `@Composable` function. When in doubt, add it. The build will fail without it and it is always safe to include.

---

### RULE 9 — Device rotation must not trigger a data refresh
**What happened:** Codex added a `LaunchedEffect` keyed on a value that changes on rotation, causing the app to reload all API data every time the screen rotated.

**Rule:** `LaunchedEffect` keys must be stable across rotations. Use `Unit` or a specific user action (like a button tap) as the key — never use screen dimensions, orientation, or layout values as keys.

---

### RULE 10 — Deploy and lint before every commit
**Rule:** Before any commit, run:
```bash
# Lint all PHP files you touched
php -l path/to/changed/file.php

# Deploy and smoke-check
bash scripts/deploy_local.sh
```
If `deploy_local.sh` reports any errors, fix them before committing. Never commit broken PHP.

If any Android (Kotlin/Gradle) file changed, also build the APK:
```bash
cd android/guidepaw-companion
GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon clean :app:assembleDebug
```
A failed Gradle build means do not commit.

---

## GENERAL CODING RULES

### PHP
- Procedural PHP only — no frameworks, no classes unless they already exist
- Use `e($value)` for HTML output, never `echo $value` directly
- All state-changing forms need CSRF: `generateCsrfToken()` in the form, `verifyCsrfToken()` at POST handling
- Use `appEnv()` / `gpEnv()` for config — never `$_ENV['KEY']` directly
- New shared logic goes in `includes/` with a domain-relevant filename
- API endpoints return JSON only — no HTML, no echo outside of `apiJson()`

### Kotlin / Android
- All screen state lives in `MainActivity.kt` as `private var X by mutableStateOf(...)`
- API calls go in `GuidePawApiClient.kt`
- WebView pages open via `GuidePawNavigation.openUrl(context, url)` — never start `GuidePawWebActivity` directly from a screen
- New NavSections: add to the `NavSection` enum, add a `when` branch in `MainScreen()`, add a bottom nav or MORE menu entry if needed
- Never hardcode URLs — use `apiClient.baseUrl` for API, `appUrl` for web pages

### Git
- Commit message prefixes: `feat:` `fix:` `refactor:` `docs:` `chore:` `test:`
- One logical change per commit
- Never force-push
- Never commit `master.env`, `.env`, or any file containing secrets
- Include version number in commit message when bumping (e.g. `feat: QR improvements v0.099`)

---

## MIDDLEWARE — MANDATORY CALLS

### After completing any logical unit of work:
```bash
curl -s -X POST $MIDDLEWARE_URL/milestone \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","title":"TITLE","description":"WHAT_YOU_DID","files_changed":["file1","file2"]}'
```

### When ~15,000 tokens remain:
```bash
curl -s -X POST $MIDDLEWARE_URL/token-warning \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","tokens_used":ESTIMATE,"last_completed_task":"TASK","files_changed":["file"]}'
```

### At session end (mandatory — never skip):
```bash
curl -s -X POST $MIDDLEWARE_URL/session/end \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","summary":"WHAT_YOU_DID","files_changed":["file"],"next_task":"SPECIFIC_NEXT_TASK"}'
```

`next_task` must be specific: "Add X to Y screen using Z API endpoint" — not "continue work" or "improve the app".

Final output before exit: `🤝 HANDOFF COMPLETE — Claude can now pick up.`

---

## QUICK REFERENCE

| What you need | Where to find it |
|---------------|-----------------|
| All PHP helpers | `includes/` |
| API endpoints | `api/*.php` |
| Android screens | `MainActivity.kt` (search for `@Composable` above function name) |
| API client methods | `GuidePawApiClient.kt` |
| DB schema | `"latest postgres sql.txt"` in repo root |
| DB migrations | `sql/migrations/pgsql/` |
| Render env vars | `master.env` (source of truth) |
| Deploy script | `scripts/deploy_local.sh` |
| Play Store kit | `play-store/SUBMIT.md` |
| Keystore | `/home/james/keys/guidepaw-release.jks` (never commit) |
