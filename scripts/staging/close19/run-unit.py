"""Verify isolation and exact installed version, then preserve every PHPUnit run."""
import datetime
import json
import pathlib
import re
import subprocess
import xml.etree.ElementTree as ET

ROOT = pathlib.Path(__file__).resolve().parents[3]
OUT = ROOT / 'build/staging/close19/unit'
WEB = 'asodeltol-close19-webserver-1'


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S')
    proof = subprocess.run(['docker', 'exec', '--user', 'www-data', WEB, 'php', '/opt/close19/isolation.php'],
                           capture_output=True, text=True, check=True).stdout
    version = subprocess.run(['docker', 'exec', '--user', 'www-data', WEB, 'php',
        '/var/www/html/admin/cli/cfg.php', '--component=local_asodeltolstaff', '--name=version'],
        capture_output=True, text=True, check=True).stdout.strip()
    expected = re.search(r'\$plugin->version\s*=\s*(\d+)', (ROOT / 'plugins/local/asodeltolstaff/version.php').read_text()).group(1)
    if version != expected:
        raise SystemExit('Installed version does not match candidate.')
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', '--workdir', '/var/www/html', WEB,
        'php', 'vendor/bin/phpunit', '-c', 'phpunit.xml', '--test-suffix', '_test.php', '--fail-on-empty-test-suite',
        '--fail-on-notice', '--fail-on-warning', '--fail-on-phpunit-deprecation', '--display-notices',
        '--log-junit', '/tmp/close19-' + stamp + '.xml', 'public/local/asodeltolstaff/tests'], capture_output=True, text=True)
    output = result.stdout + result.stderr
    (OUT / (stamp + '.txt')).write_text(output, encoding='utf-8')
    junit = OUT / (stamp + '.xml')
    subprocess.run(['docker', 'cp', WEB + ':/tmp/close19-' + stamp + '.xml', str(junit)], capture_output=True, check=True)
    tree = ET.parse(junit)
    cases = list(tree.iter('testcase'))
    report = {'status': 'PASS' if result.returncode == 0 and cases else 'FAIL', 'exit_code': result.returncode,
        'tests': len(cases), 'assertions': sum(int(c.get('assertions', 0)) for c in cases),
        'errors': sum(len(c.findall('error')) for c in cases), 'failures': sum(len(c.findall('failure')) for c in cases),
        'plugin_version': version, 'isolation': proof.strip(), 'environment': 'private close19_unit DB and data volume'}
    (OUT / (stamp + '.json')).write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(output)
    print(json.dumps(report))
    return 0 if report['status'] == 'PASS' else 1


if __name__ == '__main__':
    raise SystemExit(main())
