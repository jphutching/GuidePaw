# GuidePaw Companion — Play Store Submission Guide
## Drag-and-drop checklist. Do these steps in order.

---

## STEP 1 — Pay the $25 developer fee
1. Go to: https://play.google.com/console/signup
2. Sign in with your Google account (use a permanent account you own)
3. Pay the one-time $25 USD registration fee
4. Fill in developer name: **GuidePaw**
5. Wait for approval (usually same day, up to 48 hours)

---

## STEP 2 — Create a new app
1. In Play Console → **All apps** → **Create app**
2. App name: `GuidePaw Companion`
3. Default language: `English (United States)`
4. App or game: `App`
5. Free or paid: `Free`
6. Accept declarations → **Create app**

---

## STEP 3 — Upload the AAB (the actual build)
1. Play Console → **Release** → **Production** → **Create new release**
2. Click **Upload** → drag and drop:
   `downloads/GuidePaw_Companion_v0.098_release.aab`
3. Release notes (copy/paste):
   ```
   Initial release of GuidePaw Companion — service dog training, health tracking, and handler tools.
   ```
4. **Save** (don't submit yet — finish the store listing first)

---

## STEP 4 — Store listing
1. Play Console → **Store presence** → **Main store listing**
2. Copy from `play-store/store-listing.txt`:
   - **App name**: GuidePaw Companion
   - **Short description**: paste the short description
   - **Full description**: paste the full description
3. **Graphics** — drag and drop these files:
   | Field | File |
   |-------|------|
   | App icon | `play-store/graphics/app_icon_512.png` |
   | Feature graphic | `play-store/graphics/feature_graphic_1024x500.png` |
   | Phone screenshots | Take 2–4 screenshots on your phone and upload them here |
4. **Save**

> **Screenshots tip:** Open the app on your Android phone, navigate to the Overview screen, QR Tracking, and Training Log. Take screenshots with the phone's screenshot button. Upload those — they just need to show the app working.

---

## STEP 5 — App content declarations
1. Play Console → **Policy** → **App content**

   **Privacy policy**
   - URL: `https://guidepaw.app/privacy`

   **Ads**
   - Does your app contain ads? **No**

   **App access**
   - All or some functionality is restricted → explain:
     `Account registration required. Demo accounts available: username demo.one / demo.two / demo.three, password Demo1234!`

   **Content rating**
   - Start questionnaire → Category: **Utilities**
   - Answer all questions (no violence, no user interaction, no location sharing) → rating will be **Everyone**

   **Target audience**
   - Age range: **18 and over**

   **News app**
   - Is this a news app? **No**

   **COVID-19 contact tracing**
   - No

---

## STEP 6 — Data Safety form
1. Play Console → **Policy** → **Data safety**
2. Answer each question using `play-store/data-safety-answers.txt`
3. **Save** and **Submit**

---

## STEP 7 — Final review and submit
1. Play Console → **Release** → **Production**
2. Review all warnings — fix any that are blocking
3. Click **Review release** → **Start rollout to Production**
4. Google reviews typically take **3–7 business days** for a new app

---

## FILES IN THIS DIRECTORY
```
play-store/
  SUBMIT.md                        ← this file
  store-listing.txt                ← all text to paste into Play Console
  data-safety-answers.txt          ← answers to the Data Safety form
  graphics/
    app_icon_512.png               ← drag to "App icon" field (512×512)
    feature_graphic_1024x500.png   ← drag to "Feature graphic" field
    hi_res_icon_512.png            ← same icon, alternate copy
```

## KEYSTORE (keep safe — never commit to git)
```
File:     /home/james/keys/guidepaw-release.jks
Password: CQtoAZAWpXu24iCK*9IEYKV^
Alias:    guidepaw
Backup:   /home/james/keys/guidepaw-keystore-credentials.txt
```
> Back this up to a USB drive or encrypted cloud storage.
> Losing it means you can NEVER push an update to the Play Store.

---

## BUILD A NEW SIGNED RELEASE (when you have a new version)
```bash
cd android/guidepaw-companion
GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon clean :app:bundleRelease
# Output: app/build/outputs/bundle/release/app-release.aab
```
