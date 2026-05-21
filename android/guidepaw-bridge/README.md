# GuidePaw Bridge

Minimal Android companion app for GuidePaw wearable sync.

What it does:
- accepts a pairing link from `wearable_bridge.php`
- can sign in directly with username/password and save a token on the phone
- stores the pairing endpoint and token on the phone
- loads the connected GuidePaw account and accessible dogs from the API
- lists, views, and edits training logs for the active dog
- surfaces training suggestions from the backend
- loads the public QR profile payload for the active dog
- shows the QR image, share link, and found-dog report form
- loads billing state, support badge info, and add-on status
- can open support and service checkout sessions
- requests Health Connect permissions
- loads wearable sync summary and recent wearable snapshots for the active dog
- shows the saved wearable setup for the active dog
- reads daily steps, distance, calories burned, exercise minutes, sleep, heart-rate, and resting-heart-rate summaries
- can import FitBark or other tracker activity minutes and battery percentage from a CSV or JSON payload on the web side
- posts snapshots back to GuidePaw automatically or on demand

See also:
- `docs/ANDROID_API_CONTRACT.md`
- `docs/ANDROID_MOBILE_BACKLOG.md`

Setup:
1. Open the project in Android Studio.
2. Let Gradle sync.
3. Install on an Android phone with Samsung Health and Health Connect.
4. Pair from the GuidePaw wearable bridge page.

Notes:
- This is a phone-only companion app.
- The Galaxy Watch itself does not need a GuidePaw app for this flow.
- The app relies on Samsung Health syncing the watch data to the phone, then Health Connect exposing it to the app.
- Health Connect reads can include steps, distance, sleep, and heart rate when the phone grants the matching permissions.
