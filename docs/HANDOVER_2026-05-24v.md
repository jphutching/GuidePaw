# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `da16efa`
- Working tree: clean
- Live companion release: `v0.039` / version code `39`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.039.apk`

## What Was Finished This Session

### Android: Native Housing & Access FAQ screen (v0.039, da16efa)

New `NavSection.HOUSING_FAQ` composable — purely static content matching `housing_access_faq.php`. Added to "More" menu as "🏠 Housing & Access".

**Screen contents:**
1. **Public access** — ADA two-question rule, no certification/registration required, when removal is valid (4 bullets)
2. **Housing** — FHA vs ADA distinction, HUD governs documentation requests, ESAs matter in housing even without ADA public-access rights (4 bullets)
3. **Common disputes** — three Q&As: vest/card/certificate demand, landlord vs store distinction, disruptive-dog removal
4. **Category guide** — one-liner for each rule set: ADA public access, Fair Housing Act housing, DOT/ACAA air travel, ESAs ≠ service dogs
5. **Official sources** — ADA Service Animals FAQ, ADA Service Animals, HUD Assistance Animals — each opens in browser via `openWebPage()`
6. **Cross-links** — row of `OutlinedButton`s to `NavSection.ADA_ACCESS_CARD` and `NavSection.AIR_TRAVEL` natively

### Reference screen family now complete

Three native reference screens in the "More" menu, all cross-linked:
- `NavSection.ADA_ACCESS_CARD` — calm script, two questions, definitions, DOJ phone
- `NavSection.AIR_TRAVEL` — ACAA coverage, DOT forms, denial grounds, SDIT note
- `NavSection.HOUSING_FAQ` — FHA vs ADA distinction, disputes, category guide

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.039` / version code `39`
- APK: `downloads/GuidePaw_Companion_v0.039.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, ADA Access Card, Air Travel Rights, Housing & Access FAQ
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
- Android: The reference screen family (ADA, air travel, housing) is complete — no obvious fourth member.
- Web: No remaining freshness review items from this session.
