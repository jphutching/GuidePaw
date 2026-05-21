# GuidePaw Android Mobile Backlog

Scope:
- Android companion app only
- normal handler workflows
- no admin screens
- `guidepaw.app` as the source of truth
- read-only public pages are allowed in-app

Current status:
- Phase 1 auth/account work is implemented in the Android bridge.
- Phase 2 dog switching is implemented in the Android bridge.
- Phase 3 logs and training work is implemented in the Android bridge.
- Phase 4 public QR/profile and found-dog work is implemented in the Android bridge.
- The remaining phases below are the next mobile backlog.

## Phase 1: Account and auth

Goal: let the app sign in and identify the current user.

Backend:
- `POST /api/login.php`
- `GET /api/me.php`

Android screens:
- login
- token setup
- account summary

Must show:
- username
- current session or token label
- schema version

## Phase 2: Dogs

Goal: make the app the normal place to see and switch dogs.

Backend:
- `GET /api/dogs.php`
- active dog selection support

Android screens:
- accessible dogs list
- dog switcher
- active dog header

Must show:
- name
- breed
- access role
- lifecycle status

## Phase 3: Logs and training

Goal: cover the daily work handlers already do on the site.

Backend:
- `GET /api/logs.php?dog_id=123`
- `GET /api/logs.php?log_id=456`
- `POST /api/logs.php`

Android screens:
- log list
- add log
- edit log
- log detail
- history view

Must support:
- location
- city/state
- location type
- focus level
- skills practiced
- handler notes
- log detail and update flow

## Phase 4: QR and public profile

Goal: keep the public dog profile and found-dog flow in the app.

Backend:
- public profile token flow
- existing public profile pages
- found-dog report flow
- public profile preview API
- found-dog report API

Android screens:
- public profile preview
- QR share
- found-dog report form
- public contact details

Must support:
- contact info
- public notes
- scan/report path
- support badge display

Status:
- implemented in Android bridge
- wearable setup summary, compatibility catalog, and recent syncs are now visible for the active dog
- setup now shows the expected data route and metric focus for the chosen devices
- Health Connect sync now pulls steps, distance, calories burned, exercise minutes, sleep, heart-rate, and resting-heart-rate summaries into the wearable timeline
- FitBark is the first dedicated tracker connector path and can import rest, active, play minutes, and battery percentage

## Phase 5: Support, paywalls, and add-ons

Goal: keep the monetized flows usable in the app.

Backend:
- support funding
- purchase service
- paywall catalog
- Stripe checkout

Android screens:
- support page
- monthly support
- one-time support
- add-on catalog
- receipt/status view

Must support:
- support badge visibility
- QR tracking add-on
- extra dog slot add-on
- current plan state

Status:
- implemented in Android bridge

## Phase 6: Wearable sync

Goal: keep the Health Connect bridge working.

Backend:
- `GET /api/wearables.php?dog_id=123`
- `POST /api/wearables.php`

Android screens:
- wearable bridge setup
- Health Connect permission flow
- sync status
- recent sync summary

Must support:
- steps
- heart rate summaries
- daily snapshot sync
- auto-sync scheduling

Status:
- implemented in Android bridge

## Phase 7: Public read-only content

Goal: let the app show the public site content without needing a browser.

Pages:
- landing page
- FAQ
- breed questionnaire
- breed comparison hub
- breed family guide
- breed comparison pages
- service-dog legal info
- housing/access FAQ

Android screens:
- read-only webview wrappers or native article views
- deep links into the public guides

## Explicitly out of scope

- admin dashboard
- admin moderation
- admin analytics
- admin business costs
- internal feature flag editing
- support tooling for staff-only workflows

## Build order

1. Auth and account
2. Dogs and active dog switching
3. Logs and training
4. QR/public profile and found-dog flow
5. Support/paywalls/add-ons
6. Wearable sync
7. Public read-only content

## Definition of done

The Android app can replace the website for normal daily handler work, while the website remains the system of record and public search surface.
