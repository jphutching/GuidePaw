# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Local laptop git HEAD: `8662507`
- `origin/main`: `8662507`
- Working tree: clean
- Live companion release: `v0.022` / version code `22`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.022.apk`

## What Was Finished This Session

### Android companion v0.022
- Ported `GuidePawWebActivity.kt` from View-based XML to pure Compose.
- Replaced `setContentView(R.layout.activity_guidepaw_web)` with `setContent { MaterialTheme(GpWebColorScheme) { WebScreen(...) } }`.
- Replaced deprecated `onBackPressed()` override with `OnBackPressedCallback` registered on `onBackPressedDispatcher` in `onCreate()`.
- Promoted `isPageLoading` from a plain field to `mutableStateOf` — drives a reactive `LinearProgressIndicator` in Compose.
- Embedded `WebView` via `AndroidView` composable (Compose interop) inside a `Column`-based toolbar layout.
- All `WebViewClient` URL routing (pass-through for `*.guidepaw.app`, external intent for others) and `companion_session.php` token-handoff POST logic preserved verbatim.
- `GpWebColorScheme` defined at file level (not inside the class).
- Removed explicit `import androidx.compose.foundation.layout.weight` — `weight` is a `ColumnScope` extension and must not be imported directly (caused compile error; fixed before final build).
- Deleted `activity_guidepaw_web.xml` — **no XML layout files remain anywhere in the companion app**.
- All four version files bumped together: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current source version: `0.022` / version code `22`
- APK committed to `downloads/GuidePaw_Companion_v0.022.apk`
- Release endpoint (`/api/app_release.php`) serving correct v0.022 metadata
- Activities: `MainActivity` (Compose), `FeedbackActivity` (Compose), `GuidePawWebActivity` (Compose), `NotificationCenterActivity` (still View-based)
- **Zero XML layout files remain** — `GuidePawWebActivity` was the last one

## Verification That Matters

- `./gradlew :app:assembleDebug` passes cleanly (35 tasks, BUILD SUCCESSFUL).
- `deploy_local.sh` smoke checks pass — PHP syntax, brand header, missing links, HTTP 200/302s all clean.
- `curl -k https://10.147.18.184/api/app_release.php` returns `version_code: 22`.

## Memory Anchors

- Version bumps are a bundle of four: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is pure Compose as of v0.022 — no XML layout files remain.
- `deploy_local.sh` must pass before any commit touching PHP.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct way to INSERT and get the new row ID — never `lastInsertId()`.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension and importing it directly causes a compile error ("it is internal").

## Possible Next Tasks

- Android: Port `NotificationCenterActivity` to Compose — the only remaining View-based activity.
- Web: Review `app.php` (the companion app landing page) to reflect v0.022 release notes.
- Web: The "How it works" and FAQ card row on the landing page could be reviewed for freshness.
