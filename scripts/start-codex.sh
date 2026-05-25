#!/usr/bin/env bash
# start-codex.sh — Run Codex autonomously until it hands back to Claude.
# Usage: ./scripts/start-codex.sh ["optional task override"]

set -euo pipefail

REPO_ROOT="/home/james/projects/guidepaw"
ENV_FILE="$REPO_ROOT/middleware/.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing."; exit 1; }
set -a; source "$ENV_FILE"; set +a

export MIDDLEWARE_URL="${MIDDLEWARE_URL:-http://10.147.18.184:3333}"
export MIDDLEWARE_SECRET="${MIDDLEWARE_SECRET:?Set MIDDLEWARE_SECRET in middleware/.env}"
export OPENAI_API_KEY="${OPENAI_API_KEY:?Set OPENAI_API_KEY in middleware/.env}"

cd "$REPO_ROOT"
git pull origin main --quiet

# ── Ensure middleware is running ────────────────────────────────────────────
if ! curl -sf "$MIDDLEWARE_URL/health" > /dev/null 2>&1; then
  echo "[MW] Middleware offline — starting..."
  cd "$REPO_ROOT/middleware" && node server.js &
  sleep 3
  cd "$REPO_ROOT"
fi

# ── Extract task from HANDOFF.md (everything after "Next Task" heading until
#    the next heading, ignoring blank lines) ──────────────────────────────────
extract_task() {
  awk '/^## .*Next Task/{found=1; next} found && /^#/{exit} found && NF{print}' \
    "$REPO_ROOT/HANDOFF.md" 2>/dev/null | head -10 | tr '\n' ' ' | sed 's/[[:space:]]*$//'
}

TASK="${1:-$(extract_task)}"
[[ -z "$TASK" ]] && TASK="Read HANDOFF.md carefully and implement the next task end-to-end. Do not stop until the task is complete, the APK is built, and git push is done."

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║         Codex CLI — GuidePaw Session             ║"
echo "╚══════════════════════════════════════════════════╝"
echo "  Task : $TASK"
echo ""

# Register session with middleware
curl -s -X POST "$MIDDLEWARE_URL/session/start" \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"ai\":\"codex\",\"task\":$(python3 -c "import json,sys; print(json.dumps(sys.argv[1]))" "$TASK"),\"branch\":\"main\"}" \
  -o /dev/null

SYSPROMPT=$(cat "$REPO_ROOT/.codex/system_prompt.md" 2>/dev/null || echo "You are a senior engineer on the GuidePaw project.")
HANDOFF=$(cat "$REPO_ROOT/HANDOFF.md" 2>/dev/null || echo "No handoff yet.")

PROMPT="$SYSPROMPT

---
## HANDOFF FROM PREVIOUS SESSION
$HANDOFF

---
## YOUR TASK
$TASK

---
## MANDATORY COMPLETION STEPS
When your task is fully done (code written, built, tested, pushed to git):
1. Call POST $MIDDLEWARE_URL/session/end with your summary and next_task
2. The middleware will update HANDOFF.md automatically
3. Do NOT stop early. A no-op session is a failure.

MIDDLEWARE_URL=$MIDDLEWARE_URL
MIDDLEWARE_SECRET is in middleware/.env — source it with: set -a; source middleware/.env; set +a
REPO=/home/james/projects/guidepaw
"

printf '%s' "$PROMPT" | codex exec --dangerously-bypass-approvals-and-sandbox -
