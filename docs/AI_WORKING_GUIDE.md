# GuidePaw AI Working Guide

Use this file at the start of every GuidePaw coding or planning session.

## Current source of truth

- GitHub repository: `jphutching/GuidePaw`
- Local checkout: `/home/james/projects/gpb3/gpb3`
- Local web root: `/var/www/guidepaw`
- Local site: `https://10.147.18.184`
- Beta site: `https://beta.guidepaw.app`
- Landing page: `https://guidepaw.app`
- Deployment target: Render
- Database: PostgreSQL
- Runtime: PHP app deployed by Docker/Apache on Render

## Current operating mode

GuidePaw is in beta-stabilization mode.

The app is not in a rewrite phase. Prefer safe, small, reviewable changes that preserve the current working beta.

## Required first checks

Before changing code, check:

```bash
cd /home/james/projects/gpb3/gpb3

git status -sb
git --no-pager log --oneline --decorate -12
grep -n "plan:" render.yaml
```

Expected Render plan intent:

- Web service: `plan: starter`
- PostgreSQL database: `plan: basic-256mb`

## Safe working rules

1. Do not overwrite the current repo with historical chat zips or old exports.
2. Do not commit credentials, API keys, phone numbers, bot tokens, database passwords, or private service tokens.
3. Prefer small commits with clear messages.
4. Do not make broad rewrites of large shared files unless there is a clear bug and a rollback path.
5. If a feature changes behavior, add or update QA checklist coverage.
6. After meaningful changes, update `GUIDEPAW_PROJECT_HISTORY.md`.
7. Run the dual-site crawler before considering the work complete.

## High-risk files

Treat these as careful-edit files:

- `includes/db_connect.php`
- `includes/authz.php`
- `includes/roles.php`
- `admin_users.php`
- `dog_access.php`
- `dog_access_audit.php`
- `render.yaml`
- `docker/entrypoint.sh`
- SQL migrations under `sql/migrations/pgsql/`

Reason: these files can affect login, authorization, active dog behavior, shared dog access, deployment, or database compatibility.

## Preferred change style

When possible, add new behavior in small helper files under `includes/` instead of expanding very large page files.

Good examples:

- Notification behavior in `includes/notifications.php`
- SMS behavior in `includes/sms_notifications.php`
- Dog access notification behavior in `includes/dog_access_notifications.php`
- Role behavior in `includes/roles.php`

## Testing gate

A change is not ready until local/beta comparison passes or any failure is clearly explained.

Primary test:

```bash
php scripts/compare_site_crawler.php
```

The crawler reads configuration from command-line flags or `GUIDEPAW_*` environment variables.

Expected healthy summary:

```text
failed_pages=0; comparison_diffs=0
```

## Current product direction

GuidePaw is becoming a service-dog and working-dog management platform. Core value areas are:

- Dog profile management
- Handler profile
- Quick logs
- Training support
- Health, appointments, and medications
- ADA Access Card
- Dog sharing/co-op access
- Dog ownership transfer
- Dog audit trail
- Found-dog reporting
- Notification Center

## Current architecture choice

Continue with the current PHP/PostgreSQL/Render architecture during beta.

Do not start a Laravel, React, Node, or native-mobile rewrite unless there is a separate migration plan and a strong product reason after beta feedback.

## Best next improvements

Prioritize maintainability helpers over large rewrites:

1. Documentation for AI handoffs.
2. More centralized helper functions.
3. Cleaner testing commands.
4. Migration status checks.
5. Gradual extraction of repeated logic.
6. GitHub Actions or other CI checks later.

## Session handoff checklist

At the end of a work session, record:

- What changed.
- Commit SHA or branch name.
- Files changed.
- Tests run.
- Crawler result.
- Known issues.
- Next recommended step.
