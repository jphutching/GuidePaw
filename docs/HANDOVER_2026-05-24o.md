# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `76d3bc6`
- Working tree: clean
- Live companion release: `v0.035` / version code `35`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.035.apk`

## What Was Finished This Session

### Web: index.php public landing section refresh (76d3bc6)

Three targeted edits to the public landing page (the section shown when not logged in, lines 22–242 of `index.php`):

1. **"What people use it for" bullet 5** — "Use the companion app for training and wearable data." → "Use the companion app for native training tools, goal intake, behavior tracking, and wearable data."

2. **Companion App card in the public tools grid** — "Training-first mobile access with wearable data built in." → "Native Android app — training logs, goal intake, habit repair, behavior tracking, and wearable data. No browser wrappers."

3. **"How it works" step 4** — "Keep support options and add-ons separate from the core workflow." (vague/filler) → "Use built-in tools — goal intake, habit repair, and behavior tracking — as the dog's work develops."

The authenticated dashboard section of `index.php` (lines 244–637) was not changed — it is data-driven and does not have the same staleness issue.

### Web: app.php content refresh (previous session, cf0d1b2)

- "What it replaces / Daily handler tasks" → "What it is / Native Android — no browser wrappers"
- "What's in the app" checklist expanded from 5 items to 10, covering all five training-tool native screens with one-line descriptions each
- Download section body updated to name "all five training-tool screens"

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
- `php -l index.php` — no syntax errors

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
- Web: `faq.php` — public FAQ page; worth checking whether the questions and answers reflect the current app state.
- Web: `training_program.php` — linked from the landing page hero as "See training tools"; worth checking for freshness now that training tools are all native in the app.
