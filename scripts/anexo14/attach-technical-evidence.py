"""Attach verified local test evidence to existing ASODELTOL trace rows.

This preserves the supplied 82 requirement texts and integral states. It never
infers institutional acceptance from a passing technical test.
"""
import collections
import datetime
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[2]
TRACE = ROOT / 'docs/anexo14/TRAZABILIDAD_82_2026-10-25.json'
EVIDENCE = {
    'REQ-14-02': ('native_academic_fields_test: guarda identidad, descripción y vínculo del grupo; registra disponibilidad administrativa y metadatos descriptivos del curso', [
        'plugins/local/asodeltolstaff/classes/metadata_fields.php',
        'plugins/local/asodeltolstaff/tests/native_academic_fields_test.php']),
    'REQ-19-03': ('group_activity_notification_test: publica material y actividad con visibilidad restringida al grupo autorizado', [
        'plugins/local/asodeltolstaff/classes/group_publisher.php',
        'plugins/local/asodeltolstaff/tests/group_activity_notification_test.php']),
    'REQ-19-05': ('section_release_test: habilita una unidad para el grupo autorizado y conserva la condición de fecha existente', [
        'plugins/local/asodeltolstaff/classes/group_communication.php',
        'plugins/local/asodeltolstaff/tests/section_release_test.php']),
    'REQ-20-02': ('communication_support_test y group_activity_notification_test: prueba aviso de anuncio y notificación de actividad pendiente', [
        'plugins/local/asodeltolstaff/classes/group_publisher.php',
        'plugins/local/asodeltolstaff/tests/communication_support_test.php',
        'plugins/local/asodeltolstaff/tests/group_activity_notification_test.php']),
}


def main():
    summaries = sorted((ROOT / 'build/staging/anexo14-isolated').glob('*-summary.json'))
    if not summaries:
        raise SystemExit('No existe evidencia PHPUnit aislada.')
    summary_path = summaries[-1]
    summary = json.loads(summary_path.read_text(encoding='utf-8'))
    if summary.get('status') != 'PASS' or summary.get('test_count', 0) < 1:
        raise SystemExit('La última batería PHPUnit no está en PASS; la matriz queda intacta.')
    trace = json.loads(TRACE.read_text(encoding='utf-8'))
    rows = {row['id']: row for row in trace['rows']}
    missing = set(EVIDENCE) - rows.keys()
    if missing:
        raise SystemExit('Faltan IDs de la matriz existente: ' + ', '.join(sorted(missing)))
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    relative = str(summary_path.relative_to(ROOT))
    for reqid, (description, files) in EVIDENCE.items():
        row = rows[reqid]
        priorstate = row['state']
        row.update(
            implementation=description,
            files=files,
            positive_test=f"PHPUnit {summary['test_count']} casos PASS; cobertura técnica parcial del criterio",
            negative_test='Denegación entre grupos y/o conservación de restricción académica verificada por los tests asociados',
            observed='Evidencia técnica local parcial; se conserva el estado integral y no se acredita aceptación institucional',
            evidence=relative,
            environment='Moodle 5.3+ / PHP 8.4.27 / MySQL 8.4.13; base PHPUnit privada y dataroot separado',
            commit=commit,
            production='NO VERIFICADA; sin despliegue VPS',
            formal_acceptance='NO ACREDITADA',
        )
        row['state'] = priorstate
    trace['base_commit'] = commit
    trace['counts'] = dict(collections.Counter(row['state'] for row in trace['rows']))
    trace['formal_acceptances'] = 0
    trace['evidence_updated_at'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    TRACE.write_text(json.dumps(trace, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print(json.dumps({'total': trace['total'], 'counts': trace['counts'],
                      'formal_acceptances': 0, 'evidence': relative}, ensure_ascii=False))


if __name__ == '__main__':
    main()
