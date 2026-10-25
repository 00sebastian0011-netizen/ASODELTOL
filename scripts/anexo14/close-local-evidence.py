"""Close local evidence from actual reports, without publishing or reading secrets."""
import hashlib
import json
import pathlib
import shutil
import subprocess
import xml.etree.ElementTree as ET

ROOT = pathlib.Path(__file__).resolve().parents[2]
BUILD = ROOT / 'build/staging'
OUT = BUILD / 'anexo14-compliance'
WEB = 'asodeltol-moodle53-webserver-1'


def load(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def latest(folder, pattern='*/summary.json'):
    return sorted((BUILD / folder).glob(pattern))[-1]


def command(args, source=None):
    result = subprocess.run(args, input=source, text=True, capture_output=True, check=True)
    return result.stdout


def tally(result):
    checks = result.get('checks', [])
    return ', '.join(f'{sum(c.get("status") == state for c in checks)} {state}'
                     for state in ('PASS', 'FAIL', 'BLOCKED'))


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    context = command(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                       '/opt/asodeltol/scripts/anexo14-qa-context.php'])
    assert '"course_hidden":true' in context and '"category_hidden":true' in context
    (OUT / 'final-context.txt').write_text(context, encoding='utf-8')
    preflight = json.loads(command(['docker', 'exec', '--user', 'www-data', WEB, 'php',
                                    '/opt/asodeltol/scripts/anexo14-upgrade-preflight.php']))
    assert preflight['course_hidden'] and preflight['category_hidden']
    assert preflight['publishown_grants'] == 0 and not preflight['pending_plugins']
    (OUT / 'final-integrity.json').write_text(json.dumps(preflight, indent=2), encoding='utf-8')
    unitpath = latest('anexo14-isolated', '*-summary.json')
    unit = load(unitpath)
    assert unit['status'] == 'PASS'
    junit = unitpath.with_name(unitpath.name.replace('-summary.json', '-junit.xml'))
    cases = list(ET.parse(junit).iter('testcase'))
    assert cases and not any(c.find(tag) is not None for c in cases for tag in ('error', 'failure', 'skipped'))
    assertions = sum(int(c.attrib.get('assertions', 0)) for c in cases)
    # Verify every packaged source file against installed and actually tested code.
    source = ROOT / 'plugins/local/asodeltolstaff'
    host = {p.relative_to(source).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
            for p in source.rglob('*') if p.is_file()}
    probe = '''<?php
    $result = [];
    foreach (array_slice($argv, 1) as $root) {
        $hashes = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,
                FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) { $hashes[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname()); }
        }
        ksort($hashes); $result[] = $hashes;
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
    '''
    installed, tested = json.loads(command(['docker', 'exec', '-i', '--user', 'www-data', WEB,
        'php', '/dev/stdin', '/var/www/html/public/local/asodeltolstaff',
        unit['workspace'] + '/code/public/local/asodeltolstaff'], probe))
    assert host == installed == tested, 'Plugin differs between repository, staging and tested private runtime'
    parity = {'status': 'PASS', 'files': len(host), 'sha256_by_file': host,
              'workspace': unit['workspace'], 'junit': junit.relative_to(ROOT).as_posix()}
    (OUT / 'code-parity.json').write_text(json.dumps(parity, indent=2), encoding='utf-8')
    matrix = load(OUT / 'matrix-82.json')
    assert len(matrix['requirements']) == sum(matrix['counts'].values()) == 82
    functionalpath = latest('anexo14-functional')
    functional = load(functionalpath)
    assert functional['status'] == 'PASS' and functional['final_hidden'] is True
    reportpath = latest('anexo14-reports')
    reports = load(reportpath)
    browserpaths = sorted((BUILD / 'anexo14-browser').glob('*/summary.json'))
    best = {}
    for path in browserpaths:
        result = load(path)
        if result.get('checks') and all(c['status'] == 'PASS' for c in result['checks']):
            mode = ('ayuda' if any(c['id'].startswith('HELP-') for c in result['checks']) else
                    'archivos privados' if any(c['id'].startswith('PRIVATE-FILES-') for c in result['checks']) else 'administración')
            best[(result['browser'], mode)] = path
    release = load(BUILD / 'anexo14-release/manifest.json')
    lines = ['# Cierre técnico local ASODELTOL — 24 de octubre de 2026', '',
        'Esta entrega amplía el resultado histórico de la primera fase. Se trabajó únicamente en la rama '
        '`moodle-completo-2026-10-24`, Moodle 5.3+ / PHP 8.4.27 / MySQL 8.4, http://127.0.0.1:8053. '
        'No hubo commit, push, VPS ni cambios de producción. No se eliminaron bases, volúmenes, cursos, usuarios o evidencias.', '',
        '## Resultado de los 82 criterios', '', '| Estado | Cantidad |', '|---|---:|']
    lines += [f'| {state} | {count} |' for state, count in matrix['counts'].items()]
    lines += ['', '**Aceptación oficial: 0/82 acreditadas.** Ver alcance, evidencia y responsable por fila en '
        '`MATRIZ_ASODELTOL_82.md` y `build/staging/anexo14-compliance/ASODELTOL-matriz-82.xlsx`. '
        'Una prueba de API aislada acredita esa función local; no acredita su interfaz ni producción. '
        'La matriz ASODELTOL se mantiene separada de la referencia ASOCODEM-AF1.', '',
        '## Implementado', '',
        '- Plugin 0.3.0 / 2026102401 instalado localmente: reportes CSV/XLSX de sesiones, beneficiarios, consolidado y accesos; '
        'marcas UTC y fuentes nativas diferenciadas; citas de atención en calendario; guía de usuario visible; '
        'avance por curso/unidad y publicador limitado al grupo propio.',
        '- Publicador con capacidad dedicada, matrícula y grupo, sesskey, contenido saneado, HTTPS y archivos del borrador propio; '
        'recursos creados con restricción nativa de grupo. La nueva capacidad está registrada con cero concesiones. '
        'No se modificaron roles institucionales definitivos.',
        '- PHPUnit con cuenta/base/dataroot privados, grants exclusivos y rechazo comprobado a `moodle.mdl_course`. '
        'Se reutilizó el runtime seguro sin reinicializar la instalación operativa.',
        '- Plantilla y validador de 54 componentes para el tercero; ZIP del plugin con manifest SHA256 y procedimiento de entrega. '
        'No se inventaron materiales, reglas académicas o aprobaciones.',
        '- Zona horaria local corregida a America/Bogota; comprobación de fechas persistidas sin modificar sus instantes. '
        'La aparente duración cero inicial coincidía con la hora repetida del cambio estacional de Europe/London.', '',
        '## Pruebas actuales', '', '| Lote | Resultado | Evidencia |', '|---|---|---|',
        f'| PHPUnit privado | {len(cases)} pruebas PASS, {assertions} aserciones, 0 fallos | `{junit.relative_to(ROOT).as_posix()}` |',
        f'| Curso, archivos, foro, notas y grupos | {tally(functional)}; final oculto | `{functionalpath.relative_to(ROOT).as_posix()}` |',
        f'| Reportes CSV/XLSX | {tally(reports)} | `{reportpath.relative_to(ROOT).as_posix()}` |']
    for (browser, mode), path in sorted(best.items()):
        lines.append(f'| {browser}, {mode} | {tally(load(path))}; tamaños emulados | `{path.relative_to(ROOT).as_posix()}` |')
    lines += ['', 'También se conservan pruebas M11, exportación de 18 columnas, curso oculto, cita con protección CSRF, '
        'mensajería bidireccional y seis controles del validador de insumos. Los lotes se solapan: no sumar como criterios únicos. '
        'El bloqueo de PHPUnit en la instalación habitual se mantiene intencionalmente; el runtime privado sí pasó.', '',
        '## Integridad y fixtures conservados', '',
        f'- Estado final: curso y categoría ocultos; {preflight["users"]} usuarios, {preflight["groups"]} grupos, '
        f'{preflight["sessions"]} sesiones staff, {preflight["attendance"]} asistencias, {preflight["history"]} entradas de historial; '
        f'{preflight["roles"]} roles y {preflight["role_assignments"]} asignaciones. No hay plugins pendientes de actualización.',
        '- Se conservaron una cita de calendario, cinco recursos sintéticos DOCX/XLSX/PPTX/PDF/PNG, una página, '
        'dos mensajes y las entregas/notas sintéticas 83/97 y foro G02 del piloto. '
        'También se conservaron los pequeños PNG de las pruebas de archivos privados del beneficiario G01 y sus borradores. Nunca copiarlos a producción.',
        f'- SHA256 coincidentes en repositorio, plugin instalado y copia probada para los {len(host)} archivos: `build/staging/anexo14-compliance/code-parity.json`.', '',
        '## Fallos, bloqueos y riesgos abiertos', '',
        '- El primer ensayo concurrente de 50 sesiones independientes del mismo administrador produjo dos errores HTTP en la ruta curso. '
        'La repetición de esa ruta tuvo cero errores; no se demostró la causa inicial ni 50 beneficiarios distintos. '
        'Las mediciones están en `build/staging/anexo14-load/`; el criterio integral de rendimiento sigue abierto.',
        '- Se conservan intentos preliminares fallidos/bloqueados de preparación, navegador y pruebas. '
        'Los resultados actuales de la tabla no borran ni convierten esos intentos en PASS.',
        '- Falta contenido definitivo del tercero y revisión académica, licencias, accesibilidad y decisiones sobre modalidad/IA, '
        'evaluación, culminación y calendario. El validador registra 0 componentes READY y 54 BLOCKED por insumos ausentes.',
        '- Anexo 12, datos y cuentas institucionales definitivas, proveedor/grabaciones, fuente de asistencia efectiva, CAU y '
        'disponibilidad 24/7 requieren responsables externos y validación del entorno final. Logs no equivalen a asistencia o trabajo.',
        '- Faltan recorridos completos en otros sistemas, Firefox/Safari y hardware móvil. Emulación de tamaños no acredita esos equipos. '
        'Moodle y plugin conservan su estado de desarrollo; el paquete ALPHA no es certificación ni aceptación SENA.',
        '- El DOCX contiene discrepancias 50/80, cuatro/nueve recursos y referencias de unidades copiadas; requieren aclaración institucional.', '',
        '## Entrega', '',
        f'- Paquete: `build/staging/anexo14-release/{release["archive"]}`; SHA256 `{release["archive_sha256"]}`.',
        '- Instrucciones: `ENTREGA_TERCERO_Y_VPS.md`. El usuario indicó que el tercero cargará los materiales; esta sesión no intervino en VPS.',
        '- Evidencias verificables: `build/staging/anexo14-compliance/evidence-manifest.json`; contexto e integridad final en la misma carpeta.', '']
    closure = ROOT / 'docs/anexo14/CIERRE_TECNICO_2026-10-24.md'
    closure.write_text('\n'.join(lines), encoding='utf-8')
    files = set(source.rglob('*'))
    files.update((ROOT / 'docs/anexo14').glob('*.md'))
    files.update((ROOT / 'scripts/anexo14').glob('*.py'))
    files.update(p for p in (ROOT / 'scripts/staging').glob('*')
                 if p.is_file() and ('anexo14' in p.name or p.name.startswith(('check-', 'retest-', 'build-file-')))
                 and p.suffix in ('.py', '.php', '.ps1', '.mjs'))
    for folder in ('anexo14-qa', 'anexo14-reports', 'anexo14-functional', 'anexo14-load', 'anexo14-release'):
        files.update((BUILD / folder).rglob('*.json'))
    for path in best.values():
        files.add(path)
        files.update(path.parent.glob('*.png'))
        files.update(path.parent.glob('downloads/*.png'))
    files.update(browserpaths)
    files.update((unitpath, junit, unitpath.with_name(unitpath.name.replace('-summary.json', '-test.txt'))))
    files.update(p for p in OUT.glob('*') if p.is_file() and p.name not in ('evidence-manifest.json', 'evidence-manifest-first-phase.json'))
    files.add(BUILD / 'anexo14-release' / release['archive'])
    files.update((BUILD / 'anexo14-third-party').rglob('validation*.json'))
    files = sorted(p for p in files if p.is_file())
    manifestpath = OUT / 'evidence-manifest.json'
    historical = OUT / 'evidence-manifest-first-phase.json'
    if manifestpath.exists() and not historical.exists():
        shutil.copyfile(manifestpath, historical)
    manifest = {'scope': 'ASODELTOL local, no aceptación oficial ni producción',
        'artifacts': [{'path': p.relative_to(ROOT).as_posix(), 'bytes': p.stat().st_size,
                       'sha256': hashlib.sha256(p.read_bytes()).hexdigest()} for p in files]}
    manifestpath.write_text(json.dumps(manifest, ensure_ascii=False, indent=2), encoding='utf-8')
    assert all(hashlib.sha256((ROOT / item['path']).read_bytes()).hexdigest() == item['sha256']
               for item in manifest['artifacts'])
    print(json.dumps({'counts': matrix['counts'], 'tests': len(cases), 'assertions': assertions,
                      'parity_files': len(host), 'manifest_artifacts': len(files), 'final_hidden': True}))


if __name__ == '__main__':
    main()
