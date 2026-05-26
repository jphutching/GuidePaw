#!/usr/bin/env bash
# render-set-env.sh — Safely set one or more Render env vars WITHOUT wiping others.
# Usage: ./scripts/render-set-env.sh KEY=VALUE [KEY2=VALUE2 ...]
#
# This script paginates through ALL current env vars before merging,
# so it is safe to call without losing other vars.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$REPO_ROOT/middleware/.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing."; exit 1; }
set -a; source "$ENV_FILE"; set +a

RENDER_API_KEY="${RENDER_API_KEY:?Set RENDER_API_KEY in middleware/.env}"
RENDER_SVC_ID="srv-d8a3qidckfvc739ct2cg"

[[ $# -eq 0 ]] && { echo "Usage: $0 KEY=VALUE [KEY2=VALUE2 ...]"; exit 1; }

# Fetch ALL pages of current env vars
ALL_VARS="[]"
CURSOR=""
while true; do
  URL="https://api.render.com/v1/services/$RENDER_SVC_ID/env-vars?limit=100"
  [[ -n "$CURSOR" ]] && URL="${URL}&cursor=${CURSOR}"
  PAGE=$(curl -s "$URL" -H "Authorization: Bearer $RENDER_API_KEY" -H "Accept: application/json")
  COUNT=$(echo "$PAGE" | python3 -c "import sys,json; print(len(json.load(sys.stdin)))")
  ALL_VARS=$(python3 -c "
import json, sys
existing = json.loads('$ALL_VARS')
page = json.loads(sys.stdin.read())
existing.extend(page)
print(json.dumps(existing))
" <<< "$PAGE")
  if [[ "$COUNT" -lt 100 ]]; then break; fi
  CURSOR=$(echo "$PAGE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d[-1].get('cursor','') if d else '')")
  [[ -z "$CURSOR" ]] && break
done

# Merge new values into all existing vars
UPDATES=$(printf '%s\n' "$@" | python3 -c "
import sys, json

overrides = {}
for line in sys.stdin:
    line = line.strip()
    if '=' in line:
        k, v = line.split('=', 1)
        overrides[k.strip()] = v.strip()

import os
data = json.loads(os.environ.get('ALL_VARS_JSON', '[]'))
existing = {item['envVar']['key']: item['envVar']['value'] for item in data}
existing.update(overrides)
print(json.dumps([{'key': k, 'value': v} for k, v in existing.items()]))
" ALL_VARS_JSON="$ALL_VARS")

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
