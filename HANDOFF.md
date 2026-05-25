# 🤝 GuidePaw AI Handoff Document

| Field | Value |
|---|---|
| **From** | CLAUDE |
| **To** | CODEX |
| **Branch** | `main` |
| **Last commit** | `5eacc81` |
| **Timestamp** | `2026-05-25` |

---

## 📋 Summary of Work Completed

- v0.049–v0.054: Converted web screens to native Compose sections (Wearables, Profile, Stats, Dog Access, Smart Alerts)
- v0.055: Codex routed notificationcenter.php deeplinks to native Notification Center section
- Fixed start-codex.sh task extraction (was capturing blank line instead of task text)

---

## 🎯 Next Task for CODEX

Create a native QR Tracking section in the Android companion app (v0.056). Here are the exact steps:

**Step 1 — Create `api/qr_tracking.php`** (already created by Claude, verify it exists at `/home/james/projects/guidepaw/api/qr_tracking.php`). If missing, it should require `api_auth.php`, `includes/qr_tracking.php`, `includes/public_dog_profile_token.php`, get the active dog, call `gpDogQrTrackingSummary()`, build a public URL via `publicDogProfileToken()`, and return JSON with `dog_id`, `dog_name`, `public_url`, `total_views`, `last_viewed`, `recent_views[]`.

**Step 2 — Add to `GuidePawApiClient.kt`** (already done by Claude):
- `data class GpQrScanEvent(val viewedAt: String, val device: String, val referrer: String)`
- `data class GpQrResult(val dogId: Int, val dogName: String, val publicUrl: String, val totalViews: Int, val lastViewed: String, val recentViews: List<GpQrScanEvent>)`
- `fun getQrTracking(token: String): GpQrResult?` — calls `api/qr_tracking.php`

**Step 3 — Update `MainActivity.kt`**:
- Add `QR_TRACKING` to the `NavSection` enum (line ~144)
- Add state vars near the other section state blocks:
  ```kotlin
  private var qrResult    by mutableStateOf<GpQrResult?>(null)
  private var qrBitmap    by mutableStateOf<android.graphics.Bitmap?>(null)
  private var qrMessage   by mutableStateOf("")
  private var qrIsLoading by mutableStateOf(false)
  ```
- Add `loadQrTracking()` method — calls `api.getQrTracking(token)` on the worker thread, then fetches the QR bitmap from `https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=<URLEncoded publicUrl>` using `HttpURLConnection` and `BitmapFactory.decodeStream()`
- Add `QrTrackingSection()` composable — show dog name title, QR image (`Image(bitmap.asImageBitmap())`), total views count, copy-URL button, list of recent_views with viewed_at and device
- Wire into the `when` block: `NavSection.QR_TRACKING -> QrTrackingSection()`
- Update More menu: change `"📡 QR Tracking" to { openWebPage(...) }` → `"📡 QR Tracking" to { loadQrTracking(); currentSection = NavSection.QR_TRACKING }`

**Step 4 — Bump version** to versionCode 56 / versionName '0.056' in `app/build.gradle` and `CompanionAppVersion.kt`

**Step 5 — Build**: `cd android/guidepaw-companion && GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon :app:assembleDebug`

**Step 6 — Copy APK**: `cp app/build/outputs/apk/debug/app-debug.apk ../../downloads/GuidePaw_Companion_v0.056.apk`

**Step 7 — Deploy**: `bash scripts/deploy_local.sh`

**Step 8 — Commit and push**:
```bash
git add api/qr_tracking.php android/guidepaw-companion/ downloads/GuidePaw_Companion_v0.056.apk
git commit -m "feat: native QR Tracking section (v0.056)"
git push origin main
```

**Step 9 — Update Render env vars** for version code/name (see middleware/.env for RENDER_API_KEY), then trigger deploy hook.

**Step 10 — Call /session/end**:
```bash
set -a; source middleware/.env; set +a
curl -X POST $MIDDLEWARE_URL/session/end \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","summary":"Added native QR Tracking section v0.056","files_changed":["api/qr_tracking.php","android/..."],"next_task":"NEXT_TASK_HERE"}'
```

---

## 🚀 Pickup Instructions for CODEX

```bash
git pull origin main
set -a; source middleware/.env; set +a
# Verify api/qr_tracking.php exists
ls api/qr_tracking.php
php -l api/qr_tracking.php
```

---

## 🗂 Project Context

- **Repo:** /home/james/projects/guidepaw
- **Android source:** `android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/`
- **Key files:** `MainActivity.kt`, `GuidePawApiClient.kt`
- **Build:** `cd android/guidepaw-companion && GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon :app:assembleDebug`
- **Render deploy hook:** see `middleware/.env` → `RENDER_DEPLOY_HOOK`
- **Render API key:** see `middleware/.env` → `RENDER_API_KEY`
- **Render service ID:** `srv-d7qmnj7lk1mc73cl18j0`
