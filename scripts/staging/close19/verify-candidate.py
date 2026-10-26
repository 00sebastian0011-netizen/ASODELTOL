"""Verify tested plugin bytes match the reviewable source, then lint PHP 8.4."""
import hashlib
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[3]
WEB = 'asodeltol-close19-webserver-1'


def main():
    installed = json.loads(subprocess.check_output(['docker', 'exec', '--user', 'www-data', WEB,
                                                  'php', '/opt/close19/candidate.php'], text=True))
    base = ROOT / 'plugins/local/asodeltolstaff'
    source = {p.relative_to(base).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
              for p in base.rglob('*') if p.is_file()}
    if installed['files'] != source:
        raise SystemExit('Candidate/runtime mismatch. Copy the candidate and rerun affected tests.')
    themebase = ROOT / 'plugins/theme/rsmax'
    themesource = {p.relative_to(themebase).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
                   for p in themebase.rglob('*') if p.is_file()}
    if installed['theme_files'] != themesource:
        raise SystemExit('Theme candidate/runtime mismatch.')
    lint = []
    for relative in source:
        if relative.endswith('.php'):
            result = subprocess.run(['docker', 'exec', WEB, 'php', '-l',
                                      '/var/www/html/public/local/asodeltolstaff/' + relative], capture_output=True, text=True)
            lint.append({'file': relative, 'status': 'PASS' if result.returncode == 0 else 'FAIL'})
    for p in pathlib.Path(__file__).parent.glob('*.php'):
        result = subprocess.run(['docker', 'exec', WEB, 'php', '-l', '/opt/close19/' + p.name], capture_output=True, text=True)
        lint.append({'file': 'scripts/staging/close19/' + p.name, 'status': 'PASS' if result.returncode == 0 else 'FAIL'})
    for relative in ['classes/output/core_renderer.php', 'version.php', 'tests/favicon_test.php']:
        result = subprocess.run(['docker', 'exec', WEB, 'php', '-l', '/var/www/html/public/theme/rsmax/' + relative], capture_output=True, text=True)
        lint.append({'file': 'plugins/theme/rsmax/' + relative, 'status': 'PASS' if result.returncode == 0 else 'FAIL'})
    report = {'status': 'PASS' if all(r['status'] == 'PASS' for r in lint) else 'FAIL',
              'plugin_version': installed['version'], 'source_matches_runtime': True, 'source_sha256': source,
              'theme_version': installed['theme_version'], 'theme_source_sha256': themesource, 'lint': lint}
    (ROOT / 'build/staging/close19/candidate.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(json.dumps({'status': report['status'], 'exact_files': len(source), 'php_lint_checks': len(lint)}))
    return 0 if report['status'] == 'PASS' else 1


if __name__ == '__main__':
    raise SystemExit(main())
