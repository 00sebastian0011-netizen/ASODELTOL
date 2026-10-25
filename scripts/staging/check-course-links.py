"""Check same-origin navigation links in authenticated synthetic Moodle pages."""
import argparse
import datetime
import html.parser
import importlib.util
import json
import pathlib
import urllib.error
import urllib.parse

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:8053'
spec = importlib.util.spec_from_file_location('report_checks', pathlib.Path(__file__).with_name('check-additional-reports.py'))
helper = importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)
SKIP = ('/login/logout.php', '/admin/cli/', '/course/delete.php', '/user/delete.php', '/grade/edit/', '/enrol/unenrol.php')


class Links(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.hrefs = []

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            value = dict(attrs).get('href')
            if value:
                self.hrefs.append(value)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--courseid', type=int, required=True)
    parser.add_argument('--username', default='asodeltol_admin')
    parser.add_argument('--secret-file', type=pathlib.Path, required=True)
    args = parser.parse_args()
    client = helper.session(args.username, args.secret_file)
    routes = [f'/course/view.php?id={args.courseid}', f'/local/asodeltolstaff/index.php?courseid={args.courseid}',
              f'/local/asodeltolstaff/communication.php?courseid={args.courseid}',
              f'/local/asodeltolstaff/help.php', f'/local/asodeltolstaff/reports.php']
    candidates = set(BASE + route for route in routes)
    for route in routes:
        try:
            page = client.open(BASE + route, timeout=60).read().decode('utf-8', errors='replace')
        except (urllib.error.HTTPError, urllib.error.URLError):
            continue
        links = Links()
        links.feed(page)
        for href in links.hrefs:
            target = urllib.parse.urljoin(BASE + route, href)
            parsed = urllib.parse.urlsplit(target)
            base = urllib.parse.urlsplit(BASE)
            if parsed.scheme not in ('http', 'https') or parsed.netloc != base.netloc or not parsed.path.startswith('/'):
                continue
            if parsed.path.endswith('/login/logout.php') or 'sesskey' in urllib.parse.parse_qs(parsed.query):
                continue
            if any(parsed.path.startswith(path) for path in SKIP):
                continue
            candidates.add(urllib.parse.urlunsplit((base.scheme, base.netloc, parsed.path, parsed.query, '')))
    results = []
    for url in sorted(candidates):
        path = urllib.parse.urlsplit(url).path
        try:
            response = client.open(url, timeout=45)
            response.read(2048)
            results.append({'path': path, 'status': response.status, 'result': 'PASS' if response.status < 400 else 'FAIL'})
        except urllib.error.HTTPError as error:
            results.append({'path': path, 'status': error.code, 'result': 'FAIL'})
        except urllib.error.URLError as error:
            results.append({'path': path, 'status': None, 'result': 'FAIL', 'error': type(error.reason).__name__})
    out = ROOT / 'build/staging/course-links'
    out.mkdir(parents=True, exist_ok=True)
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
    summary = {'base': BASE, 'courseid': args.courseid, 'scope': 'same-origin anchors on course and ASODELTOL staff pages',
               'checked': len(results), 'failed': sum(row['result'] != 'PASS' for row in results), 'results': results}
    (out / (stamp + '-summary.json')).write_text(json.dumps(summary, indent=2), encoding='utf-8')
    print(json.dumps({key: value for key, value in summary.items() if key != 'results'}))
    raise SystemExit(0 if summary['failed'] == 0 else 1)


if __name__ == '__main__':
    main()
