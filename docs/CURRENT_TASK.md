# CURRENT_TASK - 2026-05-24

**Primary Goal**:
Make the Android companion app feel truly native and fluid (Jetpack Compose + Material 3) while maintaining exact visual and behavioral parity with the web app.

## Completed This Session

- [x] Clean up `index.php` landing page — hero copy, Public Dog Profile link, removed redundant nav hint
- [x] Replace Android bottom nav with Material 3 `NavigationBar` + vector icons + `BadgedBox` (v0.024)

## Phase 1 — In Progress

- [ ] Improve `OverviewSection` — surface active dog, recent log summary, and quick-action buttons more usefully
- [ ] Review `DogsSection` — dogs and logs on same screen is cramped; consider splitting or tabbing
- [ ] Update `app.php` (companion landing page) for v0.024 release notes

## Phase 2 — Queued

- [ ] Review remaining sections (Training, Wearables) for UX improvements now that nav is native
- [ ] Improve information hierarchy across web so users don't get lost

**Rules**:
- Small, safe changes
- Keep backward compatibility
- Focus on mobile-first experience
- Android: pure Compose only — no XML layouts, no View-based code
