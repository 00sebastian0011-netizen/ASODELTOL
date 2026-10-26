#!/usr/bin/env bash
# Preparation only. GitHub never runs this script and cannot activate the VPS.
set -euo pipefail
mode="$1"
root="/opt/asodeltol-diagnostics"
conf_dir="/home/user/.config/asodeltol-diagnostics"
secret="$conf_dir/token"
envfile="/etc/asodeltol-diagnostics.env"
service="/etc/systemd/system/asodeltol-diagnostics.service"
source_dir="$(cd "$(dirname "$0")" && pwd)"

if [[ "$(id -u)" -ne 0 ]]; then
  echo 'Run as root only from a locally reviewed copy.' >&2
  exit 2
fi

case "$mode" in
  prepare)
    install -d -m 0755 -o root -g root "$root"
    install -m 0644 -o root -g root "$source_dir/probes.py" "$root/probes.py"
    install -m 0644 -o root -g root "$source_dir/mcp_server.py" "$root/mcp_server.py"
    install -d -m 0700 -o user -g user "$conf_dir"
    if [[ ! -f "$secret" ]]; then
      /usr/bin/python3 - "$secret" <<'PY'
from pathlib import Path
import secrets, sys, os
path = Path(sys.argv[1])
with path.open('x', encoding='ascii') as file:
    file.write(secrets.token_urlsafe(48) + '\n')
os.chmod(path, 0o600)
PY
      chown user:user "$secret"
    fi
    [[ "$(stat -c '%U:%G:%a' "$secret")" == 'user:user:600' ]] || {
      echo 'Bearer secret permissions unsafe; refusing.' >&2; exit 3;
    }
    cat > "$envfile" <<EOF
ASODELTOL_DIAG_TOKEN_FILE=$secret
ASODELTOL_DIAG_SITE_ROOT=/home/user/htdocs/srv2085040.hstgr.cloud
ASODELTOL_DIAG_MOODLEDATA=/home/user/htdocs/moodledata
ASODELTOL_DIAG_STATE_ROOT=/home/user/.local/state/asodeltol-deploy
ASODELTOL_DIAG_VHOST=/etc/nginx/sites-enabled/srv2085040.hstgr.cloud.conf
ASODELTOL_DIAG_PORT=8766
ASODELTOL_DIAG_ORIGIN=https://aulaasodeltol.online
EOF
    chown root:root "$envfile"; chmod 0644 "$envfile"
    install -m 0644 -o root -g root "$source_dir/asodeltol-diagnostics.service" "$service"
    systemctl daemon-reload
    echo 'PREPARED_ONLY=1; token not shown; no public endpoint or active service.'
    ;;
  activate)
    [[ -s "$secret" && -f "$service" && -f "$envfile" ]] || {
      echo 'Run prepare first.' >&2; exit 4;
    }
    [[ "$(stat -c '%U:%G:%a' "$secret")" == 'user:user:600' ]] || exit 5
    /usr/bin/python3 -m py_compile "$root/probes.py" "$root/mcp_server.py"
    systemctl enable --now asodeltol-diagnostics.service
    curl --fail --silent --max-time 3 http://127.0.0.1:8766/healthz >/dev/null
    echo 'LOCAL_DIAGNOSTICS_ACTIVE=1; bind loopback only.'
    ;;
  status)
    systemctl is-active asodeltol-diagnostics.service || true
    ss -ltn '( sport = :8766 )' | sed -n '1,4p'
    ;;
  disable)
    systemctl disable --now asodeltol-diagnostics.service 2>/dev/null || true
    echo 'DIAGNOSTICS_DISABLED=1; remove optional public proxy/tunnel separately.'
    ;;
  *)
    echo 'Usage: install-diagnostics.sh prepare|activate|status|disable' >&2
    exit 2
    ;;
esac
