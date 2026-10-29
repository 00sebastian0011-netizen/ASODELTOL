#!/usr/bin/env python3
"""Authenticated HTTP checks for the 5-UT synthetic AF8 Moodle course.

Runs only on GitHub's disposable Moodle QA localhost, not on production.
This test cannot confer institutional acceptance for Anexo 14.
"""
import html
import html.parser
import http.cookiejar
import json
import os
import urllib.error
import urllib.parse
import urllib.request

ROOT = "http://127.0.0.1:8053"
if os.environ.get("ASODELTOL_QA_ONLY") != "isolated-gh-runner":
    raise SystemExit("REFUSED: HTTP QA is allowed only in disposable GitHub runner")
with open("qa-af8-full-ids.json", encoding="utf-8") as fp:
    fixture = json.load(fp)

class FormInputs(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.inputs = {}

    def handle_starttag(self, tag, attrs):
        if tag == "input":
            properties = dict(attrs)
            self.inputs[properties.get("name", "")] = properties.get("value", "")

def read(opener, path):
    try:
        response = opener.open(ROOT + path, timeout=65)
        return response.status, html.unescape(response.read().decode("utf-8", "replace"))
    except urllib.error.HTTPError as exc:
        return exc.code, html.unescape(exc.read().decode("utf-8", "replace"))

def login(username, password):
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    code, form = read(opener, "/login/index.php")
    if code != 200:
        raise AssertionError("Login form not reachable")
    parser = FormInputs()
    parser.feed(form)
    token = parser.inputs.get("logintoken")
    if not token:
        raise AssertionError("Login token absent")
    data = urllib.parse.urlencode({
        "username": username, "password": password, "logintoken": token
    }).encode()
    response = opener.open(ROOT + "/login/index.php", data, timeout=65)
    text = response.read().decode("utf-8", "replace")
    if 'name="username"' in text and 'name="password"' in text:
        raise AssertionError("Authentication failed for synthetic account")
    return opener

checks = []
def check(label, passed, detail=""):
    state = "PASS" if passed else "FAIL"
    checks.append((label, bool(passed)))
    print(f"{state} | {label} | {detail[:130]}")

admin = login("admin", os.environ["QA_ADMIN_PASS"])
g1 = login("qa_af8_g01", os.environ["QA_TEST_PASSWORD"])
g2 = login("qa_af8_g02", os.environ["QA_TEST_PASSWORD"])
trainer = login("qa_af8_trainer", os.environ["QA_TEST_PASSWORD"])
auditor = login("qa_af8_auditor", os.environ["QA_TEST_PASSWORD"])
sena = login("qa_af8_sena", os.environ["QA_TEST_PASSWORD"])

course = int(fixture["course"])
code, body = read(admin, f"/course/view.php?id={course}")
check("five canonical units in Moodle course page",
      code == 200 and all(marker in body for marker in [
          "Gestión estratégica del turismo rural sostenible",
          "Diseño estratégico de experiencias turísticas",
          "Marketing estratégico y gestión competitiva",
          "Transformación digital, inteligencia artificial",
          "Desarrollo rural sostenible, economía campesina",
      ]), f"HTTP {code}")

# Verify every component has actual readable HTML, not merely a DB record.
for itemcode, page in fixture["pages"].items():
    status, response = read(admin, f"/mod/page/view.php?id={int(page['cmid'])}")
    check(f"rendered synthetic component {itemcode}",
          status == 200 and page["marker"] in response
          and "SIMULACIÓN TÉCNICA" in response, f"HTTP {status}")

# One real beneficiary can read a Page from each of the five units.
for n in range(1, 6):
    key = f"UT{n}.01"
    page = fixture["pages"][key]
    status, response = read(g1, f"/mod/page/view.php?id={int(page['cmid'])}")
    check(f"beneficiary G01 sees UT{n}", status == 200 and page["marker"] in response,
          f"HTTP {status}")

# A group-availability restriction is tested positively and negatively over HTTP.
page = fixture["pages"]["UT1.07"]
url = f"/mod/page/view.php?id={int(page['cmid'])}"
status, body = read(g1, url)
check("G01 can read restricted synthetic Page",
      status == 200 and page["marker"] in body, f"HTTP {status}")
status, body = read(g2, url)
check("G02 cannot read G01-only Page",
      page["marker"] not in body, f"HTTP {status}")

for i, cmid in enumerate(fixture["assignments"], start=1):
    status, body = read(admin, f"/mod/assign/view.php?id={int(cmid)}")
    check(f"UT{i} native assignment renders", status == 200 and "QA ONLY" in body,
          f"HTTP {status}")

status, body = read(g1, f"/grade/report/user/index.php?id={course}&userid={fixture['users']['G01']}")
check("fictional Moodle grade is readable by its learner",
      status == 200 and "QA_ONLY_AF8_FICTITIOUS_GRADE" in body and "87" in body,
      f"HTTP {status}")

status, body = read(g1, f"/mod/forum/view.php?id={fixture['forum']}")
check("native group forum renders", status == 200 and "foro de comunicación" in body,
      f"HTTP {status}")

# Do not allow these tests to imply completion of external contract criteria.
failure_count = sum(1 for _, ok in checks if not ok)
summary = {
    "purpose": "TECHNICAL SYNTHETIC QA ONLY - NOT ANEXO 14 ACCEPTANCE",
    "environment": "ephemeral localhost MySQL QA",
    "course_UT": 5,
    "synthetic_pages": 50,
    "synthetic_assignments": 5,
    "synthetic_groups": 7,
    "synthetic_users": 10,
    "checks": len(checks),
    "passed": len(checks) - failure_count,
    "failed": failure_count,
    "institutional_acceptances": 0,
    "blocked_outside_this_test": [
        "content approval, originality, licenses, AAC/subtitles",
        "Anexo 12 official report layouts",
        "eight-day scheduling and passing-grade policy",
        "CAU SLA and live videoconference attendance",
        "institutional sign-off",
    ],
}
print(json.dumps(summary, ensure_ascii=False, sort_keys=True))
with open("qa-af8-full-summary.json", "w", encoding="utf-8") as fp:
    json.dump(summary, fp, indent=2, ensure_ascii=False)
if failure_count:
    raise SystemExit(f"FAIL: {failure_count} of {len(checks)} synthetic HTTP checks failed")
print(f"PASS: {len(checks)} synthetic HTTP checks; not institutional certification.")
