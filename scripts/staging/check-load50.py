"""50 distinct synthetic beneficiaries, local-only authenticated HTTP test."""
import concurrent.futures
import datetime
import http.cookiejar
import json
import pathlib
import re
import threading
import time
import urllib.parse
import urllib.error
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:8053'


def main():
    private = json.loads((ROOT / 'build/staging/load50/private.json').read_text())
    assert len({u['id'] for u in private['users']}) == 50
    def login(user):
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        page = client.open(BASE + '/login/index.php', timeout=60).read().decode()
        token = re.search(r'name="logintoken"\s+value="([^"]+)"', page)[1]
        data = urllib.parse.urlencode(dict(username=user['username'], password=private['password'], logintoken=token)).encode()
        client.open(BASE + '/login/index.php', data, timeout=60).read()
        page = client.open(BASE + '/user/profile.php', timeout=60).read().decode()
        assert '/login/logout.php' in page
        return client
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        clients = list(pool.map(login, private['users']))
    results = []
    for route, marker in [('/my/', 'page-my-index'),
                          (f'/course/view.php?id={private["courseid"]}', 'QA sint'),
                          (f'/grade/report/user/index.php?id={private["courseid"]}', 'page-grade-report-user-index')]:
        barrier = threading.Barrier(50)
        def fetch(client):
            barrier.wait(timeout=30)
            start = time.monotonic()
            try:
                response = client.open(BASE + route, timeout=65)
                body = response.read().decode()
                elapsed = time.monotonic() - start
                return {'seconds': round(elapsed, 3), 'pass': response.status == 200 and marker in body and elapsed < 60}
            except urllib.error.HTTPError as error:
                return {'seconds': round(time.monotonic() - start, 3), 'pass': False,
                        'error': 'HTTPError', 'http_status': error.code}
            except Exception as error:
                return {'seconds': round(time.monotonic() - start, 3), 'pass': False, 'error': type(error).__name__}
        with concurrent.futures.ThreadPoolExecutor(max_workers=50) as pool:
            rows = list(pool.map(fetch, clients))
        seconds = sorted(r['seconds'] for r in rows)
        results.append({'route': route, 'requests': 50, 'failed': sum(not r['pass'] for r in rows),
                        'p95_seconds': seconds[47], 'max_seconds': seconds[-1], 'rows': rows})
    summary = {'distinct_users': 50, 'environment': BASE, 'synthetic_only': True,
               'status': 'PASS' if all(r['failed'] == 0 for r in results) else 'FAIL', 'checks': results,
               'limitations': 'HTTP, synthetic empty course; no browser rendering, multimedia or production capacity certification'}
    target = ROOT / 'build/staging/load50'
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    (target / (stamp + '-summary.json')).write_text(json.dumps(summary, indent=2))
    (target / 'summary.json').write_text(json.dumps(summary, indent=2))
    print(json.dumps({k: v for k, v in summary.items() if k != 'checks'}))
    raise SystemExit(0 if summary['status'] == 'PASS' else 1)


if __name__ == '__main__':
    main()
