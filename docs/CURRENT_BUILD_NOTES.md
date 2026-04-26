# Current Build Notes

This package reflects the latest working changes captured during sandbox stabilization.

## Included code fixes
- PostgreSQL schema file corrected to use `SMALLINT`
- `view_logs.php` fixed for PostgreSQL string quoting
- `stats.php` fixed for PostgreSQL empty-string comparison
- `quick_log.php` updated with GPS capture and city/state display
- bottom nav + More flyout kept, floating menu button removed
- `settings.php`, `backup.php`, and other shared-nav pages load `styles.css`
- duplicate dashboard ADA tile removed
- ADA Wallet Card page reworked

## Still worth validating on a fresh machine
- 2FA screens
- backup restore import path
- collaboration invite flow
- document/media upload path
- Nginx/PHP-FPM integration after PHP built-in server passes
