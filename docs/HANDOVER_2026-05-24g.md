# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `b3b5171`
- Working tree: clean
- Live companion release: `v0.029` / version code `29`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.029.apk`

## What Was Finished This Session

### Web: menu Training section trimmed (commit `1173093`)

`includes/mobile_nav.php` Training section reduced from 14 → 8 primary items. New primary order: Goal Intake, Habit Repair, Candidate Assessment, Behavior Risk, Regression Engine, Goal Builder, Coach Review, Training Program. The six removed items (Candidate Comparison, Community Challenges, Trucking Mode, Tactical Training, Session Log, Training History) were moved into a new collapsed "More tools" subgroup within the Training section — still accessible, not prominent.

### Web: dashboard "Needs Attention" decluttered (commit `1173093`)

`index.php` — removed four status cards that didn't belong in a review queue:
- **Wearable Sync** — showed last sync date; no action required
- **Trainer Marketplace** — showed trainer count; no action required
- **Candidate Comparison** — shown whenever multiple dogs existed; no action required
- **Community Challenge** — showed challenge state; no action required

The associated DB queries were also removed (`gpCommunityChallengeState`, `gpWearableRecentEvents`, `gpTrainerMarketplaceEntries`) — the dashboard no longer calls these on every page load.

"Needs Attention" now contains only genuinely actionable items: Smart Alerts, Vet Reminders, incoming dog transfers, Candidate Assessment score (when low), Regression Engine open items, Behavior Risk score, Coach Review queue, Video Review queue.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current version: `0.029` / version code `29`
- APK: `downloads/GuidePaw_Companion_v0.029.apk`
- All four activities pure Compose — no XML layouts, no View-based code

## Full Session Summary (v0.024–v0.029 + web)

| Version | Change |
|---------|--------|
| v0.024 | Material 3 `NavigationBar` + vector icons + `BadgedBox` |
| v0.025 | `OverviewSection` — active dog as hero, recent activity, quick actions |
| v0.026 | `DogsSection` — compact header, collapsible Switch picker, dog-named logs |
| v0.027 | `TrainingSection` — dog context, edit banner + Cancel, section labels |
| v0.028 | `WearablesSection` — split into Wearable Data + Notifications sections |
| v0.029 | `MenuBottomSheet` — identity card, 46→24 items, Logs section removed |
| web | `mobile_nav.php` Training 14→8+subgroup; dashboard 4 non-actionable cards removed |

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — do not introduce View-based, XML layout, or hybrid code.
- Do NOT `import androidx.compose.foundation.layout.weight` directly — it is a `ColumnScope`/`RowScope` extension and causes a compile error if imported at the top level.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `wearablesBody` state var has been removed — wearable content is now derived inline in `WearablesSection` from reactive state.
- `renderDashboard()` now only sets `trainMessage = ""`, `statusMessage`, and `loginMessage`.
- `index.php` no longer fetches `gpCommunityChallengeState`, `gpWearableRecentEvents`, or `gpTrainerMarketplaceEntries` on dashboard load.

## Next Task

- Native Android screens for **Goal Intake**, **Habit Repair**, and **Behavior Risk** — currently these open as WebView pages from the menu. The goal is to build proper Compose screens inside `MainActivity` (or new Activity files) that replicate the core workflow natively.
