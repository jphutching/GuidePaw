# GuidePaw — TODO
Last updated: 2026-05-29

---

## Render / SEO

- [ ] **Set Bing verification env var on Render**
  - Get code from https://www.bing.com/webmasters → Add site → HTML meta tag method
  - Set `GUIDEPAW_BING_VERIFICATION=<code>` on Render (use `scripts/render-set-env.sh`)
  - Also update `master.env` with the real value
  - Covers: Yahoo, DuckDuckGo, Ecosia (all use Bing's index)

- [ ] **Set Yandex verification env var on Render**
  - Get code from https://webmaster.yandex.com → Add site → meta tag method
  - Set `GUIDEPAW_YANDEX_VERIFICATION=<code>` on Render
  - Also update `master.env`

- [ ] **Verify /plans redirect works on production**
  - `curl -I https://guidepaw.app/plans` — expect 301 → /paywalls.php
  - Also open in Android companion app to confirm no 404

---

## Google Play Store Submission
> All files are ready in `play-store/`. Follow `play-store/SUBMIT.md` step by step.

- [ ] **STEP 1 — Pay $25 developer fee**
  - https://play.google.com/console/signup
  - Sign in with a permanent Google account you own
  - Developer name: **GuidePaw**
  - Approval: usually same day, up to 48 hours

- [ ] **STEP 2 — Create app in Play Console**
  - All apps → Create app
  - Name: `GuidePaw Companion`, Language: English (US), Type: App, Price: Free
  - Accept declarations → Create app

- [ ] **STEP 3 — Upload AAB release build**
  - Release → Production → Create new release
  - Upload: `downloads/GuidePaw_Companion_v0.098_release.aab`
  - Release notes: `Initial release of GuidePaw Companion — service dog training, health tracking, and handler tools.`
  - **Save** (don't submit yet)

- [ ] **STEP 4 — Fill in store listing text and graphics**
  - Store presence → Main store listing
  - Copy text from `play-store/store-listing.txt`
  - Upload `play-store/graphics/app_icon_512.png` → App icon
  - Upload `play-store/graphics/feature_graphic_1024x500.png` → Feature graphic
  - Take 2–4 screenshots on your phone (Overview, QR Tracking, Training Log) and upload

- [ ] **STEP 5 — App content declarations**
  - Policy → App content
  - Privacy policy URL: `https://guidepaw.app/privacy`
  - Ads: **No**
  - App access: restricted — demo accounts `demo.one` / `demo.two` / `demo.three`, password `Demo1234!`
  - Content rating: Utilities → **Everyone**
  - Target audience: **18 and over**
  - News app: **No**
  - COVID contact tracing: **No**

- [ ] **STEP 6 — Fill in Data Safety form**
  - Policy → Data safety
  - Use `play-store/data-safety-answers.txt` for all answers
  - Save and Submit

- [ ] **STEP 7 — Final review and submit to production**
  - Release → Production → fix any blocking warnings
  - Review release → Start rollout to Production
  - Google review: **3–7 business days** for a new app

---

## Keystore reminder
```
File:     /home/james/keys/guidepaw-release.jks
Password: CQtoAZAWpXu24iCK*9IEYKV^
Alias:    guidepaw
Backup:   /home/james/keys/guidepaw-keystore-credentials.txt
```
Back this up to USB or encrypted cloud. Losing it = can never push Play Store updates.
