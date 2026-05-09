# GuidePaw Project History / Handoff

Last updated: 2026-05-09

This file is the durable project memory for GuidePaw. Use it before starting or resuming work so the project can continue without backtracking when a chat gets long, scattered, or sidetracked.

## How to use this file

1. Read this file before starting a new GuidePaw coding session.
2. Check **Current status**, **In progress**, **Known issues**, and **Next recommended work**.
3. Update this file after meaningful changes, especially if code is committed, a workflow changes, a bug is found, or testing reveals a blocker.
4. Do not store secrets, passwords, API tokens, private bot tokens, SMS provider secrets, or database credentials in this file.
5. Historical chat exports can be used for reference, but the current GitHub repository is the source of truth for code.

## Project source of truth

- Current code source: GitHub repository `jphutching/GuidePaw`.
- Local checkout used by James: `/home/james/projects/gpb3/gpb3`.
- Beta URL: `https://beta.guidepaw.app`.
- Landing page: `https://guidepaw.app`.
- Deployment target: Render.
- Database: PostgreSQL.
- Render web service plan should be `starter`.
- Render PostgreSQL database plan should remain `basic-256mb`.
- Email provider: Zoho ZeptoMail API.
- Admin sender configuration has been tested with `admin@guidepaw.app`.
- Telegram bot notifications are admin-oriented and should remain optional/admin-only. Bot tokens must never be committed.
- SMS notifications are an opt-in handler/user alert layer using configurable Twilio support. Twilio credentials and phone numbers must never be committed.

## Important operating rules

- Do not overwrite the current repo with historical chat zip files.
- Do not commit secrets, API keys, Telegram bot tokens, ZeptoMail tokens, Twilio tokens, database passwords, or private credentials.
- Before committing `render.yaml`, verify indentation and plans:
  - Web service: `plan: starter` under the web service.
  - Database: `plan: basic-256mb` under the database.
- If a feature adds or changes behavior, add related test items to the Beta QA Checklist.
- If work changes current status, open tasks, or next steps, update this project history file.
- Prefer small, safe commits with clear messages.
- Test or add safety checks before destructive cleanup operations.

## Current stable status

### 2026-05-08 deployment checkpoint

- Laptop, GitHub `main`, and Render beta are aligned on commit `a42246f` (`Stabilize Playwright crawlers for current GuidePaw UI`).
- Render web service `srv-d7qmnj7lk1mc73cl18j0` deployed commit `a42246f512ee754b03c7430e8fd247341db93d27`.
- Live Render deploy ID: `dep-d7uj9099rddc73fp2390`.
- Render deploy status confirmed through the Render API as `live`.
- Full Playwright suite passed against the Render beta environment: `9 passed`.
- Browser specs were stabilized for the current UI:
  - Add-dog flow opens the collapsed `dogs.php` add form before filling it.
  - Normal-user admin protection accepts `role_required` redirects as a valid block.
  - Authenticated crawler skips the beta QA checklist page to avoid intentional instructional false positives.
  - QR smoke test falls back to `GUIDEPAW_TEST_BASE_URL`.
- Later on 2026-05-08, shared logo/tagline/menu chrome was verified across normal and admin logged-in pages:
  - `scripts/check_brand_nav_presence.js` checks visible logo, tagline, primary nav, and menu button.
  - Render deploy `dep-d7uk16svikkc73beunn0` is live on commit `646f730`.
  - Brand/menu crawler passed against Render: normal checked 46 pages; admin checked 68 pages.
  - Full Render Playwright suite passed after the chrome fixes: `9 passed`.
- Responsive overflow audit added on 2026-05-08:
  - `scripts/check_responsive_overflow.js` crawls normal/admin pages at 320, 375, 768, and 1366 px widths.
  - Shared mobile/tablet safeguards in `includes/mobile_nav.php` prevent horizontal page overflow and stack app tables below 900 px.
  - Render deploy `dep-d7uko557vvec739okmsg` is live on commit `27dd8a1`.
  - Responsive audit passed against Render: normal 42 pages and admin 66 pages across all configured widths, with no overflow findings.
  - Brand/menu crawler and full Render Playwright suite also passed after the responsive changes.
- Media review workflow was added after the responsive pass:
  - Local migration `sql/migrations/pgsql/20260508_media_reviews.sql` was applied to the `guidepaw` database.
  - The new `daily_log_media_reviews` table was verified present and writable.
  - A PHP CLI smoke test saved a review row for a real training log attachment, confirming the write path.
  - The live local host at `https://10.147.18.184` was redeployed and the QA crawler passed `media_review_page_loads` with HTTP 200.
  - The beta QA checklist was marked from the crawler run after the live host pass.
  - `scripts/deploy_local.sh` was cleaned up so the deploy smoke checks finish without the earlier false-positive warnings.
  - The beta database schema was reconciled with the local migration set, and the system-health page now surfaces schema version and pending/applied migration files.
  - The admin System Health page can now run pending migrations when `APP_ALLOW_DB_MIGRATIONS=true`, and the crawler checks that migration section explicitly.
- Coach review workflow was added on 2026-05-08:
  - `coach_review.php` now routes regression events into a coach review queue for the active dog.
  - `includes/coach_reviews.php` centralizes queue, create, and update helpers for the `coach_reviews` table.
  - The Training section menu and beta QA checklist now cover Coach Review.
  - The local QA crawler checks the new coach review page alongside media review and System Health.
