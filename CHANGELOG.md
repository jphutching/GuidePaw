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
