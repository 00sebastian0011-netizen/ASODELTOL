"""Verify and reuse an initialized private PHPUnit runtime; never run init.php."""
import datetime
import argparse
import json
import pathlib
import re
import subprocess
import sys
import xml.etree.ElementTree as ET

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'build/staging/anexo14-isolated'
WEB = 'asodeltol-moodle53-webserver-1'


def command(args, text=None):
    result = subprocess.run(args, input=text, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError('Private PHPUnit command failed: ' + result.stderr[-1000:])
    return result.stdout


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--upgrade', action='store_true', help='Upgrade plugins only inside the verified private runtime')
    options = parser.parse_args()
    previous = max(OUT.glob('*-summary.json'))
    report = json.loads(previous.read_text(encoding='utf-8'))
    workspace = report['workspace']
    assert re.fullmatch(r'/tmp/asodeltol-isolated-\d{14}', workspace)
    assert re.fullmatch(r'asqa_\d{14}', report['database'])
    context = command(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                       '/opt/asodeltol/scripts/anexo14-qa-context.php'])
    assert '"course_hidden":true' in context and '"category_hidden":true' in context
    probe = '''<?php
define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);
require($argv[1] . '/code/config.php');
if ($CFG->dbname !== $argv[2] || $CFG->dbuser !== $argv[3]
        || $CFG->dbhost !== 'database' || $CFG->prefix !== 'unused_'
        || $CFG->phpunit_prefix !== 'test_'
        || realpath($CFG->phpunit_dataroot) !== $argv[1] . '/data'
        || realpath($CFG->dataroot) !== $argv[1] . '/normaldata') { exit(20); }
$db = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
foreach ($db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all() as $grant) {
    if (str_contains($grant[0], 'ON *.*') && !str_contains($grant[0], 'GRANT USAGE ON')) { exit(21); }
    if (!str_contains($grant[0], 'ON *.*') && !str_contains($grant[0], '`' . $CFG->dbname . '`.*')) { exit(22); }
}
try { $db->query('SELECT id FROM moodle.mdl_course LIMIT 1'); exit(23); }
catch (mysqli_sql_exception $e) { if ($e->getCode() !== 1142) { exit(24); } }
if ($db->query("SHOW TABLES LIKE 'test_config'")->num_rows !== 1) { exit(25); }
echo 'ISOLATION_RECHECK_PASS';
'''
    # php - accepts no filename for stdin; arguments follow -- with the PHP file
    # descriptor path, keeping the configuration secret out of argv/output.
    proof = command(['docker', 'exec', '-i', '--user', 'www-data', WEB, 'php',
                     '/dev/stdin', workspace, report['database'], report['account']], probe)
    command(['docker', 'exec', WEB, 'cp', '-a', '/var/www/html/public/local/asodeltolstaff/.',
             workspace + '/code/public/local/asodeltolstaff/'])
    command(['docker', 'exec', WEB, 'chown', '-R', 'www-data:www-data',
             workspace + '/code/public/local/asodeltolstaff'])
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    if options.upgrade:
        upgrade = subprocess.run(['docker', 'exec', '--user', 'www-data', '--workdir', workspace + '/code', WEB,
                                  'php', 'public/admin/tool/phpunit/cli/util.php', '--upgrade'], text=True, capture_output=True)
        (OUT / (stamp + '-upgrade.txt')).write_text(upgrade.stdout + upgrade.stderr, encoding='utf-8')
        if upgrade.returncode:
            raise RuntimeError('Private plugin upgrade failed; inspect upgrade artifact')
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', '--workdir',
                             workspace + '/code', WEB, 'php', 'vendor/bin/phpunit', '-c', 'phpunit.xml',
                             '--fail-on-notice', '--fail-on-phpunit-deprecation', '--display-notices',
                             '--test-suffix', '_test.php', '--fail-on-empty-test-suite',
                             '--log-junit', workspace + '/junit-' + stamp + '.xml',
                             'public/local/asodeltolstaff/tests'], text=True, capture_output=True)
    output = result.stdout + result.stderr
    (OUT / (stamp + '-test.txt')).write_text(output, encoding='utf-8')
    command(['docker', 'cp', WEB + ':' + workspace + '/junit-' + stamp + '.xml', str(OUT / (stamp + '-junit.xml'))])
    junit = ET.parse(OUT / (stamp + '-junit.xml'))
    cases = list(junit.iter('testcase'))
    report['test_count'] = len(cases)
    report['status'] = 'PASS' if result.returncode == 0 and cases else 'FAIL'
    report['previous_run'] = previous.name
    report['checks'] = [{'id': 'ISOLATION', 'status': 'PASS', 'detail': proof},
                        {'id': 'TEST', 'status': report['status'], 'exit_code': result.returncode}]
    report['final_context'] = command(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                                       '/opt/asodeltol/scripts/anexo14-qa-context.php']).strip()
    (OUT / (stamp + '-summary.json')).write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(output)
    return 0 if report['status'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