- Video review workflow was added on 2026-05-08:
  - `video_review.php` filters checkpoint videos from training logs and reuses the shared media-review storage.
  - `includes/video_reviews.php` centralizes video-specific queue and dashboard helpers.
  - `video_reviews_enabled` is now enabled through migration `20260508_video_reviews_enabled.sql`.
  - The dashboard, Training menu, beta QA checklist, and local crawler now cover Video Review.
  - The dashboard query was corrected to use the actual `daily_log_media_reviews` timestamps, and both local and beta crawlers passed `20/20` after the fix.
- Candidate scoring dashboard support was added on 2026-05-08:
  - `includes/candidate_scoring.php` now exposes a dashboard helper for the latest candidate assessment.
  - `index.php` shows the active dog's latest candidate focus in Needs Attention.
  - `scripts/local_qa_crawler.php` checks the candidate assessment page and dashboard hook.
  - `feature_roadmap.candidate_scoring_enabled` was promoted to `beta_enabled` through `20260508_candidate_scoring_beta_enabled.sql`.
  - Local and beta crawlers passed `22/22` after the candidate scoring rollout.
- Trucking mode planning was added on 2026-05-08:
  - `trucking_mode.php` lets the handler choose a day type and save route-specific notes for the active dog.
  - `includes/trucking_mode.php` holds the planner definitions and session-backed state helpers.
  - `index.php` adds a Trucking Mode shortcut in the Today section, and the mobile menu now links to the planner.
  - `scripts/local_qa_crawler.php` checks the Trucking Mode page and dashboard hook.
  - `feature_roadmap.trucking_mode_enabled` was promoted to `beta_enabled` through `20260508_trucking_mode_beta_enabled.sql`.
  - Local and beta crawlers passed `24/24` after the trucking-mode rollout.
- AI Training Assistant support was added on 2026-05-08:
  - `ai_training_assistant.php` provides bounded troubleshooting guidance for active-dog training issues.
  - `includes/training_assistant.php` contains the rule-based assistant topics and safety-aware response builder.
  - The mobile menu now links to AI Training Assistant under Support, and the beta QA checklist/crawler cover the new page.
  - `feature_flags.ai_training_assistant_enabled` and `feature_roadmap.ai_training_assistant_enabled` were both promoted through `20260508_ai_training_assistant_beta_enabled.sql`.
  - The beta crawler passed `25/25` after the flag fix and deploy refresh.
- Regression Engine work started on 2026-05-08:
  - `regression_engine.php` now exposes open regression events for the active dog with a reset-plan editor and status updates.
  - `includes/regression_engine.php` centralizes regression-event queries, dashboard alerts, and update helpers.
  - The Training menu, Today dashboard actions, beta QA checklist, local crawler, and deploy smoke checks now include Regression Engine.
  - Commit `d41b07a` captured the workflow slice.
  - Local mirror deployment reached `regression_engine.php`, but the credentialed crawler pass against `https://10.147.18.184` was still blocked by auth failures on the seeded test logins.
- Smoke-login repair tooling was added on 2026-05-08:
  - `scripts/repair_smoke_auth.php` can reset the seeded admin and regular smoke accounts back to the crawler passwords and clear 2FA for smoke testing.
  - `scripts/run_e2e.sh` and `scripts/run_admin_e2e.sh` can optionally run that repair step first when `GUIDEPAW_REPAIR_SMOKE_AUTH=yes`.
  - This is the supported path for fixing login drift on local or beta hosts without introducing a browser-side bypass.
  - The repair path now also supports a single `GUIDEPAW_SMOKE_PASSWORD` for both smoke accounts so the login secret can be kept identical while preserving distinct admin and regular usernames.
- Remaining roadmap bookkeeping was reconciled on 2026-05-08:
  - `feature_roadmap.coach_review_enabled` and `feature_roadmap.media_reviews_enabled` were promoted to `beta_enabled`.
  - The flag states were already enabled; this was a roadmap-history cleanup to match the shipped features.
- MVP training foundation roadmap rows were reconciled on 2026-05-08:
  - `feature_roadmap.goal_intake_enabled`, `training_progression_enabled`, `regression_engine_enabled`, and `habit_repair_enabled` were promoted to `beta_enabled`.
  - The corresponding feature flags were already enabled; this was final bookkeeping to clear the last stale roadmap rows.

### User roles and permissions

Added role system:

- Migration: `sql/migrations/pgsql/20260507_user_roles.sql`.
- Helper: `includes/roles.php`.
- Roles supported: `admin`, `moderator`, `user`.
- Legacy `is_admin=1` remains compatible and maps to `admin`.
- `includes/authz.php` now uses the role helper for admin and moderator authorization.
- Admin User Management lets an admin change another account's role at any time.
- Current admin account cannot be changed from the user management page.
- Built-in username `admin` is protected at the database level and application level:
  - Migration: `sql/migrations/pgsql/20260507_protect_admin_account.sql`.
  - Database trigger blocks downgrade, deactivation, username change, or delete of username `admin`.
  - Admin User Management shows protected status and blocks downgrade/deactivation/purge before submission.
- Admin/beta/system QA checklist sections are hidden from regular users.
- Regular users can still access regular site feature QA checks.
- User Role Permissions QA checklist section added and marked admin-only.

### Local QA crawler

Added local smoke-test crawler:

