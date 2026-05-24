# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `70314f2`
- Working tree: clean
- Live companion release: `v0.040` / version code `40`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.040.apk`

## What Was Finished This Session

### Android: Native Tactical Training screen (v0.040, 70314f2)

New `NavSection.TACTICAL_TRAINING` composable — purely static content matching `tactical_training.php`. Added to the "Training" section of the menu as "🎖️ Tactical Training".

**Screen contents:**
1. **Who this is for** — 5-bullet audience list (security/EP, police K9, fire/EMS, military, SAR)
2. **Module 1: Operational foundation** — description + two buttons: `NavSection.CANDIDATE_ASSESSMENT` (native, triggers `loadCandidateAssessments()`) + Training Programs (web)
3. **Module 2: Search and response** — description + two buttons: `NavSection.GOAL_BUILDER` (native) + `NavSection.TRAINING` (Log Training, native)
4. **Module 3: Distraction resilience** — description + two buttons: `NavSection.BEHAVIOR_RISK` (native, triggers `loadBehaviorRisk()`) + Trucking Mode (web)
5. **Module 4: Team proofing** — description + two buttons: `NavSection.REGRESSION` (native, triggers `loadRegressionEvents()`) + `NavSection.DOGS` (Training History, native)
6. **Suggested tactical focus** — 4 bullets (reliability before environment expansion, short high-value sessions, log everything, reassess every 4–6 weeks)
7. **Footer** — `OutlinedButton` opening `tactical_training.php` in WebView for access request management (approved/unapproved gating is not replicated natively — no API endpoint exists for it)

### No new API endpoint needed

The web `tactical_training.php` gates content behind `gpTacticalAccessCanCurrentUserView()`, a server-side PHP function with no API equivalent. The native screen skips this gate and shows the full module content to any authenticated user, with a footer button to the web version for access request submission.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.040` / version code `40`
- APK: `downloads/GuidePaw_Companion_v0.040.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, ADA Access Card, Air Travel Rights, Housing & Access FAQ, Tactical Training
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment)

## Verification

- `./gradlew :app:assembleDebug` — BUILD SUCCESSFUL (37 tasks)
- Working tree clean, `origin/main` up to date

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state and must clear `isPullingToRefresh` explicitly in their `runOnUiThread` blocks.
- `tactical_training.php` has no API endpoint — server-side gating via `gpTacticalAccessCanCurrentUserView()` is not replicated natively.

## Next Task Candidates

- Android: Native Candidate Comparison screen (`candidate_comparison.php`) — no API endpoint exists yet; would need `api/candidate_comparison.php`.
- Android: The Training section of the menu is now well-populated; the Care section (Health Docs, Vet Appointments, Medications) is all web — any of those could be next for nativisation.
- Web: No remaining freshness review items from this session.
