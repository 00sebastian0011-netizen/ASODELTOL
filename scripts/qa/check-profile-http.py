#!/usr/bin/env python3
"""Authenticated HTTP contract on an entirely disposable Moodle QA instance."""
import html.parser
import http.cookiejar
import json
import os
import re
import sys
import urllib.parse
import urllib.request

ROOT = "http://127.0.0.1:8053"
PASSWORD = os.environ["QA_TEST_PASSWORD"]
ADMIN_PASSWORD = os.environ["QA_ADMIN_PASS"]
with open("qa-ids.json", encoding="utf-8") as fp:
    FIXTURE = json.load(fp)
U = FIXTURE["users"]
C = FIXTURE["course"]
OUTSIDE = FIXTURE["outsidecourse"]

class Inputs(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.values = {}
    def handle_starttag(self, tag, attrs):
        if tag == "input":
            a = dict(attrs)
            self.values[a.get("name", "")] = a.get("value", "")

def text_of(resp):
    raw = resp.read()
    return raw.decode("utf-8", errors="replace")

def opener_for(user):
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    body = text_of(opener.open(ROOT + "/login/index.php", timeout=40))
    inp = Inputs()
    inp.feed(body)
    if "logintoken" not in inp.values:
        raise AssertionError(f"{user}: cannot obtain login token")
    username = "admin" if user == "admin" else {
        "benefg1": "qa_benef_g1",
        "benefg2": "qa_benef_g2",
        "trainer": "qa_trainer",
        "auditor": "qa_auditor",
        "sena": "qa_sena",
    }[user]
    data = urllib.parse.urlencode({
        "username": username,
        "password": ADMIN_PASSWORD if user == "admin" else PASSWORD,
        "logintoken": inp.values["logintoken"],
    }).encode()
    resp = opener.open(ROOT + "/login/index.php", data, timeout=60)
    after = text_of(resp)
    if "name=\"username\"" in after and "name=\"password\"" in after:
        raise AssertionError(f"{user}: login did not authenticate")
    return opener

def fetch(opener, uri):
    resp = opener.open(ROOT + uri, timeout=60)
    return resp.status, urllib.parse.urlparse(resp.url).path, text_of(resp)

clients = {}
for role in ["benefg1", "trainer", "auditor", "sena", "admin"]:
    clients[role] = opener_for(role)

cases = [
    # Label, viewer, target, permitted, extra url parameters.
    ("beneficiary own minimal", "benefg1", "benefg1", "minimal", ""),
    ("beneficiary other same course", "benefg1", "benefg2", "deny", ""),
    ("beneficiary other course tampered", "benefg1", "benefoutside", "deny", f"&course={OUTSIDE}"),
    ("trainer own group", "trainer", "benefg1", "allow", ""),
    ("trainer different group", "trainer", "benefg2", "deny", ""),
    ("trainer target not enrolled", "trainer", "benefoutside", "deny", f"&course={C}"),
    ("interventoria authorised student", "auditor", "benefg1", "allow", ""),
    ("interventoria out-of-scope student", "auditor", "benefoutside", "deny", ""),
    ("SENA authorised student", "sena", "benefg2", "allow", ""),
    ("SENA out-of-scope student", "sena", "benefoutside", "deny", ""),
    ("administrator normal profile", "admin", "benefg1", "allow", ""),
]

identities = {
    "benefg1": ("BeneficiarioPrimero", "qa_benef_g1@example.test", "CitySecretG01"),
    "benefg2": ("BeneficiarioSegundo", "qa_benef_g2@example.test", "CitySecretG02"),
    "benefoutside": ("BeneficiarioExterior", "qa_benef_outside@example.test", "CitySecretExterior"),
}

failed = 0
for label, viewer, target, expected, extra in cases:
    uri = f"/user/profile.php?id={U[target]}{extra}"
    try:
        code, final_path, body = fetch(clients[viewer], uri)
        name, email, secretcity = identities[target]
        has_name = name in body
        has_email = email in body
        has_secret = secretcity in body
        if expected == "minimal":
            ok = (code == 200
                  and final_path == "/local/asodeltolstaff/profile.php"
                  and has_name and has_email and not has_secret)
        elif expected == "deny":
            ok = (not has_name and not has_email and not has_secret
                  and final_path != "/login/index.php")
        else:
            ok = (code == 200 and has_name
                  and final_path != "/login/index.php")
        print(f"{'PASS' if ok else 'FAIL'} | {label} | HTTP {code} | {final_path}"
              f" | name={has_name} email={has_email} hidden_city={has_secret}")
        if not ok:
            failed += 1
    except Exception as exc:
        failed += 1
        print(f"FAIL | {label} | {type(exc).__name__}: {str(exc)[:140]}")

if failed:
    print(f"FAIL: {failed}/{len(cases)} authenticated Moodle profile cases.")
    sys.exit(1)
print(f"PASS: {len(cases)} authenticated Moodle HTTP profile cases.")
