#!/usr/bin/env bash
# Preparation only. GitHub never runs this script and cannot activate the VPS.
set -euo pipefail
mode="$1"
root="/opt/asodeltol-diagnostics"
worker_root="/opt/asodeltol-validation-worker"
conf_dir="/home/user/.config/asodeltol-diagnostics"
secret="$conf_dir/token"
envfile="/etc/asodeltol-diagnostics.env"
service="/etc/systemd/system/asodeltol-diagnostics.service"
worker_conf_dir="/home/user/.config/asodeltol-validation"
validation_state="/var/lib/asodeltol-validation"
validation_catalog="$root/production-test-catalog.json"
worker_envfile="/etc/asodeltol-validation-worker.env"
worker_service="/etc/systemd/system/asodeltol-validation-worker.service"
worker_timer="/etc/systemd/system/asodeltol-validation-worker.timer"
backup_root="/var/backups/asodeltol-diagnostics"
backup_manifest="/var/lib/asodeltol-diagnostics/last-backup.manifest"
source_dir="$(cd "$(dirname "$0")" && pwd)"
repo_root="$(cd "$source_dir/../../.." && pwd)"

if [[ "$mode" != "validate" && "$(id -u)" -ne 0 ]]; then
  echo 'Run as root only from a locally reviewed copy.' >&2
  exit 2
fi

case "$mode" in
  validate)
    required=(
      "$source_dir/probes.py" "$source_dir/mcp_server.py" "$source_dir/validation_service.py"
      "$source_dir/asodeltol-diagnostics.service" "$source_dir/asodeltol-validation-worker.service"
      "$source_dir/asodeltol-validation-worker.timer" "$repo_root/docs/anexo14/qa/production-test-catalog.json"
      "$repo_root/scripts/anexo14/production-validation.mjs" "$repo_root/scripts/anexo14/validation-guards.mjs"
      "$source_dir/validation_executor.mjs"
    )
    for file in "${required[@]}"; do [[ -f "$file" ]] || { echo "MISSING=$file" >&2; exit 10; }; done
    ! grep -Eqi 'aulas\.prospectiva\.edu\.co' "$repo_root/scripts/anexo14/production-validation.mjs" "$source_dir/validation_executor.mjs"
    grep -Fq 'https://aulaasodeltol.online' "$repo_root/scripts/anexo14/production-validation.mjs" "$source_dir/validation_executor.mjs"
    grep -Fq 'from validation_service import' "$source_dir/mcp_server.py"
    grep -Fq 'ReadWritePaths=/var/lib/asodeltol-validation' "$source_dir/asodeltol-validation-worker.service"
    echo 'INSTALL_VALIDATION=PASS; required files, origin guard, dependency copy and worker isolation verified.'
    ;;
  prepare)
    install -d -m 0755 -o root -g root "$root" "$worker_root" "$backup_root"
    install -d -m 0700 -o root -g root "$(dirname "$backup_manifest")"
    backup_dir="$backup_root/$(date -u +%Y%m%dT%H%M%SZ)"
    install -d -m 0700 -o root -g root "$backup_dir"
    backup_tmp="$backup_manifest.tmp"
    : > "$backup_tmp"
    for target in "$root/probes.py" "$root/mcp_server.py" "$root/validation_service.py" "$validation_catalog" "$service" "$worker_service" "$worker_timer" "$envfile" "$worker_envfile"; do
      if [[ -f "$target" ]]; then
        saved="$backup_dir/$(basename "$target")"
        cp -a "$target" "$saved"
        printf 'PRESENT|%s|%s\n' "$target" "$saved" >> "$backup_tmp"
      else
        printf 'ABSENT|%s|\n' "$target" >> "$backup_tmp"
      fi
    done
    install -m 0600 -o root -g root "$backup_tmp" "$backup_manifest"
    rm -f "$backup_tmp"
    install -m 0644 -o root -g root "$source_dir/probes.py" "$root/probes.py"
    install -m 0644 -o root -g root "$source_dir/mcp_server.py" "$root/mcp_server.py"
    install -m 0644 -o root -g root "$source_dir/validation_service.py" "$root/validation_service.py"
    install -m 0644 -o root -g root "$repo_root/docs/anexo14/qa/production-test-catalog.json" "$validation_catalog"
    install -m 0755 -o root -g root "$source_dir/validation_executor.mjs" "$worker_root/validation_executor.mjs"
    install -m 0644 -o root -g root "$repo_root/scripts/anexo14/production-validation.mjs" "$worker_root/production-validation.mjs"
    install -m 0644 -o root -g root "$repo_root/scripts/anexo14/validation-guards.mjs" "$worker_root/validation-guards.mjs"
    install -d -m 0700 -o user -g user "$validation_state" "$validation_state/jobs" "$validation_state/evidence"
    install -d -m 0700 -o user -g user "$worker_conf_dir"
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
ASODELTOL_VALIDATION_CATALOG_FILE=$validation_catalog
ASODELTOL_VALIDATION_STATE_DIR=$validation_state
ASODELTOL_VALIDATION_RUN_AUTH_FILE=$worker_conf_dir/run-authorization.json
EOF
    cat > "$worker_envfile" <<EOF
