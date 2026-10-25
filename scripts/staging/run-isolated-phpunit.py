"""Run plugin tests on a private code copy and a database-only MySQL account.

No existing database, volume, course, user or evidence is removed. Secrets never
appear in process arguments or reports. The local Docker runtime is required.
"""
import json
import pathlib
import secrets
import subprocess
import datetime
import sys
import zipfile
import os
import concurrent.futures
import itertools

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'build/staging/anexo14-isolated'
WEB = 'asodeltol-moodle53-webserver-1'
DB = 'asodeltol-moodle53-database-1'


def run(args, stdin=None):
    result = subprocess.run(args, input=stdin, text=True, capture_output=True)
    if result.returncode:
        # Never include stdin, which can contain the private database password.
        raise RuntimeError(f'{args[0:3]} exited {result.returncode}: {result.stderr[-1500:]}')
    return result.stdout


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    database = 'asqa_' + stamp
    account = 'qa_' + stamp
    password = secrets.token_hex(32)
    workspace = '/tmp/asodeltol-isolated-' + stamp
    report = {'database': database, 'account': account, 'workspace': workspace,
              'environment': 'local-only', 'status': 'BLOCKED', 'checks': []}
    try:
        branch = run(['git', 'branch', '--show-current']).strip()
        assert branch == 'moodle-completo-2026-10-24', 'Unexpected branch'
        context = run(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                       '/opt/asodeltol/scripts/anexo14-qa-context.php'])
        assert '"course_hidden":true' in context and '"category_hidden":true' in context
        # A new account gets privileges exclusively on a newly named database.
        sql = (f"CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
               f"CREATE USER '{account}'@'%' IDENTIFIED BY '{password}';\n"
               f"GRANT ALL PRIVILEGES ON `{database}`.* TO '{account}'@'%';\n")
        run(['docker', 'exec', '-i', DB, 'sh', '-c',
             'MYSQL_PWD="$(cat /run/secrets/db_root_password)" mysql --user=root'], sql)
        run(['docker', 'exec', WEB, 'mkdir', '-p', workspace + '/code', workspace + '/data', workspace + '/normaldata'])
        print('Packaging local Moodle without config.php for private test workspace...', flush=True)
        source = ROOT / 'build/staging/moodle53/moodle'
        if os.name == 'nt':
            source = pathlib.Path('\\\\?\\' + str(source.resolve()))
        assert (source / 'public/lib/setup.php').is_file(), 'Local Moodle source missing'
        archive = OUT / (stamp + '-code.zip')
        def entries():
            for parent, dirs, files in os.walk(source):
                dirs[:] = [d for d in dirs if d != '.git']
                for name in files:
                    path = pathlib.Path(parent) / name
                    relative = path.relative_to(source).as_posix()
                    if relative != 'config.php':
                        yield path, relative

        def read(entry):
            path, relative = entry
            return relative, path.read_bytes()

        with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_STORED) as bundle, \
                concurrent.futures.ThreadPoolExecutor(max_workers=16) as pool:
            iterator = iter(entries())
            count = 0
            while batch := list(itertools.islice(iterator, 128)):
                for relative, data in pool.map(read, batch):
                    bundle.writestr(relative, data)
                    count += 1
                if count % 4096 == 0:
                    print('Packaged files:', count, flush=True)
        run(['docker', 'cp', str(archive), WEB + ':' + workspace + '/code.zip'])
        extract = '$z=new ZipArchive(); if($z->open($argv[1])!==true || !$z->extractTo($argv[2])){exit(1);}'
        run(['docker', 'exec', WEB, 'php', '-r', extract, workspace + '/code.zip', workspace + '/code'])
        run(['docker', 'exec', WEB, 'mkdir', '-p', workspace + '/code/public/local/asodeltolstaff'])
        run(['docker', 'exec', WEB, 'cp', '-a', '/var/www/html/public/local/asodeltolstaff/.',
             workspace + '/code/public/local/asodeltolstaff/'])
        config = f'''<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'database';
$CFG->dbname = '{database}';
$CFG->dbuser = '{account}';
$CFG->dbpass = '{password}';
$CFG->prefix = 'unused_';
$CFG->dboptions = ['dbcollation' => 'utf8mb4_unicode_ci'];
$CFG->wwwroot = 'http://127.0.0.1:8053';
$CFG->dataroot = '{workspace}/normaldata';
$CFG->phpunit_prefix = 'test_';
$CFG->phpunit_dataroot = '{workspace}/data';
$CFG->noemailever = true;
$CFG->directorypermissions = 0770;
require_once(__DIR__ . '/lib/setup.php');
'''
        run(['docker', 'exec', '-i', WEB, 'sh', '-c', 'umask 077; cat > "$1"', 'sh',
             workspace + '/code/config.php'], config)
        run(['docker', 'exec', WEB, 'chown', '-R', 'www-data:www-data', workspace])
        probe = f'''<?php
$db = new mysqli('database', '{account}', '{password}', '{database}');
$grants = $db->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all();
foreach ($grants as $grant) {{
    if (str_contains($grant[0], 'ON *.*') && !str_contains($grant[0], 'GRANT USAGE ON')) {{ exit(20); }}
    if (!str_contains($grant[0], 'ON *.*') && !str_contains($grant[0], '`{database}`.*')) {{ exit(21); }}
}}
try {{ $db->query('SELECT id FROM moodle.mdl_course LIMIT 1'); exit(22); }}
catch (mysqli_sql_exception $e) {{ if ($e->getCode() !== 1142) {{ exit(23); }} }}
if ($db->query('SHOW TABLES')->num_rows !== 0) {{ exit(24); }}
echo "ISOLATION_PASS: restricted grants; staging mdl_course SELECT denied; private DB empty\\n";
'''
        isolation = run(['docker', 'exec', '-i', '--user', 'www-data', WEB, 'php'], probe)
        report['checks'].append({'id': 'ISOLATION', 'status': 'PASS', 'detail': isolation.strip()})
        print(isolation, flush=True)
        for label, command in [
            ('INIT', ['php', 'public/admin/tool/phpunit/cli/init.php', '--disable-composer']),
            ('TEST', ['php', 'vendor/bin/phpunit', '-c', 'phpunit.xml',
                      '--fail-on-notice', '--fail-on-phpunit-deprecation',
                      'public/local/asodeltolstaff/tests/manager_test.php']),
        ]:
            result = subprocess.run(['docker', 'exec', '--user', 'www-data', '--workdir',
                                     workspace + '/code', WEB] + command,
                                    text=True, capture_output=True)
            output = (result.stdout + result.stderr).replace(password, '[REDACTED]')
            (OUT / f'{stamp}-{label.lower()}.txt').write_text(output, encoding='utf-8')
            report['checks'].append({'id': label, 'status': 'PASS' if result.returncode == 0 else 'FAIL',
                                     'exit_code': result.returncode})
            print(label, result.returncode, output[-1800:], flush=True)
            if result.returncode:
                report['status'] = 'FAIL'
                raise RuntimeError(label + ' failed; see sanitized evidence')
        report['status'] = 'PASS'
    except Exception as exc:
        report['error'] = str(exc).replace(password, '[REDACTED]')
        print(report['error'], flush=True)
    finally:
        report['final_context'] = run(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                                      '/opt/asodeltol/scripts/anexo14-qa-context.php']).strip()
        (OUT / f'{stamp}-summary.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
        print('FINAL_STATUS=' + report['status'], flush=True)
    return 0 if report['status'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
