"""Preserve exactly the supplied ASODELTOL IDs and separate historical evidence.

No source workbook is modified. Tests never imply formal acceptance.
"""
import argparse
import collections
import hashlib
import json
import pathlib
import subprocess
import openpyxl

ROOT = pathlib.Path(__file__).resolve().parents[2]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('source', type=pathlib.Path)
    args = parser.parse_args()
    book = openpyxl.load_workbook(args.source)
    sheet = book['Matriz Codex']
    values = list(sheet.values)
    headers = values[0]
    rows = [dict(zip(headers, row)) for row in values[1:] if row[0]]
    ids = [row['ID REQ'] for row in rows]
    if len(ids) != 82 or len(set(ids)) != 82:
        raise ValueError('Expected exactly 82 distinct original IDs')
    sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    result = []
    latesttests = sorted((ROOT / 'build/staging/anexo14-isolated').glob('*-summary.json'))
    unitpath = latesttests[-1] if latesttests else None
    unit = json.loads(unitpath.read_text()) if unitpath else {}
    mappings = {
        'REQ-12-01': 'communication_support_test: soporte configurado y rechazo sin destinatario; falta UI/CAU productivo',
        'REQ-19-02': 'communication_support_test: anuncio al grupo y calendario nativo; falta recorrido navegador',
        'REQ-20-02': 'communication_support_test: aviso de anuncio; pendiente aviso de actividad',
        'REQ-21-02': 'observer_scope_test: consulta del propio grupo y denegación de otro; perfil sintético',
        'REQ-22-01': 'observer_scope_test: beneficiarios del propio grupo; residencia/caracterización pendientes',
        'REQ-22-02': 'observer_scope_test: API de progreso por grupo; libro de notas/UI pendientes',
    }
    technical_evidence = {
        'REQ-14-02': ('native_academic_fields_test: guarda identidad/descripcion/estado del grupo y metadatos de curso',
                      ['plugins/local/asodeltolstaff/classes/metadata_fields.php',
                       'plugins/local/asodeltolstaff/tests/native_academic_fields_test.php']),
        'REQ-19-03': ('group_activity_notification_test: actividad/materiales visibles solo para el grupo autorizado',
                      ['plugins/local/asodeltolstaff/classes/group_publisher.php',
                       'plugins/local/asodeltolstaff/tests/group_activity_notification_test.php']),
        'REQ-19-05': ('section_release_test: habilita unidad para grupo y conserva restricciones de fecha existentes',
                      ['plugins/local/asodeltolstaff/classes/group_communication.php',
                       'plugins/local/asodeltolstaff/tests/section_release_test.php']),
        'REQ-20-02': ('communication_support_test y group_activity_notification_test: aviso de anuncio y actividad pendiente',
                      ['plugins/local/asodeltolstaff/classes/group_publisher.php',
                       'plugins/local/asodeltolstaff/tests/communication_support_test.php',
                       'plugins/local/asodeltolstaff/tests/group_activity_notification_test.php']),
    }
    for row in rows:
        state = row['Evidencia local documentada']
        entry = {
            'id': row['ID REQ'], 'criterion': row['Criterio (literal Anexo 14)'],
            'previous_state': state, 'state': state,
            'implementation': 'Conservar implementación existente; revisión integral todavía abierta',
            'files': [], 'positive_test': 'Sin nueva prueba integral acreditada para este criterio',
            'negative_test': 'Pendiente según alcance', 'observed': 'Estado histórico conservado, no recertificado',
            'evidence': row['Siguiente prueba / decisión (fuente)'],
            'environment': 'Histórico local; nueva batería aislada se registra por separado',
            'commit': sha, 'production': 'NO VERIFICADA EN ESTA INTERVENCIÓN',
            'dependency': row['Dependencia'], 'next_action': row['Acción de ingeniería'],
            'formal_acceptance': 'NO ACREDITADA',
        }
        if row['ID REQ'] in mappings and unit.get('status') == 'PASS':
            entry.update(implementation=mappings[row['ID REQ']],
                         positive_test='Batería PHPUnit privada PASS; cobertura parcial del criterio',
                         negative_test='Denegación entre grupos / destinatario no configurado según caso',
                         observed='Prueba técnica parcial; estado integral conservado',
                         evidence=str(unitpath.relative_to(ROOT)),
                         environment='Moodle 5.3+ / PHP 8.4.27 / MySQL 8.4.13, BD/dataroot aislados',
                         files=['plugins/local/asodeltolstaff/tests/communication_support_test.php',
                                'plugins/local/asodeltolstaff/tests/observer_scope_test.php'])
        if row['ID REQ'] in technical_evidence and unit.get('status') == 'PASS':
            description, files = technical_evidence[row['ID REQ']]
            entry.update(implementation=description,
                         positive_test='PHPUnit 11.5.56 PASS; cubre el flujo técnico indicado, no el criterio integral',
                         negative_test='Aislamiento por grupo y/o conservación de restricciones validada por el test referido',
                         observed='Cobertura técnica parcial; estado integral y aceptación oficial conservados',
                         evidence=str(unitpath.relative_to(ROOT)),
                         environment='Moodle 5.3+ / PHP 8.4.27 / MySQL 8.4.13, BD/dataroot aislados',
                         files=files)
        loadpath = ROOT / 'build/staging/load50/summary.json'
        if row['ID REQ'] in ('REQ-01-01', 'REQ-01-02') and loadpath.exists():
            load = json.loads(loadpath.read_text())
            entry.update(implementation='Runner HTTP de 50 beneficiarios sintéticos distintos',
                         files=['scripts/staging/check-load50.py', 'scripts/staging/prepare-load-fixture.php'],
                         positive_test='Inicio, curso y calificaciones, 50 peticiones concurrentes por ruta',
                         observed=load['status'] + '; curso sintético vacío, no certifica capacidad productiva',
                         evidence=str(loadpath.relative_to(ROOT)), environment=load['environment'])
        result.append(entry)
    out = ROOT / 'build/staging/cierre82'
    out.mkdir(parents=True, exist_ok=True)
    audit = book.create_sheet('Trazabilidad nueva')
    audit.append(list(result[0]))
    for row in result:
        audit.append([json.dumps(v, ensure_ascii=False) if isinstance(v, list) else v for v in row.values()])
    book.save(out / 'ASODELTOL_Seguimiento_82_actualizado.xlsx')
    counts = dict(collections.Counter(row['state'] for row in result))
    payload = {'source': args.source.name, 'source_sha256': hashlib.sha256(args.source.read_bytes()).hexdigest(),
               'base_commit': sha, 'total': len(result), 'counts': counts, 'formal_acceptances': 0, 'rows': result}
    (out / 'matrix.json').write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding='utf-8')
    documentation = ROOT / 'docs/anexo14/TRAZABILIDAD_82_2026-10-25.json'
    documentation.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding='utf-8')
    print(json.dumps({'total': len(result), 'counts': counts, 'formal_acceptances': 0}, ensure_ascii=False))


if __name__ == '__main__':
    main()
