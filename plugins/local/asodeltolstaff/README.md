# `local_asodeltolstaff`

Plugin instalable para asistencia, seguimiento y herramientas de grupo AF8. Conserva en entidades separadas el horario programado, el intervalo reportado y el tiempo validado administrativamente, y reutiliza Moodle para calendario, publicación y finalización.

## Alcance funcional

- Registra sesiones por curso, unidad, grupo y capacitador.
- Relaciona estados provisionales de asistencia con estudiantes matriculados del grupo.
- Conserva historial anexable de creación, corrección y validación con actor, fecha y motivo.
- Limita a cada capacitador a sus grupos mediante capacidades y membresía nativa de Moodle.
- Separa la declaración del capacitador de la validación administrativa.
- Exporta filas filtradas en CSV y XLSX mediante `core\dataformat`.
- Construye las filas de exportación con consultas por lotes de sesiones, grupos, nombres y asistencia, evitando consultas repetidas por fila.
- Declara los datos tratados mediante Privacy API.

Los estados de asistencia, los 100 minutos validados en las pruebas y las referencias `example.invalid` son escenarios técnicos sintéticos. No definen asistencia válida, tiempo contractual, pagos ni política institucional.

## Reutilización de Moodle

El plugin usa matrículas, grupos, secciones, roles/capacidades, Forms API, `sesskey`, DML/XMLDB, eventos, Privacy API y data formats de Moodle. No modifica el núcleo ni el esquema de tablas nativas; crea recursos y citas mediante sus APIs. La evidencia se representa como una referencia HTTPS; no se copian grabaciones ni credenciales.

`mod_attendance` sigue siendo un candidato para la toma de lista, pero a 22 de octubre de 2026 el directorio oficial sólo declara compatibilidad hasta Moodle 5.0. No se instaló ningún plugin externo en Moodle 5.3.

## Demostración local

Con el staging local ya preparado:

```powershell
powershell -NoProfile -File scripts/staging/run-attendance-staff.ps1 -Phase All
```

El ejecutor instala o actualiza el plugin sólo si la versión registrada no coincide, usa exclusivamente las cuentas sintéticas existentes, ejecuta controles positivos y negativos por HTTP y servicio, guarda evidencia ignorada por Git bajo `build/` y oculta de nuevo el curso y su categoría en `finally`.

La versión conserva madurez ALPHA. La instalación y las pruebas locales no acreditan producción ni aceptación institucional. La entrega para el tercero, insumos y verificaciones del entorno final están en `docs/anexo14/ENTREGA_TERCERO_Y_VPS.md`.

## Ampliación local de trazabilidad — 2026-10-24

El CSV/XLSX incluye inicio y fin programados y reportados como ISO 8601 UTC
(`scheduledstart`, `scheduledend`, `reportedstart`, `reportedend`). Los valores
reportados ausentes quedan vacíos. Se conservan las columnas de duraciones.
El archivo también identifica el código y el nombre de la acción de formación;
si el curso carece de idnumber, usa su nombre corto como código.
Son marcas de sesiones formativas, no inicios/cierres de sesión de acceso a Moodle.
Esta ampliación no acredita por sí sola REQ-24-04 ni asistencia efectiva.

## Herramientas 0.4.0

Ayuda autenticada en navegación nativa; reportes CSV/XLSX de beneficiarios,
consolidado y eventos de acceso; citas de atención de al menos 60 minutos en
calendario nativo; avance de curso y cinco unidades mediante Completion API.
Los login/logout son eventos independientes y no demuestran tiempo trabajado.
Las finalizaciones nativas no sustituyen una regla institucional de culminación.

`publish.php` crea páginas, enlaces HTTPS o archivos usando APIs nativas y
disponibilidad obligatoria para el grupo propio. Requiere matrícula activa,
la capacidad `local/asodeltolstaff:publishown`; esta capacidad no se concede
por defecto a roles. Los archivos deben proceder del borrador del actor actual.
No sobrescribe recursos, impersona administradores ni define reglas académicas.

Los perfiles definitivos se configuran en la última fase autorizada. Los permisos
de reportes y avance respetan contexto y membresía; consultar logs globales exige
también `report/log:view` en sistema. La Privacy API declara las entidades propias
y el uso de calendario; archivos y recursos conservan sus mecanismos nativos.

Pruebas: `python scripts/staging/retest-isolated-phpunit.py` reutiliza solamente
un runtime privado previamente inicializado y verifica aislamiento antes de
ejecutar. No ejecutar PHPUnit sobre la base o dataroot operativos. Los recorridos
HTTP locales están documentados en `docs/anexo14/PRUEBAS_LOCALES_REPRODUCIBLES.md`.
