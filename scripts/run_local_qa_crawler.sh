#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

BASE_URL="${GUIDEPAW_BASE_URL:-https://10.147.18.184}"
ADMIN_USER="${GUIDEPAW_ADMIN_USER:-admin}"
ADMIN_PASS="${GUIDEPAW_ADMIN_PASS:-}"
REGULAR_USER="${GUIDEPAW_REGULAR_USER:-}"
REGULAR_PASS="${GUIDEPAW_REGULAR_PASS:-}"
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

php scripts/local_qa_crawler.php "${args[@]}"
