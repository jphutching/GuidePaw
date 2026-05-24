# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Local laptop git HEAD: `75a3355`
- `origin/main`: `75a3355`
- Working tree: clean
- Live companion release: `v0.023` / version code `23`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.023.apk`

## What Was Finished This Session

### Android companion v0.022
- Ported `GuidePawWebActivity.kt` from View-based XML to pure Compose.
- Replaced `setContentView(R.layout.activity_guidepaw_web)` with `setContent { MaterialTheme(GpWebColorScheme) { WebScreen(...) } }`.
- Replaced deprecated `onBackPressed()` override with `OnBackPressedCallback` registered on `onBackPressedDispatcher` in `onCreate()`.
- Promoted `isPageLoading` to `mutableStateOf` — drives a reactive `LinearProgressIndicator`.
- Embedded `WebView` via `AndroidView` composable inside a `Column`-based toolbar layout.
- All `WebViewClient` URL routing and `companion_session.php` token-handoff POST logic preserved verbatim.
- Deleted `activity_guidepaw_web.xml`.

### Android companion v0.023
- Ported `NotificationCenterActivity.kt` from View-based XML to pure Compose.
- Removed `setContentView(R.layout.activity_notifications)` and all 14 `lateinit var` View fields.
- Removed `bindViews()`, `setupUi()`, `renderState()`, `rebuildNotificationList()`, `rebuildInvitesList()`, `makePlainText()`, `dp()`.
- `MaterialButtonToggleGroup` replaced with a `Row` of 4 `FilterChip` composables (`prefAccess`, `prefCare`, `prefAdmin`, `prefGeneral` as `mutableStateOf` booleans).
- `buildNotificationCard()` / `buildInviteCard()` replaced with `NotificationCard` / `InviteCard` composables using `OutlinedCard`.
- `setLoading()` replaced by direct `isLoading` / `statusMessage` `mutableStateOf` assignments.
- `syncPreferencesToggle()` replaced by `syncPreferenceState()` — updates the 4 pref booleans after every API response.
- All 7 API call methods preserved verbatim with the same `worker.execute { ... runOnUiThread { ... } }` pattern.
- Deleted `activity_notifications.xml` and `activity_main.xml` (the latter was an orphan with no references).
- **`res/layout/` directory is now completely empty. The companion app has zero XML layout files and zero View-based code.**
- All four version files bumped together for each release.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current source version: `0.023` / version code `23`
- APK committed to `downloads/GuidePaw_Companion_v0.023.apk`
- Release endpoint (`/api/app_release.php`) serving correct v0.023 metadata
- Activities: `MainActivity` (Compose), `FeedbackActivity` (Compose), `GuidePawWebActivity` (Compose), `NotificationCenterActivity` (Compose)
- **The companion app is 100% pure Compose — no XML layouts, no View-based code, no hybrid patterns anywhere**

## Verification That Matters

- `./gradlew :app:assembleDebug` passes cleanly (35 tasks, BUILD SUCCESSFUL).
- `deploy_local.sh` smoke checks pass — PHP syntax, brand header, missing links, HTTP 200/302s all clean.
- `curl -k https://10.147.18.184/api/app_release.php` returns `version_code: 23`.

## Memory Anchors

- Version bumps are a bundle of four: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose as of v0.023 — do not introduce View-based, XML layout, or hybrid code into any activity.
- `deploy_local.sh` must pass before any commit touching PHP.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct way to INSERT and get the new row ID — never `lastInsertId()`.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension and importing it directly causes a compile error ("it is internal").

## Possible Next Tasks

- Web: Review `app.php` (the companion app landing page) to reflect v0.023 release notes.
- Web: The "How it works" and FAQ card row on the landing page could be reviewed for freshness.
- Android: The Compose migration is complete — consider UX improvements or new features now that all activities are native Compose.
