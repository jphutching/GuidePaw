# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `af71738`
- Working tree: clean
- Live companion release: `v0.041` / version code `41`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.041.apk`

## What Was Finished This Session

### Android: Native Tactical Training screen (v0.040, 70314f2)

New `NavSection.TACTICAL_TRAINING` composable. Added to "Training" section of the menu as "🎖️ Tactical Training".

**Screen contents:**
1. **Who this is for** — 5-bullet audience list (security/EP, police K9, fire/EMS, military, SAR)
2. **4 module cards** — each with two buttons (native nav or web fallback):
   - Operational foundation → Candidate Assessment (native) + Training Programs (web)
   - Search and response → Goal Builder (native) + Log Training (native)
   - Distraction resilience → Behavior Risk (native) + Trucking Mode (web)
   - Team proofing → Regression Engine (native) + Training History/Dogs (native)
3. **Suggested tactical focus** — 4 bullets
4. **Footer** — `OutlinedButton` opening `tactical_training.php` for access request management (server-side approved/unapproved gating not replicated natively — no API endpoint exists for `gpTacticalAccessCanCurrentUserView`)

### Android: Native Candidate Comparison screen (v0.041, af71738)

New `NavSection.CANDIDATE_COMPARISON` composable. Added to "Training" section of the menu as "📊 Compare Dogs". **No new API endpoint** — reuses data already loaded by `loadCandidateAssessments()` into `candidateResult`.

**Screen contents:**
1. **Empty state** — hint card with "Load Comparison" button (calls `loadCandidateAssessments()`)
2. **Summary stats row** — three `StatChip` tiles: Dogs / Assessed / Avg score
3. **Per-dog cards** — one `OutlinedCard` per dog in the user's dog list:
   - Has assessment: Focus Level badge, avg score, recommendation text, safety flags (error colour), assessment date
   - No assessment: "No assessment yet." placeholder
4. **Footer** — `OutlinedButton` opening `candidate_comparison.php` for the full 10-metric score table on web

**Key implementation detail:** `latestByDog` is derived as `result.assessments.groupBy { it.dogId }.mapValues { (_, list) -> list.first() }` — works because the API returns assessments ordered `created_at DESC`, so first-per-dog is always the most recent. `StatChip` and `formatAssessmentDate()` are small private helpers added alongside the composable.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.041` / version code `41`
- APK: `downloads/GuidePaw_Companion_v0.041.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, Candidate Comparison, ADA Access Card, Air Travel Rights, Housing & Access FAQ, Tactical Training
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment)

## Verification

- `./gradlew :app:assembleDebug` — BUILD SUCCESSFUL (37 tasks) for both v0.040 and v0.041
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
- `candidate_comparison.php` is covered natively without a new endpoint — `GpCandidateAssessmentItem` (from the existing GET) has `averageScore`, `focusLevelRecommended`, `recommendation`, `safetyFlags`; individual per-metric scores are only available on the web version.

## Next Task Candidates

- Android: Care section screens — Health Docs (`dog_health.php`), Vet Appointments (`appointments.php`), or Medications (`medications.php`) are all currently web-only; any could be nativised.
- Android: Certification screen (`certification.php`) is in the "More" menu as a web link — could be a native checklist view.
- Web: No remaining freshness review items from this session.
