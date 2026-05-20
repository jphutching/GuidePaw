# GuidePaw Bridge

Minimal Android companion app for GuidePaw wearable sync.

What it does:
- accepts a pairing link from `wearable_bridge.php`
- stores the pairing endpoint and token on the phone
- loads the connected GuidePaw account and accessible dogs from the API
- requests Health Connect permissions
- reads daily steps and heart-rate summaries
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
