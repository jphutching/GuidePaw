## v0.044 (2026-05-24)
- Android: Native Health & Docs screen — vet contacts list (clinic, vet name, tappable phone, address, notes, Primary badge), add vet form (clinic, vet, phone, address, notes, primary checkbox), document list (type chip, title, provider, date, open file link), "Upload on web" footer button; new `api/health_docs.php` (GET vets+docs, POST add_vet); pull-to-refresh; added to Care menu as "🩺 Health Docs"

## v0.043 (2026-05-24)
- Android: Native Vet Appointments screen — appointment list (sorted by time) with status-coloured badges, vet/clinic picker from saved vets, add form (title, vet, appointment time, reminder time, location, notes), Complete/Cancel buttons per scheduled appointment, pull-to-refresh; new `api/appointments.php` (GET list+vets, POST add/mark_status); added to Care menu as "📅 Vet Appointments"

## v0.042 (2026-05-24)
- Android: Native Medications screen — medication list with status-coloured badges (active/paused/completed), add form (name, dosage, schedule, status, refill date, provider, instructions, notes), inline three-button status update per medication, pull-to-refresh, footer link to full form on web; new `api/medications.php` (GET list, POST add/set_status); added to Care menu as "💊 Medications"

## v0.041 (2026-05-24)
- Android: Native Candidate Comparison screen — summary stats (dogs, assessed count, overall avg score), per-dog cards (focus level, avg score, recommendation, safety flags, assessment date), empty-state load button, link to full score table on web; added to Training menu as "📊 Compare Dogs"; no new API endpoint — reuses data already loaded by Candidate Assessment

## v0.040 (2026-05-24)
- Android: Native Tactical Training screen — 4 module cards (Operational Foundation → Candidate Assessment + Training Programs; Search/Response → Goal Builder + Log Training; Distraction Resilience → Behavior Risk + Trucking Mode; Team Proofing → Regression Engine + Training History); suggested tactical focus bullets; link to web version for access management; added to Training menu as "🎖️ Tactical Training"

## v0.039 (2026-05-24)
- Android: Native Housing & Access FAQ screen — public access rules, FHA housing rules, three common disputes, category guide (ADA/FHA/ACAA), official source links (open in browser), cross-links to ADA Card and Air Travel screens; added to menu as "🏠 Housing & Access"

## v0.038 (2026-05-24)
- Android: Native Air Travel Rights screen — ACAA coverage, DOT form requirements, denial grounds, SDIT note, practical reminders, and cross-link to ADA Access Card; added to menu as "✈️ Air Travel Rights"

## v0.037 (2026-05-24)
- Android: Native ADA Access Card screen — calm script (large text, Copy and Share buttons), two permissible questions, what staff may not require, service dog/SDIT/ESA definitions, scam warning, when access can be denied, tappable DOJ ADA Information Line, state law note with link to web version; menu item no longer opens a WebView

## v0.036 (2026-05-24)
- Android: Native Goal Builder screen — category-aware hint text on all fields, Build Draft fills blanks with category defaults and shows a draft preview card, Save Goal posts to API; menu item no longer opens WebView

## v0.035 (2026-05-24)
- Android: Pull-to-refresh on Overview screen — pull down triggers the same full dashboard refresh as the Refresh button; PTR spinner replaces the global LinearProgressIndicator during the reload

## v0.034 (2026-05-24)
- Android: Regression Engine empty-state hint card — shown when no data is loaded and no message is pending; includes a "Load Events" button
- Android: Refresh button moved inside the loaded-result block so it no longer appears alongside the hint card or error message

## v0.033 (2026-05-24)
- Android: Pull-to-refresh on Goal Intake, Habit Repair, Behavior Risk, Regression Engine, and Candidate Assessment sections
- Android: Global LinearProgressIndicator suppressed during pull-to-refresh (PTR spinner shown instead)
- Android: isPullingToRefresh state clears via setLoading() for the three setLoading-based sections; explicitly in runOnUiThread for Regression and Candidate Assessment
- Android: Compose BOM bumped from 2024.06.00 to 2024.09.03 (Material3 1.2.1 → 1.3.1); adds PullToRefreshBox

