#!/usr/bin/env bash
# start-claude.sh — Start Claude Code session for GuidePaw
# Usage: ./scripts/start-claude.sh ["optional task description"]

set -euo pipefail

REPO_ROOT="/home/james/projects/guidepaw"
ENV_FILE="$REPO_ROOT/middleware/.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing. Run install.sh first."; exit 1; }
set -a; source "$ENV_FILE"; set +a

export MIDDLEWARE_URL="${MIDDLEWARE_URL:-http://10.147.18.184:3333}"
export MIDDLEWARE_SECRET="${MIDDLEWARE_SECRET:?Set MIDDLEWARE_SECRET in middleware/.env}"
export ANTHROPIC_API_KEY="${ANTHROPIC_API_KEY:?Set ANTHROPIC_API_KEY in middleware/.env}"

TASK="${1:-}"
if [[ -z "$TASK" ]]; then
  TASK=$(python3 -c "import json; s=json.load(open('$REPO_ROOT/SESSION_STATE.json')); print(s.get('current_task','Review HANDOFF.md'))" 2>/dev/null || echo "Review HANDOFF.md and continue")
fi

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║         Claude Code — GuidePaw Session           ║"
echo "╚══════════════════════════════════════════════════╝"

cd "$REPO_ROOT"
echo "[GIT] Pulling latest..."
git pull origin main

echo "[MW]  Checking middleware..."
if ! curl -sf "$MIDDLEWARE_URL/health" > /dev/null 2>&1; then
  echo "[MW]  Middleware offline — starting..."
  cd "$REPO_ROOT/middleware" && node server.js &
  sleep 3
  cd "$REPO_ROOT"
fi

echo "[MW]  Registering Claude session..."
curl -sf -X POST "$MIDDLEWARE_URL/session/start" \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"ai\":\"claude\",\"task\":$(python3 -c "import json,sys; print(json.dumps(sys.argv[1]))" "$TASK"),\"branch\":\"main\"}" > /dev/null

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  LAST HANDOFF (from Codex or previous session)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat HANDOFF.md 2>/dev/null || echo "(No handoff yet — fresh start)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  Task        : $TASK"
echo "  Middleware  : $MIDDLEWARE_URL"
echo "  Repo        : $REPO_ROOT"
echo ""

# Claude Code reads .claude/CLAUDE.md automatically — no --system-prompt needed
claude --dangerously-skip-permissions
