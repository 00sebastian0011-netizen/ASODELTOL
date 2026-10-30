#!/usr/bin/env python3
"""Authenticated Moodle UI persistence and permission checks for PR #74."""
import datetime
import html.parser
import http.cookiejar
import json
import os
import urllib.error
import urllib.parse
import urllib.request

ROOT = "http://127.0.0.1:8053"
PASSWORD = os.environ["QA_TEST_PASSWORD"]
ADMIN_PASSWORD = os.environ["QA_ADMIN_PASS"]
with open("qa-ids.json", encoding="utf-8") as fp:
    FIXTURE = json.load(fp)
COURSE = FIXTURE["course"]


class Form(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.values = {}
        self._select = None
        self._textarea = None
        self._text = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "input" and a.get("name") and a.get("type") not in ("submit", "button"):
            if a.get("type") not in ("checkbox", "radio") or "checked" in a:
                self.values[a["name"]] = a.get("value", "")
        elif tag == "select" and a.get("name"):
            self._select = a["name"]
        elif tag == "option" and self._select and "selected" in a:
            self.values[self._select] = a.get("value", "")
        elif tag == "textarea" and a.get("name"):
            self._textarea = a["name"]
            self._text = []

    def handle_data(self, data):
        if self._textarea:
            self._text.append(data)

    def handle_endtag(self, tag):
        if tag == "select":
            self._select = None
        elif tag == "textarea" and self._textarea:
            self.values[self._textarea] = "".join(self._text)
            self._textarea = None


def login(username, password):
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    page = opener.open(ROOT + "/login/index.php", timeout=40).read().decode(errors="replace")
    form = Form(); form.feed(page)
    payload = urllib.parse.urlencode({
        "username": username, "password": password, "logintoken": form.values["logintoken"]
    }).encode()
    result = opener.open(ROOT + "/login/index.php", payload, timeout=60).read().decode(errors="replace")
    if 'name="username"' in result and 'name="password"' in result:
        raise AssertionError(f"login failed for {username}")
    return opener


def get(opener, path):
    try:
        response = opener.open(ROOT + path, timeout=60)
        return response.status, urllib.parse.urlparse(response.url).path, response.read().decode(errors="replace")
    except urllib.error.HTTPError as exc:
        return exc.code, urllib.parse.urlparse(exc.url).path, exc.read().decode(errors="replace")


failed = 0
def check(label, condition, detail):
    global failed
    print(f"{'PASS' if condition else 'FAIL'} | {label} | {detail}")
    if not condition:
        failed += 1


admin = login("admin", ADMIN_PASSWORD)
path = f"/local/asodeltolstaff/courseaction.php?id={COURSE}"
code, final, body = get(admin, path)
form = Form(); form.feed(body)
expected_fields = ["idnumber", "fullname", "summary_editor[text]", "visible", "startdate[day]",
                   "enddate[day]", "lang", "adminroleid", "auditorroleid"]
check("REQ-13-02 authenticated form renders nine fields",
      code == 200 and all(field in form.values for field in expected_fields),
      f"HTTP {code} | {final} | fields={sum(field in form.values for field in expected_fields)}/9")

values = dict(form.values)
values.update({
    "id": str(COURSE), "idnumber": "QA-REQ-13-02", "fullname": "QA Synthetic Training Action",
    "summary_editor[text]": "QA_ONLY_REQ_13_02_DESCRIPTION", "summary_editor[format]": "1",
    "visible": "0", "lang": "en", "adminroleid": "qa_admin_role",
    "auditorroleid": "qa_auditor_role", "submitbutton": "Save changes",
})
start = datetime.datetime.now() + datetime.timedelta(days=2)
end = start + datetime.timedelta(days=30)
for prefix, value in (("startdate", start), ("enddate", end)):
    values[f"{prefix}[day]"] = str(value.day)
    values[f"{prefix}[month]"] = str(value.month)
    values[f"{prefix}[year]"] = str(value.year)
    values[f"{prefix}[hour]"] = "10"
    values[f"{prefix}[minute]"] = "0"
response = admin.open(ROOT + path, urllib.parse.urlencode(values).encode(), timeout=60)
posted = response.read().decode(errors="replace")
code2, final2, reloaded = get(admin, path)
persisted = all(marker in reloaded for marker in [
    "QA-REQ-13-02", "QA Synthetic Training Action", "QA_ONLY_REQ_13_02_DESCRIPTION",
    "qa_admin_role", "qa_auditor_role",
])
check("REQ-13-02 saves and reloads all UI values", code2 == 200 and persisted,
      f"HTTP {code2} | {final2} | persisted={persisted}")

for username, label in (("qa_trainer", "trainer"), ("qa_auditor", "Interventoria")):
    client = login(username, PASSWORD)
    denied_code, denied_path, denied_body = get(client, path)
    denied = denied_code in (403, 404) or "permission" in denied_body.lower() or "permis" in denied_body.lower()
    check(f"REQ-13-02 {label} cannot edit training action", denied,
          f"HTTP {denied_code} | {denied_path} | denied={denied}")

if failed:
    raise SystemExit(f"FAIL: {failed}/4 authenticated functional UI checks")
print("PASS: 4/4 authenticated REQ-13-02 Moodle UI checks.")
