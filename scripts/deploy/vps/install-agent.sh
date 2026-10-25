#!/usr/bin/env bash
set -euo pipefail

mode="${1:-}"
install_root='/opt/asodeltol-deploy'
state_root='/home/user/.local/state/asodeltol-deploy'
repo_root='/home/user/.local/share/asodeltol-deploy/repo'
config_root='/home/user/.config/asodeltol-deploy'
site_root='/home/user/htdocs/srv2085040.hstgr.cloud'
secret_file='/etc/asodeltol-deploy-webhook.secret'
environment_file='/etc/asodeltol-deploy.env'
unit_file='/etc/systemd/system/asodeltol-deploy.service'
nginx_site='/etc/nginx/sites-enabled/srv2085040.hstgr.cloud.conf'
nginx_backup='/etc/nginx/sites-enabled/srv2085040.hstgr.cloud.conf.pre-asodeltol-deploy'
nginx_snippet='/etc/nginx/snippets/asodeltol-deploy.conf'
key_file="$config_root/github-deploy-key"
known_hosts="$config_root/github-known-hosts"
official_ed25519='SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU'

if [[ "$(id -u)" -ne 0 ]]; then
  echo 'This installer must run as root.' >&2
  exit 2
fi

prepare() {
  install -d -m 0755 -o root -g root "$install_root"
  install -m 0755 -o root -g root ./deploy_release.py "$install_root/deploy_release.py"
  install -m 0755 -o root -g root ./receiver.py "$install_root/receiver.py"
  install -m 0644 -o root -g root ../deploy-policy.json "$install_root/deploy-policy.json"

  install -d -m 0700 -o user -g user "$config_root"
  install -d -m 0700 -o user -g user "$state_root" "$(dirname "$repo_root")"
  if [[ ! -f "$key_file" ]]; then
    runuser -u user -- ssh-keygen -q -t ed25519 -N '' -C 'asodeltol-production-deploy-readonly' -f "$key_file"
  fi

  scan="$(mktemp)"
  trap 'rm -f "$scan"' RETURN
  ssh-keyscan -T 10 -t ed25519 github.com >"$scan" 2>/dev/null
  fingerprint="$(ssh-keygen -lf "$scan" -E sha256 | awk '{print $2}')"
  [[ "$fingerprint" == "$official_ed25519" ]] || {
    echo "GitHub SSH fingerprint mismatch: $fingerprint" >&2
    exit 3
  }
  install -m 0600 -o user -g user "$scan" "$known_hosts"
  printf 'DEPLOY_PUBLIC_KEY='; cat "$key_file.pub"
}

activate() {
  [[ -s "$secret_file" ]] || { echo "Missing $secret_file" >&2; exit 4; }
  [[ "$(stat -c '%U:%G:%a' "$secret_file")" == 'root:user:640' ]] || {
    echo "Unexpected webhook secret permissions" >&2
    exit 5
  }
  [[ -s "$key_file" && -s "$known_hosts" ]] || { echo 'Run prepare first.' >&2; exit 6; }

  ssh_command="/usr/bin/ssh -i $key_file -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$known_hosts"
  if [[ ! -d "$repo_root/.git" ]]; then
    runuser -u user -- env GIT_SSH_COMMAND="$ssh_command" git clone --quiet --filter=blob:none --no-checkout --branch main \
      git@github.com:00sebastian0011-netizen/ASODELTOL.git "$repo_root"
  fi
  runuser -u user -- git -C "$repo_root" config core.sshCommand "$ssh_command"
  runuser -u user -- git -C "$repo_root" config core.hooksPath /dev/null
  runuser -u user -- git -C "$repo_root" fetch --quiet --prune origin '+refs/heads/main:refs/remotes/origin/main'

  cat >"$environment_file" <<EOF
ASODELTOL_WEBHOOK_SECRET_FILE=$secret_file
ASODELTOL_REPOSITORY=00sebastian0011-netizen/ASODELTOL
ASODELTOL_REF=refs/heads/main
ASODELTOL_DEPLOY_SCRIPT=$install_root/deploy_release.py
ASODELTOL_POLICY=$install_root/deploy-policy.json
ASODELTOL_REPO_DIR=$repo_root
ASODELTOL_STATE_DIR=$state_root
ASODELTOL_SITE_ROOT=$site_root
ASODELTOL_PUBLIC_URL=https://aulaasodeltol.online
ASODELTOL_PHP=/usr/bin/php8.4
ASODELTOL_LISTEN_HOST=127.0.0.1
ASODELTOL_LISTEN_PORT=8765
ASODELTOL_DEBOUNCE_SECONDS=1.0
EOF
  chown root:root "$environment_file"
  chmod 0644 "$environment_file"

  runuser -u user -- env \
    ASODELTOL_REPO_DIR="$repo_root" \
    ASODELTOL_STATE_DIR="$state_root" \
    ASODELTOL_SITE_ROOT="$site_root" \
    ASODELTOL_POLICY="$install_root/deploy-policy.json" \
    /usr/bin/python3 "$install_root/deploy_release.py" --initialize

  if [[ ! -f '/etc/asodeltol-deploy.local-parent-mode' ]]; then
    stat -c '%U %G %a' "$site_root/public/local" >'/etc/asodeltol-deploy.local-parent-mode'
    chmod 0600 '/etc/asodeltol-deploy.local-parent-mode'
  fi
  chgrp user "$site_root/public/local"
  chmod g+w "$site_root/public/local"

  install -d -m 0755 -o root -g root "$(dirname "$nginx_snippet")"
  install -m 0644 -o root -g root ./nginx-location.conf "$nginx_snippet"
  if ! grep -Fq 'include /etc/nginx/snippets/asodeltol-deploy.conf;' "$nginx_site"; then
    [[ ! -e "$nginx_backup" ]] || { echo "Refusing to replace existing backup: $nginx_backup" >&2; exit 7; }
    cp -a "$nginx_site" "$nginx_backup"
    python3 - "$nginx_site" <<'PY'
from pathlib import Path
import sys
path = Path(sys.argv[1])
text = path.read_text(encoding='utf-8')
anchor = '  include /etc/nginx/global_settings;'
if text.count(anchor) != 1:
    raise SystemExit('Expected exactly one HTTPS global_settings anchor')
path.write_text(text.replace(anchor, anchor + '\n  include /etc/nginx/snippets/asodeltol-deploy.conf;', 1), encoding='utf-8')
PY
  fi
  nginx -t

  install -m 0644 -o root -g root ./asodeltol-deploy.service "$unit_file"
  systemctl daemon-reload
  systemctl enable --now asodeltol-deploy.service
  systemctl reload nginx
  curl --fail --silent --show-error --max-time 3 http://127.0.0.1:8765/healthz >/dev/null
  echo 'AGENT_STATUS=active'
}

remove_agent() {
  systemctl disable --now asodeltol-deploy.service 2>/dev/null || true
  if [[ -f "$nginx_backup" ]] && grep -Fq 'include /etc/nginx/snippets/asodeltol-deploy.conf;' "$nginx_site"; then
    cp -a "$nginx_backup" "$nginx_site"
    nginx -t
    systemctl reload nginx
  fi
  echo 'Agent disabled. State, keys, backups and secrets were intentionally preserved.'
}

case "$mode" in
  prepare) prepare ;;
  activate) activate ;;
  remove) remove_agent ;;
  *) echo 'Usage: install-agent.sh prepare|activate|remove' >&2; exit 2 ;;
esac
