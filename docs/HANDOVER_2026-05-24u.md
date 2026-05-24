# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `c709bc3`
- Working tree: clean
- Live companion release: `v0.038` / version code `38`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.038.apk`

## What Was Finished This Session

### Android: Native Air Travel Rights screen (v0.038, c709bc3)

New `NavSection.AIR_TRAVEL` composable — purely static content, no API calls. Added to the "More" menu as "✈️ Air Travel Rights" (between ADA Access Card and Certification).

**Screen contents:**
1. **ACAA warning** — `errorContainer` card: "Air travel is covered by the Air Carrier Access Act, not the ADA."
2. **Service dogs covered** — DOT rules apply to flights to/within/from the US; airlines may require DOT forms; dog must fit in foot space / under seat
3. **What airlines can ask or require** — DOT Service Animal Air Transportation Form, DOT Relief Attestation Form (8+ hr flights), 48-hour advance submission, harness/leash/tether control
4. **When airlines can refuse transport** — too large/heavy, direct threat to health/safety, significant cabin disruption, fails health or destination-entry rules
5. **SDIT note** — DOT rules don't treat SDITs as service animals; check airline animal policy before booking
6. **Practical reminders** — call ahead for seating/relief plans, keep copies of DOT forms, check destination-country rules for international travel, ask for Complaints Resolution Official if rights are denied
7. **Cross-link card** — `OutlinedButton` navigates to `NavSection.ADA_ACCESS_CARD` natively
8. **Sources attribution** — DOT service-animal guidance, ACAA summary

### Android: Native ADA Access Card screen (v0.037, 904c3c3)

Built in the previous session; see `HANDOVER_2026-05-24t.md` for full detail.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.038` / version code `38`
- APK: `downloads/GuidePaw_Companion_v0.038.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, ADA Access Card, Air Travel Rights
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment)

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
- Android: Housing & Access FAQ native screen — `housing_access_faq.php` is another public reference page in the same family as ADA Access Card and Air Travel Rights.
- Web: No remaining freshness review items from this session.
