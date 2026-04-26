# GuidePaw Render Beta Deploy

## Recommended Render shape
- one Docker web service
- one Render PostgreSQL instance
- one persistent disk mounted at `/data`

## Before deploy
1. Push this project to GitHub.
2. Keep `render.yaml` in the repo root.
3. Create PostgreSQL first.
4. Use persistent storage for uploads and backups.

## Required environment
- `APP_ENV=production`
- `APP_URL=https://your-service.onrender.com`
- `APP_STORAGE_PATH=/data`
- `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`
- or `DATABASE_URL` if you map it in your runtime

## Initialize the database
Use the PostgreSQL schema file:
```bash
psql "$DATABASE_URL" -f "latest postgres sql.txt"
```

## Verify after first deploy
- health check page responds
- register works
- dogs page works
- quick log works
- history and reports work
- backup page works
- uploads survive restart because storage is mounted under `/data`

## Rule
Keep PostgreSQL as the only database engine for sandbox, laptop, and Render.
