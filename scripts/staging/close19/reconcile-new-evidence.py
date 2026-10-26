"""Attach only executed close19 evidence to the 82-row canonical traceability."""
import datetime
import glob
import hashlib
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[3]
TRACE = ROOT / 'docs/anexo14/TRAZABILIDAD_82_2026-10-25.json'
BASE = ROOT / 'build/staging/close19'

def latest(pattern):
    paths = sorted(glob.glob(str(BASE / pattern)))
    if not paths:
        raise SystemExit(f'Missing executed evidence: {pattern}')
    return pathlib.Path(paths[-1])

evidence = {
    'identity': latest('identity-browser-*/results.json'),
    'identity_db': BASE / 'private/identity-check.json',
    'roles': latest('roles-browser-*/results.json'),
    'roles_xlsx': latest('roles-browser-*/xlsx-validation.json'),
    'roles_db': BASE / 'private/roles-check.json',
    'message': latest('message-browser-*/results.json'),
    'content': latest('content-browser-*/results.json'),
    'content_db': BASE / 'private/content-check.json',
    'media': BASE / 'media350-validation.json',
    'glossary': latest('glossary-browser-*/results.json'),
    'session': latest('session-browser-*/results.json'),
    'session_xlsx': latest('session-report-browser-*/xlsx-validation.json'),
    'unit': latest('unit/*.json'),
}
for key, path in evidence.items():
    if not path.is_file():
        raise SystemExit(f'Missing {key}: {path}')
    data = json.loads(path.read_text(encoding='utf-8-sig'))
    items = data.get('rows', data.get('results', []))
    if items and any(item.get('status') != 'PASS' for item in items):
        raise SystemExit(f'Latest evidence has failures: {key}')
    if key == 'unit' and data.get('status') != 'PASS':
        raise SystemExit('Latest unit run failed')

full = {
    'REQ-06-05': ['content', 'content_db'],
    'REQ-13-03': ['glossary', 'content_db'],
    'REQ-16-03': ['identity', 'identity_db'],
    'REQ-17-01': ['roles', 'roles_db'],
    'REQ-19-07': ['session'],
    'REQ-20-05': ['identity', 'identity_db'],
    'REQ-21-01': ['roles', 'roles_xlsx'],
    'REQ-22-01': ['roles', 'roles_xlsx'],
    'REQ-22-03': ['message', 'roles_db'],
    'REQ-24-03': ['session', 'session_xlsx'],
}
simulated = {'REQ-24-03'}  # Real Moodle XLSX and permissions, provisional synthetic attendance.
partial = {
    'REQ-06-01': ['content', 'media'], 'REQ-06-02': ['content', 'media'],
    'REQ-06-03': ['content', 'media'], 'REQ-06-04': ['content', 'media'],
    'REQ-07-02': ['content', 'media'], 'REQ-08-05': ['content', 'content_db'],
    'REQ-08-06': ['content'], 'REQ-09-01': ['session'],
    'REQ-10-01': ['content', 'media'], 'REQ-10-02': ['content', 'media'],
    'REQ-15-02': ['identity', 'identity_db'], 'REQ-22-02': ['roles'],
    'REQ-23-03': ['session'], 'REQ-24-01': ['roles_xlsx'],
    'REQ-24-02': ['roles', 'roles_xlsx'],
}
external = {'REQ-03-01', 'REQ-05-01', 'REQ-05-03', 'REQ-10-06', 'REQ-11-04', 'REQ-12-02'}
trace = json.loads(TRACE.read_text(encoding='utf-8'))
# The historic tracking rows were not uniformly given a previous_state, so use the frozen original ID set.
original = {r['id'] for r in trace['rows'] if r.get('state') == 'BLOQUEADO' or 'close19_20261026' in r}
if len(original) != 46:
    raise SystemExit(f'Expected 46 original blockers, got {len(original)}')
if (set(full) | set(partial) | external) - original:
    raise SystemExit('Evidence mapping includes nonblocked ID')