- `scripts/local_qa_crawler.php`
- `scripts/run_local_qa_crawler.sh`

The crawler can:

- Log in as admin.
- Optionally log in as a regular test user.
- Check core pages for application/fatal errors.
- Confirm built-in `admin` protection appears in Admin User Management.
- Confirm admin sees admin QA sections.
- Confirm regular users are blocked from admin pages and do not see admin-only QA sections.
- Optionally post a crawler summary to the QA checklist state endpoint.

### In-app Notification Center

Added user-facing in-app notifications:

- Migration: `sql/migrations/pgsql/20260507_in_app_notifications.sql`.
- Helper: `includes/notifications.php`.
- Page: `notifications.php`.
- Dashboard unread notification summary.
- Bottom nav Alerts now points to Notification Center.
- Menu section added for Notifications.
- Notification Center supports unread/read state, mark one read, mark all read, action routing, priority styling, dog association when available, category preferences, bulk delete, and persistent unread badges.
- Events wired into Notification Center: shared/co-op dog access granted, dog transfer request, transfer accepted/declined result, and found-dog report.

### Mobile viewport safety

Global responsive/viewport hardening added in `styles.css` and mobile menu styles:

- Prevents page-wide horizontal overflow.
- Wraps long text, email addresses, dog names, notes, links, errors, and code blocks.
- Keeps cards, forms, buttons, modals, dropdowns, menu panels, images, videos, canvases, and SVGs inside screen width.
- Makes tables scroll inside `.table-responsive` instead of forcing the whole page sideways.
- Improves bottom nav/menu grid behavior on narrow screens.
- Adds QA checklist coverage for mobile viewport safety.

### Beta access and notifications

- Beta invite email flow works on Render using ZeptoMail API.
- Beta invite email was received during testing.
- Invite link opened account creation successfully.
- Account creation succeeded.
- Login succeeded.
- Add Dog flow was tested and worked.
- Beta request admin email notifications were added.
- Telegram beta request notifications were added and tested.
- Admin notification test page exists.
- Admin notification test page includes SMS test support.

### SMS notifications

Added SMS implementation:

