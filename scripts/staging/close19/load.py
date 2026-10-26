"""50 distinct authenticated native Moodle sessions, with full-response validation."""
import concurrent.futures
import datetime
import hashlib
import http.cookiejar
import json
import pathlib
import re
import subprocess
import threading
import time
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[3]
OUT = ROOT / 'build/staging/close19'
BASE = 'http://127.0.0.1:8054'


def main():
    fixture = json.loads((OUT / 'private/fixture.json').read_text(encoding='utf-8-sig'))
    password = (OUT / 'private/synthetic-password.txt').read_text().strip()
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S')
    resources = []
    stop = threading.Event()

    def stats():
        while not stop.is_set():
            result = subprocess.run(['docker', 'stats', '--no-stream', '--format', '{{json .}}',
                'asodeltol-close19-webserver-1', 'asodeltol-close19-database-1'], capture_output=True, text=True)
            resources.append({'utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                              'sample': [json.loads(line) for line in result.stdout.splitlines() if line.strip()]})
            stop.wait(2)

    thread = threading.Thread(target=stats)
    thread.start()
    clients = [urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())) for _ in range(50)]

    def request(index, path, post=None, binary=False, expected=None):
        started = time.perf_counter()
        row = {'actor': index + 1, 'path': urllib.parse.urlsplit(path).path}
        try:
            data = urllib.parse.urlencode(post).encode() if post else None
            response = clients[index].open(BASE + path, data=data, timeout=60)
            body = response.read()
            contenttype = response.headers.get('Content-Type', '')
            ok = response.status in (200, 206)
            if binary:
                ok &= not contenttype.startswith('text/html') and len(body) > 100
                row['sha256'] = hashlib.sha256(body).hexdigest()
                ok &= row['sha256'] == expected
            else:
                text = body.decode('utf-8', errors='replace')
                ok &= 'id="loginbtn"' not in text and 'class="errorcode"' not in text
            row.update(http=response.status, bytes=len(body), status='PASS' if ok else 'FAIL')
        except Exception as error:
            row.update(http=getattr(error, 'code', None), status='FAIL', error=type(error).__name__)
            if hasattr(error, 'read'):
                # Preserve only the public error identifier, never an HTML/config dump.
                code = re.search(r'(?:errorcode|error/)[^<>]{0,100}', error.read().decode('utf-8', errors='replace'))
                row['public_error_identifier'] = code[0] if code else 'not exposed'
        row['seconds'] = round(time.perf_counter() - started, 4)
        if row['seconds'] >= 60:
            row['status'] = 'FAIL'
        return row

    def login(index):
        started = time.perf_counter()
        try:
            page = clients[index].open(BASE + '/login/index.php', timeout=60).read().decode()
            token = re.search(r'name="logintoken"\s+value="([^"]+)"', page)[1]
            result = request(index, '/login/index.php', {'username': fixture['users'][index]['username'],
                'password': password, 'logintoken': token})
            result['seconds'] = round(time.perf_counter() - started, 4)
            return result
        except Exception as error:
            return {'actor': index + 1, 'status': 'FAIL', 'error': type(error).__name__,
                    'seconds': round(time.perf_counter() - started, 4), 'path': '/login/index.php'}

    phases = {}
    paths = {'home': '/?redirect=0', 'course': '/course/view.php?id=' + str(fixture['courseid']),
        'unit': '/course/section.php?id=' + str(fixture['sectionid']),
        'page': '/mod/page/view.php?id=' + str(fixture['modules']['C01']['cmid']),
        'assignment': '/mod/assign/view.php?id=' + str(fixture['assignment']),
        'quiz': '/mod/quiz/view.php?id=' + str(fixture['modules']['C09']['cmid']),
        'grades': '/grade/report/user/index.php?id=' + str(fixture['courseid']),
        'file': fixture['file_url'], 'video': fixture['video_url'], 'audio': fixture['audio_url']}
    try:
        with concurrent.futures.ThreadPoolExecutor(max_workers=50) as pool:
            phases['login'] = list(pool.map(login, range(50)))
            for name, path in paths.items():
                print('Phase:', name, flush=True)
                phases[name] = list(pool.map(lambda i: request(i, path, binary=name in ('file', 'video', 'audio'),
                    expected=fixture.get(name + '_sha256')), range(50)))
    finally:
        stop.set()
        thread.join()
    summary = {}
    for name, rows in phases.items():
        values = sorted(row['seconds'] for row in rows)
        summary[name] = {'requests': len(rows), 'failed': sum(row['status'] != 'PASS' for row in rows),
            'p50_seconds': values[24], 'p95_seconds': values[47], 'p99_seconds': values[49], 'max_seconds': values[-1]}
    report = {'base': BASE, 'plugin_version': fixture['plugin_version'], 'distinct_authenticated_users': 50,
        'synthetic_content_only': True, 'browser_rendering_measured': False,
        'status': 'PASS' if all(r['status'] == 'PASS' and r['seconds'] < 60 for rows in phases.values() for r in rows) else 'FAIL',
        'threshold_seconds_exclusive': 60, 'phases': summary, 'observations': phases, 'resource_samples': resources}
    (OUT / ('load-' + stamp + '.json')).write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(json.dumps({k: v for k, v in report.items() if k not in ('observations', 'resource_samples')}, indent=2))
    return 0 if report['status'] == 'PASS' else 1


if __name__ == '__main__':
    raise SystemExit(main())