seen = {}
for row in trace['rows']:
    req = row['id']
    if req not in original:
        continue
    if req in full:
        state = 'PASS_SIMULATED' if req in simulated else 'PASS_REAL_LOCAL'
        keys = full[req]
        row['state'] = 'VERIFICADO LOCAL'
        row['technical_status'] = 'VERIFICADO EN MOODLE LOCAL CON DATOS SINTÉTICOS'
        row['plugin_version'] = '0.5.1 (2026102601)'
        row['observed'] = 'Función técnica comprobada en staging 8054; aceptación contractual no acreditada.'
    elif req in partial:
        state = 'PARTIAL'
        keys = partial[req]
        row['technical_status'] = 'SUBPRUEBA LOCAL APROBADA; CRITERIO INTEGRAL AÚN BLOQUEADO'
    elif req in external:
        state, keys = 'BLOCKED_EXTERNAL', []
    else:
        state, keys = 'NOT_EXECUTED', []
    files = [{'path': str(evidence[key].relative_to(ROOT)).replace('\\', '/'),
              'sha256': hashlib.sha256(evidence[key].read_bytes()).hexdigest()} for key in keys]
    row['close19_20261026'] = {'status': state, 'evidence': files,
        'environment': 'http://127.0.0.1:8054, isolated local Docker',
        'formal_acceptance': 'NO ACREDITADA'}
    if keys:
        previous = row.get('evidence')
        row['evidence'] = list(dict.fromkeys(([previous] if isinstance(previous, str) else list(previous or [])) +
                                                 [f['path'] for f in files]))
    seen[req] = state
trace['counts'] = {key: sum(row['state'] == key for row in trace['rows']) for key in
                   ['VERIFICADO LOCAL', 'PENDIENTE', 'BLOQUEADO']}
trace['close19_assessment'] = {'assessed_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'originally_blocked': 46,
    'statuses': {status: sum(value == status for value in seen.values()) for status in
                 ['PASS_REAL_LOCAL', 'PASS_SIMULATED', 'PARTIAL', 'FAIL', 'BLOCKED_EXTERNAL', 'NOT_EXECUTED']},
    'official_acceptances': 0,
    'note': 'A passing subtest never changes an integral criterion from BLOQUEADO.'}
assert len(trace['rows']) == 82 and sum(trace['counts'].values()) == 82
TRACE.write_text(json.dumps(trace, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
matrix = ROOT / 'docs/anexo14/MATRIZ_ASODELTOL_82.md'
lines = matrix.read_text(encoding='utf-8').splitlines()
byid = {row['id']: row for row in trace['rows']}
for index, line in enumerate(lines):
    if line.startswith('**34 verificados locales**') or line.startswith('**43 verificados locales**') or \
            line.startswith('**44 verificados locales**'):
        lines[index] = '**44 verificados locales, 2 pendientes, 36 bloqueados, 0 aceptaciones oficiales.** ' + \
            'De los 46 bloqueos originales, esta misión verificó técnicamente 9 de forma local y 1 con asistencia sintética; ' + \
            '15 tienen sólo subpruebas. Ningún estado certifica producción.'
    if not line.startswith('| REQ-'):
        continue
    fields = line.split('|')
    req = fields[1].strip()
    if req not in seen:
        continue
    row = byid[req]
    status = seen[req]
    if status in {'PASS_REAL_LOCAL', 'PASS_SIMULATED'}:
        fields[4] = ' IMPLEMENTADO+VERIFICADO '
        fields[5] = (' Función técnica probada con datos sintéticos; asistencia declarada provisional. ' if
                     status == 'PASS_SIMULATED' else ' Función técnica probada en Moodle local. ')
        fields[6] = ' ' + status + '; aceptación oficial pendiente '
    elif status == 'PARTIAL':
        fields[5] = ' Subprueba local aprobada; criterio integral aún bloqueado. '
        fields[6] = ' PARTIAL '
    if status not in {'NOT_EXECUTED', 'BLOCKED_EXTERNAL'}:
        fields[9] = ' ' + '; '.join(item['path'] for item in row['close19_20261026']['evidence']) + ' '
    lines[index] = '|'.join(fields)
matrix.write_text('\n'.join(lines) + '\n', encoding='utf-8')
print(json.dumps({'counts': trace['counts'], 'close19': trace['close19_assessment']['statuses']}))
