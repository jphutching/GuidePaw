# GuidePaw Handover

Generated from the current repository state for a fresh chat handoff.

## Current Snapshot

- Git HEAD (local + origin/main): `3481765`
- Working tree: clean
- Live companion release: `v0.029` / version code `29`
- Live APK: `https://guidepaw.app/downloads/GuidePaw_Companion_v0.029.apk`

## What Was Finished This Session

### Web: app.php content update (commit `d196078`)
- "Minimum parity / Training is the floor" section replaced with "What's in the app / Core handler workflow" listing actual shipped features (training logs, dogs, wearables, notifications, feedback).
- Download section updated to note the build is fully native Compose with no XML layouts.
- Download card changed from `landing-card` class to `panel` for visual consistency.

### Android: OverviewSection redesign (commit `013e5ee`, v0.025)
- Active dog name promoted to `titleLarge` bold hero — breed + lifecycle status below, dog count + switch hint if multiple dogs.
- "Log Training" (filled) and "View History" (outlined) quick-action buttons navigate directly to `NavSection.TRAINING` / `NavSection.DOGS`.
- Recent activity shows the last 2 log entries inline: date, location, focus level, skills practiced.
- Username demoted to a secondary muted line under the dog info.

### Android: DogsSection declutter (commit `066b98f`, v0.026)
- Replaced flat dogs-then-divider-then-logs layout with a compact active-dog header card.
- "Switch" button appears only when multiple dogs are accessible; tapping it expands the full `DogCard` list.
- Dog picker auto-collapses after selecting a dog (`onSelected` callback added to `DogCard`).
- Log history labelled `"[Dog name]'s Logs"` instead of generic "Recent Logs".
- Empty log state uses `SummaryCard` for visual consistency.

### Android: TrainingSection improvements (commit `b2219ef`, v0.027)
- Active dog context card at the top ("Logging for [name]" / "No active dog — go to Dogs").
- Edit mode gets a `GpPrimaryContainer`-tinted banner with the editing message and a Cancel button; Cancel resets all fields and clears `currentEditingLogId`.
- Fields grouped with `labelLarge` section labels: "Location", "Focus level: X", "Skills practiced".
- Disabled submit button explains itself: "Select an active dog to enable logging." appears below when no dog is active.

### Android: WearablesSection restructure (commit `c07cda9`, v0.028)
- Split "Wearables & Notifications" blob into two labelled sections with a `HorizontalDivider`.
- Wearable Data section: shows active dog name and description derived directly from reactive state — `wearablesBody` string var removed entirely.
- Notifications section: surfaces `currentUnreadCount` inline (shown only when > 0); "Notification Center" promoted to a filled button.
- `renderDashboard()` simplified — `wearablesBody` assignment removed.

### Android: MenuBottomSheet tidy (commit `cc88d8e`, v0.029)
- Identity card added at the top (active dog name + "Signed in as [username]").
- "Logs" section removed — Log Training and View History are already the first two nav bar tabs.
- Training trimmed from 14 → 7 items: Log Training, Goal Intake, Habit Repair, Behavior Risk, Regression Engine, Candidate Assessment, Goal Builder.
- Dog section: Dog Audit removed (admin-facing), 6 items remain.
- More trimmed from 16 → 7 items: Notifications, Smart Alerts, Feedback, ADA Access Card, Certification, Plans, FAQ.
- Total menu items: 46 → 24.

## Android Companion State

- Package: `com.guidepaw.companion`
- Current source version: `0.029` / version code `29`
- APK committed to `downloads/GuidePaw_Companion_v0.029.apk`
- Activities: `MainActivity` (Compose), `FeedbackActivity` (Compose), `GuidePawWebActivity` (Compose), `NotificationCenterActivity` (Compose)
- 100% pure Compose — no XML layouts, no View-based code

## Verification That Matters

- `./gradlew :app:assembleDebug` passes cleanly at every version bump (BUILD SUCCESSFUL).
- `deploy_local.sh` smoke checks pass — PHP syntax, brand header, missing links, HTTP 200/302s all clean.
- `php -l` passes on all touched PHP files.

## Memory Anchors

- Version bumps are a bundle of four files: `build.gradle`, `CompanionAppVersion.kt`, `companion_release.php` defaults, APK in `downloads/`. All must move together.
- The Android companion is 100% pure Compose — do not introduce View-based, XML layout, or hybrid code.
- Do NOT `import androidx.compose.foundation.layout.weight` directly — it is a `ColumnScope`/`RowScope` extension and causes a compile error if imported at the top level.
- `insertAndGetId($pdo, $sql, $params)` from `db_core.php` is the correct INSERT pattern — never `lastInsertId()`.
- `deploy_local.sh` must pass before any commit touching PHP.
- `wearablesBody` state var has been removed — wearable content is now derived inline in `WearablesSection` from `currentDogs`/`currentActiveDogId`.
- `renderDashboard()` now only sets `trainMessage = ""`, `statusMessage`, and `loginMessage` — it no longer sets any section-specific body text.

## Possible Next Tasks

- Web: Improve information hierarchy across pages so users don't get lost navigating the app.
- Android: Native screens for Goal Intake, Habit Repair, and Behavior Risk (currently open as WebView pages from the menu).
