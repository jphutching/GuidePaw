#!/usr/bin/env bash
# render-set-env.sh — Set one or more Render env vars safely.
# Uses master.env as the source of truth so secrets are never lost.
# Usage: ./scripts/render-set-env.sh KEY=VALUE [KEY2=VALUE2 ...]

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$REPO_ROOT/middleware/.env"
MASTER="$REPO_ROOT/master.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing."; exit 1; }
[[ -f "$MASTER"   ]] || { echo "[ERROR] master.env missing at $MASTER"; exit 1; }

set -a; source "$ENV_FILE"; set +a

RENDER_API_KEY="${RENDER_API_KEY:?Set RENDER_API_KEY in middleware/.env}"
RENDER_SVC_ID="srv-d8a3qidckfvc739ct2cg"

[[ $# -eq 0 ]] && { echo "Usage: $0 KEY=VALUE [KEY2=VALUE2 ...]"; exit 1; }

# Keys that live only in middleware/.env or are not Render app vars
SKIP="RENDER_SVC_ID RENDER_MIDDLEWARE_SVC_ID RENDER_DB_ID RENDER_ENV_GROUP_ID \
      RENDER_API_KEY RENDER_DEPLOY_HOOK RENDER_SERVICE_NAME RENDER_APP_URL \
      RENDER_MIDDLEWARE_URL MIDDLEWARE_SECRET MIDDLEWARE_URL \
      ANTHROPIC_API_KEY OPENAI_API_KEY \
      DB_EXTERNAL_URL GITHUB_REPO GITHUB_BRANCH"

python3 - "$MASTER" "$RENDER_SVC_ID" "$RENDER_API_KEY" "$SKIP" "$@" <<'PYEOF'
import sys, re, json, urllib.request, urllib.error

master_path  = sys.argv[1]
svc_id       = sys.argv[2]
api_key      = sys.argv[3]
skip_keys    = set(sys.argv[4].split())
overrides    = {}
for arg in sys.argv[5:]:
    if '=' in arg:
        k, _, v = arg.partition('=')
        overrides[k.strip()] = v.strip()

if not overrides:
    print("[WARN] No KEY=VALUE pairs found in arguments.")
    sys.exit(0)

# Build env var map from master.env
vars = {}
with open(master_path) as f:
    for line in f:
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        m = re.match(r'^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)', line)
        if m:
            k, v = m.group(1), m.group(2).strip()
            if k not in skip_keys:
                vars[k] = v

# Apply the overrides on top
vars.update(overrides)

payload = [{'key': k, 'value': v} for k, v in vars.items()]
print(f"Pushing {len(payload)} env vars (master.env + overrides) to Render...")

data = json.dumps(payload).encode()
req = urllib.request.Request(
    f'https://api.render.com/v1/services/{svc_id}/env-vars',
    data=data,
    method='PUT',
    headers={
        'Authorization': f'Bearer {api_key}',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
)
try:
    resp = urllib.request.urlopen(req)
    print(f"[OK] HTTP {resp.status} — updated on Render: {list(overrides.keys())}")
except urllib.error.HTTPError as e:
    print(f"[ERROR] HTTP {e.code} — {e.read().decode()[:300]}")
    sys.exit(1)
PYEOF

# Also update master.env with the new values
TODAY=$(date +%Y-%m-%d)
python3 - "$MASTER" "$TODAY" "$@" <<'PYEOF'
import sys, re

master_path = sys.argv[1]
today       = sys.argv[2]
updates     = {}
for arg in sys.argv[3:]:
    if '=' in arg:
        k, _, v = arg.partition('=')
        updates[k.strip()] = v.strip()

if not updates:
    sys.exit(0)

with open(master_path, 'r') as f:
    lines = f.readlines()

new_lines = []
handled   = set()
i = 0
while i < len(lines):
    line = lines[i]
    m = re.match(r'^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)', line.strip())
    if m:
        key = m.group(1)
        old_val = m.group(2).strip()
        if key in updates and updates[key] != old_val:
            new_lines.append(f'# was: {old_val} ({today})\n')
            new_lines.append(f'{key}={updates[key]}\n')
            handled.add(key)
            i += 1
            continue
    new_lines.append(line)
    i += 1

# Append brand-new keys
existing_keys = {re.match(r'^([A-Za-z_][A-Za-z0-9_]*)', l.strip()).group(1)
                 for l in lines if re.match(r'^[A-Za-z_]', l.strip())}
new_keys = [k for k in updates if k not in handled and k not in existing_keys]
if new_keys:
    if new_lines and new_lines[-1].strip():
        new_lines.append('\n')
    for k in new_keys:
        new_lines.append(f'{k}={updates[k]}\n')

with open(master_path, 'w') as f:
    f.writelines(new_lines)

print(f'[master.env] synced: {list(updates.keys())}')
PYEOF
