# `local_asodeltolstaff`

Plugin instalable para el prototipo de asistencia y seguimiento verificable de la Misión 11. Su objetivo es cubrir únicamente la brecha que Moodle core no resuelve: conservar en entidades separadas el horario programado, el intervalo reportado y el tiempo validado administrativamente.

## Alcance funcional

- Registra sesiones por curso, unidad, grupo y capacitador.
- Relaciona estados provisionales de asistencia con estudiantes matriculados del grupo.
- Conserva historial anexable de creación, corrección y validación con actor, fecha y motivo.
- Limita a cada capacitador a sus grupos mediante capacidades y membresía nativa de Moodle.
- Separa la declaración del capacitador de la validación administrativa.
- Exporta filas filtradas en CSV y XLSX mediante `core\dataformat`.
- Declara los datos tratados mediante Privacy API.

Los estados de asistencia, los 100 minutos validados en las pruebas y las referencias `example.invalid` son escenarios técnicos sintéticos. No definen asistencia válida, tiempo contractual, pagos ni política institucional.

## Reutilización de Moodle

El plugin usa matrículas, grupos, secciones, roles/capacidades, Forms API, `sesskey`, DML/XMLDB, eventos, Privacy API y data formats de Moodle. No modifica el núcleo ni tablas nativas. La evidencia se representa como una referencia HTTPS; no se copian grabaciones ni credenciales.

`mod_attendance` sigue siendo un candidato para la toma de lista, pero a 22 de octubre de 2026 el directorio oficial sólo declara compatibilidad hasta Moodle 5.0. No se instaló ningún plugin externo en Moodle 5.3.

## Demostración local

Con el staging local ya preparado:

```powershell
powershell -NoProfile -File scripts/staging/run-attendance-staff.ps1 -Phase All
```

El ejecutor instala o actualiza el plugin sólo si la versión registrada no coincide, usa exclusivamente las cuentas sintéticas existentes, ejecuta controles positivos y negativos por HTTP y servicio, guarda evidencia ignorada por Git bajo `build/` y oculta de nuevo el curso y su categoría en `finally`.

No ejecutar este prototipo en producción. Antes de promoverlo se requieren PHPUnit/Behat en CI, revisión independiente, política de retención, aprobación de capacidades y decisiones institucionales DP-009 a DP-013 y DP-018.
