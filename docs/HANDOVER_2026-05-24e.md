# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Local laptop git HEAD: `4b5812a`
- `origin/main`: `6ea4b8c` (one commit behind — not yet pushed)
- Working tree: clean (untracked: `docs/CURRENT_TASK.md`, `docs/guidepaw_ai_team_config.md`, `downloads/GuidePaw_Companion_v0.024.apk` already committed)
- Live companion release: `v0.024` / version code `24`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.024.apk`

## What Was Finished This Session

### Web: index.php landing page cleanup (commit `6ea4b8c`)
- Hero lead paragraph replaced — raw `$landingDescription` (which included SEO noise "Guide Paw searches should land here too.") swapped for clean user-facing copy.
- "Public Dog Profile" card converted from a dead `<div>` to a proper `<a href="dogs.php">` link, consistent with all other grid cards.
- Redundant "Need another tool?" nav hint removed from the logged-in dashboard (the bottom nav is already on screen).

### Android: Material 3 bottom navigation (commit `4b5812a`, v0.024)
- `BottomNav()` composable replaced: custom `Surface + Row + TextButton` → `NavigationBar + NavigationBarItem`.
- Emoji-in-Text icons replaced with real vector icons: `Icons.Filled.Home`, `Bolt`, `Pets`, `Notifications`, `Menu`.
- Unread count badge replaced: manually-positioned `Box + background` → `BadgedBox + Badge`.
- `NAV_ITEMS` simplified from a list of emoji-string pairs to a plain `List<NavSection>`.
- Net change: 54 insertions, 74 deletions.
- All four version files bumped together: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current source version: `0.024` / version code `24`
- APK committed to `downloads/GuidePaw_Companion_v0.024.apk`
- Release endpoint (`/api/app_release.php`) will serve v0.024 once deployed
- Activities: `MainActivity` (Compose), `FeedbackActivity` (Compose), `GuidePawWebActivity` (Compose), `NotificationCenterActivity` (Compose)
- The companion app is 100% pure Compose — no XML layouts, no View-based code

## Verification That Matters

- `./gradlew :app:assembleDebug` passes cleanly (35 tasks, BUILD SUCCESSFUL).
- `deploy_local.sh` smoke checks pass — PHP syntax, brand header, missing links, HTTP 200/302s all clean.
- `php -l` passes on all touched PHP files.

## Memory Anchors

- Version bumps are a bundle of four: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — do not introduce View-based, XML layout, or hybrid code.
- Do NOT `import androidx.compose.foundation.layout.weight` directly — it is a `ColumnScope`/`RowScope` extension and causes a compile error if imported at the top level.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.

## Possible Next Tasks

- Android: `OverviewSection` — currently shows a plain summary card and two buttons (Refresh / Sign Out). Could be improved to surface more useful at-a-glance info (active dog, recent log, quick actions).
- Android: `DogsSection` — dogs and logs on the same screen is a bit cramped; consider splitting or adding a tab within the section.
- Web: Review `app.php` (companion landing page) for v0.024 release notes.
- Web: `docs/CURRENT_TASK.md` and `docs/guidepaw_ai_team_config.md` are untracked — commit them if they should be version-controlled.
