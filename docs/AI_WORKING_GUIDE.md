# GuidePaw AI Working Guide

Last updated: 2026-05-07

This guide is for ChatGPT or any AI assistant helping with GuidePaw. Read this before editing code, proposing architecture changes, or troubleshooting deployment issues.

## Source of truth

- Current code source: GitHub repository `jphutching/GuidePaw`.
- Local checkout used by James: `/home/james/projects/gpb3/gpb3`.
- Local web root: `/var/www/guidepaw`.
- Local site: `https://10.147.18.184`.
- Beta site: `https://beta.guidepaw.app`.
- Landing page: `https://guidepaw.app`.
- Deployment target: Render.
- Database: PostgreSQL only.
- Email provider: Zoho ZeptoMail API.
- SMS provider path: Twilio-compatible opt-in support.

## Do not backtrack

- Do not overwrite the current repo with historical chat zips.
- Historical archives are reference-only.
- The current GitHub repo and laptop checkout are the active source.
- Keep PostgreSQL as the only supported runtime database.
- Do not reintroduce MySQL or MariaDB runtime paths.

## Current stable checkpoint

The latest verified stable checkpoint is:

- Branch: `main`.
- Commit: `ce64a57`.
- Local repo matched `origin/main`.
- Working tree was clean.
- Local web root was refreshed with `rsync`.
- Dual local/beta crawler passed with `failed_pages=0`, `comparison_diffs=0`, and `admin_paths=49/49`.

## Supported architecture

GuidePaw currently uses:

- PHP app code.
- PostgreSQL database.
- Render Docker web service.
- Render PostgreSQL database.
- Local Ubuntu/Nginx/PHP-FPM/PostgreSQL development environment.
- Apache inside the Render Docker container.
- Direct PHP page scripts plus shared helpers in `includes/`.
- SQL migrations under `sql/migrations/pgsql/`.
- QA crawler scripts under `scripts/`.

This architecture is working. Do not recommend a full rewrite unless James explicitly asks for a long-term migration plan.

## Render plan rules

Before committing or changing `render.yaml`, verify:

- Web service plan is `starter`.
- Database plan is `basic-256mb`.
- Render branch is `main`.
- Domain includes `beta.guidepaw.app`.
- Real secrets are not committed.

Use:

```bash
grep -n "plan:" render.yaml
```

Expected important lines:

```text
plan: starter
plan: basic-256mb
```

## Secrets rule

Never commit:

- Passwords.
- API keys.
- ZeptoMail tokens.
- Telegram bot tokens.
- Twilio tokens.
- Database passwords.
- Private phone numbers.
- Private email credentials.

Render secret placeholders may use `sync: false`.

## Safe change rules

Prefer small changes.

Before changing app behavior:

1. Check current repo status.
2. Identify the smallest file set needed.
3. Avoid broad rewrites.
4. Preserve beta behavior unless the task requires changing it.
5. Add or update QA checklist items when behavior changes.
6. Run the crawler after changes.
7. Update `GUIDEPAW_PROJECT_HISTORY.md` when status or next steps change.

## High-risk files and areas

Be careful with:

- `includes/db_connect.php`
- Authentication/session logic.
- Role/admin authorization logic.
- Dog access ownership and sharing helpers.
- Render blueprint indentation.
- SQL migrations and triggers.
- Notification dispatch helpers.
- Any page that performs destructive actions.

Avoid mass-formatting or broad mechanical rewrites of large PHP files.

## Preferred cleanup strategy

For easier AI handling, prefer documentation and small helper extraction over refactoring stable behavior.

Good cleanup:

- Add architecture maps.
- Add feature maps.
- Add testing command docs.
- Add safe change rules.
- Add small wrappers or helper functions only when needed.

Risky cleanup:

- Moving many page scripts at once.
- Splitting major shared files without tests.
- Renaming routes.
- Replacing auth/session handling.
- Converting to a framework during beta.

## Required verification command

After app behavior changes, run the dual-site crawler from the laptop repo root:

```bash
cd /home/james/projects/gpb3/gpb3

GUIDEPAW_LOCAL_URL='https://10.147.18.184' \
GUIDEPAW_BETA_URL='https://beta.guidepaw.app' \
GUIDEPAW_LOCAL_ADMIN_USER='admin' \
GUIDEPAW_LOCAL_ADMIN_PASS='admin123' \
GUIDEPAW_BETA_ADMIN_USER='admin' \
GUIDEPAW_BETA_ADMIN_PASS='admin123' \
GUIDEPAW_LOCAL_REGULAR_USER='test acct' \
GUIDEPAW_LOCAL_REGULAR_PASS='test123' \
GUIDEPAW_BETA_REGULAR_USER='test acct' \
GUIDEPAW_BETA_REGULAR_PASS='test123' \
php scripts/compare_site_crawler.php
```

Expected success:

```text
failed_pages=0; comparison_diffs=0
```

## Local web root refresh

After pulling verified code to the laptop, refresh the local web root with:

```bash
cd /home/james/projects/gpb3/gpb3

sudo rsync -a \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.env' \
  --exclude='node_modules/' \
  /home/james/projects/gpb3/gpb3/ \
  /var/www/guidepaw/

sudo chown -R www-data:www-data /var/www/guidepaw
sudo systemctl restart php8.5-fpm
sudo systemctl reload nginx
```

## Main docs to read

- `GUIDEPAW_PROJECT_HISTORY.md`
- `README.md`
- `docs/ARCHITECTURE_MAP.md`
- `docs/FEATURE_MAP.md`
- `docs/TESTING_COMMANDS.md`
- `docs/SAFE_CHANGE_RULES.md`

## Current product direction

GuidePaw is moving from build stabilization into beta operations:

1. Keep the current architecture stable.
2. Bring in beta users carefully.
3. Use the QA checklist and crawler after changes.
4. Fix user-facing friction before adding large new systems.
5. Decide free vs paid features after beta feedback.

## Preferred answer style for AI assistants

When helping James:

- Be practical.
- Give exact commands.
- Avoid vague architecture rewrites.
- Keep the stable beta working.
- Do not ask for repeated information already known from this guide.
- Call out risks before destructive or broad changes.
- Favor small commits with clear rollback paths.
