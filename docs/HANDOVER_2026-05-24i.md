# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `3f44a99`
- Working tree: clean
- Live companion release: `v0.031` / version code `31`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.031.apk`

## What Was Finished This Session

### Native Regression Engine screen (v0.031)

- New API endpoint `api/regression_engine.php`:
  - GET: returns open regression events for the active dog (via `gpRegressionEngineOpenEvents` + `gpRegressionEngineOpenCount`)
  - POST `action=update_event`: calls `gpRegressionEngineUpdateEvent`, returns refreshed event list
- New data classes in `GuidePawApiClient.kt`: `GpRegressionEventItem`, `GpRegressionResult`
- New API methods: `regressionEvents(token)`, `updateRegressionEvent(token, eventId, status, recommendedAction)`
- `RegressionSection()` composable in `MainActivity.kt`:
  - Dog name + open count badge
  - Static 3-step reset plan card (same text as web page)
  - Open events list — each card shows detected reason, module/category/date meta, status badge, and optional recommended action note
  - Per-event inline edit: `FilterChip` row for 5 statuses (open, in_review, paused_for_review, resolved, closed) + `OutlinedTextField` for reset plan + Save/Cancel buttons
  - Only one event can be in edit mode at a time (`regressionExpandedEventId` state)
  - Refresh button at bottom

### Native Candidate Assessment screen (v0.031)

- New API endpoint `api/candidate_assessment.php`:
  - GET: returns dogs list + recent assessments + score label map (from `candidateScoreLabels()`)
  - POST `action=create`: validates dog ownership, clamps scores, calls `recommendCandidateFocusLevel()`, inserts row
  - POST `action=archive`: sets status to archived
- New data classes: `GpCandidateDogItem`, `GpCandidateAssessmentItem`, `GpCandidateAssessmentsResult`
- New API methods: `candidateAssessments(token)`, `createCandidateAssessment(...)`, `archiveCandidateAssessment(token, assessmentId)`
- `CandidateAssessmentSection()` composable:
  - Dog selector via `ExposedDropdownMenuBox` (requires `@OptIn(ExperimentalMaterial3Api::class)`)
  - 10 score sliders (1f..5f, steps=3) — one per `candidateScoreKeys()` field, labels from API `score_labels` map
  - Health notes + safety flags `OutlinedTextField`
  - Save Assessment button → score average, focus level, and recommendation computed server-side
  - Recent assessments list with Archive action
- State in MainActivity: `candidateResult`, `candidateMessage`, `candidateDogId`, `candidateScores` (LinkedHashMap mutableStateOf), `candidateHealthNotes`, `candidateSafetyFlags`, `candidateDogExpanded`

### NavSection enum

`NavSection` now has 10 values:
`OVERVIEW, TRAINING, DOGS, WEARABLES, MORE, GOAL_INTAKE, HABIT_REPAIR, BEHAVIOR_RISK, REGRESSION, CANDIDATE_ASSESSMENT`

The menu bottom sheet Training section now routes all 5 non-log items to native screens — no more `openWebPage()` calls for training tools.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.031` / version code `31`
- APK: `downloads/GuidePaw_Companion_v0.031.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment

## Verification

- `./gradlew :app:assembleDebug` — BUILD SUCCESSFUL (35 tasks)
- `deploy_local.sh` smoke checks — all pass
- `curl -k https://10.147.18.184/api/app_release.php` — returns `version_code: 31`

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and related APIs require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.

## Next Task Candidates

- Web: Review `app.php` (companion app landing page) to reflect v0.031 release notes.
- Web: "How it works" and FAQ card row on the landing page could be reviewed for freshness.
- Android: Menu Training section still has "🧩 Goal Builder" opening WebView — could become a native form or stay as WebView (goal_builder.php is complex).
- Android: Consider a native screen for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Android: UX improvements now that all core screens are native — e.g., pull-to-refresh, empty states, error retry buttons.
