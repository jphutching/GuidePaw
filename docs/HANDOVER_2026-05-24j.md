# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `aa71bdc` (local only — not yet pushed)
- Working tree: clean
- Live companion release: `v0.032` / version code `32`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.032.apk`

## What Was Finished This Session

### Android UX improvements (v0.032)

- New `isErrorText(msg: String): Boolean` private helper — detects error prefixes ("could not", "failed", "error", "unable", "invalid", "not found"); used by both `SectionMessage` and `setLoading()`
- New `isStatusError by mutableStateOf(false)` Activity state field
- `setLoading(loading, message)` now auto-sets `isStatusError = !loading && isErrorText(message)` so the global top-bar status message turns `MaterialTheme.colorScheme.error` red on failure without any call-site changes
- New `SectionMessage(message: String, onRetry: (() -> Unit)? = null)` composable (placed after `SummaryCard`):
  - If `isErrorText(message)` → renders a `Card` with `errorContainer` background, `onErrorContainer` text, and an optional Retry `TextButton`
  - Otherwise → plain `Text` in `primary` color (success / info)
- All 5 bare section message `Text()` calls replaced with `SectionMessage()` wired to their load functions:
  - `goalIntakeMessage` → retry calls `loadGoalIntake(goalIntakeFilter)`
  - `habitRepairMessage` → retry calls `loadHabitRepair()`
  - `behaviorRiskMessage` → retry calls `loadBehaviorRisk(behaviorRiskResult?.dogId)`
  - `regressionMessage` → retry calls `loadRegressionEvents()`
  - `candidateMessage` → retry calls `loadCandidateAssessments()`
- GoalIntake empty-state fixed: was showing "No X goals found" even after a failed load. Now has three branches:
  1. `goalIntakeGoals.isNotEmpty()` → render list
  2. `isLoading` → `SummaryCard { Text("Loading goals...") }`
  3. `goalIntakeMessage.isBlank()` → `SummaryCard { Text("No X goals found.") }` (suppressed when an error message is already shown)

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.032` / version code `32`
- APK: `downloads/GuidePaw_Companion_v0.032.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment

## Verification

- `./gradlew :app:assembleDebug` — BUILD SUCCESSFUL (35 tasks)
- `deploy_local.sh` smoke checks — all pass

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and related APIs require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- Material3 BOM 2024.06.00 = Material3 1.2.1 — `PullToRefreshBox` is NOT available (added in 1.3.0); `PullToRefreshContainer` is available but experimental.

## Next Task Candidates

- Android: Pull-to-refresh on scrollable sections — requires `@OptIn(ExperimentalMaterial3Api::class)` and `PullToRefreshContainer` (the only option in Material3 1.2.1); or bump the BOM to 2024.09.00+ to get `PullToRefreshBox`.
- Android: Menu Training section still has "🧩 Goal Builder" opening WebView — `goal_builder.php` is complex; could stay as WebView or become a native form.
- Android: Consider native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Web: Review `app.php` (companion app landing page) to reflect v0.032 release notes.
- Web: "How it works" and FAQ card row on the landing page could be reviewed for freshness.
