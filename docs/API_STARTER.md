# API Starter

This build includes a minimal token-based JSON API for future Flutter/mobile work.

Endpoints:
- `POST /api/login.php`
- `GET /api/me.php`
- `GET /api/dogs.php`
- `GET /api/logs.php?dog_id=123`
- `POST /api/logs.php`

Authentication:
- Create tokens in `api_tokens.php`
- Pass `Authorization: Bearer <token>`


## 2FA note
When a user has 2FA enabled, `/api/login.php` requires either `totp_code` or `recovery_key` along with username and password.
