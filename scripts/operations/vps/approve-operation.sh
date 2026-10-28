#!/usr/bin/env bash
set -euo pipefail
operation="${1:-}"; shift || true
case "$operation" in backup|staff_deploy_0600|moodle_upgrade|policy_publish|policy_verify|rollback) ;; *) echo 'invalid operation' >&2; exit 2;; esac
[[ "$(id -u)" == 0 ]] || { echo 'run as root' >&2; exit 2; }
parameters='{}'
case "$operation" in
  backup) label="${1:-manual}"; [[ "$label" =~ ^[A-Za-z0-9_.-]{1,80}$ ]] || exit 2; parameters="{\"label\":\"$label\"}";;
  staff_deploy_0600) sha="${1:-}"; [[ "$sha" =~ ^[0-9a-f]{40}$ ]] || exit 2; parameters="{\"sha\":\"$sha\"}";;
  rollback) backup="${1:-}"; [[ "$backup" =~ ^[A-Za-z0-9_.-]{1,100}$ ]] || exit 2; parameters="{\"backup_id\":\"$backup\"}";;
  *) [[ $# == 0 ]] || exit 2;;
esac
dir=/var/lib/asodeltol-operations/approvals; install -d -m 0750 -o root -g user "$dir"
id="$(date +%s)-$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"; expires="$(( $(date +%s) + 900 ))"
printf '{"operation":"%s","parameters":%s,"expires_at":%s}\n' "$operation" "$parameters" "$expires" >"$dir/$id.json"
chown root:user "$dir/$id.json"; chmod 0640 "$dir/$id.json"
printf 'AUTHORIZATION_ID=%s\n' "$id"
