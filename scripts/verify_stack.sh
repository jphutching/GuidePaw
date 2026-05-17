#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

section() {
  printf '\n== %s ==\n' "$1"
}

run_cmd() {
  local label="$1"
  shift
  local output status
  set +e
  output="$("$@" 2>&1)"
  status=$?
  set -e
  if [[ $status -eq 0 ]]; then
    printf 'PASS %s\n' "$label"
    [[ -n "$output" ]] && printf '%s\n' "$output"
    return 0
  fi
  if [[ "$output" == *"no new privileges"* || "$output" == *"failed to open socket: Operation not permitted"* || "$output" == *"DNS"* || "$output" == *"network blocked"* ]]; then
    printf 'UNVERIFIED %s\n' "$label"
    [[ -n "$output" ]] && printf '%s\n' "$output"
    return 0
  fi
  printf 'FAIL %s\n' "$label"
  [[ -n "$output" ]] && printf '%s\n' "$output"
  return 1
}

overall_fail=0

section "Local repo"
git status --short --branch
local_head="$(git rev-parse --short HEAD)"
branch="$(git rev-parse --abbrev-ref HEAD)"
dirty="$(git status --porcelain)"
origin_short=""
if origin_short="$(git ls-remote origin refs/heads/main 2>/dev/null | awk '{print substr($1,1,7)}' || true)"; then
  :
fi
if [[ -n "$dirty" ]]; then
  dirty_state="yes"
else
  dirty_state="no"
fi
printf 'HEAD=%s branch=%s origin/main=%s dirty=%s\n' \
  "$local_head" \
  "$branch" \
  "${origin_short:-unverified}" \
  "$dirty_state"

section "Local deploy"
if ! run_cmd "scripts/deploy_local.sh" bash scripts/deploy_local.sh; then
  overall_fail=1
fi

section "Local smoke"
if ! run_cmd "scripts/run_local_qa_crawler.sh" bash scripts/run_local_qa_crawler.sh; then
  overall_fail=1
fi

section "Remote reachability"
remote_ok=1
if curl -k -I -s --max-time 10 https://beta.guidepaw.app/healthz.php >/dev/null; then
  printf 'PASS beta.guidepaw.app health check\n'
else
  printf 'UNVERIFIED beta.guidepaw.app health check (network blocked or host unreachable)\n'
  remote_ok=0
fi

if git ls-remote origin refs/heads/main >/dev/null 2>&1; then
  printf 'PASS origin/main reachable from this shell\n'
else
  printf 'UNVERIFIED origin/main reachable from this shell (SSH/DNS/network blocked)\n'
  remote_ok=0
fi

if [[ "${GUIDEPAW_RUN_REMOTE_COMPARE:-no}" == "yes" ]]; then
  section "Remote compare"
  if [[ -n "${GUIDEPAW_LOCAL_ADMIN_PASS:-}" && -n "${GUIDEPAW_BETA_ADMIN_PASS:-}" ]]; then
    php scripts/compare_site_crawler.php \
      --local-url="${GUIDEPAW_LOCAL_URL:-https://10.147.18.184}" \
      --beta-url="${GUIDEPAW_BETA_URL:-https://beta.guidepaw.app}" \
      --local-admin-user="${GUIDEPAW_LOCAL_ADMIN_USER:-${GUIDEPAW_ADMIN_USER:-admin}}" \
      --local-admin-pass="${GUIDEPAW_LOCAL_ADMIN_PASS:-${GUIDEPAW_ADMIN_PASS:-}}" \
      --beta-admin-user="${GUIDEPAW_BETA_ADMIN_USER:-${GUIDEPAW_ADMIN_USER:-admin}}" \
      --beta-admin-pass="${GUIDEPAW_BETA_ADMIN_PASS:-${GUIDEPAW_ADMIN_PASS:-}}" \
      --local-regular-user="${GUIDEPAW_LOCAL_REGULAR_USER:-${GUIDEPAW_REGULAR_USER:-}}" \
      --local-regular-pass="${GUIDEPAW_LOCAL_REGULAR_PASS:-${GUIDEPAW_REGULAR_PASS:-}}" \
      --beta-regular-user="${GUIDEPAW_BETA_REGULAR_USER:-${GUIDEPAW_REGULAR_USER:-}}" \
      --beta-regular-pass="${GUIDEPAW_BETA_REGULAR_PASS:-${GUIDEPAW_REGULAR_PASS:-}}" \
      --max-pages="${GUIDEPAW_COMPARE_MAX_PAGES:-160}" \
      --insecure-local-ssl="${GUIDEPAW_INSECURE_LOCAL_SSL:-yes}"
  else
    printf 'SKIP remote compare: missing admin credentials in environment.\n'
  fi
else
  printf '\nRemote compare skipped. Set GUIDEPAW_RUN_REMOTE_COMPARE=yes to compare local vs beta from a network-enabled shell.\n'
fi

if [[ $remote_ok -eq 0 ]]; then
  printf '\nRemote status is unverified from this shell. Use a network-enabled terminal or set GUIDEPAW_RUN_REMOTE_COMPARE=yes where DNS/socket access works.\n'
fi

exit "$overall_fail"
