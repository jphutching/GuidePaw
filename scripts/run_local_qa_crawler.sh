#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

BASE_URL="${GUIDEPAW_BASE_URL:-http://10.147.18.184}"
ADMIN_USER="${GUIDEPAW_ADMIN_USER:-admin}"
ADMIN_PASS="${GUIDEPAW_ADMIN_PASS:-}"
REGULAR_USER="${GUIDEPAW_REGULAR_USER:-}"
REGULAR_PASS="${GUIDEPAW_REGULAR_PASS:-}"
MARK_CHECKLIST="${GUIDEPAW_MARK_CHECKLIST:-no}"

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

php scripts/local_qa_crawler.php "${args[@]}"
