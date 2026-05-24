# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `cf0d1b2`
- Working tree: clean
- Live companion release: `v0.033` / version code `33`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.033.apk`

## What Was Finished This Session

### Web: app.php content refresh

Three stale sections updated in `app.php` (companion app landing page):

1. **Hero panel** — "What it replaces / Daily handler tasks" renamed to "What it is / Native Android — no browser wrappers"; body copy updated to accurately describe the fully-native state.

2. **"What's in the app" checklist** — removed the false "Goal intake, habit repair, and behavior risk views are next in line" sentence (all five have been native since v0.030–0.031). New heading: "Full handler workflow — native". Checklist now lists all ten items including one-line descriptions for each of the five training-tool screens. Added "Pull down to refresh any list" note.

3. **Download section** — body now explicitly names "all five training-tool screens" rather than vague "no XML layouts" language.

No structural changes to the page. PHP syntax clean; `deploy_local.sh` passes.

### Android UX improvements — error handling (v0.032, previous session)

- `SectionMessage(message, onRetry?)` composable: errors render as `errorContainer` card with Retry button; info/success stays as plain primary text
- `isErrorText()` helper + `isStatusError` state: global top-bar status message turns `error` red automatically on failure
- GoalIntake empty-state bug fixed: loading placeholder shown while loading; empty state suppressed when error message already showing
- All five section message sites wired to `SectionMessage()` with retry lambdas

### Android UX improvements — pull-to-refresh (v0.033, previous session)

- Compose BOM bumped `2024.06.00` → `2024.09.03` (Material3 1.2.1 → 1.3.1)
- `PullToRefreshBox` wraps the scrollable Column in all five training-tool sections
- `isPullingToRefresh` state; global `LinearProgressIndicator` suppressed while PTR spinner is active
- `setLoading(false)` clears the flag for GoalIntake/HabitRepair/BehaviorRisk; `loadRegressionEvents()` and `loadCandidateAssessments()` set it false explicitly in their `runOnUiThread` blocks

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.033` / version code `33`
- APK: `downloads/GuidePaw_Companion_v0.033.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment
- Compose BOM: `2024.09.03` (Material3 1.3.1)

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state. Any state that must clear on load completion needs to be explicitly set in their `runOnUiThread` blocks.

## Next Task Candidates

- Android: Overview section pull-to-refresh — currently has an explicit Refresh button; PTR would be a more natural gesture alongside the existing button.
- Android: Regression section initial empty state — when first navigated to, the section shows nothing until the user pulls to refresh (no "Load" button unlike BehaviorRisk). A "Pull down to load" hint card would help.
- Android: Menu Training section still has "🧩 Goal Builder" opening WebView — `goal_builder.php` is complex; could stay as WebView or become a native form.
- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Web: "How it works" and FAQ card row on the landing page (`index.php`) could be reviewed for freshness.
