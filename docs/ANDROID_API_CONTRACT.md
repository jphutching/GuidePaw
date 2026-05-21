# GuidePaw Android API Contract

This app does not need a Laravel rewrite before Android work starts.
The current PHP backend already exposes the endpoints the Android bridge needs.

## Current mobile approach

- Keep GuidePaw PHP/PostgreSQL as the system of record.
- Use the existing JSON API for Android authentication, dog lookup, log sync, and wearable sync.
- Add a thin mobile API layer only if a future screen needs a new contract that the current endpoints do not cover.

## Current endpoints

### Authentication

- `POST /api/login.php`
- Supports username/password login
- Supports 2FA via `totp_code` or `recovery_key`
- Returns a bearer token for mobile use

### Account context

- `GET /api/me.php`
- Returns the authenticated user, database driver, and schema version

### Dogs

- `GET /api/dogs.php`
- Returns dogs accessible to the authenticated user

### Training logs

- `GET /api/logs.php?dog_id=123`
- `GET /api/logs.php?log_id=456`
- `POST /api/logs.php`
- Read and write daily logs for a dog the user can access
- Return log detail and training suggestions for the selected dog

### Wearable sync

- `GET /api/wearables.php?dog_id=123`
- `POST /api/wearables.php`
- Returns wearable trend data, current wearable setup, recent sync events, and accepts synced snapshots from the Android bridge
- The setup payload includes the selected handler wearable, dog tracker, sync mode, route, focus, and notes so the app can show which devices should be feeding GuidePaw and how the data should flow in
- Health Connect snapshots can include steps, distance, sleep, and heart-rate summaries for the active dog
- FitBark imports can store rest, active, and play minutes as the first dedicated dog-tracker connector

### Public profile and found-dog reporting

- `GET /api/public_profile.php?dog_id=123`
- `POST /api/found_dog_reports.php`
- Returns public QR/profile details for the selected dog and accepts native found-dog reports

### Billing, support, and add-ons

- `GET /api/billing.php?dog_id=123`
- `POST /api/billing.php`
- Returns the current plan state, support badge, support receipt history, and a la carte service catalog
- Starts Stripe Checkout for one-time or monthly support and for eligible add-on services

## Current Android bridge flow

1. User logs in through the app or the GuidePaw pairing link.
2. The app stores a bearer token and a selected dog.
3. The app requests Health Connect permissions.
4. The app reads steps and heart-rate summaries.
5. The app posts snapshots to `/api/wearables.php`.
6. The app loads the public QR/profile payload and can send found-dog reports.
7. The app loads billing state and can open support or add-on checkout sessions.

## What is already enough

The current API surface is enough for:

- user sign-in
- account bootstrap
- dog selection
- log list/detail/read/write
- wearable snapshot sync
- public profile preview
- native found-dog reporting
- billing state and checkout initiation

## What to add later only if needed

Add a thin API layer only when the Android app needs:

- push notification inbox
- profile editing
- dog invite / handler invite management
- public QR profile management
- offline sync conflict resolution

## Recommendation

Start Android against the current API contract now.
Do not move the backend to Laravel just to begin mobile work.

## Implementation order

Use `docs/ANDROID_MOBILE_BACKLOG.md` as the work queue. It keeps the mobile scope on normal handler workflows and leaves admin tools web-only.
