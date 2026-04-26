# Release Checklist

- [ ] Back up current app folder
- [ ] Export JSON or full package backup
- [ ] Confirm database credentials in `includes/db_connect.php`
- [ ] Copy new files into web root
- [ ] Confirm `uploads/`, `uploads/images/`, and `uploads/videos/` exist
- [ ] Run `sql/migrations/v8_release_upgrade.sql` on existing databases
- [ ] Open app while online to refresh service worker cache
- [ ] Test login, log entry, stats, backup, and ADA card pages
