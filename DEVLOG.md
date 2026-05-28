# DEVLOG.md
# Append-only running log. Never overwrite. Never delete entries.
# The middleware appends here on every session end and milestone.
# Claude and Codex both write here. James can read it to see the full history.

---

## 2026-05-28 | Claude | v0.098 — Play Store prep + full sync

**What was done:**
- Synced all version files to 0.098 — website was stuck showing 0.095
- Pushed Render env vars: `VERSION_CODE=98`, `VERSION_NAME=0.098`, `APK_PATH=downloads/GuidePaw_Companion_v0.098_release.apk`
- Deployed locally — server now reports `companion version → 0.098`
- Committed `play-store/` submission kit (SUBMIT.md, store-listing.txt, data-safety-answers.txt, graphics/)
- Committed signed release APK/AAB for v0.097 and v0.098
- Committed `privacy.php` at `/privacy`
- Full DB backup: `storage/guidepaw-backup-20260528.sql`
- Created `CODEX_BOOT.md` — identity and reasoning layer for Codex sessions
- Created `CODEX_RULES.md` — 10 strict rules from past screwups
- Created `PROJECT_STATE.md` — persistent source of truth (this replaces the part of HANDOFF.md that kept getting overwritten)
- Updated middleware to append to `DEVLOG.md` on session end and milestones

**Next:** James submits to Play Store following `play-store/SUBMIT.md`. Codex can work on natively building remaining WebView-only screens (Training Log list, Public Dog Profile, Community Challenges detail).

---

## 2026-05-27 | Claude | v0.092–v0.097 — badge, update check, demo system, found-dog reports

**What was done:**
- v0.092: New badge image + tagline "TRAINING TRUST FOR THE JOURNEY"
- v0.093: Update check button in Settings, block auto-downgrade
- v0.094: Remove token UI from Settings, auto-revoke on login
- v0.095: Demo countdown banner, reset endpoint, demo smoke tests
- v0.096: Isolated ephemeral demo sessions, fixed PHP boolean coercion bug
- v0.097: Found-dog reports in QR Tracking, live changelog dialog, demo banner fix

---

## 2026-05-26 | Claude/Codex | v0.079–v0.091 — native sections, brand header, various fixes

**What was done:**
- v0.079: 4 native Android sections (FAQ, Add Dog, Plans tier cards, Trainer Marketplace)
- v0.080–v0.091: State Access Laws, GPS auto-detect, Health Summary, multiple section ports
- Fixed deploy_local.sh reading version from source not nginx
- Fixed render-set-env.sh (was rewritten after Codex damage)
- Brand header updated — larger, centered title

---