- `includes/sms_notifications.php` helper.
- Twilio-compatible SMS sending via REST API.
- Global SMS flag: `SMS_NOTIFY_ENABLED`.
- Provider flag: `SMS_PROVIDER=twilio`.
- Dog access SMS flag: `DOG_ACCESS_NOTIFY_SMS_ENABLED`.
- Found-dog SMS flag: `FOUND_DOG_NOTIFY_SMS_ENABLED`.
- Required Render secrets/placeholders: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM_NUMBER`, `ADMIN_NOTIFY_SMS_PHONE`.
- Handler Profile opt-in fields: `sms_phone`, `sms_notifications_enabled`.
- Migration added: `sql/migrations/pgsql/20260507_sms_notifications.sql`.
- SMS is opt-in per handler/user.
- SMS is wired for shared/co-op dog access granted, dog ownership transfer request, transfer accepted/declined result, and public found-dog report for opted-in owner/handler.
- SMS failures are designed to log and fail safely without blocking the underlying action.
- Telegram remains admin-only / admin-oriented.

### Render blueprint

- `render.yaml` was corrected so the web service is `starter` and the database remains `basic-256mb`.
- SMS/Twilio environment placeholders were added with `sync: false` for secrets.
- Past issue: bad indentation accidentally changed both web and database plans. Watch this carefully.

### Handler profile / required fields

Required Handler Profile fields are display name, public phone, and public email. Backup contact name and phone are optional.

Fixes completed:

- Handler Profile form no longer requires backup contacts.
- Safe placeholder behavior was added so legacy completion gate no longer blocks over missing backup contacts.
- Admin profile completion report exists at `admin_profile_completion.php`.
- Handler profile images are now stored as small cropped database data URIs to avoid Render ephemeral filesystem loss.
- SMS opt-in controls were added to Handler Profile.

### Manage Dogs / active dog behavior

Completed behavior:

- If a handler has only one dog, GuidePaw uses that dog as the active dog by default through existing backend active-dog logic.
- After at least one dog exists, the Add Another Dog form starts collapsed.
- Dog names on Manage Dogs were enlarged.
- Active dog row is highlighted.
- Active dog badge says `Active Profile`.
- Active dog note says GuidePaw is currently using that dog profile.
- Access and Audit buttons are linked from dog rows.
- Manage Dogs shows ownership/access badges.

### Dog Access / co-op / transfer / retirement

Major feature stack added:

- `dog_access.php` page.
- Co-op/shared handler access.
- Co-op/shared handler access now uses pending invites that the recipient accepts from Notifications.
- Viewer and Contributor/Editor permission choices.
- Temporary access end date.
- Revocation of shared access.
- Central helper now exposes owner-only checks for dog access and collaboration approval paths.
- Manage Dogs now splits active dogs from archived/retired dogs.
- Dog transfer requests now trigger admin email/Telegram alerts in addition to the recipient notification.
- Dog lifecycle statuses: Active, In training, Retired, Archived, Deceased, Transferred.
- Ownership transfer request workflow.
- Receiving handler can accept or decline transfer.
- Dog history stays attached to the dog profile.
- Previous owner can optionally remain as editor.
- Dog access email, SMS, and in-app notifications added.
- Dashboard pending-transfer alert added.
- Inactive dogs are cleared from active selection when marked retired/archived/deceased/transferred.
- Temporary shared access expiry helper added.
- Expired access cleanup runs from Dashboard, Manage Dogs, Dog Access & Status, and Dog Audit timeline.

### Dog Access audit trail

Added:

- Migration for `dog_access_audit_events`.
- Database triggers for dog lifecycle status changes, owner changes, shared/co-op handler access changes, and transfer request lifecycle.
- `dog_access_audit.php` timeline page.
- Audit button linked from Manage Dogs.

### Beta QA Checklist

Added:

- `beta_qa_checklist.php` page.
- `includes/beta_qa_checklist_items.php` comprehensive checklist data.
- `includes/beta_qa_checklist_extra_items.php` extension checklist data.
- `beta_qa_checklist_state.php` save/load endpoint.
- Migration for `beta_qa_checklist_state`.
- Menu link under Support: `Beta QA Checklist`.

Checklist behavior:

- Comprehensive in-app testing checklist.
- Progress bar.
- Search.
- Show all/open/completed filter.
- Browser fallback saving.
- Database-backed per-user checklist progress.
- Database-backed notes.
- Print support.
- Extension-file support for future checklist sections.
- Role-aware visibility: admin/beta/system checks are visible only to admins.
- SMS notification, Notification Center, Mobile viewport safety, User Role Permissions, and Local QA Crawler sections added.

### ADA Access Card

Completed changes from prior work:

- ADA wallet terminology was changed toward ADA Access Card.
- Button naming was adjusted.
- Lock-screen/focus behavior was improved so the access card content is visually emphasized.
- The detailed notes page now links back with ADA Access Card wording instead of the old wallet-card label.
- README, upgrade notes, current build notes, and the service worker cache list were normalized to the ADA Access Card name as well.
- The legacy fix notes file now uses ADA Access Card wording too.
- ADA Access Card now auto-detects the reviewed state law summary on load when geolocation access is already granted, and still offers manual/GPS overrides.
- The auto-detect path skips webdriver and Playwright automation sessions so crawls stay stable.

### Air travel access

- Added `air_travel_rights.php` for service-dog flight guidance.
- The Access menu now links to Air Travel Rights.
- The dashboard Today section now includes an Air Travel shortcut.
- The public dog profile now links to Air Travel Rights alongside the breed questionnaire.
- The dashboard Today actions now expose Health Docs, Vet Appointments, and Medications alongside the other care shortcuts.
- The QA crawler now directly verifies Health Docs, Vet Appointments, and Medications page loads as part of the care slice.
- Smart Alerts and Certification are now exposed in the dashboard Today actions and verified by the QA crawler.
- Training Program, Training Session Log, Training History, and Stats are now verified directly by the QA crawler, with Stats also exposed in Today actions.
- Admin Notification Test, Profile Completion, Found Dog Reports, API Tokens, and Backup Tools are now directly covered by the QA crawler.
- The page explains the DOT service-dog cabin baseline and notes that service dogs in training are not covered by the DOT air-travel service-animal definition.
- The ADA Access Card and Detailed ADA Notes pages link to the new flight guidance page.
- Beta QA and the local crawler now cover the new air-travel page.

### E2E cleanup

- Local E2E cleanup script exists:
  - `scripts/cleanup_e2e_data.php`
  - `scripts/run_e2e_cleanup.sh`
- Dry run successfully identified one E2E test dog and related logs/media.
- Cleanup with `--yes` removed matched E2E database rows.
- Later dry run showed zero matched E2E dogs/logs/media.
- User chose to keep the cleanup scripts untracked at that time.

## Current in-progress / needs verification

These items need beta testing after Render redeploy:

1. Confirm migrations have applied on Render.
2. Confirm `users.user_role` exists and existing admins still map to admin.
3. Confirm username `admin` is active/admin and cannot be downgraded, disabled, or deleted.
4. Confirm Admin User Management can change another user's role.
5. Confirm regular users cannot access admin-only pages.
6. Confirm beta QA checklist hides admin-only sections from regular users.
7. Run local QA crawler from the laptop.
8. Open `beta_qa_checklist.php` and verify account-backed save/load works.
9. Confirm Notification Center opens from bottom nav and menu.
10. Confirm notification create/read/open flows work.
11. Confirm mobile viewport safety on Dashboard, Menu, Dog Access, Handler Profile, Dog Profile, Feedback, Admin pages, and checklist.
12. Confirm Dog Access page opens from Manage Dogs.
13. Confirm Dog Audit page opens from Manage Dogs.
14. Confirm shared/co-op access grant works.
15. Confirm temporary access end date expires as expected.
16. Confirm transfer request appears on receiving handler dashboard and Notification Center.
17. Confirm transfer accept/decline updates ownership, audit trail, and notifications correctly.
18. Confirm email notifications fire for dog access/transfer actions.
19. Confirm SMS notifications fire for opted-in handler/user actions after Twilio env vars are configured.
20. Confirm Admin Notification Test can send SMS after Twilio env vars are configured.
21. Confirm retired/archived dogs do not remain active working profiles.
22. Confirm Handler Profile picture persists after login/refresh/redeploy.
23. Confirm backup contacts are still optional and not blocking login.
24. Confirm Handler Profile SMS opt-in saves correctly.
25. Confirm Render web/database plans stayed correct after all commits.
26. Confirm training goal intake archive/restore filters and goal history views work as intended.
27. Confirm public Breed Questionnaire loads, ranks results, and links from public dog profile / support menu.
28. Confirm Media Review loads for logs with attachments and saves camera/audio/training-value feedback.

## Known risks / areas to watch

- `admin_users.php` was compacted during role/protection work; retest export/deactivate/reactivate/role-change flows carefully.
- Hard purge was temporarily disabled in the compact admin user management page pending full retention review; use deactivate during beta.
- Large shared files like `includes/db_connect.php` are hard to safely patch through tool calls. Avoid broad rewrites unless necessary.
- Some pages still directly check `$_SESSION['is_admin']`; legacy compatibility should keep admin behavior working, but role-specific moderator access may need page-by-page refinement.
- Some dog access behavior may still depend on existing helper functions inside `db_connect.php`. Test viewer/editor permission boundaries carefully.
- If database migrations are not automatically run on Render, new tables/columns/triggers may not exist until migrations are applied.
- Dog audit triggers log database-level changes, but actor attribution may not always identify the exact acting user for all status changes because triggers only see table values. Page-level audit events may be added later for more precise actor logging.
- Handler profile images already saved before the database-image change may still point to missing filesystem paths. Re-upload after deploy if old profile images are broken.
- SMS requires external Twilio configuration and costs money per message. Keep it opt-in and test with limited recipients.
- Twilio trial accounts may only send to verified recipient numbers.
- The viewport safety CSS should reduce off-screen content, but individual pages with unusual inline styles may still need page-specific cleanup after testing.
- GPS/state-law service dog information should be treated carefully and sourced from authoritative legal references before release.

## Next recommended work

Recommended immediate order:

1. Pull latest GitHub changes to the laptop.
2. Let Render redeploy.
3. Confirm Render web/database plans stayed correct.
4. Confirm migrations apply on Render.
5. Run local QA crawler on the laptop against the local site.
6. Test user roles: admin, moderator, regular user.
7. Open and use `beta_qa_checklist.php` as admin and regular user to confirm role-aware QA visibility.
8. Run the Beta QA Checklist against Local QA Crawler, Role Permissions, Notification Center, viewport safety, Dog Access/Audit/Checklist/SMS features.
9. Fix any application errors or broken workflows found by the checklist.
10. Configure Twilio/SMS Render env vars later if SMS testing is desired.
11. Add page-level audit events if database-trigger audit detail is not specific enough.
12. Harden access checks in the central shared dog helper functions if viewer/editor boundaries are not strict enough.
13. Revisit state service-dog law detection for ADA Access Card with authoritative sources.

## Recently added files / important files

- `GUIDEPAW_PROJECT_HISTORY.md` — this file.
- `scripts/local_qa_crawler.php`
- `scripts/run_local_qa_crawler.sh`
- `includes/roles.php`
- `sql/migrations/pgsql/20260507_user_roles.sql`
- `sql/migrations/pgsql/20260507_protect_admin_account.sql`
- `notifications.php`
- `includes/notifications.php`
- `sql/migrations/pgsql/20260507_in_app_notifications.sql`
- `includes/sms_notifications.php`
- `sql/migrations/pgsql/20260507_sms_notifications.sql`
- `beta_qa_checklist.php`
- `beta_qa_checklist_state.php`
- `includes/beta_qa_checklist_items.php`
- `includes/beta_qa_checklist_extra_items.php`
- `sql/migrations/pgsql/20260506_beta_qa_checklist_state.sql`
- `dog_access.php`
- `dog_access_audit.php`
- `includes/dog_access_notifications.php`
- `includes/dog_access_dashboard.php`
- `includes/dog_access_expiry.php`
- `sql/migrations/pgsql/20260506_dog_access_status_transfer.sql`
- `sql/migrations/pgsql/20260506_dog_access_audit_trail.sql`
- `admin_profile_completion.php`

## Recent commit log notes

Recent feature commits include:

- Stabilize Playwright crawlers for current GuidePaw UI.
- Add shared brand and menu chrome to app pages.
- Show shared chrome on missing dog profile.
- Add responsive overflow audit and mobile safeguards.
- Stack app tables on mobile and tablet.
- Protect built-in admin account role.
- Protect built-in admin in user management.
- Add local QA crawler smoke test.
- Add local QA crawler runner.
- Add crawler and admin protection QA items.
- Add user role permission migration.
- Add user role authorization helper.
- Use role helper for admin authorization.
- Add admin user role management.
- Restrict admin QA sections by role.
- Add user role QA checklist items.
- Add in-app notifications migration.
- Add in-app notification center page.
- Show notification center on dashboard.
- Link notification center in app menu.
- Add global mobile viewport safety styles.
- Add in-app notification helper.
- Create in-app notifications for dog access events.
- Create in-app notifications for found dog reports.
- Add notification center and viewport QA checklist items.
- Add configurable SMS notification helper.
- Add handler SMS notification opt in.
- Send SMS for dog access notifications.
- Send SMS for found dog reports.
- Add SMS notification preference migration.
- Add SMS notification environment placeholders.
- Add admin SMS notification test.
- Add SMS notification QA checklist items.
- Expand training goal intake to show active/archived/all views and restore archived goals from the intake page.
- Add public Breed Questionnaire and link it from public dog profile and support menu.
- Add Media Review for attached training log photos, videos, and audio.

## Testing command reminders

Local project path:

```bash
cd /home/james/projects/gpb3/gpb3
```

Useful status checks:

```bash
git status
git log --oneline -12
grep -n "plan:" render.yaml
```

Run local QA crawler:

```bash
GUIDEPAW_ADMIN_PASS='your-admin-password' bash scripts/run_local_qa_crawler.sh
```

Run crawler with a regular test account too:

```bash
GUIDEPAW_ADMIN_PASS='your-admin-password' \
GUIDEPAW_REGULAR_USER='test' \
GUIDEPAW_REGULAR_PASS='test-password' \
bash scripts/run_local_qa_crawler.sh
```

Optional: write crawler summary to QA checklist state:

```bash
GUIDEPAW_ADMIN_PASS='your-admin-password' \
GUIDEPAW_MARK_CHECKLIST=yes \
bash scripts/run_local_qa_crawler.sh
```

E2E cleanup dry run example:

```bash
APP_ENV=local \
DB_HOST=localhost \
DB_PORT=5432 \
DB_DATABASE=guidepaw \
DB_USERNAME=guidepaw \
DB_PASSWORD='[use local secret from your environment/history, do not commit]' \
php scripts/cleanup_e2e_data.php --dry-run
```

Only run destructive cleanup after dry-run output is correct:

```bash
php scripts/cleanup_e2e_data.php --yes
```

2026-05-08 — Trainer marketplace rollout
- Commit(s): b54cb41 add trainer marketplace directory
- Files changed: trainer_marketplace.php, includes/trainer_marketplace.php, index.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, scripts/deploy_local.sh, sql/migrations/pgsql/20260508_trainer_marketplace_beta_enabled.sql
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: push the trainer directory, wait for Render, and rerun the beta crawler

2026-05-08 — Wearable integrations rollout
- Commit(s): 16ac664 add wearable integrations hub, 4e574e5 enable wearable integrations flag
- Files changed: wearable_integrations.php, includes/wearable_integrations.php, index.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, scripts/deploy_local.sh, sql/migrations/pgsql/20260508_wearable_integrations_beta_enabled.sql
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: none; beta crawler passed 35/35 after the flag fix

2026-05-08 — Behavior risk scoring rollout
- Commit(s): 1a82a5c add behavior risk scoring
- Files changed: behavior_risk_scoring.php, includes/behavior_risk_scoring.php, index.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, scripts/deploy_local.sh, sql/migrations/pgsql/20260508_behavior_risk_scoring_beta_enabled.sql
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: apply the migration, verify local/beta crawlers, then commit and push

2026-05-08 — Goal builder rollout
- Commit(s): b76e41b add goal builder workflow
- Files changed: goal_builder.php, includes/goal_builder.php, index.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, training_goal_intake.php, sql/migrations/pgsql/20260508_goal_builder_beta_enabled.sql
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: apply the goal-builder migration, verify local/beta crawlers, then commit and push

2026-05-08 — Community challenges rollout
- Commit(s): 2d4064f add community challenges workflow
- Files changed: community_challenges.php, includes/community_challenges.php, index.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, scripts/deploy_local.sh, sql/migrations/pgsql/20260508_community_challenges_beta_enabled.sql
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: none; beta crawler passed 29/29 after the deploy

2026-05-08 — Notification Center enhancement rollout
- Commit(s): 117e46c add notification center preferences
- Files changed: notifications.php, includes/mobile_nav.php, includes/notifications.php, includes/beta_qa_checklist_extra_items.php, scripts/local_qa_crawler.php
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: push to main, let Render deploy, and verify the beta crawler against the notification center changes

2026-05-08 — Candidate comparison rollout
- Commit(s): e9f3f8f add candidate comparison workflow
- Files changed: candidate_comparison.php, includes/candidate_comparison.php, includes/candidate_scoring.php, candidate_assessment.php, index.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, sql/migrations/pgsql/20260508_candidate_comparison_beta_enabled.sql
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: none; beta crawler passed 27/27 after the deploy

2026-05-08 — Core account and log page QA coverage
- Commit(s): 94e5bcc cover core account and log pages in qa crawler
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: none; local and beta crawlers passed 73/73 after the crawler session-cookie fix

2026-05-09 — Feedback-driven crawl prioritization
- Commit(s): 94d5e33 add feedback-driven crawl prioritization, a7e0470 wire feedback crawl into wrapper scripts
- Files changed: scripts/local_qa_crawler.php, scripts/run_local_qa_crawler.sh, scripts/run_admin_e2e.sh, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the feedback-prioritized crawler and wrapper pass-throughs are committed and pass 73/73 on both hosts
- Follow-up: the crawler now also prints the top feedback hotspots so the DB signal is easier to triage

2026-05-09 — Local crawler wrapper ergonomics
- Commit(s): pending
- Files changed: scripts/run_local_qa_crawler.sh, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the local crawler wrapper now self-loads the checked-in test env files and falls back to the standard smoke credentials

2026-05-09 — Feedback hotspot summaries
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the feedback-driven crawler now prints the top feedback hotspots plus status and priority counts

2026-05-09 — Admin purge safety cleanup
- Commit(s): pending
- Files changed: admin_users.php, includes/beta_qa_checklist_extra_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; hard purge is now explicitly disabled on the admin user page during beta and the checklist wording matches the safer flow

2026-05-09 — Profile completion report hardening
- Commit(s): pending
- Files changed: admin_profile_completion.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the admin profile-completion report now computes missing-field status in PHP instead of relying on a DB regex query

2026-05-09 — Dog profile guard and smoke check
- Commit(s): pending
- Files changed: dog_profile.php, scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; dog profile now fails closed if the row disappears after access checks, and the crawler reports the page as its own smoke target

2026-05-09 — Dog access guard and smoke check
- Commit(s): pending
- Files changed: dog_access.php, scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; dog access now fails closed if the selected dog row disappears after access checks, and the crawler reports the selected access page explicitly

2026-05-09 — Found-dog report smoke coverage
- Commit(s): a5eeb03 add found dog report smoke coverage
- Files changed: scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the crawler now submits a unique public found-dog report and verifies that the admin queue lists it, while also covering the found-dog test page

2026-05-09 — Local crawler mode switch
- Commit(s): 3f77f68 add local crawler mode switch
- Files changed: scripts/run_local_qa_crawler.sh, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the local QA wrapper now supports explicit `php` or `playwright` modes instead of only auto-falling back when the PHP crawler cannot run here

2026-05-09 — Login preflight for PHP crawler
- Commit(s): e2a4b8e harden crawler transport handling
- Files changed: scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the PHP crawler now loads `login.php` before posting credentials so it matches the browser flow more closely

2026-05-09 — Login failure diagnostics for crawler
- Commit(s): e2a4b8e harden crawler transport handling
- Files changed: scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the crawler now logs whether login failed at preflight, POST, or invalid-credential detection so the auth failure source is visible

2026-05-09 — Auto wrapper transport guard
- Commit(s): e2a4b8e harden crawler transport handling
- Files changed: scripts/run_local_qa_crawler.sh, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the local crawler wrapper now stops on sandbox socket failures instead of falling through to a second browser failure in the same shell

2026-05-09 — Browser crawler fallback
- Commit(s): 1cea147 add browser crawler fallback
- Files changed: scripts/run_local_qa_crawler.sh, tests/browser/guidepaw-auth-crawl.spec.js, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: none; the local QA wrapper now falls back to Playwright when PHP socket access is blocked, and the browser auth crawl includes the found-dog public report submit/admin-queue check

2026-05-09 — Auth and admin utility crawl expansion
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: rerun the crawler in an unsandboxed shell or live environment so the new beta token, registration, 2FA, beta-request, and admin utility probes can be verified end to end

2026-05-09 — Log and training utility crawl expansion
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: rerun the crawler in an unsandboxed shell or live environment so the new log edit, profile edit, habit repair, goal intake, history export, and backup export probes can be verified end to end

2026-05-09 — Log handler and redirect crawl expansion
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: rerun the crawler in an unsandboxed shell or live environment so the new manage-dogs, backup-import, update-log, and save-log guard probes can be verified end to end

2026-05-09 — Login, logout, and health crawl expansion
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: rerun the crawler in an unsandboxed shell or live environment so the new login, logout, healthz, csrf-token, and admin home probes can be verified end to end

2026-05-09 — Public profile and admin-login crawl repair
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, tests/browser/guidepaw-auth-crawl.spec.js, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the browser smoke suite so the found-dog public report flow and admin login helper stay stable with the regular-user session reset

2026-05-09 — Collaboration and public-profile crawl expansion
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: rerun the crawler so collaboration and public-profile/report probes are verified in the HTTP path

2026-05-09 — API crawl expansion
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: no
- Next recommended step: rerun the crawler so api/login, api/me, api/dogs, and api/logs are verified against the admin token path

2026-05-09 — API deploy sync fix
- Commit(s): pending
- Files changed: scripts/deploy_local.sh, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun deploy_local.sh so the api/ directory is copied to the live mirror before the next smoke pass

2026-05-09 — PHP crawler admin-session fallback
- Commit(s): pending
- Files changed: scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the wrapper; if the PHP crawler cannot keep the admin session alive it should fall back to Playwright instead of dumping false admin failures

2026-05-09 — Wrapper fallback noise reduction
- Commit(s): pending
- Files changed: scripts/run_local_qa_crawler.sh, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the wrapper and confirm the admin-session fallback now jumps straight to Playwright without printing the full PHP failure table

2026-05-09 — Password recovery UX alignment
- Commit(s): pending
- Files changed: reset_password.php, scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the crawler and confirm the reset-password page shows visibility toggles and password confirmation before saving

2026-05-09 — Camera access enhancement
- Commit(s): pending
- Files changed: log_entry.php, scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the crawler and confirm the detailed log form exposes direct photo/video capture controls on mobile

2026-05-09 — Tile layout cleanup
- Commit(s): 06e8211 soften dashboard tile layout
- Files changed: index.php, includes/mobile_nav.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the crawler and confirm the dashboard still exposes the common actions while the menu carries the broader quick-action set.

2026-05-09 — Menu and navigation enhancement
- Commit(s): 7083173 improve menu navigation search
- Files changed: includes/mobile_nav.php, training_program.php, scripts/local_qa_crawler.php, includes/beta_qa_checklist_items.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: rerun the crawler and confirm the menu search and training quick links are visible in beta

## Open decisions / ideas

- Which exact admin pages moderators should be allowed to access.
- Whether hard purge should remain disabled during beta or be reintroduced with stronger retention/export safeguards.
- Whether profile images should eventually move from DB data URI to object storage if image volume grows.
- Whether SMS should be limited to urgent alerts only after beta testing costs/usage.

2026-05-09 — Admin shell consistency pass
- Commit(s): pending
- Files changed: admin_feedback.php, admin_found_dog_reports.php, admin_notification_test.php, admin_audit_log.php, admin_beta_requests.php, admin_feature_roadmap.php, db_status.php, scripts/local_qa_crawler.php
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: commit the admin shell fix, then rerun the crawler and confirm the admin pages still load with the shared menu shell asserted

2026-05-09 — Admin shell completion pass
- Commit(s): pending
- Files changed: admin.php, admin_users.php, scripts/local_qa_crawler.php
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep the admin shell assertions in the crawler and watch for any pages that drift away from the shared banner/nav pattern

2026-05-09 — Dog Access state-model fix
- Commit(s): pending
- Files changed: includes/db_connect.php, dog_access.php, notifications.php, collaboration.php, includes/dog_access_notifications.php, includes/dog_access_expiry.php, includes/media_reviews.php, includes/video_reviews.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: rerun the mirror and beta crawler plus the dog-access invite/accept path to confirm the live schema no longer rejects handler writes

2026-05-09 — Found-dog flow verification and feedback cleanup
- Commit(s): pending
- Files changed: GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: no
- Next recommended step: keep working through the remaining beta feedback backlog if needed; the found-dog report path and related feedback rows #38, #39, #40, and #43 are now verified fixed on beta

2026-05-09 — Settings page cleanup
- Commit(s): pending
- Files changed: settings.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep Settings focused on account/security, verify the redundant handler profile shortcut stays removed, and push the cleanup to beta

2026-05-09 — Alerts and training suggestion links
- Commit(s): pending
- Files changed: alerts.php, training_program.php, includes/training_suggestion_links.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: yes
- Next recommended step: keep clickable Start module links aligned with the live alert and training suggestion copy, then push the slice and verify beta after Render catches up

2026-05-09 — Admin browser crawl expansion
- Commit(s): pending
- Files changed: tests/browser/guidepaw-admin-safe.spec.js
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep the admin-safe browser crawl aligned with the shared shell and add any newly important admin surfaces as they stabilize

2026-05-09 — Admin control panel landing
- Commit(s): pending
- Files changed: login.php, admin.php, scripts/local_qa_crawler.php, tests/browser/guidepaw-admin-safe.spec.js, tests/browser/guidepaw-auth-crawl.spec.js
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep admins landing on the admin control panel first and continue watching beta feedback for any other admin-first workflow gaps

2026-05-09 — QR tracking enhancement
- Commit(s): pending
- Files changed: includes/qr_tracking.php, qr_tracking.php, public_dog_profile.php, dog_profile.php, includes/mobile_nav.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, tests/browser/profile_qr_smoke.spec.js, sql/migrations/pgsql/20260509_qr_tracking.sql, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: yes
- Next recommended step: push the QR tracking slice, let beta deploy, then mark beta feedback #35 fixed after the live host confirms the new tracking page and scan counter

2026-05-09 — Found-dog maps link cleanup
- Commit(s): pending
- Files changed: includes/found_dog_reports.php, admin_found_dog_reports.php, scripts/local_qa_crawler.php, tests/browser/guidepaw-auth-crawl.spec.js, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: push the Maps-link update, then verify found-dog submissions carry either GPS coordinates or a real Google Maps search link instead of a dog-profile fallback

2026-05-09 — Edit profile hardening and crawler cleanup
- Commit(s): pending
- Files changed: edit_profile.php, scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: push the edit-profile escaping fix, then rerun beta smoke so the profile page and the training-log edit flow stay green together

2026-05-09 — Training log edit workflow
- Commit(s): pending
- Files changed: edit_log.php, update_log.php, view_logs.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, tests/browser/edit_log_smoke.spec.js, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep the log editor discoverable from Training History and verify the beta edit/save path stays stable across ownership and access roles

2026-05-09 — Feedback category picker enhancement
- Commit(s): 59cf331 improve feedback category picker
- Files changed: feedback.php, includes/beta_qa_checklist_items.php, scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: yes
- In-progress item changed: yes
- Next recommended step: push the feedback picker update, let beta deploy, and confirm the report form now exposes Bug, Feature request, and Enhancement as distinct choices

2026-05-09 — Crawler admin-session cleanup
- Commit(s): ea3a76c fix crawler admin session handling
- Files changed: scripts/local_qa_crawler.php, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep the admin session probe on its own cookie jar, then leave the public login/logout checks and admin-home check independent so beta smoke stays honest

2026-05-09 — Found-dog location link fallback
- Commit(s): d55c4ab fix found dog location links
- Files changed: includes/found_dog_reports.php, admin_found_dog_reports.php, tests/browser/guidepaw-auth-crawl.spec.js, GUIDEPAW_PROJECT_HISTORY.md
- New checklist items added: no
- In-progress item changed: yes
- Next recommended step: keep the found-dog notifications using Google Maps only for real map-like locations, and fall back to the dog profile for synthetic or non-map text so the notification links stay usable

## Update log template

When updating this file, add a short note like:

```text
YYYY-MM-DD — Summary of work completed
- Commit(s): abc1234 message, def5678 message
- Files changed: file1.php, file2.php
- New checklist items added: yes/no
- In-progress item changed: yes/no
- Next recommended step: ...
```
