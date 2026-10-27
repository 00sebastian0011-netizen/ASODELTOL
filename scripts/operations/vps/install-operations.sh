#!/usr/bin/env bash
set -euo pipefail
[[ "$(id -u)" == 0 ]] || { echo 'Run once as root.' >&2; exit 2; }
admin_ssh=0; [[ "${1:-}" == "--enable-admin-ssh" ]] && admin_ssh=1
root=/opt/asodeltol-operations; state=/home/user/.local/state/asodeltol-operations; env=/etc/asodeltol-operations.env
site=/home/user/htdocs/srv2085040.hstgr.cloud; repo=/home/user/.local/share/asodeltol-deploy/repo; deploystate=/home/user/.local/state/asodeltol-deploy
[[ -d "$site/admin/cli" && -d "$repo/.git" && -f "$site/config.php" ]] || { echo 'Expected Moodle site or deployment repository missing.' >&2; exit 3; }
install -d -m 0755 -o root -g root "$root"; install -d -m 0700 -o user -g user "$state"; install -d -m 0750 -o root -g user /var/lib/asodeltol-operations/approvals
for file in operations_server.py backup.php rollback.php policy_publish.php policy_verify.php; do install -m 0755 -o root -g root "$file" "$root/$file"; done
install -m 0750 -o root -g root approve-operation.sh /usr/local/sbin/asodeltol-approve-operation
install -m 0755 -o root -g root asodeltol-agent-command /usr/local/lib/asodeltol-agent-command
install -m 0644 -o root -g root asodeltol-operations.service /etc/systemd/system/asodeltol-operations.service
install -m 0644 -o root -g root ../../../scripts/deploy/vps/deploy_release.py "$root/deploy_release.py"
install -m 0644 -o root -g root ../../../scripts/deploy/deploy-policy.json "$root/deploy-policy.json"
install -m 0644 -o root -g root ../../../docs/anexo14/qa/POLITICA_DATOS_AUTORIZACION_ASODELTOL_2026.md "$root/policy.md"
token=/etc/asodeltol-operations.token
if [[ ! -s "$token" ]]; then umask 077; head -c 48 /dev/urandom | base64 -w0 >"$token"; fi
chown root:user "$token"; chmod 0640 "$token"
cat >"$env" <<EOF
ASODELTOL_OPS_TOKEN_FILE=$token
ASODELTOL_OPS_APPROVAL_DIR=/var/lib/asodeltol-operations/approvals
ASODELTOL_OPS_STATE_DIR=$state
ASODELTOL_OPS_ROOT=$root
ASODELTOL_SITE_ROOT=$site
ASODELTOL_MOODLEDATA=/home/user/htdocs/moodledata
ASODELTOL_REPO_DIR=$repo
ASODELTOL_DEPLOY_STATE_DIR=$deploystate
ASODELTOL_STATE_DIR=$deploystate
ASODELTOL_POLICY=$root/deploy-policy.json
ASODELTOL_POLICY_SOURCE=$root/policy.md
ASODELTOL_PHP=/usr/bin/php8.4
ASODELTOL_PUBLIC_URL=https://aulaasodeltol.online
ASODELTOL_OPS_PORT=8767
EOF
chmod 0640 "$env"; chown root:user "$env"
systemctl daemon-reload; systemctl enable --now asodeltol-operations.service
for n in {1..10}; do curl -fsS --max-time 2 http://127.0.0.1:8767/healthz >/dev/null && break; sleep 1; done
systemctl is-active --quiet asodeltol-operations.service || { journalctl -u asodeltol-operations.service -n 30 --no-pager >&2; exit 4; }
if [[ "$admin_ssh" == 1 ]]; then
  key="${ASODELTOL_AGENT_SSH_PUBLIC_KEY:-}"
  [[ "$key" =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+\ .+$ ]] || { echo 'Set ASODELTOL_AGENT_SSH_PUBLIC_KEY to one dedicated ed25519 public key.' >&2; exit 5; }
  sshdir=/home/user/.ssh; auth="$sshdir/authorized_keys"; install -d -m 0700 -o user -g user "$sshdir"; touch "$auth"; chown user:user "$auth"; chmod 0600 "$auth"
  marker='# ASODELTOL-CODEX-ADMIN'
  grep -Fq "$marker" "$auth" || printf 'restrict,command="/usr/local/lib/asodeltol-agent-command" %s %s\n' "$key" "$marker" >>"$auth"
  printf 'user ALL=(ALL) NOPASSWD: ALL\n' >/etc/sudoers.d/asodeltol-codex-agent
  chmod 0440 /etc/sudoers.d/asodeltol-codex-agent; visudo -cf /etc/sudoers.d/asodeltol-codex-agent >/dev/null
  sudo -u user ssh -T -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR localhost true >/dev/null 2>&1 || true
  echo 'ADMIN_SSH_STATUS=enabled'
fi
echo 'OPERATIONS_MCP_STATUS=active'
