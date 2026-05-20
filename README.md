# GuidePaw PostgreSQL Package

This is the current PostgreSQL-only GuidePaw package prepared from the latest working build we stabilized in sandbox.

What is included:
- the current PHP app code
- PostgreSQL schema and PostgreSQL-only runtime defaults
- mobile bottom navigation + flyout menu
- Quick Log GPS capture with city/state display
- fixed Training History PostgreSQL query
- fixed Reports PostgreSQL empty-string query
- polished ADA Access Card page
- updated guides for Ubuntu, Render, and phone sandbox

## Current known-good areas
- first-run registration / onboarding
- dashboard
- dogs list and dog profile save
- quick log save
- training history
- reports
- backup page
- ADA Access Card
- PostgreSQL connection path

## Recommended environments
- Ubuntu laptop sandbox: PHP + PostgreSQL first, then Nginx/PHP-FPM
- Render beta: Docker web service + Render PostgreSQL + persistent disk
- Phone sandbox only if needed: Termux host PostgreSQL + Debian proot PHP

## Main docs
- `docs/INSTALL_UBUNTU.md`
- `docs/INSTALL_TERMUX_DEBIAN.md`
- `docs/RENDER_DEPLOY.md`
- `docs/CURRENT_BUILD_NOTES.md`
- `docs/ANDROID_API_CONTRACT.md`

## Important
This package is PostgreSQL-only. Do not reintroduce MySQL/MariaDB files or runtime paths.
