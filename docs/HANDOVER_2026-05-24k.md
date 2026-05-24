# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `c438778`
- Working tree: clean
- Live companion release: `v0.033` / version code `33`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.033.apk`

## What Was Finished This Session

### Android UX improvements — error handling (v0.032)

- New `isErrorText(msg: String): Boolean` helper — detects error-prefix words ("could not", "failed", "error", "unable", "invalid", "not found"); used by both `SectionMessage` and `setLoading()`
- New `isStatusError by mutableStateOf(false)` state field
- `setLoading(loading, message)` now auto-sets `isStatusError = !loading && isErrorText(message)`, coloring the global top-bar status message `MaterialTheme.colorScheme.error` on failure without call-site changes
- New `SectionMessage(message: String, onRetry: (() -> Unit)? = null)` composable (after `SummaryCard`):
  - Error messages → `Card` with `errorContainer` background + optional Retry `TextButton`
  - Info/success messages → plain `Text` in primary color
- All 5 section message `Text()` calls replaced with `SectionMessage()` wired to their section's load function
- GoalIntake empty-state bug fixed: now shows "Loading goals…" while loading, and suppresses "No X goals found" when an error message is already showing

### Android UX improvements — pull-to-refresh (v0.033)

- Compose BOM bumped from `2024.06.00` → `2024.09.03` (Material3 1.2.1 → 1.3.1); adds `PullToRefreshBox`
- New `isPullingToRefresh by mutableStateOf(false)` state field
- Global `LinearProgressIndicator` suppressed while `isPullingToRefresh` is true (PTR spinner shown instead — no double spinners)
- `setLoading(false, ...)` clears `isPullingToRefresh` — handles Goal Intake, Habit Repair, Behavior Risk
- `loadRegressionEvents()` and `loadCandidateAssessments()` (which manage their own message state instead of using `setLoading`) explicitly set `isPullingToRefresh = false` in both their success and error `runOnUiThread` blocks
- `PullToRefreshBox` wraps the scrollable `Column` in all five training-tool sections: Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment
- `@OptIn(ExperimentalMaterial3Api::class)` added to BehaviorRiskSection and RegressionSection (PullToRefreshBox is experimental in Material3 1.3.1)

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.033` / version code `33`
- APK: `downloads/GuidePaw_Companion_v0.033.apk`
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
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state directly. Any state that needs clearing on load completion must be explicitly set in their `runOnUiThread` blocks.

## Next Task Candidates

- Android: Menu Training section still has "🧩 Goal Builder" opening WebView — `goal_builder.php` is complex; could stay as WebView or become a native form.
- Android: Consider native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Android: Overview section could get pull-to-refresh (currently has an explicit Refresh button; PTR would be a nicer gesture).
- Android: Empty states — some sections (Behavior Risk, Regression) have no empty-state card when data hasn't been loaded yet on first visit; BehaviorRisk already has a "Load Assessment" button but Regression just shows nothing until the user pulls to refresh.
- Web: Review `app.php` (companion app landing page) to reflect v0.033 release notes.
