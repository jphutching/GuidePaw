#!/usr/bin/env bash
# render-set-env.sh — Safely set one or more Render env vars WITHOUT wiping others.
# Usage: ./scripts/render-set-env.sh KEY=VALUE [KEY2=VALUE2 ...]
# Example: ./scripts/render-set-env.sh GUIDEPAW_COMPANION_VERSION_CODE=56 GUIDEPAW_COMPANION_VERSION_NAME=0.056
#
# This script always fetches the current env vars first and merges,
# so it is safe to call from Codex or Claude without losing other vars.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$REPO_ROOT/middleware/.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing."; exit 1; }
set -a; source "$ENV_FILE"; set +a

RENDER_API_KEY="${RENDER_API_KEY:?Set RENDER_API_KEY in middleware/.env}"
RENDER_SVC_ID="srv-d7qmnj7lk1mc73cl18j0"

[[ $# -eq 0 ]] && { echo "Usage: $0 KEY=VALUE [KEY2=VALUE2 ...]"; exit 1; }

# Fetch current env vars
CURRENT=$(curl -s "https://api.render.com/v1/services/$RENDER_SVC_ID/env-vars" \
  -H "Authorization: Bearer $RENDER_API_KEY" \
  -H "Accept: application/json")

# Merge new values into existing
UPDATES=$(printf '%s\n' "$@" | python3 -c "
import sys, json, os

# Parse KEY=VALUE args from stdin
overrides = {}
for line in sys.stdin:
    line = line.strip()
    if '=' in line:
        k, v = line.split('=', 1)
        overrides[k.strip()] = v.strip()

current_json = os.environ.get('CURRENT_JSON', '[]')
data = json.loads(current_json)
existing = {item['envVar']['key']: item['envVar']['value'] for item in data}
existing.update(overrides)
print(json.dumps([{'key': k, 'value': v} for k, v in existing.items()]))
" CURRENT_JSON="$CURRENT")

HTTP=$(curl -s -o /tmp/render-env-response.json -w "%{http_code}" -X PUT \
  "https://api.render.com/v1/services/$RENDER_SVC_ID/env-vars" \
  -H "Authorization: Bearer $RENDER_API_KEY" \
  -H "Content-Type: application/json" \
  -d "$UPDATES")

if [[ "$HTTP" == "200" ]]; then
  echo "[OK] Updated env vars on Render: $*"
else
  echo "[ERROR] HTTP $HTTP — $(cat /tmp/render-env-response.json)"
  exit 1
fi
