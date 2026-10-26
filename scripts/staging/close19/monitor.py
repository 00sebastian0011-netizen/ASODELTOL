"""Append-only HTTP/login observations; never infer contractual uptime from a smoke run."""
import argparse
import datetime
import hashlib
import html.parser
import json
import pathlib
import time
import urllib.error
import urllib.parse
import urllib.request


class LoginForm(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.inputs = set()

    def handle_starttag(self, tag, attrs):
        if tag == 'input':
            self.inputs.add(dict(attrs).get('name'))


def login_present(body):
    parser = LoginForm()
    parser.feed(body)
    return {'username', 'password', 'logintoken'} <= parser.inputs


def observe(base):
    rows = []
    for route in ['/', '/login/index.php']:
        started = time.perf_counter()
        row = {'path': route, 'utc': datetime.datetime.now(datetime.timezone.utc).isoformat()}
        try:
            request = urllib.request.Request(base.rstrip('/') + route, headers={'User-Agent': 'ASODELTOL-availability/1.0'})
            with urllib.request.urlopen(request, timeout=60) as response:
                body = response.read(2_000_000).decode('utf-8', errors='replace')
                row.update(http=response.status, final_path=urllib.parse.urlsplit(response.url).path,
                           login_form=login_present(body), content_sha256=hashlib.sha256(body.encode()).hexdigest())
                row['status'] = 'PASS' if response.status == 200 and (route == '/' or row['login_form']) else 'FAIL'
        except (urllib.error.URLError, TimeoutError) as error:
            row.update(status='FAIL', http=getattr(error, 'code', None), error=type(error).__name__)
        row['seconds'] = round(time.perf_counter() - started, 4)
        rows.append(row)
    return rows


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--base', default='http://127.0.0.1:8054')
    parser.add_argument('--output', type=pathlib.Path, default=pathlib.Path('build/staging/close19/availability.jsonl'))
    parser.add_argument('--interval', type=float, default=3600)
    parser.add_argument('--count', type=int, default=1, help='0 monitors until explicitly stopped')
    args = parser.parse_args()
    url = urllib.parse.urlsplit(args.base)
    if url.scheme not in ('http', 'https') or url.username or url.password or url.query or url.fragment:
        raise SystemExit('Use an HTTP(S) site origin without credentials or query.')
    if args.interval < 1 or args.count < 0:
        raise SystemExit('Invalid monitor interval/count.')
    args.output.parent.mkdir(parents=True, exist_ok=True)
    previous = None
    if args.output.exists() and args.output.stat().st_size:
        previous = json.loads(args.output.read_text(encoding='utf-8').splitlines()[-1])['sha256']
    number = 0
    failed = False
    while args.count == 0 or number < args.count:
        started = time.monotonic()
        record = {'base': args.base, 'checks': observe(args.base), 'previous_sha256': previous,
                  'contractual_period_accredited': False}
        record['sha256'] = hashlib.sha256(json.dumps(record, sort_keys=True).encode()).hexdigest()
        # No page HTML, session cookies or login tokens are stored.
        with args.output.open('a', encoding='utf-8') as stream:
            stream.write(json.dumps(record) + '\n')
        previous = record['sha256']
        failed |= any(row['status'] != 'PASS' for row in record['checks'])
        print(json.dumps(record), flush=True)
        number += 1
        if args.count == 0 or number < args.count:
            time.sleep(max(0, args.interval - (time.monotonic() - started)))
    return int(failed)


if __name__ == '__main__':
    raise SystemExit(main())
