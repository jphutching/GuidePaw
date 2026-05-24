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

## Next Up

- [ ] Improve information hierarchy across web so users don't get lost
- [ ] Review `MenuBottomSheet` for UX improvements
- [ ] Goal intake, habit repair, and behavior risk views (Android)

**Rules**:
- Small, safe changes
- Keep backward compatibility
- Focus on mobile-first experience
- Android: pure Compose only — no XML layouts, no View-based code
