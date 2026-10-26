"""Official matching Playwright/Linux image. No Docker socket or application DB credentials exposed."""
import datetime
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[3]
OUT = ROOT / 'build/staging/close19'
IMAGE = 'mcr.microsoft.com/playwright:v1.64.1-noble'


def main():
    subprocess.run(['docker', 'exec', '--user', 'www-data', 'asodeltol-close19-webserver-1',
                    'php', '/opt/close19/isolation.php'], check=True)
    snapshot = subprocess.check_output(['docker', 'exec', '--user', 'www-data', 'asodeltol-close19-webserver-1',
                                       'php', '/opt/close19/context.php', 'allow-synthetic-trainer-messages'], text=True)
    runtime = json.loads(snapshot)
    if runtime['plugin_version'] != '2026102506' or runtime['theme_version'] != '2026102500' or not runtime['synthetic_contacts_ready']:
        raise RuntimeError('Expected candidate and synthetic fixture required.')
    (OUT / 'context-final.json').write_text(snapshot, encoding='utf-8')
    image = json.loads(subprocess.check_output(['docker', 'image', 'inspect', IMAGE], text=True))[0]
    # Execute the resolved immutable image ID, never an unverified moving tag.
    result = subprocess.run(['docker', 'run', '--rm', '--network', 'asodeltol-close19_outbound', '--ipc=host',
        '--mount', 'type=bind,source=' + str(ROOT) + ',target=/work,readonly',
        '--mount', 'type=bind,source=' + str(OUT) + ',target=/work/build/staging/close19',
        '--mount', 'type=bind,source=' + str(OUT / 'private') + ',target=/work/build/staging/close19/private,readonly',
        '--workdir', '/work', image['Id'], 'node', 'scripts/staging/close19/linux-webkit.mjs'])
    (OUT / 'linux-runtime.json').write_text(json.dumps({'image_id': image['Id'], 'repo_digests': image.get('RepoDigests', []),
        'utc': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'exit_code': result.returncode,
        'scope': 'Linux browser only; Moodle 8054 reached by in-container loopback relay; app DB not mounted'}, indent=2), encoding='utf-8')
    return result.returncode


if __name__ == '__main__':
    raise SystemExit(main())
