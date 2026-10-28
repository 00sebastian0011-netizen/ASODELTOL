#!/usr/bin/env bash
set -euo pipefail
[[ "$(id -u)" == 0 ]] || { echo 'run as root' >&2; exit 2; }
auth=/home/user/.ssh/authorized_keys
if [[ -f "$auth" ]]; then sed -i '/# ASODELTOL-CODEX-ADMIN$/d' "$auth"; fi
rm -f /etc/sudoers.d/asodeltol-codex-agent
echo 'ADMIN_SSH_STATUS=revoked'
