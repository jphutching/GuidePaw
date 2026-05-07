# GuidePaw Architecture Map

This map is for orientation. It does not replace the code.

## Application shape

GuidePaw is currently a PHP/PostgreSQL web app with page-level PHP scripts, shared helpers in `includes/`, SQL migrations, Docker deployment, and Render beta hosting.

## Main runtime pieces

- `Dockerfile` — Render container build using PHP/Apache.
- `docker/apache.conf` — Apache virtual host config.
- `docker/php.ini` — PHP runtime config.
- `docker/entrypoint.sh` — container startup and migration/bootstrap behavior.
- `render.yaml` — Render blueprint for beta web service and PostgreSQL database.

## Database

- Database engine: PostgreSQL.
- Migration folder: `sql/migrations/pgsql/`.
- GuidePaw should remain PostgreSQL-only.
- Do not reintroduce MySQL/MariaDB runtime paths.

## Shared includes

Common shared code lives under `includes/`.

Important helpers include:

- `includes/db_connect.php` — database connection and important shared database/application helpers.
- `includes/authz.php` — authorization helpers.
- `includes/roles.php` — user role helpers.
- `includes/feature_flags.php` — feature flag/runtime setting helpers.
- `includes/smtp_mailer.php` — ZeptoMail/email behavior.
- `includes/notifications.php` — in-app notification helper.
- `includes/sms_notifications.php` — SMS/Twilio-compatible helper.
- `includes/dog_access_notifications.php` — dog access notification behavior.
- `includes/dog_access_dashboard.php` — dashboard dog access/transfer helpers.
- `includes/dog_access_expiry.php` — temporary shared-access expiry cleanup.

## User and authorization areas

- Login/session behavior is page-script based.
- Roles supported: `admin`, `moderator`, `user`.
- Legacy admin support through `is_admin=1` remains important.
- Built-in username `admin` is protected at database and application layers.

Important files:

- `includes/roles.php`
- `includes/authz.php`
- `admin_users.php`
- `sql/migrations/pgsql/20260507_user_roles.sql`
- `sql/migrations/pgsql/20260507_protect_admin_account.sql`

## Dog profile and dog management

Important areas:

- `dogs.php`
- `manage_dogs.php`
- `dog_profile.php`
- Active dog selection and single-dog default behavior are important user experience flows.

## Dog access, co-op, transfer, and audit

Important files:

- `dog_access.php`
- `dog_access_audit.php`
- `includes/dog_access_notifications.php`
- `includes/dog_access_dashboard.php`
- `includes/dog_access_expiry.php`
- `sql/migrations/pgsql/20260506_dog_access_status_transfer.sql`
- `sql/migrations/pgsql/20260506_dog_access_audit_trail.sql`

Core concepts:

- Shared/co-op dog access.
- Viewer and contributor/editor permissions.
- Temporary access expiration.
- Ownership transfer request flow.
- Dog lifecycle statuses.
- Audit timeline.

## Notifications

Notification channels:

- In-app Notification Center.
- ZeptoMail email.
- Optional/admin-oriented Telegram.
- Optional opt-in SMS through Twilio-compatible config.

Important files:

- `notifications.php`
- `includes/notifications.php`
- `includes/sms_notifications.php`
- `admin_notification_test.php`
- `sql/migrations/pgsql/20260507_in_app_notifications.sql`
- `sql/migrations/pgsql/20260507_sms_notifications.sql`

## Beta QA and testing

Important files:

- `beta_qa_checklist.php`
- `beta_qa_checklist_state.php`
- `includes/beta_qa_checklist_items.php`
- `includes/beta_qa_checklist_extra_items.php`
- `scripts/local_qa_crawler.php`
- `scripts/run_local_qa_crawler.sh`
- `scripts/compare_site_crawler.php`

Primary testing concept:

- Local crawler checks the local site.
- Dual-site crawler compares local and beta behavior.
- Beta QA Checklist tracks manual and workflow testing.

## Frontend structure

- The app currently uses server-rendered PHP pages.
- Global styling is in `styles.css`.
- Mobile bottom navigation and menu behavior are part of the current app experience.
- Viewport safety has been hardened globally but individual pages may still need page-specific cleanup.

## Refactor guidance

Do not perform a broad reorganization during beta.

Safe improvements:

- Add focused helper functions.
- Move repeated business logic into new `includes/` files.
- Add documentation.
- Add tests/crawlers.
- Add migration/status checks.

Risky improvements:

- Renaming page scripts.
- Moving many files at once.
- Rewriting `includes/db_connect.php`.
- Replacing auth/session flow.
- Replacing Render deployment config.
- Changing database schema for cosmetic reasons only.
