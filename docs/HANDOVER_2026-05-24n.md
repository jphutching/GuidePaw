# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `3a25f69`
- Working tree: clean
- Live companion release: `v0.035` / version code `35`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.035.apk`

## What Was Finished This Session

### Android: Overview pull-to-refresh (v0.035)

- `@OptIn(ExperimentalMaterial3Api::class)` added to `OverviewSection()`
- `PullToRefreshBox` wraps the Overview `Column` — same pattern as the five training-tool sections
- `onRefresh = { isPullingToRefresh = true; refreshCurrent() }` — triggers the full dashboard reload (`me`, `dogs`, `logs`, `notifications`, `app update check`)
- `refreshCurrent()` → `refreshDashboard()` already calls `setLoading(false, ...)` on both success and error paths, which clears `isPullingToRefresh` automatically — no extra plumbing needed
- Global `LinearProgressIndicator` is suppressed while PTR spinner is active (existing `isLoading && !isPullingToRefresh` guard)
- Explicit Refresh button kept alongside Sign Out at the bottom of the screen

### Pull-to-refresh coverage — now complete

All six scrollable sections have PTR:
- Overview (`refreshCurrent()`)
- Goal Intake (`loadGoalIntake(goalIntakeFilter)`)
- Habit Repair (`loadHabitRepair()`)
- Behavior Risk (`loadBehaviorRisk(behaviorRiskResult?.dogId)`)
- Regression Engine (`loadRegressionEvents()`)
- Candidate Assessment (`loadCandidateAssessments()`)

Training, Dogs, and Wearables sections are not scrollable list screens that benefit from PTR (Training is a form, Dogs/Wearables have their own refresh logic or static content).

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.035` / version code `35`
- APK: `downloads/GuidePaw_Companion_v0.035.apk`
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
- `isPullingToRefresh` is cleared by `setLoading(false, ...)` for all sections that use `setLoading`; the two that don't (Regression, Candidate Assessment) clear it manually.

## Next Task Candidates

- Android: Menu Training section "🧩 Goal Builder" still opens WebView — `goal_builder.php` is complex; could stay or become a native form.
- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Web: "How it works" and FAQ card row on the landing page (`index.php`) could be reviewed for freshness.
