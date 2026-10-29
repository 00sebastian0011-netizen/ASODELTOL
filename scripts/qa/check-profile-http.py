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
        cookies = [c.name for c in opener.handlers if hasattr(c, 'cookiejar') for c in c.cookiejar]
        marker_names = ["invalidlogin", "cookiesnotenabled", "loginerrors", "loginerror",
                        "forcepasswordchange", "passwordexpired", "notconfirmed", "policy"]
        flags = {m: m in after.lower() for m in marker_names if m in after.lower()}
        raise AssertionError(f"{user}: Moodle returned login form after POST; "
                             f"final_path={urllib.parse.urlparse(resp.url).path} "
                             f"query={urllib.parse.urlparse(resp.url).query[:40]} "
                             f"cookies={cookies} flags={flags}")
    return opener

def fetch(opener, uri):
    resp = opener.open(ROOT + uri, timeout=60)
    return resp.status, urllib.parse.urlparse(resp.url).path, text_of(resp)

def check(label, condition, detail):
    global failed
    ok = bool(condition)
    print(f"{'PASS' if ok else 'FAIL'} | {label} | {detail}")
    if not ok:
        failed += 1

clients = {}
for role in ["benefg1", "trainer", "auditor", "sena", "admin"]:
    clients[role] = opener_for(role)

cases = [
    # Label, viewer, target, expected profile decision, hidden-city expectation, URL suffix.
    ("beneficiary own minimal", "benefg1", "benefg1", "minimal", False, ""),
    ("beneficiary other same course", "benefg1", "benefg2", "deny", False, ""),
    ("beneficiary other course tampered", "benefg1", "benefoutside", "deny", False, f"&course={OUTSIDE}"),
    ("trainer own group", "trainer", "benefg1", "allow", False, ""),
    ("trainer different group", "trainer", "benefg2", "deny", False, ""),
    ("trainer target not enrolled with altered course", "trainer", "benefoutside", "deny", False, f"&course={C}"),
    ("interventoria authorised student", "auditor", "benefg1", "allow", True, ""),
    ("interventoria out-of-scope student", "auditor", "benefoutside", "deny", False, ""),
    ("SENA authorised student", "sena", "benefg2", "allow", True, ""),
    ("SENA out-of-scope student", "sena", "benefoutside", "deny", False, ""),
    ("administrator normal profile", "admin", "benefg1", "allow", True, ""),
]

identities = {
    "benefg1": ("BeneficiarioPrimero", "qa_benef_g1@example.test", "CitySecretG01"),
    "benefg2": ("BeneficiarioSegundo", "qa_benef_g2@example.test", "CitySecretG02"),
    "benefoutside": ("BeneficiarioExterior", "qa_benef_outside@example.test", "CitySecretExterior"),
}

failed = 0
for label, viewer, target, expected, expected_city, extra in cases:
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
                  and has_secret == expected_city
                  and final_path != "/login/index.php")
        print(f"{'PASS' if ok else 'FAIL'} | {label} | HTTP {code} | {final_path}"
              f" | name={has_name} email={has_email} hidden_city={has_secret}")
        if not ok:
            failed += 1
    except Exception as exc:
        failed += 1
        print(f"FAIL | {label} | {type(exc).__name__}: {str(exc)[:140]}")

# Real Moodle gradebook read access. The marker and numeric grade must both be
# rendered for each authorised role, while beneficiary remains limited to self.
for role in ["benefg1", "trainer", "auditor", "sena"]:
    try:
        code, path, body = fetch(clients[role], f"/grade/report/user/index.php?id={C}&userid={U['benefg1']}")
        check(f"{role} reads authorised grade", code == 200 and "QA_ONLY_SYNTHETIC_GRADE" in body and "87" in body,
              f"HTTP {code} | {path} | item={'QA_ONLY_SYNTHETIC_GRADE' in body} grade={'87' in body}")
    except Exception as exc:
        check(f"{role} reads authorised grade", False, f"{type(exc).__name__}: {str(exc)[:120]}")

# Evidence visibility is group-aware for the trainer and transversal read-only
# for Interventoria/SENA.
for role, see_g1, see_g2 in [("trainer", True, False), ("auditor", True, True), ("sena", True, True)]:
    try:
        code, path, body = fetch(clients[role], f"/local/asodeltolstaff/index.php?courseid={C}")
        g1 = "QA_EVIDENCE_G01" in body and "qa_evidence_g01" in body
        g2 = "QA_EVIDENCE_G02" in body and "qa_evidence_g02" in body
        check(f"{role} evidence scope", code == 200 and g1 == see_g1 and g2 == see_g2,
              f"HTTP {code} | {path} | G01={g1} G02={g2}")
    except Exception as exc:
        check(f"{role} evidence scope", False, f"{type(exc).__name__}: {str(exc)[:120]}")

# Interventoria and SENA may inspect, but cannot edit grades/session records or
# export personal datasets. Verify the actual HTTP response, not just role setup.
for role in ["auditor", "sena"]:
    negative_cases = [
        ("grade editing", f"/grade/edit/tree/index.php?id={C}"),
        ("session editing", f"/local/asodeltolstaff/session.php?courseid={C}&id={FIXTURE['sessions'][0]}"),
        ("data export", f"/local/asodeltolstaff/export.php?courseid={C}&format=csv"),
    ]
    for action, uri in negative_cases:
        try:
            code, path, body = fetch(clients[role], uri)
            denied = ("nopermissions" in body.lower() or "permission" in body.lower()
                      or "permis" in body.lower() or "accessdenied" in body.lower())
            is_download = "QA_EVIDENCE_G01" in body or "sessioncode" in body.lower() and "," in body
            check(f"{role} denied {action}", denied and not is_download,
                  f"HTTP {code} | {path} | denied={denied} download={is_download}")
        except Exception as exc:
            check(f"{role} denied {action}", False, f"{type(exc).__name__}: {str(exc)[:120]}")

total = len(cases) + 4 + 3 + 6
if failed:
    print(f"FAIL: {failed}/{total} authenticated Moodle QA cases.")
    sys.exit(1)
print(f"PASS: {total} authenticated Moodle HTTP privacy, grade, evidence and denial cases.")
