# GuidePaw Project History / Handoff

Last updated: 2026-05-07

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
- SMS notifications are now an opt-in handler/user alert layer using configurable Twilio support. Twilio credentials and phone numbers must never be committed.

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

### User roles and permissions

Added role system:

- Migration: `sql/migrations/pgsql/20260507_user_roles.sql`.
- Helper: `includes/roles.php`.
- Roles supported:
  - `admin`
  - `moderator`
  - `user`
- Legacy `is_admin=1` remains compatible and maps to `admin`.
- `includes/authz.php` now uses the role helper for admin and moderator authorization.
- Admin User Management now lets an admin change another account's role at any time.
- Current admin account cannot be changed from the user management page.
- Admin/beta/system QA checklist sections are hidden from regular users.
- Regular users can still access regular site feature QA checks.
- User Role Permissions QA checklist section added and marked admin-only.

### In-app Notification Center

Added user-facing in-app notifications:

- Migration: `sql/migrations/pgsql/20260507_in_app_notifications.sql`.
- Helper: `includes/notifications.php`.
- Page: `notifications.php`.
- Dashboard unread notification summary.
- Bottom nav Alerts now points to Notification Center.
- Menu section added for Notifications.
- Notification Center supports unread/read state, mark one read, mark all read, action routing, priority styling, and dog association when available.
- Events wired into Notification Center:
  - shared/co-op dog access granted
  - dog transfer request
  - transfer accepted/declined result
  - found-dog report

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
- Admin notification test page now includes SMS test support.

### SMS notifications

Added SMS implementation:

- `includes/sms_notifications.php` helper.
- Twilio-compatible SMS sending via REST API.
- Global SMS flag: `SMS_NOTIFY_ENABLED`.
- Provider flag: `SMS_PROVIDER=twilio`.
- Dog access SMS flag: `DOG_ACCESS_NOTIFY_SMS_ENABLED`.
- Found-dog SMS flag: `FOUND_DOG_NOTIFY_SMS_ENABLED`.
- Required Render secrets/placeholders:
  - `TWILIO_ACCOUNT_SID`
  - `TWILIO_AUTH_TOKEN`
  - `TWILIO_FROM_NUMBER`
  - `ADMIN_NOTIFY_SMS_PHONE`
- Handler Profile opt-in fields:
  - `sms_phone`
  - `sms_notifications_enabled`
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
- Viewer and Contributor/Editor permission choices.
- Temporary access end date.
- Revocation of shared access.
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
- SMS notification, Notification Center, Mobile viewport safety, and User Role Permissions QA sections added.

### ADA Access Card

Completed changes from prior work:

- ADA wallet terminology was changed toward ADA Access Card.
- Button naming was adjusted.
- Lock-screen/focus behavior was improved so the access card content is visually emphasized.
- Future idea: GPS/state-law detection for service dog law information. This is not fully confirmed as complete and should remain a roadmap/testing item.

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
3. Confirm Admin User Management can change another user's role.
4. Confirm regular users cannot access admin-only pages.
5. Confirm beta QA checklist hides admin-only sections from regular users.
6. Open `beta_qa_checklist.php` and verify account-backed save/load works.
7. Confirm Notification Center opens from bottom nav and menu.
8. Confirm notification create/read/open flows work.
9. Confirm mobile viewport safety on Dashboard, Menu, Dog Access, Handler Profile, Dog Profile, Feedback, Admin pages, and checklist.
10. Confirm Dog Access page opens from Manage Dogs.
11. Confirm Dog Audit page opens from Manage Dogs.
12. Confirm shared/co-op access grant works.
13. Confirm temporary access end date expires as expected.
14. Confirm transfer request appears on receiving handler dashboard and Notification Center.
15. Confirm transfer accept/decline updates ownership, audit trail, and notifications correctly.
16. Confirm email notifications fire for dog access/transfer actions.
17. Confirm SMS notifications fire for opted-in handler/user actions after Twilio env vars are configured.
18. Confirm Admin Notification Test can send SMS after Twilio env vars are configured.
19. Confirm retired/archived dogs do not remain active working profiles.
20. Confirm Handler Profile picture persists after login/refresh/redeploy.
21. Confirm backup contacts are still optional and not blocking login.
22. Confirm Handler Profile SMS opt-in saves correctly.
23. Confirm Render web/database plans stayed correct after all commits.

## Known risks / areas to watch

- Large shared files like `includes/db_connect.php` are hard to safely patch through tool calls. Avoid broad rewrites unless necessary.
- Some pages still directly check `$_SESSION['is_admin']`; legacy compatibility should keep admin behavior working, but role-specific moderator access may need page-by-page refinement.
- Some dog access behavior may still depend on existing helper functions inside `db_connect.php`. Test viewer/editor permission boundaries carefully.
- If database migrations are not automatically run on Render, new tables/columns may not exist until migrations are applied.
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
5. Test user roles: admin, moderator, regular user.
6. Open and use `beta_qa_checklist.php` as admin and regular user to confirm role-aware QA visibility.
7. Run the Beta QA Checklist against Role Permissions, Notification Center, viewport safety, Dog Access/Audit/Checklist/SMS features.
8. Fix any application errors or broken workflows found by the checklist.
9. Configure Twilio/SMS Render env vars later if SMS testing is desired.
10. Add page-level audit events if database-trigger audit detail is not specific enough.
11. Harden access checks in the central shared dog helper functions if viewer/editor boundaries are not strict enough.
12. Revisit state service-dog law detection for ADA Access Card with authoritative sources.

## Recently added files / important files

- `GUIDEPAW_PROJECT_HISTORY.md` — this file.
- `includes/roles.php`
- `sql/migrations/pgsql/20260507_user_roles.sql`
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

## Open decisions / ideas

- Which exact admin pages moderators should be allowed to access.
- Whether Dog Access should use invite-only pending acceptance for shared co-op access, instead of immediately accepted shared access.
- Whether to add stronger owner-only restrictions inside the central helper functions.
- Whether transfer requests should also trigger Telegram/admin notifications.
- Whether retired/archived dogs should have a dedicated archive list rather than appearing in normal Manage Dogs.
- Whether ADA Access Card should auto-detect state and show state-specific service dog law summaries.
- Whether profile images should eventually move from DB data URI to object storage if image volume grows.
- Whether SMS should be limited to urgent alerts only after beta testing costs/usage.
- Whether Notification Center should later add user preferences, categories, bulk delete, or persistent notification badges.

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
