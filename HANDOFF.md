# 🤝 GuidePaw AI Handoff Document

> **Read these first (in order):**
> 1. `cat CODEX_BOOT.md` — who you are and how to think
> 2. `cat CODEX_RULES.md` — 10 rules from past screwups
> 3. `cat PROJECT_STATE.md` — persistent source of truth (version, architecture, accounts)
> 4. `cat DEVLOG.md | tail -80` — recent session history
> This file (HANDOFF.md) is written at the end of each Claude session — use it for "what just happened" only.

| Field | Value |
|---|---|
| **From** | CLAUDE |
| **To** | CODEX |
| **Branch** | `main` |
| **Timestamp** | `2026-05-31` |

---

## 📋 Summary of Work Completed

Full feedback queue sweep. All 18 open items triaged; 17 resolved (fixed or closed), 1 deferred to Android backlog.

New features shipped:
- **`api/places_search.php`** — server-side Google Places proxy (nearby, text, reverse geocode)
- **`nearby_places.php`** — new page: Dog Parks / Dog-Friendly Eateries / Veterinary Clinics by location
- **`dog_health.php`** — "Find nearby vets" section with GPS + city/zip search, click-to-fill add-vet form
- **`edit_log.php`** — GPS button now reverse-geocodes and shows "Near: [address]" after acquiring coordinates
- **`breed_gallery.php`** — full-size lightbox on breed modal photo
- **`certification.php`** — ADA disclaimer banner (certs not legally required)
- **`admin.php`** — QA Checklist link added to More admin tools
- **`index.php`** — logo added to landing page hero
- **`app.php`** — "debug APK" label replaced with "APK"; sideload note updated for release builds
- **`admin_paywall_catalog.php`** — add-item form collapsed by default

Everything is deployed to Render (pushed to `main`, Render auto-deploys on push).

---

## 📁 Files Changed This Session

- `breed_gallery.php`
- `app.php`
- `admin.php`
- `certification.php`
- `index.php`
- `api/places_search.php` *(new)*
- `dog_health.php`
- `nearby_places.php` *(new)*
- `edit_log.php`
- `admin_paywall_catalog.php`
- `DEVLOG.md`

---

## 🎯 Next Task

**#69 — Native Android push notifications**

The one remaining open feedback item. User wants native OS-level notifications with proper Android permission requests (POST_NOTIFICATIONS, ACCESS_FINE_LOCATION, etc.) and possibly FCM-backed push.

This requires Android work:
1. Add `POST_NOTIFICATIONS` runtime permission request (Android 13+) to the companion app
2. Integrate FCM (Firebase Cloud Messaging) — server-side token storage + send trigger
3. Request other permissions (location, files) at the right moment in the app flow

This is a multi-session Android task. Do not conflate it with the existing in-app web notification system (`user_notifications` table / `notifications.php`) which already works.

---

## 🗂 Project Context

- **App:** GuidePaw (guidepaw.app) — assistive navigation + Android companion
- **Repo:** https://github.com/jphutching/GuidePaw (private)
- **Local:** /home/james/projects/guidepaw
- **App (Render):** https://guidepaw-ch3y.onrender.com
- **Middleware (laptop):** http://10.147.18.184:3333
- **Current version:** v0.099 (Android companion)

---

## 🚀 Pickup Instructions

```bash
git pull origin main
curl -s -X POST $MIDDLEWARE_URL/session/start \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"ai":"codex","task":"Android native push notifications — FCM integration and runtime permission requests","branch":"main"}'
```
