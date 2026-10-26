"""Create an independent disposable-by-design runtime, but never delete existing data.

The code volume lives in Linux for reproducible IO. Existing staging at 8053 is
read only as the source of a previously packaged Moodle archive, never its DB.
"""
import argparse
import hashlib
import json
import os
import pathlib
import secrets
import subprocess
import time

ROOT = pathlib.Path(__file__).resolve().parents[3]
OUT = ROOT / 'build/staging/close19'
WEB = 'asodeltol-close19-webserver-1'
DB = 'asodeltol-close19-database-1'


def run(args, stdin=None):
    result = subprocess.run(args, input=stdin, text=True, capture_output=True)
    if result.returncode:
        # stdin may be a secret-bearing SQL statement, never include it.
        error = result.stderr[-700:]
        for secretfile in (OUT / 'private').glob('*password*.txt'):
            value = secretfile.read_text().strip()
            if value:
                error = error.replace(value, '[REDACTED]')
        raise RuntimeError('Command failed: ' + ' '.join(args[:3]) + ': ' + error)
    return result.stdout


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--archive', required=True, type=pathlib.Path)
    parser.add_argument('--phpunit', action='store_true')
    parser.add_argument('--monitor', action='store_true')
    args = parser.parse_args()
    if not args.archive.is_file():
        raise SystemExit('A local prepackaged Moodle archive is required.')
    OUT.mkdir(parents=True, exist_ok=True)
    private = OUT / 'private'
    private.mkdir(exist_ok=True)
    for name in ['db-password', 'db-root-password', 'phpunit-password', 'admin-password', 'synthetic-password']:
        path = private / (name + '.txt')
        if not path.exists():
            path.write_text('Qa!9' + secrets.token_hex(32), encoding='utf-8')
    os.environ['CLOSE19_PRIVATE'] = private.as_posix()
    os.environ['CLOSE19_SCRIPTS'] = (ROOT / 'scripts/staging/close19').as_posix()
    (OUT / 'monitor').mkdir(exist_ok=True)
    os.environ['CLOSE19_EVIDENCE'] = (OUT / 'monitor').as_posix()
    compose = ['docker', 'compose', '-f', str(ROOT / 'infra/local/close19/compose.yml')]
    if args.monitor:
        compose += ['--profile', 'monitoring']
    print('Starting isolated close19 containers (8054)...', flush=True)
    run(compose + ['up', '-d'])
    present = run(['docker', 'exec', WEB, 'sh', '-c', 'test -f /var/www/html/public/version.php && echo present || true'])
    if not present.strip():
        print('Importing cached Moodle code to the new Linux code volume...', flush=True)
        run(['docker', 'cp', str(args.archive.resolve()), WEB + ':/tmp/moodle-source.zip'])
        # Omit the root runtime configuration and all vendored plugin copies.
        extract = '''$z=new ZipArchive(); if($z->open('/tmp/moodle-source.zip')!==true){exit(1);}
        for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i);
          if($n==='config.php'||str_starts_with($n,'public/local/asodeltolstaff/')||str_contains($n,'/.git/'))continue;
          if(!$z->extractTo('/var/www/html',[$n])){exit(2);}}
        '''
        run(['docker', 'exec', WEB, 'php', '-r', extract])
        run(['docker', 'cp', str(ROOT / 'scripts/staging/close19/runtime-config.php.template'), WEB + ':/var/www/html/config.php'])
    run(['docker', 'exec', WEB, 'mkdir', '-p', '/var/www/html/public/local/asodeltolstaff',
         '/var/www/moodledata', '/var/www/phpunitdata'])
    run(['docker', 'cp', str(ROOT / 'plugins/local/asodeltolstaff') + '/.', WEB + ':/var/www/html/public/local/asodeltolstaff/'])
    run(['docker', 'exec', WEB, 'mkdir', '-p', '/var/www/html/public/theme/rsmax'])
    run(['docker', 'cp', str(ROOT / 'plugins/theme/rsmax') + '/.', WEB + ':/var/www/html/public/theme/rsmax/'])
    run(['docker', 'exec', WEB, 'chown', '-R', 'www-data:www-data', '/var/www/html',
         '/var/www/moodledata', '/var/www/phpunitdata'])
    password = (private / 'phpunit-password.txt').read_text()
    sql = ("CREATE DATABASE IF NOT EXISTS close19_unit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
           f"CREATE USER IF NOT EXISTS 'close19_unit'@'%' IDENTIFIED BY '{password}';\n"
           "GRANT ALL PRIVILEGES ON close19_unit.* TO 'close19_unit'@'%';\n")
    run(['docker', 'exec', '-i', DB, 'sh', '-c',
         'MYSQL_PWD="$(cat /run/secrets/db_root_password)" mysql -u root'], sql)
    proof = run(['docker', 'exec', '--user', 'www-data', WEB, 'php', '/opt/close19/isolation.php'])
    print(proof.strip(), flush=True)
    check = run(['docker', 'exec', WEB, 'sh', '-c',
                 'test -f /var/www/html/.close19-installed && echo yes || true'])
    if not check.strip():
        run(['docker', 'cp', str(ROOT / 'scripts/staging/close19/install.php'), WEB + ':/var/www/html/admin/cli/close19-install.php'])
        print('Installing only the new close19 database...', flush=True)
        output = run(['docker', 'exec', '--user', 'www-data', WEB, 'php', '/var/www/html/admin/cli/close19-install.php'])
        (OUT / 'install.txt').write_text(output, encoding='utf-8')
        run(['docker', 'exec', WEB, 'touch', '/var/www/html/.close19-installed'])
    else:
        output = run(['docker', 'exec', '--user', 'www-data', WEB, 'php', '/var/www/html/admin/cli/upgrade.php', '--non-interactive'])
        (OUT / ('upgrade-' + str(int(time.time())) + '.txt')).write_text(output, encoding='utf-8')
    if args.phpunit:
        # Recheck immediately before init; the app and PHPUnit accounts cannot access each other's DB.
        run(['docker', 'exec', '--user', 'www-data', WEB, 'php', '/opt/close19/isolation.php'])
        initialised = run(['docker', 'exec', WEB, 'sh', '-c', 'test -f /var/www/html/phpunit.xml && echo yes || true'])
        if not initialised.strip():
            print('Initializing PHPUnit exclusively in the verified unit DB/dataroot...', flush=True)
            output = run(['docker', 'exec', '--user', 'www-data', '--workdir', '/var/www/html', WEB,
                          'php', 'public/admin/tool/phpunit/cli/init.php', '--disable-composer'])
            (OUT / 'phpunit-init.txt').write_text(output, encoding='utf-8')
    (OUT / 'runtime.json').write_text(json.dumps({'base': 'http://127.0.0.1:8054', 'server': WEB,
        'database_container': DB, 'app_database': 'close19', 'unit_database': 'close19_unit',
        'archive_sha256': hashlib.file_digest(args.archive.open('rb'), 'sha256').hexdigest(),
        'isolation': proof.strip(), 'production_touched': False}, indent=2), encoding='utf-8')
    print('ISOLATED_RUNTIME_READY', flush=True)


if __name__ == '__main__':
    main()
