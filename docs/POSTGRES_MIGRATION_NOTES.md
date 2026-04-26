# Render PostgreSQL migration notes

This package is intended for a PostgreSQL-backed Render beta.

What is ready:
- Docker web service packaging with `pdo_pgsql`
- Render blueprint with managed Postgres wiring
- PostgreSQL schema in `latest postgres sql.txt`
- PostgreSQL-safe insert/upsert helpers in the PHP runtime
- TOTP-backed 2FA for web login and API token issuance

Compatibility pass completed in this packaged PostgreSQL build:
- replaced legacy SQL interval/date arithmetic queries with PostgreSQL syntax where required
- replaced legacy SQL upserts with PostgreSQL `ON CONFLICT`
- removed runtime reliance on `JSON_TABLE` for stats aggregation
- replaced `lastInsertId`-dependent insert flows with `RETURNING id` where needed

Still recommended before public launch:
- run end-to-end tests on backup/restore, collaboration, stats, and alerts against a live Postgres instance
- verify cold-start deploys with a clean Render database
- move persistent uploads to object storage when beta traffic grows