## v0.032 (2026-05-24)
- Android: Added `SectionMessage` composable — errors display in an `errorContainer` card with a Retry button; success/info stays as plain primary-colored text
- Android: Global status bar message now colors red when the message is an error (detected via `isErrorText()`)
- Android: GoalIntake section now shows a "Loading goals…" placeholder instead of "No goals found" while loading, and suppresses the empty state when an error message is already shown
- Android: All five section message sites (Goal Intake, Habit Repair, Behavior Risk, Regression, Candidate Assessment) upgraded from bare `Text()` to `SectionMessage()` with section-specific retry lambdas

## v0.031 (2026-05-24)
- Android: Native Regression Engine screen — open events list, per-event inline status update and reset plan editor, static reset plan card
- Android: Native Candidate Assessment screen — 10 score sliders (1–5), dog selector, health notes, safety flags, recent assessments with archive
- Android: New API endpoints api/regression_engine.php and api/candidate_assessment.php
- Android: Both screens previously opened WebView; now full native Compose

## v0.023 (2026-05-24)
- Android: Ported NotificationCenterActivity to Compose — removed 14 lateinit var View fields, MaterialButtonToggleGroup replaced with FilterChip Row, dynamic card builders replaced with NotificationCard/InviteCard composables
- Android: Deleted activity_notifications.xml and activity_main.xml — the res/layout/ directory is now empty

## v0.022 (2026-05-24)
- Android: Ported GuidePawWebActivity to Compose — removed XML layout, replaced deprecated onBackPressed() with OnBackPressedCallback, embedded WebView via AndroidView
- Android: Removed activity_guidepaw_web.xml — no XML layout files remain in any activity

## v0.021 (2026-05-24)
- Android: Ported FeedbackActivity to Compose — deleted activity_feedback.xml, replaced 9 lateinit var Views with mutableStateOf, converted deprecated onActivityResult to registerForActivityResult
- Android: All activities are now pure Compose — no remaining View-based or XML layout code in the companion app

## v0.020 (2026-05-24)
- Android: Ported the Menu tab from a View-based AlertDialog to a native Compose ModalBottomSheet — all hybrid View/Compose code removed from MainActivity
- Web: Consolidated landing page public sections into one grid, fixed three unlinked cards (Breed Questionnaire, Support Options, Service Dog Notes), removed duplicate Housing & Access FAQ entry

## v0.019
- Web + Android: Added feedback link to login screens
- Android: Allowed anonymous feedback submission (no login required)
- Android: Fixed sign-in keyboard options and added show-password toggle

## v21
- Added migration tracker and DB status page
- Added quick log workflow
- Added starter token-based JSON API
- Added centralized error logging and friendly error handling

## PostgreSQL compatibility build
- patched common legacy SQL-specific SQL usage for the Render/PostgreSQL package
- updated insert flows to use RETURNING where IDs are needed
- fixed PostgreSQL schema constraint typos

## v19-render-ready
- added Docker-based Render deployment support
- added env-based DB config and health check
- added Render docs, entrypoint, and persistent storage support
- added brand shortlist and default beta branding

# Changelog

## v11 Fresh Install + Full Multi-Dog Backup/Restore
- cleaned the fresh-install SQL so it no longer includes legacy backfill statements
- expanded backup/export to include owned dogs, collaborators by username, vets, appointments, dog documents, logs, and packaged files
- expanded restore/import to rebuild dogs, vet contacts, appointments, dog documents, logs, and collaborator links when matching users already exist
- split provider letters into ESA Letter and Service Dog Letter document types
- refreshed backup UI text for the new owned-dog restore behavior

## v10 Collaboration + Multi-Dog + Health Records
- added multiple dogs per handler
- added handler collaboration with handshake approval
- added dog health documents, vets, and appointments
- added dog-scoped logs and active-dog workflow

## v12 Notifications
- Added browser/PWA vet appointment reminders with permission prompt and test alert
- Added `appointment_notifications.php` reminder feed endpoint
- Added service worker notification display + click handling
- Added dashboard reminder controls and status badge

## v15.0 - Smart alerts, certification, medication tracking, and UI polish
- Added smart alert engine and Alerts screen.
- Added dog medication tracking with refill and reminder fields.
- Added certification checklist and readiness assessments.
- Added shared mobile styling and dashboard tiles for the new modules.
- Updated fresh install SQL with medication and certification tables.
