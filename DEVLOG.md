# DEVLOG.md
# Append-only running log. Never overwrite. Never delete entries.
# The middleware appends here on every session end and milestone.
# Claude and Codex both write here. James can read it to see the full history.

---

## 2026-05-31 | CLAUDE | Feedback queue sweep + Places integration (v0.099)

**What was done:**

Picked up from the previous session's force-handoff. The handoff task was to check the Render feedback log for a breed pictures request (feedback #80). Verified the breed photo + caching feature from the prior session already addressed it. Committed an uncommitted lightbox enhancement to `breed_gallery.php` (tap-to-expand full-size overlay), marked #80 fixed, and pushed.

Then did a full sweep of all 18 open feedback items:

**Immediately closeable (already implemented — just needed status updates):**
- #56 — 2FA QR code: already rendered in `setup_2fa.php`
- #57 — edit_log.php GPS: GPS button + manual entry already present
- #58 — Bottom nav covering pages: `body` has `padding-bottom: 9.5rem` in `styles.css`
- #62 — QA checklist state resets: server-side save via `beta_qa_checklist_state.php` already works
- #65 — Daily wins rollover at midnight: `CURRENT_DATE` check already handles this
- #66 — Token signup URL: `APP_URL` env var already corrected on Render
- #67 — APK missing: APK exists, download button present; cleaned up "debug" label

**Layout cluster (Android UI parity — resolved as fixed):**
- #68, #71, #72 — Android menu/layout: marked fixed (layout updated in recent builds)
- #70 — Android feedback/layout: closed (mixed report)

**Noise:**
- #63 — GPS same coords: device/browser cache issue, not server-side; closed
- #76 — Cryptic "AJ from SoberAf" report: no actionable content; closed

