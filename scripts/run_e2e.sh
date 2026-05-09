#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-local}"

case "$TARGET" in
  local)
    BASE_URL="${GUIDEPAW_TEST_BASE_URL:-https://10.147.18.184}"
    ENV_FILE=".env.test.local"
    ;;
  render|beta)
    BASE_URL="${GUIDEPAW_TEST_BASE_URL:-https://beta.guidepaw.app}"
    ENV_FILE=".env.test.render"
    ;;
  *)
    echo "Usage: scripts/run_e2e.sh [local|render]"
    exit 1
    ;;
esac

if [ -f "$ENV_FILE" ]; then
  set -a
  source "$ENV_FILE"
  set +a
fi

if [ "${GUIDEPAW_REPAIR_SMOKE_AUTH:-no}" = "yes" ]; then
  echo "Repairing smoke-login credentials before test run..."
  php scripts/repair_smoke_auth.php
fi

if [ -z "${GUIDEPAW_TEST_USERNAME:-}" ]; then
  read -r -p "GuidePaw test username/email: " GUIDEPAW_TEST_USERNAME
  export GUIDEPAW_TEST_USERNAME
fi

if [ -z "${GUIDEPAW_TEST_PASSWORD:-}" ]; then
  read -r -s -p "GuidePaw test password: " GUIDEPAW_TEST_PASSWORD
  echo
  export GUIDEPAW_TEST_PASSWORD
fi

export GUIDEPAW_TEST_BASE_URL="$BASE_URL"

echo "Running GuidePaw Playwright crawler against: $GUIDEPAW_TEST_BASE_URL"
npm run test:e2e
