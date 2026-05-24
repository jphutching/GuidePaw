# CURRENT_TASK - 2026-05-24

**Primary Goal**:
Make the Android companion app feel truly native and fluid (Jetpack Compose + Material 3) while maintaining exact visual and behavioral parity with the web app.

## Completed This Session

- [x] Clean up `index.php` landing page — hero copy, Public Dog Profile link, removed redundant nav hint
- [x] Replace Android bottom nav with Material 3 `NavigationBar` + vector icons + `BadgedBox` (v0.024)
- [x] Update `app.php` — reflect actual shipped features, note native Compose build
- [x] Redesign `OverviewSection` — active dog as hero, compact recent-activity summary, quick Log Training / View History buttons (v0.025)
- [x] Declutter `DogsSection` — compact active-dog header, collapsible Switch picker, logs labelled with dog name (v0.026)
- [x] Improve `TrainingSection` — active dog context, edit-mode banner with Cancel, section labels, disabled-state explanation (v0.027)
- [x] Restructure `WearablesSection` — split into Wearable Data + Notifications, active dog in wearable card, unread count surfaced inline (v0.028)
- [x] Tidy `MenuBottomSheet` — identity card at top, Logs section removed, Training trimmed 14→7, More trimmed 16→7; 46→24 total items (v0.029)
- [x] Improve web information hierarchy — web menu Training trimmed 14→8 primary + "More tools" subgroup; dashboard "Needs Attention" cleared of 4 non-actionable status cards

## Next Up

- [ ] Native Android screens for Goal Intake, Habit Repair, and Behavior Risk (currently open as WebView pages from the menu)

**Rules**:
- Small, safe changes
- Keep backward compatibility
- Focus on mobile-first experience
- Android: pure Compose only — no XML layouts, no View-based code
