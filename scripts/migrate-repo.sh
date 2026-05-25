#!/usr/bin/env bash
# =============================================================================
# migrate-repo.sh
# Moves /home/james/projects/gpb3/gpb3 → /home/james/projects/guidepaw
#
# Run this ONCE on the laptop before running install.sh
# Usage: bash migrate-repo.sh
#
# What it does:
#   1. Creates /home/james/projects/guidepaw
#   2. Copies all content from gpb3/gpb3 (preserving git history)
#   3. Updates git remote to jphutching/GuidePaw (if not already set)
#   4. Verifies the new location works
#   5. Leaves gpb3/gpb3 in place (renamed to gpb3/gpb3.bak) — remove manually after confirming
# =============================================================================

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

OLD_PATH="/home/james/projects/gpb3/gpb3"
NEW_PATH="/home/james/projects/guidepaw"
BACKUP_PATH="/home/james/projects/gpb3/gpb3.bak"

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║     GuidePaw Repo Migration                      ║"
echo "║     gpb3/gpb3 → projects/guidepaw                ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

# ── Sanity checks ─────────────────────────────────────────────────────────────
if [[ ! -d "$OLD_PATH" ]]; then
  # Maybe it's somewhere else — try to find it
  FOUND=$(find /home/james/projects -name ".git" -maxdepth 4 -type d 2>/dev/null | head -5)
  if [[ -n "$FOUND" ]]; then
    warn "Could not find $OLD_PATH"
    warn "Found these git repos instead:"
    echo "$FOUND" | sed 's|/.git||'
    read -p "Enter the FULL path to your GuidePaw repo: " CUSTOM_PATH
    OLD_PATH="${CUSTOM_PATH}"
  else
    error "Could not find any git repo under /home/james/projects. Check the path."
  fi
fi

if [[ -d "$NEW_PATH" ]]; then
  if [[ -d "$NEW_PATH/.git" ]]; then
    success "Migration already done — $NEW_PATH exists with .git"
    info "Skipping migration, continuing to verification..."
  else
    error "$NEW_PATH exists but is not a git repo. Remove it manually first."
  fi
else
  # ── Stash any uncommitted changes ────────────────────────────────────────────
  info "Checking for uncommitted changes in $OLD_PATH..."
  cd "$OLD_PATH"
  if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Uncommitted changes detected — stashing..."
    git stash push -m "pre-migration stash $(date +%Y%m%d-%H%M%S)"
    STASHED=true
  else
    STASHED=false
  fi

  # ── Copy to new location ──────────────────────────────────────────────────────
  info "Creating $NEW_PATH..."
  mkdir -p "$NEW_PATH"

  info "Copying repo (this preserves git history)..."
  rsync -a --info=progress2 "$OLD_PATH/" "$NEW_PATH/"
  success "Copy complete"

  # ── Restore stash in new location ────────────────────────────────────────────
  if [[ "$STASHED" == "true" ]]; then
    cd "$NEW_PATH"
    git stash pop || warn "Could not pop stash — run 'git stash pop' manually in $NEW_PATH"
  fi

  # ── Rename old location to .bak ───────────────────────────────────────────────
  mv "$OLD_PATH" "$BACKUP_PATH"
  success "Old path renamed to $BACKUP_PATH (remove manually after confirming everything works)"
fi

# ── Update git remote ─────────────────────────────────────────────────────────
cd "$NEW_PATH"
CURRENT_REMOTE=$(git remote get-url origin 2>/dev/null || echo "none")
info "Current git remote: $CURRENT_REMOTE"

if [[ "$CURRENT_REMOTE" != "git@github.com:jphutching/GuidePaw.git" ]]; then
  if [[ "$CURRENT_REMOTE" == "none" ]]; then
    git remote add origin git@github.com:jphutching/GuidePaw.git
  else
    git remote set-url origin git@github.com:jphutching/GuidePaw.git
  fi
  success "Remote updated → git@github.com:jphutching/GuidePaw.git"
else
  success "Remote already correct"
fi

# ── Verify git works ──────────────────────────────────────────────────────────
info "Verifying git connectivity..."
if git ls-remote --exit-code origin HEAD &>/dev/null; then
  success "GitHub SSH connection works"
else
  warn "Could not connect to GitHub via SSH. Check that your SSH key is added:"
  warn "  cat ~/.ssh/id_ed25519.pub"
  warn "  Add at: https://github.com/settings/keys"
fi

# ── Copy middleware files into repo ───────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_FILES="$SCRIPT_DIR/../repo-files"

if [[ -d "$REPO_FILES" ]]; then
  info "Installing middleware files into repo..."
  cp -rn "$REPO_FILES/." "$NEW_PATH/" 2>/dev/null || true

  # Make scripts executable
  chmod +x "$NEW_PATH/scripts/"*.sh 2>/dev/null || true
  success "Middleware files installed"
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           Migration Complete                     ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo "  New repo path  : $NEW_PATH"
echo "  Backup (old)   : $BACKUP_PATH"
echo "  Git remote     : git@github.com:jphutching/GuidePaw.git"
echo ""
echo -e "${YELLOW}  NEXT STEPS:${NC}"
echo "  1. cd $NEW_PATH && git status   (verify everything looks right)"
echo "  2. git pull origin main          (sync with GitHub)"
echo "  3. bash scripts/install.sh       (install middleware + systemd)"
echo "  4. Once confirmed working:       rm -rf $BACKUP_PATH"
echo ""
