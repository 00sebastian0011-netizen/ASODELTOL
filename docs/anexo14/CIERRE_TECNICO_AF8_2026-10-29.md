# Cierre técnico AF8 — intervención de producción 2026-10-29

## Alcance y decisión

Intervención ejecutada el 29 de octubre de 2026, 14:58–14:59 COT, sobre el curso AF8 de producción. El objetivo fue corregir exclusivamente la ausencia de UT2–UT5 sin publicar contenido, cambiar matrículas ni alterar datos académicos reales.

La fuente canónica de títulos y orden fue `catalog/af8/catalog.json` de `main` (`9cda0a937cfa4e27548e519ef6571fa88dd6df4e`). El catálogo marca las cinco unidades como `unpublished`; por ello las cuatro unidades nuevas quedaron ocultas.

## Cambio aplicado

- Se conservó la sección general sin cambios.
- Se conservó UT1, visible y con su título existente.
- Se crearon mediante las API nativas de Moodle las secciones UT2, UT3, UT4 y UT5.
- Se asignaron los títulos exactos del catálogo.
- UT2–UT5 quedaron con `visible=0`.
- No se crearon actividades, contenidos, usuarios, grupos, métodos de matrícula, notas ni reglas de acceso.

## Respaldo y reversión

Antes del cambio se generó un respaldo nativo Moodle del curso 4, protegido con modo `0600`:

- ubicación operativa: `/home/user/backups/af8-structure-20261029T195817Z/`;
- tamaño MBZ previo: 58,874,581 bytes;
- SHA-256 previo: `f033b55724a708575b380bebb456c876ae4333954f6dd8383a054248a0372238`.

Después del cambio se generó un segundo MBZ y se conservaron `pre.json`, `post.json`, `compare.json`, `change.log` y `SHA256SUMS`. La reversión preferida es retirar únicamente las secciones vacías 2–5 mediante API Moodle después de un nuevo preflight; el MBZ previo queda como recuperación adicional. No se ensayó una restauración sobre producción.

## Validación antes/después

| Invariante | Antes | Después | Resultado |
| --- | ---: | ---: | --- |
| Curso visible/formato topics | sí | sí | PASS |
| Actividades del curso | 17 | 17 | PASS |
| Grupos | 7 | 7 | PASS |
| Membresías de grupo | 582 | 582 | PASS |
| Usuarios matriculados agregados | 584 | 584 | PASS |
| Ítems de calificación | 1 | 1 | PASS |
| Registros de calificación | 1 | 1 | PASS |
| Roles en contexto | auditor 1, docente editor 1, estudiante 582 | sin cambios | PASS |
| UT2–UT5 ocultas | no existían | 4/4 ocultas | PASS |

El conteo de 584 matriculados incluye los 582 estudiantes y los dos roles de personal indicados; no se extrajeron identidades ni datos personales.

## Estado de requisitos

- Estructura de cinco UT: **PASS técnico en producción**. Existen UT1–UT5, con UT2–UT5 ocultas hasta aprobación académica.
- Contenido definitivo, finalización, nota aprobatoria, calendario y progresión de ocho días hábiles: **BLOCKED** por decisión/insumo institucional.
- `REQ-08-05`: **BLOCKED**, no PASS. El texto contractual exige “cinco componentes” por módulo; crear cinco unidades no resuelve qué representan esos componentes ni las contradicciones 50/80 ítems y 4/9 recursos.
- Restauración integral, CAU/SLA, correo externo, prueba física macOS y aceptación institucional: permanecen fuera de lo demostrado por esta intervención.

## Conclusión

Se cierra el defecto técnico de ausencia de UT2–UT5 sin afectar datos reales. No procede declarar cierre integral ni aceptación institucional mientras continúen los bloqueos académicos y las firmas pendientes.
