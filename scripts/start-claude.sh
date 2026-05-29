#!/usr/bin/env bash
# start-claude.sh — Start an interactive Claude Code session for GuidePaw.
# Usage: ./scripts/start-claude.sh ["optional task override"]

set -euo pipefail

REPO_ROOT="/home/james/projects/guidepaw"
ENV_FILE="$REPO_ROOT/middleware/.env"

[[ -f "$ENV_FILE" ]] || { echo "[ERROR] $ENV_FILE missing."; exit 1; }
set -a; source "$ENV_FILE"; set +a

export MIDDLEWARE_URL="${MIDDLEWARE_URL:-http://10.147.18.184:3333}"
export MIDDLEWARE_SECRET="${MIDDLEWARE_SECRET:?Set MIDDLEWARE_SECRET in middleware/.env}"
export ANTHROPIC_API_KEY="${ANTHROPIC_API_KEY:?Set ANTHROPIC_API_KEY in middleware/.env}"
unset OPENAI_API_KEY 2>/dev/null || true

cd "$REPO_ROOT"
echo "[GIT] Pulling latest..."
git checkout HEAD -- HANDOFF.md SESSION_STATE.json DEVLOG.md 2>/dev/null || true
git pull origin main --quiet

# ── Ensure middleware is running ────────────────────────────────────────────
if ! curl -sf "$MIDDLEWARE_URL/health" > /dev/null 2>&1; then
  echo "[MW]  Middleware offline — starting..."
  cd "$REPO_ROOT/middleware" && nohup node server.js > /tmp/middleware.log 2>&1 & disown
  sleep 3
  cd "$REPO_ROOT"
fi

# ── Extract task from HANDOFF.md ─────────────────────────────────────────────
extract_task() {
  awk '/^## .*Next Task/{found=1; next} found && /^#/{exit} found && NF{print}' \
    "$REPO_ROOT/HANDOFF.md" 2>/dev/null | head -10 | tr '\n' ' ' | sed 's/[[:space:]]*$//'
}

TASK="${1:-$(extract_task)}"
[[ -z "$TASK" ]] && TASK="Read CLAUDE_BOOT.md, PROJECT_STATE.md, DEVLOG.md, and HANDOFF.md. Review Codex's last commits for issues, then implement the next task."

# ── Register session with middleware ─────────────────────────────────────────
curl -s -X POST "$MIDDLEWARE_URL/session/start" \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"ai\":\"claude\",\"task\":$(python3 -c "import json,sys; print(json.dumps(sys.argv[1]))" "$TASK"),\"branch\":\"main\"}" \
  -o /dev/null

# ── Print full session context to terminal ───────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║         Claude Code — GuidePaw Session           ║"
echo "╚══════════════════════════════════════════════════╝"
echo "  Task        : $TASK"
echo "  Middleware  : $MIDDLEWARE_URL"
echo "  Dashboard   : $MIDDLEWARE_URL/dashboard"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  CLAUDE_BOOT.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat "$REPO_ROOT/CLAUDE_BOOT.md" 2>/dev/null || echo "(not found)"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  PROJECT_STATE.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat "$REPO_ROOT/PROJECT_STATE.md" 2>/dev/null || echo "(not found)"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  DEVLOG.md — last 40 lines"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
tail -40 "$REPO_ROOT/DEVLOG.md" 2>/dev/null || echo "(not found)"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  HANDOFF.md"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
cat "$REPO_ROOT/HANDOFF.md" 2>/dev/null || echo "(no handoff yet)"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  git log --oneline -10"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
git log --oneline -10
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Starting Claude Code..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Claude Code auto-reads .claude/CLAUDE.md and CLAUDE.md at startup
claude --dangerously-skip-permissions
