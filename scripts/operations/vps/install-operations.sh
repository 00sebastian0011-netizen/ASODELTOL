#!/usr/bin/env bash
set -euo pipefail
[[ "$(id -u)" == 0 ]] || { echo 'Run once as root.' >&2; exit 2; }
root=/opt/asodeltol-operations; state=/home/user/.local/state/asodeltol-operations; env=/etc/asodeltol-operations.env
site=/home/user/htdocs/srv2085040.hstgr.cloud; repo=/home/user/.local/share/asodeltol-deploy/repo; deploystate=/home/user/.local/state/asodeltol-deploy
[[ -d "$site/admin/cli" && -d "$repo/.git" && -f "$site/config.php" ]] || { echo 'Expected Moodle site or deployment repository missing.' >&2; exit 3; }
install -d -m 0755 -o root -g root "$root"; install -d -m 0700 -o user -g user "$state"; install -d -m 0750 -o root -g user /var/lib/asodeltol-operations/approvals
for file in operations_server.py backup.php rollback.php policy_publish.php policy_verify.php; do install -m 0755 -o root -g root "$file" "$root/$file"; done
install -m 0750 -o root -g root approve-operation.sh /usr/local/sbin/asodeltol-approve-operation
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
echo 'OPERATIONS_MCP_STATUS=active'
