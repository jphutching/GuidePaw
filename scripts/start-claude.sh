#!/usr/bin/env bash
# start-claude.sh — Start an interactive Claude Code session for GuidePaw.
# Claude reads HANDOFF.md, does its task, writes a new handoff, then the user
# runs start-codex.sh to hand off to Codex.
# Usage: ./scripts/start-claude.sh ["optional task override"]

set -euo pipefail

REPO_ROOT="/home/james/projects/guidepaw"
ENV_FILE="$REPO_ROOT/middleware/.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing."; exit 1; }
set -a; source "$ENV_FILE"; set +a

export MIDDLEWARE_URL="${MIDDLEWARE_URL:-http://10.147.18.184:3333}"
export MIDDLEWARE_SECRET="${MIDDLEWARE_SECRET:?Set MIDDLEWARE_SECRET in middleware/.env}"
export ANTHROPIC_API_KEY="${ANTHROPIC_API_KEY:?Set ANTHROPIC_API_KEY in middleware/.env}"
unset OPENAI_API_KEY  # prevent auth conflict

cd "$REPO_ROOT"
git pull origin main --quiet

# ── Ensure middleware is running ────────────────────────────────────────────
if ! curl -sf "$MIDDLEWARE_URL/health" > /dev/null 2>&1; then
  echo "[MW] Middleware offline — starting..."
  cd "$REPO_ROOT/middleware" && node server.js &
  sleep 3
  cd "$REPO_ROOT"
fi

# ── Extract task from HANDOFF.md ────────────────────────────────────────────
extract_task() {
  awk '/^## .*Next Task/{found=1; next} found && /^#/{exit} found && NF{print}' \
    "$REPO_ROOT/HANDOFF.md" 2>/dev/null | head -10 | tr '\n' ' ' | sed 's/[[:space:]]*$//'
}

TASK="${1:-$(extract_task)}"
[[ -z "$TASK" ]] && TASK="Read HANDOFF.md and implement the next task."

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║         Claude Code — GuidePaw Session           ║"
echo "╚══════════════════════════════════════════════════╝"
echo "  Task : $TASK"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat HANDOFF.md 2>/dev/null || echo "(No handoff yet)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Register session with middleware
curl -s -X POST "$MIDDLEWARE_URL/session/start" \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"ai\":\"claude\",\"task\":$(python3 -c "import json,sys; print(json.dumps(sys.argv[1]))" "$TASK"),\"branch\":\"main\"}" \
  -o /dev/null

# Claude Code reads .claude/CLAUDE.md automatically for system context.
# The user interacts with Claude; when done Claude posts /session/end and
# the user then runs start-codex.sh.
claude --dangerously-skip-permissions
