# 🤝 GuidePaw AI Handoff Document

> **Handoff from:** CLAUDE → CODEX

| Field | Value |
|---|---|
| **From** | CLAUDE |
| **To** | CODEX |
| **Branch** | `main` |
| **Last commit** | `e939761` |
| **Timestamp** | `2026-05-25T~19:30Z` |

---

## 📋 Summary of Work Completed This Session

Continued native Android screen conversion (v0.049 → v0.054):

| Version | What was converted |
|---|---|
| v0.049 | GPS address resolution fixed (business name + full street), Wearables redesigned to card picker UI |
| v0.050 | Samsung Galaxy Watch 6 Classic added to wearable catalog |
| v0.051 | Handler Profile section native (identity, address, backup contact, SMS) |
| v0.052 | Stats section native (api/stats.php, summary tiles, skill/env bar charts, 14-day trend table) |
| v0.053 | Dog Access section native (handlers list, pending invites, incoming transfers, invite form for owners) |
| v0.054 | **Smart Alerts section native** (api/alerts.php, color-coded alert cards, WebView deep-link buttons) |

All APKs built, deployed to `/downloads/`, Render env vars updated and redeployed each version.

---

## 📁 Files Changed This Session (v0.054 specifically)

- `api/alerts.php` ← NEW: calls `getDogAlertItems()`, returns alerts with absolute `action_url`
- `android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/GuidePawApiClient.kt` — `GpAlert` data class + `getAlerts()` method
- `android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/MainActivity.kt` — `SMART_ALERTS` NavSection, `SmartAlertsSection()` composable, `loadAlerts()` action, More menu wired
- `android/guidepaw-companion/app/build.gradle` — versionCode 54, versionName '0.054'
- `android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/CompanionAppVersion.kt` — VERSION_CODE 54
- `downloads/GuidePaw_Companion_v0.054.apk`

---

## 🎯 Next Task for CODEX

**Convert QR Tracking to native Android section (v0.055)**

Steps:
1. Create `api/qr_tracking.php` — returns `{ dog_name, public_url, scan_history: [{scanned_at, location, ip}] }`. The `public_url` comes from `publicDogProfileUrl()` in `includes/training_helpers.php`. Scan history is in `dog_qr_scans` table (check schema — may not exist yet, return empty array if table missing).
2. In `GuidePawApiClient.kt` add:
   - `data class GpQrScanEvent(val scannedAt: String, val location: String, val ip: String)`
   - `data class GpQrResult(val dogName: String, val publicUrl: String, val scanHistory: List<GpQrScanEvent>)`
   - `fun getQrTracking(token: String): GpQrResult`
3. In `MainActivity.kt`:
   - Add `QR_TRACKING` to `NavSection` enum
   - Add state: `qrResult by mutableStateOf<GpQrResult?>(null)`, `qrBitmap by mutableStateOf<android.graphics.Bitmap?>(null)`, `qrMessage`, `qrIsLoading`
   - Add `loadQrTracking()`: calls `api.getQrTracking()` then fetches bitmap from `https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=<encoded_url>` via `HttpURLConnection`, decodes with `BitmapFactory.decodeStream()`
   - Add `QrTrackingSection()` composable: show `Image(bitmap.asImageBitmap())` in a centered card, copy-URL button, scan history list below
   - Wire into `when` block and More menu ("📡 QR Tracking" → `NavSection.QR_TRACKING`)
4. Bump to versionCode 55 / versionName '0.055'
5. Build APK, copy to `downloads/GuidePaw_Companion_v0.055.apk`, deploy

**Imports needed:**
```kotlin
import android.graphics.BitmapFactory
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.foundation.Image
```

---

## 🔑 Key Architecture Notes

- All native sections follow the same pattern: state vars → `load*()` method on worker thread → `*Section()` composable with pull-to-refresh header + `← Back` TextButton at bottom
- `openWebPage(url)` opens in `GuidePawWebActivity` (WebView with session handoff)
- `friendlyMessage(t.message, fallback)` for error display
- Render deploy: update `GUIDEPAW_COMPANION_VERSION_NAME` + `GUIDEPAW_COMPANION_VERSION_CODE` env vars via `PUT /v1/services/srv-d7qmnj7lk1mc73cl18j0/env-vars`, then trigger `POST https://api.render.com/deploy/srv-d7qmnj7lk1mc73cl18j0?key=9qBuNV8WkLU`
- Render API key: see `middleware/.env` → `RENDER_API_KEY`

---

## 🚀 Pickup Instructions for CODEX

```bash
git pull origin main
curl -s $MIDDLEWARE_URL/status | python3 -m json.tool

# Register session
curl -s -X POST $MIDDLEWARE_URL/session/start \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","task":"Convert QR Tracking to native Android section (v0.055)","branch":"main"}'
```

---

## 📌 Middleware Quick Reference

| Action | Command |
|--------|---------|
| Milestone | `curl -X POST $MIDDLEWARE_URL/milestone -H "Authorization: Bearer $MIDDLEWARE_SECRET" -H "Content-Type: application/json" -d '{"ai":"codex","title":"TITLE","description":"DESC","files_changed":["file"]}'` |
| Token warning | `curl -X POST $MIDDLEWARE_URL/token-warning -H "Authorization: Bearer $MIDDLEWARE_SECRET" -H "Content-Type: application/json" -d '{"ai":"codex","tokens_used":N,"last_completed_task":"TASK"}'` |
| End session | `curl -X POST $MIDDLEWARE_URL/session/end -H "Authorization: Bearer $MIDDLEWARE_SECRET" -H "Content-Type: application/json" -d '{"ai":"codex","summary":"SUMMARY","next_task":"TASK"}'` |

---

## 🗂 Project Context

- **App:** GuidePaw (guidepaw.app) — assistive navigation + Android companion
- **Repo:** https://github.com/jphutching/GuidePaw (private)
- **Local:** /home/james/projects/guidepaw
- **Middleware (laptop):** http://10.147.18.184:3333
- **App (Render):** https://guidepaw-ch3y.onrender.com
- **Middleware (Render):** https://guidepaw-middleware-kfzu.onrender.com

---
*Last updated by CLAUDE — 2026-05-25*
