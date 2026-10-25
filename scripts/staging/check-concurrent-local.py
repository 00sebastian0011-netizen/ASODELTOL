"""Measure 50 independent local sessions of ONE existing synthetic identity.

This deliberately does not claim 50 distinct beneficiaries or video/download QA.
"""
import concurrent.futures
import argparse
import datetime
import importlib.util
import json
import pathlib
import subprocess
import threading
import time
import re
import html as html_module
import urllib.error
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('report_checks', pathlib.Path(__file__).with_name('check-additional-reports.py'))
helper = importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--route', choices=['all', 'course', 'staff', 'grades'], default='all')
    options = parser.parse_args()
    raw = subprocess.run(['docker', 'exec', '--user', 'www-data', 'asodeltol-moodle53-webserver-1',
                          'php', '/opt/asodeltol/scripts/anexo14-qa-context.php'], text=True, capture_output=True, check=True)
    context = json.loads(raw.stdout.split('ASODELTOL_QA_CONTEXT=', 1)[1])
    assert context['course_hidden'] and context['category_hidden']
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    out = ROOT / 'build/staging/anexo14-load' / stamp
    out.mkdir(parents=True, exist_ok=True)
    print('Creating 50 independent local sessions of existing synthetic administrator...', flush=True)
    def login(_):
        return helper.session('asodeltol_admin', ROOT / 'build/staging/moodle53/secrets/admin-password.txt')
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        clients = list(pool.map(login, range(50)))
    checks = []
    routes = [('course', f'/course/view.php?id={context["course_id"]}', 'ASODELTOL'),
              ('staff', f'/local/asodeltolstaff/index.php?courseid={context["course_id"]}', 'M11-AF8-G01-01'),
              ('grades', f'/grade/report/grader/index.php?id={context["course_id"]}', 'page-grade-report-grader-index')]
    for name, path, marker in routes:
        if options.route not in ('all', name):
            continue
        barrier = threading.Barrier(50)
        lock = threading.Lock()
        active = 0
        peak = 0
        def fetch(client):
            nonlocal active, peak
            barrier.wait(timeout=30)
            with lock:
                active += 1
                peak = max(peak, active)
            started = time.perf_counter()
            try:
                response = client.open(helper.BASE + path, timeout=65)
                html = response.read().decode('utf-8', errors='replace')
                return {'seconds': round(time.perf_counter() - started, 3),
                        'pass': response.status == 200 and marker in html, 'http_status': response.status}
            except urllib.error.HTTPError as error:
                body = error.read().decode('utf-8', errors='replace')
                messages = re.findall(r'<div[^>]*class=["\'][^"\']*(?:alert-danger|errorbox)[^"\']*["\'][^>]*>(.*?)</div>', body, re.S)
                safe = [html_module.unescape(re.sub('<[^>]+>', ' ', value)).strip()[:300] for value in messages]
                secret = (ROOT / 'build/staging/moodle53/secrets/admin-password.txt').read_text().strip()
                safe = [re.sub(r'(?i)(sesskey|token|password)=\S+', r'\1=[REDACTED]', message.replace(secret, '[REDACTED]')) for message in safe]
                return {'seconds': round(time.perf_counter()-started, 3), 'pass': False,
                        'error_type': 'HTTPError', 'http_status': error.code, 'safe_error_messages': safe}
            except Exception as error:
                return {'seconds': round(time.perf_counter() - started, 3), 'pass': False, 'error_type': type(error).__name__}
            finally:
                with lock:
                    active -= 1
        with concurrent.futures.ThreadPoolExecutor(max_workers=50) as pool:
            results = list(pool.map(fetch, clients))
        times = sorted(row['seconds'] for row in results)
        entry = {'path': path, 'requests': 50, 'peak_inflight': peak,
                 'p50_seconds': times[24], 'p95_seconds': times[47], 'max_seconds': times[-1],
                 'failed_requests': sum(not r['pass'] for r in results),
                 'status': 'PASS' if all(r['pass'] and r['seconds'] < 60 for r in results) else 'FAIL',
                 'results': results}
        checks.append(entry)
        print(json.dumps({k: v for k, v in entry.items() if k != 'results'}), flush=True)
    report = {'environment': helper.BASE, 'identity': 'existing synthetic administrator', 'distinct_users': 1,
              'independent_sessions': 50, 'threshold_seconds_from_source': 60, 'checks': checks,
              'requirement_fully_verified': False,
              'remaining': '50 distinct beneficiary identities, real course resources, video/download, browser rendering and representative infrastructure remain unverified.'}
    (out / 'summary.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    final = subprocess.run(['docker', 'exec', '--user', 'www-data', 'asodeltol-moodle53-webserver-1',
                            'php', '/opt/asodeltol/scripts/anexo14-qa-context.php'], text=True, capture_output=True, check=True)
    (out / 'final-context.txt').write_text(final.stdout, encoding='utf-8')
    assert '"course_hidden":true' in final.stdout and '"category_hidden":true' in final.stdout
    return 1 if any(check['status'] != 'PASS' for check in checks) else 0


if __name__ == '__main__':
    sys.exit(main())
