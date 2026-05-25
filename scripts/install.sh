#!/usr/bin/env bash
# =============================================================================
# install.sh — GuidePaw Middleware one-command setup
# james@10.147.18.184 — Ubuntu 26.04 LTS
#
# Prerequisites:
#   1. Run migrate-repo.sh first (moves gpb3/gpb3 → projects/guidepaw)
#   2. Fill in middleware/.env (copy from .env.laptop, add your keys)
#
# Usage:
#   chmod +x scripts/install.sh && ./scripts/install.sh
# =============================================================================

set -euo pipefail
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

REPO_PATH="/home/james/projects/guidepaw"
MIDDLEWARE_DIR="$REPO_PATH/middleware"
ENV_FILE="$MIDDLEWARE_DIR/.env"

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║     GuidePaw Middleware Installer                ║"
echo "║     james@10.147.18.184 — Ubuntu 26.04 LTS      ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

# ── Check repo exists ─────────────────────────────────────────────────────────
[[ -d "$REPO_PATH/.git" ]] || error "Repo not found at $REPO_PATH — run migrate-repo.sh first"

# ── Node.js 20+ ───────────────────────────────────────────────────────────────
info "Checking Node.js..."
if ! command -v node &>/dev/null || [[ $(node -e "process.exit(parseInt(process.version.slice(1))<20?1:0)"; echo $?) == "1" ]]; then
  info "Installing Node.js 20 via nvm..."
  curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
  export NVM_DIR="$HOME/.nvm"; source "$NVM_DIR/nvm.sh"
  nvm install 20 && nvm use 20 && nvm alias default 20
  success "Node.js $(node --version)"
else
  success "Node.js $(node --version) OK"
fi

# ── Claude Code CLI ───────────────────────────────────────────────────────────
info "Checking Claude Code CLI..."
if ! command -v claude &>/dev/null; then
  npm install -g @anthropic-ai/claude-code
  success "Claude Code installed"
else
  success "Claude Code OK"
fi

# ── Codex CLI ─────────────────────────────────────────────────────────────────
info "Checking Codex CLI..."
if ! command -v codex &>/dev/null; then
  npm install -g @openai/codex
  success "Codex CLI installed"
else
  success "Codex CLI OK"
fi

# ── .env ──────────────────────────────────────────────────────────────────────
if [[ ! -f "$ENV_FILE" ]]; then
  warn "No .env found at $ENV_FILE"
  LAPTOP_ENV="$(dirname "${BASH_SOURCE[0]}")/../.env.laptop"
  if [[ -f "$LAPTOP_ENV" ]]; then
    cp "$LAPTOP_ENV" "$ENV_FILE"
    warn "Copied .env.laptop → middleware/.env — fill in the FILL_IN_* values now"
  else
    warn "Create $ENV_FILE manually before starting the middleware"
  fi
fi

# ── Auto-generate MIDDLEWARE_SECRET if still placeholder ─────────────────────
if [[ -f "$ENV_FILE" ]] && grep -q "FILL_IN_RUN_openssl" "$ENV_FILE" 2>/dev/null; then
  SECRET=$(openssl rand -hex 32)
  sed -i "s/FILL_IN_RUN_openssl_rand_-hex_32/$SECRET/" "$ENV_FILE"
  echo ""
  echo -e "${YELLOW}  ┌─────────────────────────────────────────────────────┐${NC}"
  echo -e "${YELLOW}  │  MIDDLEWARE_SECRET generated:                        │${NC}"
  echo -e "${YELLOW}  │  $SECRET  │${NC}"
  echo -e "${YELLOW}  │                                                      │${NC}"
  echo -e "${YELLOW}  │  ⚠️  Copy this to Render dashboard env vars NOW       │${NC}"
  echo -e "${YELLOW}  └─────────────────────────────────────────────────────┘${NC}"
  echo ""
fi

# ── Middleware npm deps ───────────────────────────────────────────────────────
info "Installing middleware dependencies..."
cd "$MIDDLEWARE_DIR"
npm install
success "Dependencies installed"

# ── Create DB directory ───────────────────────────────────────────────────────
mkdir -p "/home/james/guidepaw-middleware"
success "DB directory ready"

# ── systemd service ───────────────────────────────────────────────────────────
NODE_BIN=$(which node)
info "Installing systemd service..."
sudo tee /etc/systemd/system/guidepaw-middleware.service > /dev/null <<EOF
[Unit]
Description=GuidePaw AI Middleware
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=james
WorkingDirectory=$MIDDLEWARE_DIR
ExecStart=$NODE_BIN server.js
Restart=always
RestartSec=10
EnvironmentFile=$ENV_FILE
StandardOutput=journal
StandardError=journal
SyslogIdentifier=guidepaw-middleware

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable guidepaw-middleware
sudo systemctl restart guidepaw-middleware
sleep 2

# ── Verify middleware ─────────────────────────────────────────────────────────
HEALTH=$(curl -sf http://localhost:3333/health 2>/dev/null || echo "FAIL")
if [[ "$HEALTH" != "FAIL" ]]; then
  success "Middleware running — http://localhost:3333/health"
  success "ZeroTier access — http://10.147.18.184:3333/health"
else
  warn "Middleware may not be running yet. Check: journalctl -u guidepaw-middleware -f"
fi

# ── UFW — ZeroTier only ───────────────────────────────────────────────────────
if command -v ufw &>/dev/null; then
  ZT_IFACE=$(ip link show 2>/dev/null | grep -o 'zt[a-z0-9]*' | head -1 || echo "")
  if [[ -n "$ZT_IFACE" ]]; then
    sudo ufw allow in on "$ZT_IFACE" to any port 3333 comment "GuidePaw Middleware ZeroTier" 2>/dev/null || true
    success "UFW: port 3333 open on $ZT_IFACE (ZeroTier only)"
  else
    warn "ZeroTier interface not found — add manually: sudo ufw allow in on ztXXX to any port 3333"
  fi
fi

# ── Make scripts executable ───────────────────────────────────────────────────
chmod +x "$REPO_PATH/scripts/"*.sh 2>/dev/null || true

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           Install Complete!                      ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo "  Status:   sudo systemctl status guidepaw-middleware"
echo "  Logs:     journalctl -u guidepaw-middleware -f"
echo "  Health:   curl http://10.147.18.184:3333/health"
echo ""
echo -e "${YELLOW}  Remaining FILL_IN_* items in middleware/.env:${NC}"
grep "FILL_IN" "$ENV_FILE" 2>/dev/null | grep -v "^#" | sed 's/=.*//' | sed 's/^/  → /' || true
echo ""
echo "  Start Claude: ./scripts/start-claude.sh"
echo "  Start Codex:  ./scripts/start-codex.sh"
echo ""
