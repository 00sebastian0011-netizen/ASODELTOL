"""Read actual Moodle XLSX bytes and reconcile scoped rows against QA350 group ranges."""
import hashlib
import json
import sys
import zipfile
from datetime import datetime, timezone
from pathlib import Path
from xml.etree import ElementTree as ET

root = Path(__file__).resolve().parents[3]
evidence = root / 'build/staging/close19'
dirs = sorted((p for p in evidence.glob('roles-browser-*') if (p / 'results.json').is_file()),
              key=lambda p: p.stat().st_mtime)
if not dirs:
    raise SystemExit('No browser evidence')
run = dirs[-1]
ns = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
out = {'run': run.name, 'checked_at': datetime.now(timezone.utc).isoformat(), 'files': {}}
for profile, group, lower, upper in [
        ('sena', 'QA350 group 1', 1, 50),
        ('interventoria', 'QA350 group 2', 51, 100),
        ('trainer', 'QA350 group 1', 1, 50)]:
    path = run / f'{profile}-participants.xlsx'
    with zipfile.ZipFile(path) as archive:
        if archive.testzip() is not None:
            raise RuntimeError(f'{profile}: corrupt zip member')
        shared = ET.fromstring(archive.read('xl/sharedStrings.xml'))
        strings = [''.join(node.itertext()) for node in shared.findall('s:si', ns)]
        sheet = ET.fromstring(archive.read('xl/worksheets/sheet1.xml'))
        rows = []
        for row in sheet.findall('.//s:sheetData/s:row', ns):
            values = {}
            for cell in row.findall('s:c', ns):
                ref = cell.get('r', '')
                column = ''.join(c for c in ref if c.isalpha())
                value = cell.find('s:v', ns)
                inline = cell.find('s:is', ns)
                if cell.get('t') == 'inlineStr' and inline is not None:
                    values[column] = ''.join(inline.itertext())
                elif value is not None:
                    values[column] = strings[int(value.text)] if cell.get('t') == 's' else value.text
            rows.append(values)
    if len(rows) != 51:
        raise RuntimeError(f'{profile}: expected header + 50 students, got {len(rows)}')
    if not rows[0].get('G', '').startswith('Residence') or not rows[0].get('H', '').startswith('Characterisation'):
        raise RuntimeError(f'{profile}: profile column labels are missing or untranslated')
    data = rows[1:]
    users = {row.get('D') for row in data}
    expected = {f'qa350_{i:03d}' for i in range(lower, upper + 1)}
    if users != expected or len(users) != 50 or {row.get('C') for row in data} != {group}:
        raise RuntimeError(f'{profile}: group/user range mismatch')
    if profile != 'interventoria':
        characterised = next(row for row in data if row.get('D') == 'qa350_003')
        if characterised.get('G') != 'Residencia QA navegador' or characterised.get('H') != 'Grupo sintético QA':
            raise RuntimeError(f'{profile}: saved private profile values missing from authorised export')
    out['files'][profile] = {'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
                             'students': len(data), 'unique_users': len(users), 'group': group,
                             'source': 'Moodle XLSX download, browser-authenticated'}
target = run / 'xlsx-validation.json'
target.write_text(json.dumps(out, indent=2), encoding='utf8')
print(json.dumps({'status': 'PASS_REAL_LOCAL', 'profiles': 3, 'students_per_file': 50,
                  'evidence': str(target)}, ensure_ascii=False))
