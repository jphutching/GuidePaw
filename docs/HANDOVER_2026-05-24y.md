# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `b5a0b0a`
- Working tree: clean (package.json changes are unrelated noise)
- Live companion release: `v0.045` / version code `45`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.045.apk`

## What Was Finished This Session

### v0.040 — Native Tactical Training screen

`NavSection.TACTICAL_TRAINING` — 4 module cards (Operational Foundation, Search/Response, Distraction Resilience, Team Proofing) each with native nav buttons or web fallbacks, suggested focus bullets, footer link to `tactical_training.php` for access management. No API endpoint — server-side gating via `gpTacticalAccessCanCurrentUserView()` not replicated natively.

### v0.041 — Native Candidate Comparison screen

`NavSection.CANDIDATE_COMPARISON` — reuses already-loaded `candidateResult` data, no new API. Summary stats row (`StatChip` tiles: Dogs/Assessed/Avg score), per-dog `OutlinedCard` (focus level, avg score, recommendation, safety flags, assessment date), empty-state load button, footer to web for full score table. `StatChip` and `formatAssessmentDate()` added as private helpers.

### v0.042 — Native Medications screen

`NavSection.MEDICATIONS` — pull-to-refresh list, status-coloured badges (green=active, amber=paused, muted=completed), inline 3-button status switcher per medication, collapsible add form (name, dosage, schedule, status dropdown, refill date, provider, instructions, notes), footer to web. New `api/medications.php` (GET list, POST add/set_status).

### v0.043 — Native Vet Appointments screen

`NavSection.APPOINTMENTS` — pull-to-refresh appointment list sorted by time, status-coloured badges, vet/clinic picker from saved vets, add form (title, vet, appointment datetime, reminder datetime, location, notes), Complete/Cancel buttons per scheduled appointment. New `api/appointments.php` (GET appointments+vets, POST add/mark_status).

### v0.044 — Native Health & Docs screen

`NavSection.HEALTH_DOCS` — vet contacts with tappable phone numbers (ACTION_DIAL), Primary badge, add vet form (clinic, vet name, phone, address, hours/notes, primary checkbox), document list (type chip, title, provider, date, open file link), upload footer to web. New `api/health_docs.php` (GET vets+docs, POST add_vet). `GpVetItem` updated to include `address`, `notes`, `isPrimary`. All Care menu items now native.

### v0.045 — Native Certification screen

`NavSection.CERTIFICATION` — summary stats row (items/proficient/in-training/readiness%), collapsible category accordions (tap to toggle, shows per-category proficient count), 3-button status switcher per checklist item (active status = filled Button, others = OutlinedButton), "Load starter checklist" button when empty, latest assessment snapshot (4 score chips), add assessment form (date, four 0–100% score fields, notes), pull-to-refresh. New `api/certification.php` (GET, POST seed_template/update_item/add_assessment). More menu item now native.

**Build fix:** `@OptIn(ExperimentalMaterial3Api::class)` was missing from `CertificationSection()` — compile error on first attempt, fixed before final build.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.045` / version code `45`
- APK: `downloads/GuidePaw_Companion_v0.045.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens (19 total): Overview, Training Log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, Candidate Comparison, ADA Access Card, Air Travel Rights, Housing & Access FAQ, Tactical Training, Medications, Vet Appointments, Health & Docs, Certification
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, Medications, Vet Appointments, Health & Docs, Certification

## New API Endpoints (this session)

| File | Actions |
|------|---------|
| `api/medications.php` | GET list; POST add_medication, set_status |
| `api/appointments.php` | GET appointments+vets; POST add_appointment, mark_status |
| `api/health_docs.php` | GET vets+docs; POST add_vet |
| `api/certification.php` | GET; POST seed_template, update_item, add_assessment |

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable — forgetting it causes a compile error.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state and must clear `isPullingToRefresh` explicitly in their `runOnUiThread` blocks.
- `GpVetItem` includes `address`, `notes`, and `isPrimary` fields (added in v0.044) — used by both health docs and appointments.
- `StatChip` and `formatAssessmentDate()` are private composable/function helpers defined in MainActivity alongside `CandidateComparisonSection`.
- `formatAppointmentDateTime()` is a private helper for parsing `yyyy-MM-dd HH:mm:ss` → `MMM d, yyyy h:mm a`.

## Next Task Candidates

- Android: Smart Alerts screen (`alerts.php`) — currently web-only in More menu.
- Android: FAQ screen (`faq.php`) — static content, good candidate for a native reference screen with no API needed.
- Web: No remaining freshness review items flagged.
- The More menu now has only two remaining web-only items: Smart Alerts and FAQ (Plans/paywalls.php stays web by design).