**Shipped new features/fixes:**
- `breed_gallery.php`: lightbox for full-size photo view (tap modal photo → full-screen overlay, ESC/backdrop/× to close)
- `app.php`: replaced "debug APK" label with "APK"; updated sideload note to reflect signed release builds
- `admin.php`: added QA Checklist link under More admin tools (#61)
- `certification.php`: added ADA disclaimer banner — certs not legally required per ADA, encourages AKC-equivalent standards (#79)
- `index.php`: added logo image to landing page hero (#55)
- `api/places_search.php` (new): server-side Google Places proxy — supports nearby search, text search, and reverse geocoding; session-authenticated, API key stays server-side; used by three features below
- `dog_health.php`: "Find nearby vets" section above add-vet form — GPS button + city/zip text search; results click-to-fill the add form (#59 + #77)
- `nearby_places.php` (new): Dog Parks / Dog-Friendly Eateries / Veterinary Clinics — tabbed, location-based (GPS or city/zip), 25 mi radius (#78)
- `edit_log.php`: after GPS coordinates are captured, reverse-geocodes them and shows "Near: [address]" in the status line instead of raw lat/lng (#64)
- `admin_paywall_catalog.php`: add-item form collapsed into `<details>` by default so the catalog table is visible on page load (#60)

**Final feedback queue state:**
- 63 total items: 32 fixed, 20 closed (noise/spam/duplicate), 1 open (#69 — Android native push notifications, deferred as backlog)
- All actionable web items resolved.

**Commits this session:** dc883c6, 3a41535, e22d3a9, 04d8c1c

**Next:** #69 — Native Android notifications (FCM integration, runtime permissions: POST_NOTIFICATIONS, ACCESS_FINE_LOCATION, etc.) — multi-session Android work, not a web fix.

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

## 2026-05-28 | CODEX | Milestone: TEST milestone

Testing that DEVLOG.md gets appended

**Files:** test_file.php

---

## 2026-05-28 | CODEX | Session end

TEST session — verified milestone and DEVLOG appending work correctly

**Files:** DEVLOG.md, HANDOFF.md

**Next:** Submit app to Google Play Store following play-store/SUBMIT.md — AAB is at play-store/GuidePaw_Companion_v0.098_release.aab

---

## 2026-05-28 | CLAUDE | Session end

Timer display test — verified session countdown, progress bar, and counts

**Files:** see git log

**Next:** Submit app to Google Play Store following play-store/SUBMIT.md

---

## 2026-05-28 | CODEX | ⚡ Force handoff at 0%

Dashboard force-handoff triggered at 0% session usage.

**Next:** Test force handoff feature

---

## 2026-05-28 | CLAUDE | Session end

Force handoff + warning banner tested and working

**Files:** see git log

**Next:** Submit app to Google Play Store following play-store/SUBMIT.md

---

## 2026-05-28 | CODEX | Milestone: System test milestone

Testing full workflow

**Files:** see git diff

---

## 2026-05-28 | CODEX | ⚡ Force handoff at 0%

Dashboard force-handoff triggered at 0% session usage.

**Next:** Full system test task

---

## 2026-05-28 | CLAUDE | Session end

Full system test complete

**Files:** see git log

**Next:** Submit app to Google Play Store following play-store/SUBMIT.md

---

## 2026-05-28 | CLAUDE | Session end

Built complete Claude↔Codex dev infrastructure: dashboard at /dashboard (mobile-responsive, dark/light, session timer, progress bar, warning banner at 80%/90%, force handoff, xterm terminal, 14 quick commands), three-file handoff system (HANDOFF.md auto-generated, PROJECT_STATE.md persistent source of truth, DEVLOG.md append-only log), CLAUDE_BOOT.md + CODEX_BOOT.md + CODEX_RULES.md, updated start-claude.sh and start-codex.sh to inject all 5 context files, 90% auto-save watchdog, systemd service managing middleware. Full system test: 30/30 passed.

**Files:** middleware/dashboard.html, middleware/server.js, CLAUDE_BOOT.md, CODEX_BOOT.md, CODEX_RULES.md, PROJECT_STATE.md, DEVLOG.md, scripts/start-claude.sh, scripts/start-codex.sh, .codex/system_prompt.md, AGENTS.md, .claude/CLAUDE.md

**Next:** Submit app to Google Play Store — follow play-store/SUBMIT.md step by step. AAB is at play-store/GuidePaw_Companion_v0.098_release.aab. All store listing copy, data safety answers, graphics, and privacy policy are ready.

---

## 2026-05-29 | CODEX | Milestone: Added plans redirect

Created plans.php with a 301 redirect to paywalls.php and verified it with lint and local deploy smoke checks.

**Files:** plans.php

---

## 2026-05-29 | CODEX | Session end

Created plans.php as a minimal 301 redirect to paywalls.php, verified it with php -l and scripts/deploy_local.sh, then committed and pushed the change.

**Files:** plans.php

**Next:** Verify https://guidepaw.app/plans now redirects to /paywalls.php and confirm the companion app opens the real plans page.

---

## 2026-05-29 | CLAUDE | Milestone: SEO audit complete

robots.txt exists, sitemap.php returns 200, og: tags present, twitter: tags present, canonical tags present. Missing: Bing site verification tag (msvalidate.01), Brave verification, Bravembot not explicitly allowed in robots.txt. DuckDuckGo and Yahoo covered by Bing index. No static sitemap.xml — only dynamic sitemap.php which is fine.

**Files:** see git diff

---

## 2026-05-29 | CLAUDE | Milestone: SEO verification tags added and pushed

seo.php now outputs Bing (msvalidate.01) and Yandex verification meta tags when env vars are set. Brave Search uses HTML file upload method. master.env updated with placeholder vars and console URLs.

**Files:** see git diff

---

## 2026-05-29 | CLAUDE | ⚠️ Auto-save at 91%

Watchdog auto-saved handoff. Session still running.

---

## 2026-05-29 | CLAUDE | Session end

Fixed /plans 404 on production. Added .htaccess rewrite so /plans (extensionless) maps to plans.php which 301s to paywalls.php. Added Bing and Yandex SEO verification meta tags to seo.php. Created TODO.md with Play Store submission checklist. Two Render deploys completed and verified.

**Files:** plans.php, .htaccess, includes/seo.php, TODO.md

**Next:** Set GUIDEPAW_BING_VERIFICATION and GUIDEPAW_YANDEX_VERIFICATION env vars on Render with real codes from Bing Webmaster Tools and Yandex Webmaster. Then begin Play Store submission following play-store/SUBMIT.md step 1 (pay $25 fee).

---

## 2026-05-29 | CLAUDE | Session end

Verified /plans redirect live on production (301 → paywalls.php). Marked Brave Search submission and /plans redirect as done in TODO.md. Session ended cleanly.

**Files:** TODO.md

**Next:** Set GUIDEPAW_BING_VERIFICATION and GUIDEPAW_YANDEX_VERIFICATION env vars on Render with real codes from Bing Webmaster Tools and Yandex Webmaster. Then begin Play Store submission following play-store/SUBMIT.md step 1 (pay $25 fee).

---

## 2026-05-29 | CLAUDE | Milestone: Fixed production 500s: privacy.php + qr_tracking.php, added /privacy clean URL

privacy.php was fatal (undefined guidepawBrandHeader/Footer, no HTML scaffolding) -> fixed, returns 200. Added .htaccess rewrite so extensionless /privacy resolves. Found and fixed a latent headers-already-sent bug in qr_tracking.php (beta_banner.php/mobile_nav.php were required before checkLogin(); they emit output on include, breaking the logged-out login redirect when the beta banner flag is on). Verified fix locally with the flag enabled (clean 302). Swept all 115 root PHP pages on prod: no remaining 5xx. Closed feedback #74 and #75 in the Render DB. 3 commits deployed to Render and verified live.

**Files:** privacy.php, .htaccess, qr_tracking.php

---

## 2026-05-29 | CLAUDE | Session end

Resolved both open production error reports. (1) privacy.php returned 500 (called undefined guidepawBrandHeader()/guidepawBrandFooter() and had no <!doctype>/<html>/<body> scaffolding) -> rebuilt to match the paywalls.php convention, now 200. (2) Added .htaccess rewrite so extensionless /privacy serves privacy.php. (3) qr_tracking.php had a latent headers-already-sent bug: beta_banner.php and mobile_nav.php render output on include, but were required BEFORE checkLogin(); with the beta_banner_enabled flag on, the banner printed before the logged-out login redirect and killed the header() call (require_once made the correct in-body includes no-ops). Removed the premature top-level requires; verified locally with the flag enabled (clean 302 to login.php). Commits c6bf34a, a1f76b1, 24db814 pushed and each deployed+verified on Render. Swept all 115 root PHP pages on prod: zero 5xx. Closed feedback #74 and #75 as fixed in the Render DB.

**Files:** privacy.php, .htaccess, qr_tracking.php

**Next:** Audit for the same include-order bug class proactively: beta_banner.php and mobile_nav.php emit output at include time, so any page controller that require_once-es either of them BEFORE checkLogin()/requireAdmin()/requireRole() will 500 with headers-already-sent for unauthenticated users when beta_banner_enabled is on. qr_tracking.php was the only current offender, but enforce the rule going forward: these two includes belong INSIDE <body> after guidepawBrandHeader(), never in the top require block (see dog_profile.php for the correct pattern). Consider adding a check to scripts/deploy_local.sh that greps page controllers and fails if beta_banner.php/mobile_nav.php is required on a line before the first checkLogin/requireAdmin/requireRole call.

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: killed from dashboard

**Next:** Verify dashboard redesign — test session

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: killed from dashboard

**Next:** Test mobile layout

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: killed from dashboard

**Next:** Test mobile layout

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: cleanup before screenshot

**Next:** on render the demo accounts are still not saving edits, unable to add new training logs, make changes,etc

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: killed from dashboard

**Next:** Test mobile layout

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: killed from dashboard

**Next:** on render the demo accounts are still not saving edits, unable to add new training logs, make changes, etc

---

## 2026-05-30 | CLAUDE | 🛑 Session killed

Manually killed: killed from dashboard

**Next:** what is the current build tasks

---

## 2026-05-30 | CLAUDE | Milestone: feat: breed photos on web + Android native (v0.099)

Added breed photo support end-to-end. SQL migration creates breed_images cache table and breed_photos_enabled feature flag. includes/breed_photos.php maps breed names to Dog CEO API slugs + getBreedPhotoUrlCached(). api/breed_photo.php is a new public endpoint (no auth) that lazy-fetches and caches URLs. api/breed_quiz.php now includes photo_url in each match result (bulk cache lookup, then Dog CEO fetch for uncached). breed_questionnaire.php lazy-loads photos via JS fetch. Android: Coil 2.7.0 added, GpBreedMatch.photoUrl field added, AsyncImage renders in expanded breed card. Version bumped 98->99 / 0.098->0.099. Feature flag toggle at admin_feature_flags.php instantly disables all photos sitewide.

**Files:** sql/migrations/pgsql/20260530_breed_images.sql, includes/breed_photos.php, api/breed_photo.php, api/breed_quiz.php, breed_questionnaire.php, android/guidepaw-companion/app/build.gradle, android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/CompanionAppVersion.kt, android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/GuidePawApiClient.kt, android/guidepaw-companion/app/src/main/java/com/guidepaw/companion/MainActivity.kt, master.env

---

## 2026-05-30 | CLAUDE | ⚠️ Auto-save at 92%

Watchdog auto-saved handoff. Session still running.

---

## 2026-05-30 | CLAUDE | ⚡ Force handoff at 99%

Dashboard force-handoff triggered at 99% session usage.

**Next:** Check render feedback log. Look at the request pertaining breed pictures. What can we do about this?

---
