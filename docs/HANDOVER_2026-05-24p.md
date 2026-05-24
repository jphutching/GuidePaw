# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `d31ca23`
- Working tree: clean
- Live companion release: `v0.035` / version code `35`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.035.apk`

## What Was Finished This Session

### Web: faq.php content refresh (d31ca23)

Three changes to `faq.php`:

1. **"What is GuidePaw?" answer updated** — removed the vague "keep logs, public contact details, and support tools in one place" description; replaced with one that names the companion app, training tools, and wearable data.

2. **New FAQ entry: "Is there a mobile app?"** — names all native screens (training logs, goal intake, habit repair, behavior risk scoring, regression event tracking, wearable data), states "no browser wrappers", and links to `app.php`. Added in both HTML and JSON-LD schema.

3. **New FAQ entry: "What training tools are built into the platform?"** — one-line description of each of the five training-tool screens (goal intake, habit repair, behavior risk, regression engine, candidate assessment). Added in both HTML and JSON-LD schema.

### Web content refresh completed this session (summary)

| File | What changed |
|------|-------------|
| `app.php` | Checklist expanded to all 10 native screens; stale "next in line" text removed |
| `index.php` | Companion app bullet and grid card updated; "How it works" step 4 rewritten |
| `faq.php` | Two new FAQ entries; opener answer updated |

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.035` / version code `35`
- APK: `downloads/GuidePaw_Companion_v0.035.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment)

## Verification

- `deploy_local.sh` smoke checks — all pass
- `php -l faq.php` — no syntax errors

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state and must clear `isPullingToRefresh` explicitly in their `runOnUiThread` blocks.

## Next Task Candidates

- Android: Menu Training section "🧩 Goal Builder" still opens WebView — `goal_builder.php` is complex; could stay or become a native form.
- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Web: `training_program.php` — still linked from the landing page hero as "See training tools"; worth checking for freshness now that all training tools are native in the app.
- Web: `service_dog_rights.php` and `service_dog_esa_legal_info.php` — public reference pages linked from the landing page; have not been reviewed for freshness this session.
