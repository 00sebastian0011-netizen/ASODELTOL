"""Download, reconcile and verify actual Moodle enrollment/completion exports."""
import csv
import datetime
import http.cookiejar
import io
import json
import pathlib
import re
import subprocess
import urllib.parse
import urllib.request
import openpyxl

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:8053'


def session(username, secret):
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    html = client.open(BASE + '/login/index.php', timeout=60).read().decode()
    token = re.search(r'name="logintoken"\s+value="([^"]+)"', html)
    assert token, 'Login token missing'
    password = secret.read_text().strip()
    data = urllib.parse.urlencode({'username': username, 'password': password, 'logintoken': token[1]}).encode()
    client.open(BASE + '/login/index.php', data, timeout=60).read()
    password = ''
    profile = client.open(BASE + '/user/profile.php', timeout=60).read().decode()
    assert '/login/logout.php' in profile, 'Authentication not proven'
    return client


def main():
    output = subprocess.run(['docker', 'exec', '--user', 'www-data', 'asodeltol-moodle53-webserver-1',
                             'php', '/opt/asodeltol/scripts/anexo14-report-context.php'],
                            text=True, capture_output=True, check=True)
    context = json.loads(output.stdout)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    out = ROOT / 'build/staging/anexo14-reports' / stamp
    out.mkdir(parents=True, exist_ok=True)
    admin = session('asodeltol_admin', ROOT / 'build/staging/moodle53/secrets/admin-password.txt')
    checks = []
    for dataset, columns in [('participants', 8), ('consolidated', 7), ('access', 12)]:
        rows = {}
        for format, extension in [('csv', 'csv'), ('excel', 'xlsx')]:
            url = BASE + '/local/asodeltolstaff/export.php?' + urllib.parse.urlencode(
                {'courseid': context['course_id'], 'dataset': dataset, 'format': format})
            response = admin.open(url, timeout=120)
            data = response.read()
            (out / (dataset + '.' + extension)).write_bytes(data)
            if extension == 'xlsx':
                assert data.startswith(b'PK'), 'Expected XLSX ZIP'
                book = openpyxl.load_workbook(io.BytesIO(data), data_only=True)
                rows[format] = [['' if v is None else str(v) for v in row] for row in book.worksheets[0].values]
            else:
                text = data.decode('utf-8-sig')
                dialect = csv.Sniffer().sniff(text[:4096], delimiters=',;\t')
                rows[format] = list(csv.reader(io.StringIO(text), dialect))
            assert len(rows[format][0]) == columns
        assert rows['csv'] == rows['excel'], 'CSV/XLSX content differs'
        expected = context['participants_rows'] if dataset == 'participants' else context['access_rows'] if dataset == 'access' else 1
        assert len(rows['csv']) == expected + 1
        if dataset == 'consolidated':
            assert rows['csv'][1] == [str(v) for v in context['consolidated'][0].values()]
        checks.append({'id': dataset.upper(), 'status': 'PASS', 'rows': expected, 'columns': columns,
                       'csv_xlsx_equal': True, 'native_data_reconciled': True})
        guest = urllib.request.urlopen(url, timeout=60).read()
        assert not guest.startswith(b'PK') and b'logintoken' in guest, 'Guest export denial not proven'
        checks.append({'id': 'GUEST-DENIED-' + dataset, 'status': 'PASS'})
    result = {'environment': BASE, 'checks': checks, 'context': context,
              'contractual_acceptance': False,
              'meaning': 'Native enrollment/completion; no document, characterization or successful-completion policy inferred.'}
    (out / 'summary.json').write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'checks': checks, 'evidence': str(out)}, ensure_ascii=False))


if __name__ == '__main__':
    main()
