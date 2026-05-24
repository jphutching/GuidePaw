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
