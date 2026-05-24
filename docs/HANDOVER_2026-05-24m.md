# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `dda4349`
- Working tree: clean
- Live companion release: `v0.034` / version code `34`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.034.apk`

## What Was Finished This Session

### Web: app.php content refresh (cf0d1b2)

Three stale sections corrected in `app.php`:
- Hero panel: "What it replaces" → "What it is / Native Android — no browser wrappers"
- "What's in the app" checklist: removed false "Goal intake, habit repair, and behavior risk views are next in line" text; expanded to all ten items with one-line descriptions for each of the five training-tool screens; added pull-to-refresh mention
- Download section body: now explicitly names "all five training-tool screens"

### Android: Regression Engine empty-state hint card (v0.034, dda4349)

- When `regressionResult == null` and `regressionMessage` is blank (no in-flight load, no error), a `SummaryCard` now appears with "No regression data loaded yet. Pull down to refresh, or tap below." and a full-width "Load Events" button
- Refresh button moved from the always-visible Column level into the `if (result != null)` block — it no longer appears alongside the hint card or an error message

### State logic for Regression section (reference for future changes)

The three states when `regressionResult == null`:
1. `regressionMessage = "Loading..."` — SectionMessage shows it as small primary text; hint card suppressed (message not blank)
2. `regressionMessage = "Could not load..."` — SectionMessage shows error card with Retry; hint card suppressed (message not blank)
3. `regressionMessage = ""` — SectionMessage shows nothing; hint card shown with Load Events button

`loadRegressionEvents()` does NOT call `setLoading()` — it manages `regressionMessage` and `isPullingToRefresh` directly. Same pattern for `loadCandidateAssessments()`.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.034` / version code `34`
- APK: `downloads/GuidePaw_Companion_v0.034.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment
- Compose BOM: `2024.09.03` (Material3 1.3.1)

## Verification

- `./gradlew :app:assembleDebug` — BUILD SUCCESSFUL (37 tasks)
- `deploy_local.sh` smoke checks — all pass

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state and must clear `isPullingToRefresh` explicitly in their `runOnUiThread` blocks.

## Next Task Candidates

- Android: Overview section pull-to-refresh — currently has an explicit Refresh button; PTR would be a more natural gesture alongside it.
- Android: Menu Training section "🧩 Goal Builder" still opens WebView — `goal_builder.php` is complex; could stay or become a native form.
- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Web: "How it works" and FAQ card row on the landing page (`index.php`) could be reviewed for freshness.
