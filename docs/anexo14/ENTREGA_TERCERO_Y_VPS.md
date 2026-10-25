# Entrega técnica para carga por tercero y futura publicación

El usuario indicó que un tercero cargará los materiales. Esta intervención prepara código, plantillas y evidencia local; no realiza operaciones en VPS ni producción. Las pruebas sintéticas no son aprobación institucional.

## Paquete de aplicación

`build/staging/anexo14-release/local_asodeltolstaff-0.4.0.zip` contiene exclusivamente el plugin y sus pruebas. El manifest del paquete identifica cada archivo y su SHA256. No incluye Moodle core, configuración, contraseñas, bases, volúmenes ni materiales académicos.

Versión de plugin: `2026102402` / `0.4.0`, con madurez ALPHA conservada. Objetivo: Moodle 5.3+, PHP 8.4 y MySQL compatible. No se agregan dependencias Composer ni tablas. La actualización desde `2026102202` registra savepoints y la capacidad nueva `local/asodeltolstaff:publishown`, sin concederla automáticamente a ningún arquetipo. Los datos nuevos de calendario y recursos se guardan mediante las APIs nativas.

## Funciones entregadas

| Función | Entrada del responsable | Evidencia/limitación |
|---|---|---|
| Guía de usuario | Ninguna información académica | Ayuda en navegación nativa para usuarios autenticados; escritorio, tablet y móvil |
| Beneficiarios por grupo | Matrículas y grupos autorizados | CSV/XLSX, estado de matrícula y finalización nativa; no sustituye las columnas desconocidas del Anexo 12 |
| Consolidado | Reglas oficiales de finalización configuradas en Moodle | Cuenta alumnos únicos, activos, grupos y finalizaciones Moodle; no inventa el significado de culminación exitosa |
| Sesiones/asistencia | Sesiones y fuente aceptada de asistencia | Reporte existente ampliado; registro provisional no prueba presencia efectiva |
| Accesos y tiempos | Logging nativo, fechas oficiales del curso, franjas de atención | Eventos independientes de login/logout, fechas configuradas y citas; nunca se calcula tiempo trabajado con esos eventos |
| Hora de dudas | Grupo, capacitador, fecha, duración y enlace aprobados | Formulario con sesskey y capacidades; calendario nativo por grupo; duración mínima de 60 minutos |
| Publicación para el grupo propio | Archivos, página o URL y referencia de revisión | Capacidad dedicada, matrícula activa, grupo y disponibilidad nativa obligatorios; no concede autorización académica ni cambia material existente |
| Avance por curso y unidad | Finalización de actividades configurada según reglas autorizadas | Porcentajes nativos; beneficiario propio, capacitador de su grupo e Interventoría con permisos de lectura; sin actividades elegibles muestra ausencia de configuración |

El reporte de accesos permite al capacitador exportar sus propios eventos. Consultar accesos de todos los capacitadores requiere también `report/log:view` en contexto de sistema; una capacidad de exportación de curso no concede acceso global a logs.

## Carga de los 54 componentes del catálogo

Plantilla Excel y manifest: `build/staging/anexo14-third-party/`. Hay **50 componentes de cinco unidades y cuatro recursos generales enumerados**. Se conservan las discrepancias del documento original; no se fabrican los cinco recursos generales adicionales mencionados en otra instrucción del DOCX.

El tercero completa por recurso: código estable, tipo permitido por catálogo, archivo o URL HTTPS, SHA256, autor, licencia, referencia de revisión, subtítulos y estado de revisión. Los tipos no definidos quedan vacíos. No cambiar identificadores para sortear errores.

```powershell
python scripts/anexo14/content-package.py validate build/staging/anexo14-third-party/manifest.json
```

Salida 0: paquete listo para revisión; 2: faltan insumos; 1: archivo, identificador o integridad inválidos. **El validador no publica contenido, no verifica la autenticidad de una aprobación, ni reemplaza sanitización Moodle, revisión de licencias, originalidad, accesibilidad o análisis antimalware.** Los videos requieren medición real de resolución/códec y revisión de accesibilidad. Las actividades interactivas, cuestionarios, glosarios y foros se cargan/configuran con los mecanismos nativos y revisión académica.

La carga se realiza en la edición/curso que el responsable identifique expresamente, conservando los códigos del catálogo como idnumber. Mantener ocultos curso, categoría y materiales pendientes de revisión. Ninguna plantilla contiene objetivos, evaluaciones, notas, fechas o políticas institucionales inventados.

## Procedimiento para el responsable del VPS

1. Identificar la instalación activa y su versión real; verificar PHP CLI 8.4, Moodle 5.3, DocumentRoot `/public`, acceso autorizado y recuperación proporcional al cambio. No asumir que Git coincide con lo desplegado.
2. Verificar el SHA256 del ZIP. Instalar mediante la administración de plugins o colocar **solo** `asodeltolstaff/` en `public/local/` de la instalación identificada. Preservar la versión anterior para reversión y los permisos del usuario propietario del sitio.
3. Revisar los plugins pendientes antes de la actualización. Ejecutar la actualización nativa con el propietario del sitio, desde la raíz Moodle: `php admin/cli/upgrade.php --non-interactive`. No ejecutar como root ni incorporar actualizaciones ajenas al alcance.
4. Purgar cachés con `php admin/cli/purge_caches.php`. Comprobar versión instalada `2026102402`, navegación de ayuda, formularios, reportes y permisos positivos/negativos. Verificar zona horaria institucional antes de programar fechas; el staging usa `America/Bogota`.
5. Configurar los insumos y roles institucionales en la última fase autorizada: SENA, Interventoría, datos personales, duplicidad documental, Anexo 12, calendario, finalización, CAU y proveedor de videoconferencia. Conceder `publishown` únicamente al perfil autorizado y probar publicación y denegación entre grupos. En staging la capacidad está registrada y tiene cero concesiones.
6. Registrar la aceptación real por requisito y comprobar HTTPS, cron, monitoreo y posibilidad de reversión. Un HTTP 200 o un paquete instalado no constituyen aceptación del Anexo.

**No copiar la base de staging, sus volúmenes, cuentas `.invalid`, sesiones, cita, mensajes, archivos, foro o calificaciones sintéticas al VPS.** Los scripts de staging están limitados a localhost. PHPUnit requiere su propia base/cuenta/dataroot; no usar el prefijo ni el dataroot de la instalación operativa.

## Entregas externas que continúan abiertas

El tercero debe aportar contenidos y evidencias; el responsable institucional debe resolver modalidad, IA, licencias/originalidad, aprobación y culminación, calendario y excepciones, proveedor/retención de grabaciones, fuente de asistencia, campos personales y Anexo 12. La operación debe demostrar CAU y disponibilidad 24/7. También faltan Firefox/Safari, otros sistemas operativos y la carga representativa de 50 beneficiarios distintos. Estos puntos no se cierran sustituyéndolos por fixtures o plantillas.
