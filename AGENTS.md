# Repository Guidelines

## Project Structure & Module Organization

GuidePaw is a PHP/PostgreSQL web app with a native Android companion build.

- Root `*.php` files are page controllers and public/admin screens.
- `includes/` contains shared PHP helpers for auth, DB access, training, feedback, billing, notifications, SEO, and wearable logic.
- `api/` contains JSON endpoints consumed by the Android app and other clients.
- `sql/migrations/pgsql/` contains PostgreSQL migrations.
- `assets/`, `uploads/`, `downloads/`, and `styles.css` hold public assets and APKs.
- `android/guidepaw-companion/` is the Android companion app.
- `tests/browser/` contains Playwright browser tests; `scripts/` contains deploy, crawl, and smoke-test utilities.

## Build, Test, and Development Commands

- `php -l path/to/file.php` checks PHP syntax for touched files.
- `bash scripts/deploy_local.sh` syncs and smoke-tests the local app.
- `bash scripts/run_local_qa_crawler.sh` runs the GuidePaw local QA crawler.
- `GUIDEPAW_CHECK_API_ROUTES=yes bash scripts/run_local_qa_crawler.sh` includes API route checks.
- `npm run test:e2e` runs Playwright browser tests.
- `cd android/guidepaw-companion && GRADLE_USER_HOME=$PWD/.gradle ./gradlew --no-daemon :app:assembleDebug` builds the Android debug APK.

## Coding Style & Naming Conventions

Use concise, procedural PHP matching the existing codebase. Prefer shared helpers in `includes/` over duplicating logic. Avoid leaking DB/schema/debug details into UI or API responses.

Name PHP helpers by domain, for example `includes/feedback_submission.php`. API endpoints should return JSON with `success` and a clear `message`.

For Android, keep package code under `com.guidepaw.companion`, bump `versionCode`, `versionName`, `CompanionAppVersion`, and the published APK filename together.

## Testing Guidelines

At minimum, lint touched PHP files and run the local crawler before committing. Run the Android Gradle build whenever files under `android/guidepaw-companion/` change. Use Playwright when browser behavior, auth flows, responsive layout, or admin/user permissions are affected.

Test names should describe behavior, not implementation, for example `forum_thread_search` or `breed_questionnaire_toy_alignment`.

## Commit & Pull Request Guidelines

History uses short imperative commit messages, such as `replace companion menu with web structure` or `fix android branding and feedback uploads`. Keep commits focused and include generated APK updates only when the Android build changes.

Pull requests should include the user-facing change, touched areas, verification commands, and screenshots for UI changes. Link feedback report IDs when applicable.

## Security & Configuration Tips

Do not commit secrets or local `.env` files. Use `.env.render.example` for documented configuration. Keep admin-only behavior out of Android. Treat feedback attachments as untrusted input.
