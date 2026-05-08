# GuidePaw Architecture Map

Last updated: 2026-05-07

This file maps the current GuidePaw application so future coding sessions can find the right files quickly.

## Runtime shape

GuidePaw is a PHP/PostgreSQL web app.

- Local development: Ubuntu, Nginx, PHP-FPM, PostgreSQL.
- Beta deployment: Render Docker web service plus Render PostgreSQL.
- Docker runtime: `php:8.3-apache`.
- Database runtime: PostgreSQL only.
- App style: direct PHP pages with shared helpers in `includes/`.

## Key root files

- `README.md` — current package overview.
- `GUIDEPAW_PROJECT_HISTORY.md` — durable project memory and current status.
- `render.yaml` — Render blueprint.
- `Dockerfile` — Render Docker image definition.
- `styles.css` — global app styling and mobile viewport safety.
- `manifest.json` — PWA/web app manifest.

## Deployment files

- `Dockerfile`
- `docker/apache.conf`
- `docker/php.ini`
- `docker/entrypoint.sh`
- `render.yaml`

Render should deploy from GitHub `main` to `https://beta.guidepaw.app`.

## Database files

- `sql/migrations/pgsql/` — PostgreSQL migrations.
- `includes/db_connect.php` — core DB connection and shared database helpers.

High-risk note: avoid broad rewrites of `includes/db_connect.php`. It may contain shared compatibility and business helpers used across many pages.

## Auth, roles, and permissions

Important files:

- `includes/authz.php`
- `includes/roles.php`
- `admin_users.php`
- `sql/migrations/pgsql/20260507_user_roles.sql`
- `sql/migrations/pgsql/20260507_protect_admin_account.sql`

Current roles:

- `admin`
- `moderator`
- `user`

Legacy `is_admin=1` remains compatible and maps to admin behavior.

## Beta access and admin flow

Important files may include:

- `beta_token.php`
- `register.php`
- `admin_beta_requests.php`
- `admin_notification_test.php`
- `includes/smtp_mailer.php`

Email provider path: Zoho ZeptoMail API.

## Dog profile and management

Important files:

- `dogs.php`
- `manage_dogs.php`
- `dog_profile.php`
- `quick_log.php`
- `view_logs.php`
- `stats.php`

Current behavior:

- A single dog can become the active dog automatically.
- Manage Dogs shows active dog state, dog access links, and audit links.

## Dog access, co-op, transfer, and audit

Important files:

- `dog_access.php`
- `dog_access_audit.php`
- `includes/dog_access_notifications.php`
- `includes/dog_access_dashboard.php`
- `includes/dog_access_expiry.php`
- `sql/migrations/pgsql/20260506_dog_access_status_transfer.sql`
- `sql/migrations/pgsql/20260506_dog_access_audit_trail.sql`

Major behavior:

- Shared/co-op dog access.
- Viewer and contributor/editor access.
- Temporary access expiry.
- Ownership transfer workflow.
- Dog lifecycle statuses.
- Audit timeline.

## Notifications

Important files:

- `notifications.php`
- `includes/notifications.php`
- `includes/dog_access_notifications.php`
- `includes/sms_notifications.php`
- `sql/migrations/pgsql/20260507_in_app_notifications.sql`
- `sql/migrations/pgsql/20260507_sms_notifications.sql`

Notification channels:

- In-app Notification Center.
- Email through ZeptoMail.
- Optional/admin-oriented Telegram.
- Optional opt-in SMS through Twilio-compatible support.

## Handler profile

Important files:

- `handler_profile.php`
- `admin_profile_completion.php`

Current required fields:

- Display name.
- Public phone.
- Public email.

Backup contact fields are optional.

Handler profile images are stored as small cropped database data URIs to avoid Render ephemeral filesystem loss.

## Training and health features

Important pages include:

- `training_program.php`
- `candidate_assessment.php`
- `training_goal_intake.php`
- `habit_repair.php`
- `training_session_log.php`
- `training_history.php`
- `dog_health.php`
- `appointments.php`
- `medications.php`

## ADA / access information

Important pages:

- `ada_access_card.php`
- `service_dog_rights.php`
- `certification.php`

Current direction: ADA Access Card terminology and service-dog support information.

Do not add GPS/state-law legal summaries without checking authoritative legal sources.

## QA and test helpers

Important files:

- `beta_qa_checklist.php`
- `beta_qa_checklist_state.php`
- `includes/beta_qa_checklist_items.php`
- `includes/beta_qa_checklist_extra_items.php`
- `scripts/local_qa_crawler.php`
- `scripts/run_local_qa_crawler.sh`
- `scripts/compare_site_crawler.php`

The dual crawler is the main current automated safety check.

## Common change locations

Use this map before editing:

- Styling/mobile issue: start with `styles.css`, then page-specific markup.
- Login/permissions issue: check `includes/authz.php`, `includes/roles.php`, and the target page.
- Dog access issue: check `dog_access.php`, related `includes/dog_access_*`, and migrations.
- Notification issue: check `includes/notifications.php`, `notifications.php`, and event-specific sender helpers.
- SMS issue: check `includes/sms_notifications.php`, Handler Profile opt-in, and Render env vars.
- Beta invite issue: check beta request/admin pages and `includes/smtp_mailer.php`.
- Render deploy issue: check `render.yaml`, `Dockerfile`, and `docker/entrypoint.sh`.

## Refactor guidance

Prefer extracting small helpers and adding docs/tests over moving many files.

Do not reorganize routes or folders during beta unless a specific problem requires it.
