# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `2feae90`
- Working tree: clean
- Live companion release: `v0.036` / version code `36`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.036.apk`

## What Was Finished This Session

### Android: Native Goal Builder screen (v0.036, 2feae90)

Replaced the WebView-launched `goal_builder.php` with a full native Compose screen (`NavSection.GOAL_BUILDER`).

**How it works:**
1. **Form** — all 10 fields (dog selector, category, problem, desired behavior, context, trigger, time budget, best rewards, safety risk chip, success criteria, maintenance plan) with category-aware placeholder hints that update live when the category changes
2. **Build Draft** — fills any blank field with the selected category's default hint text, then flips to a read-only draft preview card; user-typed values are never overwritten
3. **Draft preview** — lists all drafted values and a training path tip ("Loose leash path: Build this through the training ladder…"); has Edit (returns to form with values intact) and Save Goal buttons
4. **Save Goal** — posts to `api/training_goals.php` via the existing `createTrainingGoal()` API; clears form on success and shows "Goal saved. Ready to build another."
5. **Error handling** — `SectionMessage` composable shows validation errors (missing problem/criteria, no dog) and API errors with the standard `friendlyMessage()` pattern

**Menu item:** `"🧩 Goal Builder"` now sets `currentSection = NavSection.GOAL_BUILDER` instead of calling `openWebPage("https://guidepaw.app/goal_builder.php")`.

**8 categories with full hint sets:** potty, leash, barking, cab_calm, jumping, public_manners, psd_foundation, other — matching `includes/goal_builder.php` exactly.

**New state variables:** `goalBuilderDogId`, `goalBuilderCategory`, `goalBuilderProblem`, `goalBuilderDesired`, `goalBuilderContext`, `goalBuilderTrigger`, `goalBuilderBudget`, `goalBuilderReinforcer`, `goalBuilderSafetyRisk`, `goalBuilderCriteria`, `goalBuilderMaintenance`, `goalBuilderShowDraft`, `goalBuilderMessage`.

**New functions:** `GoalBuilderSection()`, `GoalBuilderDraftRow()`, `submitGoalBuilderGoal()`.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.036` / version code `36`
- APK: `downloads/GuidePaw_Companion_v0.036.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment) — Goal Builder does not have a list to refresh

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

- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Android: `goal_builder.php` web page still exists and is still linked from `training_program.php` nav — could add a note pointing mobile users to the app, or leave as-is since web users still need it.
- Web: No remaining freshness review items from this session — all flagged pages have been reviewed.
