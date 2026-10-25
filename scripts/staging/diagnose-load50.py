"""Read-only diagnostic, synthetic localhost identity, no credentials in output."""
import http.cookiejar
import json
import pathlib
import re
import urllib.error
import urllib.parse
import urllib.request
root = pathlib.Path(__file__).resolve().parents[2]
private = json.loads((root / 'build/staging/load50/private.json').read_text())
base = 'http://127.0.0.1:8053'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
page = client.open(base + '/login/index.php', timeout=60).read().decode()
token = re.search(r'name="logintoken"\s+value="([^"]+)"', page)[1]
data = urllib.parse.urlencode(dict(username=private['users'][0]['username'], password=private['password'], logintoken=token)).encode()
client.open(base + '/login/index.php', data, timeout=60).read()
try:
    response = client.open(base + f'/grade/report/user/index.php?id={private["courseid"]}', timeout=60)
    print('HTTP', response.status)
except urllib.error.HTTPError as error:
    body = error.read().decode()
    messages = re.findall(r'<[^>]+class="[^"]*(?:alert-danger|errorbox)[^"]*"[^>]*>(.*?)</div>', body, re.S)
    print('HTTP', error.code)
    for message in messages:
        plain = re.sub('<[^>]+>', ' ', message).strip()
        print(plain.replace(private['password'], '[REDACTED]')[:350])
