#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
policy="$(cd "$script_dir/.." && pwd)/deploy-policy.json"
sandbox="$(mktemp -d /tmp/asodeltol-deploy-selftest.XXXXXX)"
cleanup() {
  case "$sandbox" in
    /tmp/asodeltol-deploy-selftest.*) rm -rf -- "$sandbox" ;;
    *) echo "Unsafe self-test path: $sandbox" >&2; exit 90 ;;
  esac
}
trap cleanup EXIT

origin="$sandbox/origin.git"
work="$sandbox/work"
repo="$sandbox/repo"
site="$sandbox/site"
state="$sandbox/state"

git init --quiet --bare "$origin"
git init --quiet "$work"
git -C "$work" config user.name 'ASODELTOL deploy self-test'
git -C "$work" config user.email 'deploy-selftest@invalid.local'
mkdir -p "$work/plugins/theme/rsmax/style" \
  "$work/plugins/theme/rsmax/amd/build" \
  "$work/plugins/theme/rsmax/templates" \
  "$work/plugins/theme/rsmax/classes" \
  "$work/plugins/local/asodeltolstaff"
printf ':root { --probe: 0; }\n' >"$work/plugins/theme/rsmax/style/probe.css"
printf 'define([],function(){return "zero";});\n' >"$work/plugins/theme/rsmax/amd/build/probe.min.js"
printf '<div>zero</div>\n' >"$work/plugins/theme/rsmax/templates/probe.mustache"
printf '<?php\nclass theme_rsmax_probe { public const VALUE = "zero"; }\n' >"$work/plugins/theme/rsmax/classes/probe.php"
printf '<?php\n$plugin = new stdClass();\n' >"$work/plugins/theme/rsmax/version.php"
printf '<?php\n$plugin = new stdClass();\n' >"$work/plugins/local/asodeltolstaff/version.php"
git -C "$work" add .
git -C "$work" commit --quiet -m initial
git -C "$work" branch -M main
git -C "$work" remote add origin "$origin"
git -C "$work" push --quiet -u origin main
git clone --quiet --no-checkout --branch main "$origin" "$repo"

mkdir -p "$site/public/theme" "$site/public/local" "$site/admin/cli"
cp -a "$work/plugins/theme/rsmax" "$site/public/theme/rsmax"
cp -a "$work/plugins/local/asodeltolstaff" "$site/public/local/asodeltolstaff"
printf '<?php\necho "cache-purged\\n";\n' >"$site/admin/cli/purge_caches.php"

export ASODELTOL_REPO_DIR="$repo"
export ASODELTOL_SITE_ROOT="$site"
export ASODELTOL_STATE_DIR="$state"
export ASODELTOL_POLICY="$policy"
export ASODELTOL_PHP=/usr/bin/php8.4
export ASODELTOL_PUBLIC_URL=https://aulaasodeltol.online

/usr/bin/python3 "$script_dir/deploy_release.py" --initialize >/dev/null

deploy_change() {
  local label="$1"
  local path="$2"
  local value="$3"
  local before
  before="$(git -C "$work" rev-parse HEAD)"
  printf '%s\n' "$value" >"$work/$path"
  git -C "$work" add "$path"
  git -C "$work" commit --quiet -m "selftest $label"
  git -C "$work" push --quiet origin main
  local sha result
  sha="$(git -C "$work" rev-parse HEAD)"
  result="$(/usr/bin/python3 "$script_dir/deploy_release.py" --before "$before" --sha "$sha")"
  python3 - "$label" "$result" <<'PY'
import json
import sys
data = json.loads(sys.argv[2])
if data['status'] != 'deployed':
    raise SystemExit(f"{sys.argv[1]} was not deployed: {data}")
print(f"SELFTEST_{sys.argv[1].upper()}_SECONDS={data['duration_seconds']}")
PY
}

deploy_change css 'plugins/theme/rsmax/style/probe.css' ':root { --probe: 1; }'
deploy_change javascript 'plugins/theme/rsmax/amd/build/probe.min.js' 'define([],function(){return "one";});'
deploy_change mustache 'plugins/theme/rsmax/templates/probe.mustache' '<div>one</div>'
deploy_change php 'plugins/theme/rsmax/classes/probe.php' '<?php class theme_rsmax_probe { public const VALUE = "one"; }'

printf ':root { --probe: direct-production-edit; }\n' >"$site/public/theme/rsmax/style/probe.css"
drift_before="$(git -C "$work" rev-parse HEAD)"
printf ':root { --probe: repository-two; }\n' >"$work/plugins/theme/rsmax/style/probe.css"
git -C "$work" add plugins/theme/rsmax/style/probe.css
git -C "$work" commit --quiet -m 'selftest protected drift'
git -C "$work" push --quiet origin main
drift_sha="$(git -C "$work" rev-parse HEAD)"
drift_result="$(/usr/bin/python3 "$script_dir/deploy_release.py" --before "$drift_before" --sha "$drift_sha")"
python3 - "$drift_result" <<'PY'
import json
import sys
data = json.loads(sys.argv[1])
if data['status'] != 'manual_required':
    raise SystemExit(f"Production drift was not protected: {data}")
print('SELFTEST_DRIFT_PROTECTION=PASS')
PY
grep -q 'direct-production-edit' "$site/public/theme/rsmax/style/probe.css"

printf ':root { --probe: 1; }\n' >"$site/public/theme/rsmax/style/probe.css"
before_hash="$(sha256sum "$site/public/theme/rsmax/amd/build/probe.min.js" | awk '{print $1}')"
rollback_before="$(git -C "$work" rev-parse HEAD)"
printf 'define([],function(){return "rollback";});\n' >"$work/plugins/theme/rsmax/amd/build/probe.min.js"
git -C "$work" add plugins/theme/rsmax/amd/build/probe.min.js
git -C "$work" commit --quiet -m 'selftest rollback'
git -C "$work" push --quiet origin main
rollback_sha="$(git -C "$work" rev-parse HEAD)"
if ASODELTOL_PUBLIC_URL=http://127.0.0.1:9 /usr/bin/python3 "$script_dir/deploy_release.py" --before "$rollback_before" --sha "$rollback_sha" >/dev/null 2>&1; then
  echo 'Rollback self-test unexpectedly succeeded.' >&2
  exit 8
fi
after_hash="$(sha256sum "$site/public/theme/rsmax/amd/build/probe.min.js" | awk '{print $1}')"
[[ "$before_hash" == "$after_hash" ]] || { echo 'Rollback did not restore the previous component.' >&2; exit 9; }
echo 'SELFTEST_ROLLBACK=PASS'
echo 'SELFTEST_RESULT=PASS'
