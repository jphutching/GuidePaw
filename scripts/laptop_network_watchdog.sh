#!/usr/bin/env bash
set -euo pipefail

SITE_URL="${GUIDEPAW_WATCHDOG_SITE_URL:-https://beta.guidepaw.app/healthz.php}"
LOCAL_URL="${GUIDEPAW_WATCHDOG_LOCAL_URL:-http://127.0.0.1/healthz.php}"
RESTART_CMD="${GUIDEPAW_WATCHDOG_RESTART_CMD:-}"
DRY_RUN="${GUIDEPAW_WATCHDOG_DRY_RUN:-no}"
LOG_PREFIX="[guidepaw-watchdog]"

log() {
  printf '%s %s\n' "$LOG_PREFIX" "$*"
}

run_cmd() {
  if [[ "$DRY_RUN" == "yes" ]]; then
    log "dry-run: $*"
    return 0
  fi
  "$@"
}

url_ok() {
  local url="$1"
  curl -fsS --max-time 8 "$url" >/dev/null 2>&1
}

reconnect_wifi() {
  if ! command -v nmcli >/dev/null 2>&1; then
    log "nmcli not found; skipping Wi-Fi reconnect attempts"
    return 1
  fi

  log "nudging NetworkManager"
  run_cmd nmcli networking on || true
  run_cmd nmcli radio wifi on || true
  run_cmd nmcli dev wifi rescan || true

  local connections
  mapfile -t connections < <(nmcli -t -f NAME,TYPE connection show | awk -F: '$2 == "802-11-wireless" { print $1 }' | sed '/^$/d')
  if [[ ${#connections[@]} -eq 0 ]]; then
    log "no saved Wi-Fi connections found"
    return 1
  fi

  local conn
  for conn in "${connections[@]}"; do
    log "trying saved Wi-Fi connection: $conn"
    if run_cmd nmcli con up id "$conn"; then
      sleep 5
      if url_ok "$SITE_URL" || url_ok "$LOCAL_URL"; then
        log "connectivity restored through $conn"
        return 0
      fi
    fi
  done

  return 1
}

restart_site_if_needed() {
  if [[ -z "$RESTART_CMD" ]]; then
    return 1
  fi

  log "running site restart command"
  if [[ "$DRY_RUN" == "yes" ]]; then
    log "dry-run: $RESTART_CMD"
    return 0
  fi

  bash -lc "$RESTART_CMD"
}

main() {
  if url_ok "$SITE_URL" || url_ok "$LOCAL_URL"; then
    log "site check ok"
    exit 0
  fi

  log "site check failed; attempting Wi-Fi recovery"

  if reconnect_wifi; then
    if url_ok "$SITE_URL" || url_ok "$LOCAL_URL"; then
      log "site check ok after Wi-Fi recovery"
      exit 0
    fi
  fi

  log "Wi-Fi recovery did not restore the site"

  if restart_site_if_needed; then
    sleep 3
    if url_ok "$SITE_URL" || url_ok "$LOCAL_URL"; then
      log "site check ok after restart command"
      exit 0
    fi
  fi

  log "still down"
  exit 1
}

main "$@"
