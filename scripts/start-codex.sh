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
# Reset middleware-managed files so git pull never aborts on local writes
git checkout HEAD -- HANDOFF.md SESSION_STATE.json DEVLOG.md 2>/dev/null || true
git pull origin main --quiet

# ── Ensure middleware is running ────────────────────────────────────────────
if ! curl -sf "$MIDDLEWARE_URL/health" > /dev/null 2>&1; then
  echo "[MW] Middleware offline — starting..."
  cd "$REPO_ROOT/middleware" && node server.js &
  sleep 3
  cd "$REPO_ROOT"
fi

# ── Extract task from HANDOFF.md ─────────────────────────────────────────────
extract_task() {
  awk '/^## .*Next Task/{found=1; next} found && /^#/{exit} found && NF{print}' \
    "$REPO_ROOT/HANDOFF.md" 2>/dev/null | head -10 | tr '\n' ' ' | sed 's/[[:space:]]*$//'
}

TASK="${1:-$(extract_task)}"
[[ -z "$TASK" ]] && TASK="Read CODEX_BOOT.md, CODEX_RULES.md, PROJECT_STATE.md, DEVLOG.md, and HANDOFF.md. Then implement the next task end-to-end. Do not stop until the task is complete, deployed, and pushed to git."

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║         Codex CLI — GuidePaw Session             ║"
echo "╚══════════════════════════════════════════════════╝"
echo "  Task : $TASK"
echo ""

# ── Register session with middleware ─────────────────────────────────────────
curl -s -X POST "$MIDDLEWARE_URL/session/start" \
  -H "Authorization: Bearer $MIDDLEWARE_SECRET" \
  -H "Content-Type: application/json" \
  -d "{\"ai\":\"codex\",\"task\":$(python3 -c "import json,sys; print(json.dumps(sys.argv[1]))" "$TASK"),\"branch\":\"main\"}" \
  -o /dev/null

# ── Build full context — all 5 key files injected into the prompt ────────────
SYSPROMPT=$(cat "$REPO_ROOT/.codex/system_prompt.md" 2>/dev/null || echo "You are a senior engineer on GuidePaw.")
BOOT=$(cat "$REPO_ROOT/CODEX_BOOT.md" 2>/dev/null || echo "")
RULES=$(cat "$REPO_ROOT/CODEX_RULES.md" 2>/dev/null || echo "")
PROJECT=$(cat "$REPO_ROOT/PROJECT_STATE.md" 2>/dev/null || echo "")
DEVLOG=$(tail -80 "$REPO_ROOT/DEVLOG.md" 2>/dev/null || echo "")
HANDOFF=$(cat "$REPO_ROOT/HANDOFF.md" 2>/dev/null || echo "No handoff yet.")

PROMPT="$SYSPROMPT

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## CODEX_BOOT.md — READ THIS FULLY BEFORE ANY CODE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$BOOT

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## CODEX_RULES.md — 10 RULES FROM PAST SCREWUPS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$RULES

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## PROJECT_STATE.md — CURRENT VERSION & ARCHITECTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$PROJECT

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## DEVLOG.md — RECENT SESSION HISTORY (last 80 lines)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$DEVLOG

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## HANDOFF.md — WHAT THE LAST AI DID AND WHAT'S NEXT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$HANDOFF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## YOUR TASK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$TASK

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
## MANDATORY COMPLETION STEPS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Lint PHP: php -l path/to/file.php
2. Deploy: bash scripts/deploy_local.sh
3. Git push: git push origin main
4. Call session/end:
   curl -s -X POST $MIDDLEWARE_URL/session/end \\
     -H 'Authorization: Bearer $MIDDLEWARE_SECRET' \\
     -H 'Content-Type: application/json' \\
     -d '{\"ai\":\"codex\",\"summary\":\"WHAT_YOU_DID\",\"files_changed\":[\"file\"],\"next_task\":\"SPECIFIC_NEXT_TASK\"}'
5. Final output: 🤝 HANDOFF COMPLETE — Claude can now pick up.

MIDDLEWARE_URL=$MIDDLEWARE_URL
Source secrets: set -a; source middleware/.env; set +a
REPO=$REPO_ROOT
"

printf '%s' "$PROMPT" | codex exec --dangerously-bypass-approvals-and-sandbox -
