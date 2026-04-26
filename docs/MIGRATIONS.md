# Migration System

This package uses PostgreSQL migrations only.

Controls:
- `db_status.php` shows driver, schema version, and pending migration files.
- To allow in-app migration execution, set `APP_ALLOW_DB_MIGRATIONS=true`.

Folder:
- `sql/migrations/pgsql`
