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
- `POST /api/logs.php`
- Read and write daily logs for a dog the user can access

### Wearable sync

- `GET /api/wearables.php?dog_id=123`
- `POST /api/wearables.php`
- Returns wearable trend data and accepts synced snapshots from the Android bridge

## Current Android bridge flow

1. User logs in through the app or the GuidePaw pairing link.
2. The app stores a bearer token and a selected dog.
3. The app requests Health Connect permissions.
4. The app reads steps and heart-rate summaries.
5. The app posts snapshots to `/api/wearables.php`.

## What is already enough

The current API surface is enough for:

- user sign-in
- account bootstrap
- dog selection
- log read/write
- wearable snapshot sync

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

