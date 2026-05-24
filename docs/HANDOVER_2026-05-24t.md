# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `904c3c3`
- Working tree: clean
- Live companion release: `v0.037` / version code `37`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.037.apk`

## What Was Finished This Session

### Android: Native ADA Access Card screen (v0.037, 904c3c3)

Replaced the WebView-launched `ada_access_card.php` with a full native Compose screen (`NavSection.ADA_ACCESS_CARD`). Menu item "🪪 ADA Access Card" now sets `currentSection = NavSection.ADA_ACCESS_CARD` instead of calling `openWebPage(...)`.

**Screen contents (all hardcoded federal content — no API calls):**
1. **Handler / dog identity** — `currentMe?.username` + active dog name from already-loaded state
2. **Calm script** — displayed in a `primaryContainer` card in large bold text; Copy button (writes to `ClipboardManager`) and Share button (`Intent.ACTION_SEND`)
3. **Two permissible questions** — "Is the dog required because of a disability?" / "What work or task has the dog been trained to perform?"
4. **What staff may NOT require** — certification/registry papers, medical records/diagnosis, task demonstration on demand, vest or special equipment
5. **Definitions** — Service dog (individually trained, handler training is valid), SDIT (not ADA service animal until training complete; state law may differ), ESA (comfort-only, no ADA public-access rights)
6. **Scam warning** — online registrations/certificates/ID cards/vests do not create ADA rights
7. **When access can be denied** — out of control (handler fails to correct), not housebroken; fear/allergies alone are not valid reasons
8. **DOJ ADA Information Line** — 800-514-0301 (tappable `tel:` dial intent), TTY 800-514-0383
9. **State law note** — explains the screen covers federal content only; `OutlinedButton` opens `ada_access_card.php` in the web version for state-specific notes and GPS state detection

**New imports added:** `android.content.ClipData`, `android.content.ClipboardManager`, `androidx.compose.ui.platform.LocalContext`

**What the web version has that the native screen intentionally skips:**
- Per-state law profiles (50 states × reviewed notes) — too large to embed; deferred to web
- GPS state detection via Nominatim reverse geocode — deferred to web
- Lockscreen / high-contrast / screenshot-ready display modes — not needed natively

### Android: Native Goal Builder screen (v0.036, 2feae90)

Built in the previous session; see `HANDOVER_2026-05-24s.md` for full detail.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.037` / version code `37`
- APK: `downloads/GuidePaw_Companion_v0.037.apk`
- All activities pure Compose — no XML layouts, no View-based code
- Native screens: Overview, Training log, Dogs/History, Wearables/Alerts, Goal Intake, Goal Builder, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, ADA Access Card
- Compose BOM: `2024.09.03` (Material3 1.3.1)
- Pull-to-refresh: all six scrollable sections (Overview, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment)

## Verification

- `./gradlew :app:assembleDebug` — BUILD SUCCESSFUL (37 tasks)
- `deploy_local.sh` smoke checks — all pass

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — no XML layouts, no View-based code.
- Do NOT `import androidx.compose.foundation.layout.weight` — it is a `ColumnScope`/`RowScope` extension; importing it directly causes a compile error.
- `ExposedDropdownMenuBox` and `PullToRefreshBox` require `@OptIn(ExperimentalMaterial3Api::class)` on the composable.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `loadRegressionEvents()` and `loadCandidateAssessments()` do NOT call `setLoading()` — they manage their own section message state and must clear `isPullingToRefresh` explicitly in their `runOnUiThread` blocks.

## Next Task Candidates

- Android: Native screens for Tactical Training (`tactical_training.php`) or Candidate Comparison (`candidate_comparison.php`).
- Android: Air Travel Rights native screen — `air_travel_rights.php` is a public reference page similar in structure to the ADA Access Card; a natural next companion screen.
- Web: No remaining freshness review items from this session.
