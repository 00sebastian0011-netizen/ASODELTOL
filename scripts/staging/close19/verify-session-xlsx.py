"""Reconcile actual Moodle session XLSX exports with scoped QA350 UI evidence."""
import glob
import hashlib
import json
import pathlib

import openpyxl

ROOT = pathlib.Path(__file__).resolve().parents[3] / 'build/staging/close19'
session = pathlib.Path(sorted(glob.glob(str(ROOT / 'session-browser-*')))[-1])
report = pathlib.Path(sorted(glob.glob(str(ROOT / 'session-report-browser-*')))[-1])
code = json.loads((session / 'results.json').read_text(encoding='utf-8'))['rows'][0]['observed']['code']
result = {'status': 'PASS_REAL_LOCAL', 'session_code': code, 'files': {}}
for profile, expected in [('sena', True), ('interventoria', False)]:
    path = report / f'{profile}-sessions.xlsx'
    rows = list(openpyxl.load_workbook(path, read_only=True, data_only=True).active.values)
    present = any(code in str(cell) for row in rows for cell in row)
    if present != expected:
        raise SystemExit(f'FAIL {profile}: expected session visible={expected}, observed={present}')
    if profile == 'sena':
        header = rows[0]
        for label in ('Session identifier', 'Group', 'Provisional attendance status', 'Training action code'):
            if label not in header:
                raise SystemExit(f'FAIL {profile}: required report column absent: {label}')
        indexed = {name: header.index(name) for name in header}
        matched = [row for row in rows[1:] if row[indexed['Session identifier']] == code]
        if len(matched) != 50 or {row[indexed['Group']] for row in matched} != {'QA350 group 1'} \
                or {row[indexed['Training action code']] for row in matched} != {'QA350-AF8'} \
                or 'reported_present' not in {row[indexed['Provisional attendance status']] for row in matched}:
            raise SystemExit('FAIL: provisional attendance/session/group/action reconciliation')
    result['files'][profile] = {'rows_including_header': len(rows), 'session_visible': present,
                                'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(report / 'xlsx-validation.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result))
