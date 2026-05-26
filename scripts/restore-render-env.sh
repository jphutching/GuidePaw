#!/usr/bin/env bash
# restore-render-env.sh — Push ALL vars from master.env to Render in one shot.
# Usage: bash scripts/restore-render-env.sh
# Run this any time env vars go missing on Render.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MASTER="$REPO_ROOT/master.env"
[[ -f "$MASTER" ]] || { echo "[ERROR] master.env not found at $REPO_ROOT"; exit 1; }

# Load master.env
set -a; source "$MASTER"; set +a
RENDER_API_KEY="${RENDER_API_KEY:?RENDER_API_KEY missing from master.env}"
SVC_ID="${RENDER_SVC_ID:?RENDER_SVC_ID missing from master.env}"

# Build the env var list from master.env (skip comment/blank lines and non-web vars)
SKIP_KEYS="RENDER_SVC_ID|RENDER_MIDDLEWARE_SVC_ID|RENDER_DB_ID|RENDER_ENV_GROUP_ID|DB_EXTERNAL_URL|MIDDLEWARE_URL|MIDDLEWARE_SECRET|GITHUB_REPO|GITHUB_BRANCH"

PAYLOAD=$(python3 - "$MASTER" <<'EOF'
import sys, re

skip = set(re.split(r'\|', "RENDER_SVC_ID|RENDER_MIDDLEWARE_SVC_ID|RENDER_DB_ID|RENDER_ENV_GROUP_ID|DB_EXTERNAL_URL|MIDDLEWARE_URL|MIDDLEWARE_SECRET|GITHUB_REPO|GITHUB_BRANCH"))

vars = []
with open(sys.argv[1]) as f:
    for line in f:
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        if '=' not in line:
            continue
        key, _, val = line.partition('=')
        key = key.strip()
        val = val.strip()
        if key in skip:
            continue
        vars.append({"key": key, "value": val})

import json
print(json.dumps(vars))
EOF
)

COUNT=$(echo "$PAYLOAD" | python3 -c "import sys,json; print(len(json.load(sys.stdin)))")
echo "Pushing $COUNT vars to Render service $SVC_ID..."

HTTP=$(curl -s -o /tmp/render-restore-response.json -w "%{http_code}" -X PUT \
  "https://api.render.com/v1/services/$SVC_ID/env-vars" \
  -H "Authorization: Bearer $RENDER_API_KEY" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")

if [[ "$HTTP" == "200" ]]; then
  echo "[OK] All $COUNT vars pushed to Render."
  echo ""
  echo "Trigger a deploy to pick them up:"
  echo "  bash scripts/render-set-env.sh   (no-op, just to verify)"
  echo "  or trigger via Render dashboard / API"
else
  echo "[ERROR] HTTP $HTTP"
  cat /tmp/render-restore-response.json
  exit 1
fi
