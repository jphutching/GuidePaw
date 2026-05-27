#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ -f ".env.test.local" ]]; then
  set -a
  source ".env.test.local"
  set +a
fi

if [[ -f ".env.test.admin.local" ]]; then
  set -a
  source ".env.test.admin.local"
  set +a
fi

BASE_URL="${GUIDEPAW_BASE_URL:-https://10.147.18.184}"
CRAWLER_MODE="${GUIDEPAW_CRAWLER_MODE:-auto}"
ADMIN_USER="${GUIDEPAW_ADMIN_USER:-${GUIDEPAW_ADMIN_TEST_USERNAME:-admin}}"
ADMIN_PASS="${GUIDEPAW_ADMIN_PASS:-${GUIDEPAW_ADMIN_TEST_PASSWORD:-${GUIDEPAW_SMOKE_PASSWORD:-}}}"
# Demo accounts are public — use demo.sarah as the default regular crawl user
REGULAR_USER="${GUIDEPAW_REGULAR_USER:-${GUIDEPAW_TEST_USERNAME:-demo.sarah}}"
REGULAR_PASS="${GUIDEPAW_REGULAR_PASS:-${GUIDEPAW_TEST_PASSWORD:-${GUIDEPAW_SMOKE_PASSWORD:-Demo1234!!}}}"
MARK_CHECKLIST="${GUIDEPAW_MARK_CHECKLIST:-no}"
FEEDBACK_DB_HOST="${GUIDEPAW_FEEDBACK_DB_HOST:-${DB_HOST:-}}"
FEEDBACK_DB_PORT="${GUIDEPAW_FEEDBACK_DB_PORT:-${DB_PORT:-5432}}"
FEEDBACK_DB_NAME="${GUIDEPAW_FEEDBACK_DB_NAME:-${DB_DATABASE:-}}"
FEEDBACK_DB_USER="${GUIDEPAW_FEEDBACK_DB_USER:-${DB_USERNAME:-}}"
FEEDBACK_DB_PASS="${GUIDEPAW_FEEDBACK_DB_PASSWORD:-${DB_PASSWORD:-}}"
FEEDBACK_LIMIT="${GUIDEPAW_FEEDBACK_LIMIT:-200}"

if [[ -z "$ADMIN_PASS" ]]; then
  echo "Missing GUIDEPAW_ADMIN_PASS. Example:" >&2
  echo "GUIDEPAW_ADMIN_PASS='your-admin-password' bash scripts/run_local_qa_crawler.sh" >&2
  exit 2
fi

args=(
  "--base-url=$BASE_URL"
  "--admin-user=$ADMIN_USER"
  "--admin-pass=$ADMIN_PASS"
  "--mark-checklist=$MARK_CHECKLIST"
)

if [[ -n "$REGULAR_USER" && -n "$REGULAR_PASS" ]]; then
  args+=("--regular-user=$REGULAR_USER" "--regular-pass=$REGULAR_PASS")
fi

if [[ -n "$FEEDBACK_DB_HOST" && -n "$FEEDBACK_DB_NAME" && -n "$FEEDBACK_DB_USER" ]]; then
  args+=(
    "--feedback-db-host=$FEEDBACK_DB_HOST"
    "--feedback-db-port=$FEEDBACK_DB_PORT"
    "--feedback-db-name=$FEEDBACK_DB_NAME"
    "--feedback-db-user=$FEEDBACK_DB_USER"
    "--feedback-db-pass=$FEEDBACK_DB_PASS"
    "--feedback-limit=$FEEDBACK_LIMIT"
  )
fi

export GUIDEPAW_TEST_BASE_URL="$BASE_URL"
export GUIDEPAW_TEST_USERNAME="$REGULAR_USER"
export GUIDEPAW_TEST_PASSWORD="$REGULAR_PASS"
export GUIDEPAW_ADMIN_TEST_USERNAME="$ADMIN_USER"
export GUIDEPAW_ADMIN_TEST_PASSWORD="$ADMIN_PASS"

case "$CRAWLER_MODE" in
  php)
    php scripts/local_qa_crawler.php "${args[@]}"
    exit $?
    ;;
  playwright)
    echo "GUIDEPAW_CRAWLER_MODE=playwright; skipping PHP crawler." >&2
    exec npm run test:e2e -- tests/browser/guidepaw-auth-crawl.spec.js tests/browser/guidepaw-admin-safe.spec.js tests/browser/guidepaw-demo-smoke.spec.js
    ;;
esac

php_output="$(mktemp)"
if php scripts/local_qa_crawler.php "${args[@]}" >"$php_output" 2>&1; then
  cat "$php_output"
  rm -f "$php_output"
  exit 0
fi

if grep -q 'Admin session did not survive login; falling back to Playwright crawler.' "$php_output"; then
  rm -f "$php_output"
  echo "PHP QA crawler could not keep the admin session alive; using Playwright fallback." >&2
  exec npm run test:e2e -- tests/browser/guidepaw-auth-crawl.spec.js tests/browser/guidepaw-admin-safe.spec.js tests/browser/guidepaw-demo-smoke.spec.js
fi

cat "$php_output" >&2
if grep -Eq 'failed to open socket: Operation not permitted|Could not resolve host|setsockopt: Operation not permitted' "$php_output"; then
  rm -f "$php_output"
  echo "PHP QA crawler cannot reach the target from this shell; run GUIDEPAW_CRAWLER_MODE=playwright or use an unsandboxed shell." >&2
  exit 1
fi

rm -f "$php_output"
echo "PHP QA crawler failed or is unavailable here; falling back to Playwright crawler." >&2
npm run test:e2e -- tests/browser/guidepaw-auth-crawl.spec.js tests/browser/guidepaw-admin-safe.spec.js tests/browser/guidepaw-demo-smoke.spec.js
