# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `1d62138`
- Working tree: clean
- Live companion release: `v0.035` / version code `35`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.035.apk`

## What Was Finished This Session

### Web: service_dog_esa_legal_info.php fixes (1d62138)

Two targeted edits:

1. **Bottom line section — leaked draft note removed** — The body paragraph read "The draft you shared lines up with the big federal points, but it needed softer housing wording and a clearer air-travel distinction. This page adds the missing public-facing summary without replacing the detailed ADA and air-travel notes already in GuidePaw." This was development/drafting language that was never meant to be published. Replaced with a proper public-facing summary: what the page covers, where to go for detail, and a reminder to verify from official sources.

2. **GuidePaw note description updated** — "GuidePaw is a practical organizer for handlers. It helps with training logs, public profiles, breed research, and support tools." updated to name the native Android companion app alongside the web tools, consistent with the refreshes to `app.php`, `index.php`, and `faq.php` this session.

### Web: service_dog_rights.php review (no changes needed)

Reviewed and found current — ADA two-question rule, permissible/prohibited staff asks, grounds for removal, handler responsibilities, HIPAA clarification, DOJ ADA Information Line numbers (800-514-0301 / TTY 800-514-0383), and sticky action links all accurate. No stale language, no "coming soon" text.

### Web: training_program.php nav label fix (f1111aa)

Nav button "AKC programs" renamed to "Programs & tests" to match the actual section heading ("Helpful programs and tests"). Rest of the page reviewed and found current — data-driven, no stale content.

### Web content refresh completed this session (full summary)

| File | What changed |
|------|-------------|
| `app.php` | Checklist expanded to all 10 native screens; stale "next in line" text removed |
| `index.php` | Companion app bullet and grid card updated; "How it works" step 4 rewritten |
| `faq.php` | Two new FAQ entries; opener answer updated |
| `training_program.php` | Nav button label fixed to match section heading |
| `service_dog_esa_legal_info.php` | Leaked draft note removed; GuidePaw description updated |
| `service_dog_rights.php` | Reviewed — no changes needed |

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.035` / version code `35`
- APK: `downloads/GuidePaw_Companion_v0.035.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment)

## Verification

- `deploy_local.sh` smoke checks — all pass
- `php -l service_dog_esa_legal_info.php` — no syntax errors

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state and must clear `isPullingToRefresh` explicitly in their `runOnUiThread` blocks.

## Next Task Candidates

- Android: Menu Training section "🧩 Goal Builder" still opens WebView — `goal_builder.php` is complex; could stay or become a native form.
- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Web: No remaining freshness review items from this session — all flagged pages have been reviewed.
