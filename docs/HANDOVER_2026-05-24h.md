# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local): `93ccf2c`
- Working tree: clean
- Live companion release: `v0.030` / version code `30`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.030.apk`

## What Was Finished This Session

### Native screens for Goal Intake, Habit Repair, and Behavior Risk (commit `93ccf2c`)

Three items in the Training section of the menu that previously opened a WebView now open native Compose screens inside `MainActivity`.

**API endpoints created:**
- `api/training_goals.php` — GET (list goals, status filter) + POST (create/archive/restore)
- `api/habit_repair.php` — GET (protocol definitions + recent incidents) + POST (log incident / archive)
- `api/behavior_risk.php` — GET (scored assessment for a dog or all dogs)

**API client additions (`GuidePawApiClient.kt`):**
- 8 new data classes: `GpTrainingGoalItem`, `GpTrainingGoalsResult`, `GpHabitRepairProtocol`, `GpBehaviorIncidentItem`, `GpHabitRepairResult`, `GpCandidateSummary`, `GpBehaviorRiskResult`
- 7 new methods: `trainingGoals()`, `createTrainingGoal()`, `archiveTrainingGoal()`, `habitRepair()`, `createBehaviorIncident()`, `archiveBehaviorIncident()`, `behaviorRisk()`

**New screens (`MainActivity.kt`):**
- `NavSection` enum extended with `GOAL_INTAKE`, `HABIT_REPAIR`, `BEHAVIOR_RISK`
- `GoalIntakeSection` — dog/category dropdowns, full form, active/archived/all filter tabs, recent goals list with archive action
- `HabitRepairSection` — protocol chip selector, numbered steps card, log form with severity slider (1–5), recent incidents with archive action
- `BehaviorRiskSection` — dog selector (multi-dog accounts), score + band card (colour-coded by risk), reasons list, recommendations list, incidents, candidate assessment card, Refresh button

Menu items for the three tools now call `loadGoalIntake()` / `loadHabitRepair()` / `loadBehaviorRisk()` and set the nav section instead of calling `openWebPage()`.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.030` / version code `30`
- APK: `downloads/GuidePaw_Companion_v0.030.apk`
- All activities pure Compose — no XML layouts, no View-based code

## Full Session Summary (v0.024–v0.030 + web)

| Version | Change |
|---------|--------|
| v0.024 | Material 3 `NavigationBar` + vector icons + `BadgedBox` |
| v0.025 | `OverviewSection` — active dog as hero, recent activity, quick actions |
| v0.026 | `DogsSection` — compact header, collapsible Switch picker, dog-named logs |
| v0.027 | `TrainingSection` — dog context, edit banner + Cancel, section labels |
| v0.028 | `WearablesSection` — split into Wearable Data + Notifications sections |
| v0.029 | `MenuBottomSheet` — identity card, 46→24 items, Logs section removed |
| web    | `mobile_nav.php` Training 14→8+subgroup; dashboard 4 non-actionable cards removed |
| v0.030 | Native Goal Intake, Habit Repair, Behavior Risk screens (no more WebView) |

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — do not introduce View-based, XML layout, or hybrid code.
- Do NOT `import androidx.compose.foundation.layout.weight` directly — it is a `ColumnScope`/`RowScope` extension and causes a compile error if imported at the top level.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- New `NavSection` values that are NOT bottom-nav tabs must still be exhaustively covered in any `when` that is used as an expression; use `else` for the bottom-nav icon/title `when` blocks to stay safe.

## Next Task Candidates

- Native screen for **Regression Engine** (`regression_engine.php`) — currently WebView from menu
- Native screen for **Candidate Assessment** (`candidate_assessment.php`) — currently WebView from menu
- Push the v0.030 APK as the official release (replace placeholder with signed build if signing is set up)
