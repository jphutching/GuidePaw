#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-dry-run}"

case "$MODE" in
  dry-run)
    php scripts/cleanup_e2e_data.php --dry-run
    ;;
  delete|yes)
    php scripts/cleanup_e2e_data.php --yes
    ;;
  *)
    echo "Usage: scripts/run_e2e_cleanup.sh [dry-run|delete]"
    exit 1
    ;;
esac
