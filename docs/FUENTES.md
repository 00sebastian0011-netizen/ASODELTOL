# Fuentes técnicas y normativas

Consulta realizada para la Misión 01 el 21 de octubre de 2026. Las páginas de versiones, compatibilidad y plugins son información cambiante y deben verificarse otra vez antes de implementar o desplegar.

## Moodle 5.x

- [Moodle 5.0: requisitos, PHP 8.4 y estado de soporte](https://moodledev.io/general/releases/5.0)
- [Política de compatibilidad de PHP](https://moodledev.io/general/development/policies/php)
- [Finalización de actividad](https://docs.moodle.org/500/en/Completion_tracking)
- [Restricción de acceso](https://docs.moodle.org/500/en/Manage_access_restrictions)
- [Informe de finalización de actividad](https://docs.moodle.org/500/en/Activity_completion_report)
- [Cohortes](https://docs.moodle.org/500/en/Cohorts_enrolment)
- [Sincronización de cohortes](https://docs.moodle.org/500/en/enrol/cohort/edit)
- [Grupos](https://docs.moodle.org/500/en/Groups_overview)
- [Logs](https://docs.moodle.org/500/en/Logs)
- [Reportes personalizados](https://docs.moodle.org/500/en/Custom_reports)
- [Exportación de calificaciones](https://docs.moodle.org/500/en/Grade_export)
- [BigBlueButton](https://docs.moodle.org/500/en/BigBlueButton)
- [Actividad LTI External tool](https://docs.moodle.org/500/en/mod/lti/mod)
- [Roles: administrador](https://docs.moodle.org/500/en/Administrator), [manager](https://docs.moodle.org/500/en/Manager), [profesor](https://docs.moodle.org/500/en/Teacher), [profesor sin edición](https://docs.moodle.org/500/en/Non-editing_teacher) y [estudiante](https://docs.moodle.org/500/en/Student_role)

### Aprovisionamiento contrastado para Moodle 5.3 — Misión 04

- [Carga de cursos](https://docs.moodle.org/503/en/Upload_courses): campos `category_idnumber`, `visible`, `groupmode`, `groupmodeforce`, `enablecompletion` y matriculación de cohorte con `enrolment_N_addtogroup`.
- [Creación y plantillas de curso](https://docs.moodle.org/503/en/Adding_a_new_course): importación desde curso plantilla o backup real.
- [Carga de cohortes](https://docs.moodle.org/503/en/cohort/upload): formato CSV y contextos admitidos.
- [Sincronización de cohortes](https://docs.moodle.org/503/en/Cohort_sync): enrolamiento sincronizado de miembros.
- [CLI oficial de carga de cursos — rama `MOODLE_503_STABLE`](https://github.com/moodle/moodle/blob/MOODLE_503_STABLE/public/admin/tool/uploadcourse/cli/uploadcourse.php): modos y argumentos usados por el procedimiento.
- [Creación rápida de módulo — rama `MOODLE_503_STABLE`](https://github.com/moodle/moodle/blob/MOODLE_503_STABLE/public/course/format/classes/external/new_module.php): evidencia de que la operación está condicionada a `FEATURE_QUICKCREATE`.
- [Declaración de External Services en Moodle 5.3](https://moodledev.io/docs/5.3/apis/subsystems/external/description): las funciones disponibles se declaran y se descubren; no se inventan endpoints.

## Desarrollo y calidad

- [Tipos de plugin](https://moodledev.io/docs/5.1/apis/plugintypes) — la taxonomía aplica a los tipos usados por Moodle 5.x.
- [APIs de Moodle](https://moodledev.io/docs/5.0/category/subsystems)
- [Access API](https://moodledev.io/docs/5.0/apis/subsystems/access)
- [Privacy API FAQ](https://moodledev.io/docs/5.0/apis/subsystems/privacy/faq)
- [Seguridad para desarrolladores](https://moodledev.io/general/development/policies/security)
- [Pruebas en Moodle](https://moodledev.io/general/development/process/testing)
- [PHPUnit en Moodle](https://moodledev.io/docs/5.3/guides/testing) — indica PHPUnit 11 para Moodle 5.0+; verificar la guía de la rama exacta al implementar.
- [Behat](https://moodledev.io/general/development/tools/behat)
- [Report builder API, PHPDoc 5.0](https://phpdoc.moodledev.io/5.0/d7/d4a/classcore__reportbuilder_1_1datasource.html)
- [Backup de cursos](https://docs.moodle.org/500/en/Backup_restore)
- [Preguntas de backup y restauración](https://docs.moodle.org/500/en/Backup_and_restore_FAQ)

## Plugins candidatos

- [Attendance: versiones](https://moodle.org/plugins/mod_attendance/versions) — el directorio declara una versión para Moodle 5.0.
- [Attendance: documentación](https://docs.moodle.org/500/en/Attendance)

Que una versión aparezca compatible no constituye aprobación. Debe revisarse código, licencia, mantenimiento, privacidad, incidencias, dependencias y funcionamiento en staging.

## Protección de datos en Colombia

- [Ley 1581 de 2012 — Gestor Normativo de Función Pública](https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981)
- [Texto de la Ley 1581 de 2012 — SIC](https://sedeelectronica.sic.gov.co/sites/default/files/normatividad/Ley_1581_2012.pdf)
- [Políticas de tratamiento y referencia al Decreto 1074 de 2015 — SIC](https://sedeelectronica.sic.gov.co/publicaciones/boletin-juridico/concepto/politicas-de-tratamiento-de-datos-personales)

La aplicación concreta debe validarse con el responsable institucional y asesoría jurídica; este repositorio no emite concepto legal.
