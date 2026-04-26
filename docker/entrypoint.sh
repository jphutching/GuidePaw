#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/html"
STORAGE_ROOT="${APP_STORAGE_PATH:-/data}"
UPLOADS_TARGET="${STORAGE_ROOT%/}/uploads"
TMP_TARGET="${STORAGE_ROOT%/}/tmp"

mkdir -p "$UPLOADS_TARGET/images" "$UPLOADS_TARGET/videos" "$UPLOADS_TARGET/documents" "$TMP_TARGET"
chown -R www-data:www-data "$STORAGE_ROOT"

if [ -d "$APP_ROOT/uploads" ] && [ ! -L "$APP_ROOT/uploads" ]; then
  rm -rf "$APP_ROOT/uploads"
fi
ln -sfn "$UPLOADS_TARGET" "$APP_ROOT/uploads"

if [ -f "$APP_ROOT/index.php" ]; then
  chown -h www-data:www-data "$APP_ROOT/uploads" || true
fi

exec "$@"
