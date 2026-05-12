# Laptop Reconnect

This repo now includes a small laptop-side watchdog that checks whether GuidePaw is reachable and tries to recover Wi-Fi if it is not.

Files:
- `scripts/laptop_network_watchdog.sh`
- `systemd/guidepaw-laptop-watchdog.service`
- `systemd/guidepaw-laptop-watchdog.timer`

Default behavior:
- checks `https://beta.guidepaw.app/healthz.php`
- falls back to `http://127.0.0.1/healthz.php`
- nudges `nmcli` to turn networking/Wi-Fi back on
- retries saved Wi-Fi connections
- optionally runs a restart command if `GUIDEPAW_WATCHDOG_RESTART_CMD` is set

Dry run:
```bash
GUIDEPAW_WATCHDOG_DRY_RUN=yes bash scripts/laptop_network_watchdog.sh
```

Optional restart hook:
```bash
GUIDEPAW_WATCHDOG_RESTART_CMD='systemctl --user restart guidepaw-local' bash scripts/laptop_network_watchdog.sh
```

For a systemd user timer, copy the unit files into your user systemd directory and enable the timer.