ASODELTOL_VALIDATION_CATALOG_FILE=$validation_catalog
ASODELTOL_VALIDATION_STATE_DIR=$validation_state
ASODELTOL_VALIDATION_RUN_AUTH_FILE=$worker_conf_dir/run-authorization.json
ASODELTOL_QA_ACCOUNT_FILE=$worker_conf_dir/qa-account.json
ASODELTOL_QA_CONFIG_FILE=$worker_conf_dir/validation-config.json
ASODELTOL_QA_REPO_ROOT=$worker_root
ASODELTOL_QA_RUNNER=$worker_root/production-validation.mjs
ASODELTOL_QA_REPORT_ROOT=$validation_state/evidence
ASODELTOL_QA_EXECUTOR_TIMEOUT_MS=90000
EOF
    chown root:user "$envfile" "$worker_envfile"; chmod 0640 "$envfile" "$worker_envfile"
    install -m 0644 -o root -g root "$source_dir/asodeltol-diagnostics.service" "$service"
    install -m 0644 -o root -g root "$source_dir/asodeltol-validation-worker.service" "$worker_service"
    install -m 0644 -o root -g root "$source_dir/asodeltol-validation-worker.timer" "$worker_timer"
    systemctl daemon-reload
    echo 'PREPARED_ONLY=1; token/account/auth files not created or shown; diagnostics and worker remain inactive.'
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
  activate-worker)
    [[ -f "$worker_service" && -f "$worker_timer" && -s "$worker_conf_dir/qa-account.json" && -s "$worker_conf_dir/validation-config.json" && -s "$worker_conf_dir/run-authorization.json" ]] || {
      echo 'Worker activation requires private QA account, config and unexpired authorization files.' >&2; exit 6;
    }
    for file in "$worker_conf_dir/qa-account.json" "$worker_conf_dir/validation-config.json" "$worker_conf_dir/run-authorization.json"; do
      [[ "$(stat -c '%U:%G:%a' "$file")" == 'user:user:600' ]] || { echo "Unsafe permissions: $file" >&2; exit 7; }
    done
    /usr/bin/node --check "$worker_root/validation_executor.mjs"
    /usr/bin/node --check "$worker_root/production-validation.mjs"
    systemctl enable --now asodeltol-validation-worker.timer
    echo 'VALIDATION_WORKER_ACTIVE=1; timer enabled, one bounded job per activation.'
    ;;
  status)
    systemctl is-active asodeltol-diagnostics.service || true
    systemctl is-active asodeltol-validation-worker.timer || true
    ss -ltn '( sport = :8766 )' | sed -n '1,4p'
    ;;
  disable)
    systemctl disable --now asodeltol-diagnostics.service 2>/dev/null || true
    systemctl disable --now asodeltol-validation-worker.timer 2>/dev/null || true
    echo 'DIAGNOSTICS_DISABLED=1; remove optional public proxy/tunnel separately.'
    ;;
  rollback)
    [[ -s "$backup_manifest" ]] || { echo 'No verified local backup manifest.' >&2; exit 8; }
    systemctl disable --now asodeltol-validation-worker.timer 2>/dev/null || true
    while IFS='|' read -r state target saved; do
      [[ -n "$state" && -n "$target" ]] || continue
      if [[ "$state" == 'PRESENT' && -f "$saved" ]]; then
        install -m 0644 -o root -g root "$saved" "$target"
      elif [[ "$state" == 'ABSENT' ]]; then
        rm -f "$target"
      fi
    done < "$backup_manifest"
    systemctl daemon-reload
    echo 'ROLLBACK_PREPARED=1; worker disabled; restored files from the root-owned manifest.'
    ;;
  *)
    echo 'Usage: install-diagnostics.sh validate|prepare|activate|activate-worker|status|disable|rollback' >&2
    exit 2
    ;;
esac
