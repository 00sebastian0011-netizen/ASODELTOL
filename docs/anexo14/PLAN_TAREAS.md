# Plan de tareas — Verificación Anexo 14, ASOCODEM-AF1

> Documento histórico de ASOCODEM-AF1. Para la intervención ASODELTOL del 24/10/2026 prevalece la orden actual: solo local, sin commit/push, VPS ni producción. Las menciones anteriores a una eventual prueba productiva no autorizan ejecutarla. Ver `MATRIZ_ASODELTOL_82.md` para el inventario separado de ASODELTOL.

## Alcance y resultado

Este plan transcribe los **43 requisitos marcados «NO CUMPLE»** en la revisión 9 del archivo `1-Verificacion_Anexo14_ASOCODEM_AF1_primera_revision (1).xlsx`, generado el 22 de octubre de 2026. Mantiene los identificadores REQ del libro para que cada tarea se pueda rastrear hasta su criterio y observación.

El archivo evalúa la acción **ASOCODEM-AF1** en `aulas.prospectiva.edu.co`. Este repositorio describe y prueba principalmente **AF8/ASODELTOL** en un staging local. No hay evidencia que demuestre que sean la misma acción, instalación o versión. Por eso, el código o las pruebas de este repositorio no se consideran evidencia de cumplimiento del sitio evaluado.

**Autorización:** el alcance inicial fue solo el repositorio; después se autorizó preparar y probar staging local con datos sintéticos, y se permitió una eventual prueba CRUD sintética en producción únicamente si se verifican antes los prerrequisitos de seguridad. No se accedió ni se modificó la plataforma evaluada ni producción. El staging local es una instalación separada de AF8/ASODELTOL y su evidencia no acredita el sitio AF1.

### Estado de esta intervención

- [x] Crear esta carpeta y transcribir los 43 requisitos no conformes como tareas trazables.
- [x] Contrastar el alcance del libro con la documentación y las pruebas versionadas del repositorio.
- [x] Ejecutar `npm.cmd run quality`: validación del catálogo correcta y 32/32 pruebas aprobadas.
- [x] Implementar consultas por lotes para la exportación del plugin `local_asodeltolstaff` y añadir una prueba Moodle que limita lecturas al generar 20 filas.
- [x] Preparar `scripts/anexo14/management-smoke.php` para CRUD sintético aislado de curso/grupo/usuario y asignación de grupo; requiere correr primero en staging.
- [x] Preparar Moodle 5.3/PHP 8.4 local aislado y ejecutar el smoke test CRUD: 6 controles PASS, 4 PARTIAL y 0 FAIL; curso/grupo eliminados y usuario de prueba eliminado lógicamente.
- [ ] Ejecutar la prueba PHPUnit del plugin y el recorrido HTTP de exportación.
- [ ] Ejecutar el CRUD sintético en producción únicamente después de aceptar staging, verificar respaldo/reversión, confirmar ventana/categoría aislada y habilitar acceso CLI.
- [ ] Completar y volver a verificar los 43 requisitos en la acción y plataforma evaluadas.

**Conclusión:** el trabajo de documentación de esta intervención está completo; la verificación del Anexo 14 **no está terminada**. El libro conserva 43 requisitos «NO CUMPLE» y no se generó evidencia nueva en la plataforma objeto de evaluación.

La prueba sintética completada solo acredita llamadas API en el staging local Moodle 5.3/PHP 8.4. El acceso a producción no está configurado y no se dispone de ventana ni respaldo/reversión verificado; no se han realizado cambios ni pruebas de escritura allí. La prueba de carga en producción no está autorizada; debe ejecutarse en un staging representativo.

## Tareas pendientes

Los estados indican el siguiente paso necesario; «bloqueada» no modifica ni reemplaza el resultado del Excel.

| ID / prioridad | Tarea y criterio de cierre | Estado y dependencia |
|---|---|---|
| REQ-01-02 · Alta | Repetir la carga con N igual al grupo más grande de la acción; registrar errores, tiempos y recursos probados, y contrastar con el umbral de menos de 60 s. | **Pendiente — staging autorizado**; el registro actual informa 1.654 errores y no basta para aislar su causa. |
| REQ-02-04 · Alta | Reproducir `navigator.appVersion.match(...) is null` en el navegador/dispositivo que falló, corregir el componente responsable y repetir el recorrido sin errores de consola ni respuestas 4xx/5xx. | **Pendiente — acceso y reproducción**; no se puede atribuir la causa al código de este repositorio con la evidencia disponible. |
| REQ-04-02 · Alta | Publicar medios de prueba aprobados y comprobar reproducción efectiva de video embebido, YouTube y MP3. | **Bloqueada — faltan medios aprobados y acceso de prueba**. |
| REQ-06-02 · Alta | Incorporar videos aprobados con resolución y códec requeridos y documentar la inspección de cada archivo. | **Bloqueada — faltan videos fuente con derechos verificados**. |
| REQ-06-03 · Alta | Verificar que cada video tenga subtítulos en español o interpretación en lengua de señas. | **Bloqueada — depende de los videos y sus recursos accesibles**. |
| REQ-06-04 · Media | Aportar infografías aprobadas y revisar objetivo, orden, vigencia y legibilidad con el responsable académico. | **Bloqueada — falta contenido aprobado y revisión académica**. |
| REQ-06-05 · Media | Incorporar y probar al menos una actividad interactiva formativa que no se registre en el libro de calificaciones. | **Bloqueada — falta definir y aprobar la actividad**. |
| REQ-07-02 · Alta | Inventariar las referencias del curso, verificar licencias y completar citas con el estilo aprobado. | **Bloqueada — faltan referencias autorizadas y revisión documental**. |
| REQ-08-06 · Media | Comprobar anexos, glosario y bibliografía; habilitar y revisar los que correspondan o solicitar que Interventoría acepte N/A si la acción no los utiliza. | **Pendiente — requiere validar aplicabilidad**; el comentario indica que esos recursos no se usan, pero el Excel los marca «NO CUMPLE». |
| REQ-08-08 · Alta | En un curso de prueba, demostrar que quien no completó la unidad 1 no puede abrir la 2; documentar también el caso autorizado. | **Pendiente — prueba funcional en la acción evaluada**; el repositorio documenta progresión para AF8, no evidencia del AF1 evaluado. |
| REQ-08-09 · Alta | Publicar en calendario una hora de atención sincrónica de dudas aprobada para cada acción de formación y verificar su visibilidad. | **Bloqueada — se requiere horario y capacitador asignado**. |
| REQ-09-02 · Alta | Para cada sesión aplicable, publicar la grabación y verificar acceso con un beneficiario de prueba; si no hay sesiones, acordar con Interventoría la aplicabilidad de N/A. | **Pendiente — falta confirmar modalidad y disponer de grabaciones**. |
| REQ-13-01 · Alta | Con una cuenta administradora autorizada y datos sintéticos en ambiente no productivo, probar crear, editar, consultar y eliminar una acción de formación. | **Prueba API PASS en staging local — curso sintético oculto creado, consultado, editado y eliminado. No automatiza la interfaz web ni acredita AF1.** |
| REQ-13-02 · Alta | Revisar con Administración los campos exigidos por el anexo y comprobar guardado y lectura de una acción de prueba. | **Parcialmente preparada — el smoke test verifica código, nombre, descripción y ocultamiento de un curso sintético; los demás campos contractuales requieren definición y revisión de Interventoría.** |
| REQ-13-03 · Media | Confirmar si se utilizará glosario; si aplica, probar edición con administrador y capacitador; si no, solicitar N/A. | **Pendiente — confirmar aplicabilidad y perfiles**. |
| REQ-14-01 · Alta | En staging, probar creación, consulta, edición, eliminación y asignación de miembros de un grupo sintético. | **Prueba API PASS en staging local — grupo sintético creado, consultado, editado, asociado al usuario sintético y eliminado. No prueba interfaz ni perfiles web.** |
| REQ-14-02 · Alta | Verificar que el formulario de grupo guarde nombre/número, descripción, estado y acción relacionada. | **Parcialmente preparada — el smoke test prueba nombre, identificador y descripción por API; estado y formulario institucional no están cubiertos.** |
| REQ-15-01 · Alta | En staging y con usuarios sintéticos, probar crear, editar, consultar y eliminar un usuario de prueba. | **Prueba API PASS en staging local — cuenta `nologin` sintética creada, consultada, editada y eliminada lógicamente por Moodle. No prueba el formulario web.** |
| REQ-15-02 · Alta | Acordar los campos de perfil requeridos, su finalidad y protección de privacidad; verificar su guardado con datos sintéticos. | **Bloqueada — el script solo prueba nombre, correo `.invalid` y ciudad sintética; falta aprobar tipo/número de documento, residencia y caracterización.** |
| REQ-16-01 · Alta | Asignar un beneficiario sintético a un grupo de prueba y comprobar la membresía desde ambos perfiles autorizados. | **Prueba API PASS en staging local — usuario sintético matriculado y agregado al grupo; no prueba permisos ni acceso por perfiles en la web.** |
| REQ-16-02 · Alta | Probar una asignación masiva con archivo sintético y reconciliar entradas, errores y miembros resultantes. | **Pendiente — requiere staging y perfil con permiso de carga**. |
| REQ-16-03 · Alta | Intentar duplicar un identificador sintético y comprobar que el alta/asignación repetida se rechaza sin duplicar registros. | **Parcialmente preparada — el smoke test comprueba duplicidad de nombre de usuario; el anexo exige duplicidad de documento y el campo institucional no está definido.** |
| REQ-17-02 · Media | Crear en staging un rol sintético con código, descripción y capacidades aprobadas; verificar permisos permitidos y denegados. | **Pendiente — requiere administrador y matriz de capacidades aprobada**. |
| REQ-18-01 · Alta | Ocultar una acción sintética en staging y demostrar que el beneficiario no accede ni por URL directa; restaurar el estado al terminar. | **Pendiente — requiere staging y cuentas de prueba**; no ocultar cursos productivos. |
| REQ-19-01 · Alta | Probar con capacitadores sintéticos que cada uno solo puede acceder a sus grupos, incluidos intentos por URL directa. | **Pendiente — requiere cuentas y asignaciones de prueba**. |
| REQ-19-02 · Media | Publicar un anuncio y cronograma aprobados con una cuenta capacitadora sintética y comprobar visibilidad para el beneficiario. | **Bloqueada — faltan cronograma aprobado, cuenta y curso de prueba**. |
| REQ-19-03 · Alta | Publicar material y actividad aprobados con un capacitador de prueba y comprobar disponibilidad para su grupo. | **Bloqueada — faltan contenido aprobado, cuenta y staging**. |
| REQ-19-04 · Alta | Registrar una calificación de prueba con el capacitador y verificarla desde el beneficiario correspondiente. | **Pendiente — requiere cuentas, actividad y nota sintéticas en staging**. |
| REQ-19-05 · Alta | Probar apertura de módulo para el grupo autorizado y verificar denegación/no visibilidad para quienes no cumplen la condición. | **Pendiente — requiere cuenta de capacitador y grupo de prueba**; no cambiar fechas o restricciones reales. |
| REQ-20-01 · Media | Subir y descargar un archivo sintético en escritorio y celular con una cuenta beneficiaria y registrar el resultado. | **Pendiente — requiere cuenta y prueba de interfaz en ambos dispositivos**. |
| REQ-20-02 · Media | Configurar fechas de actividades y publicar un anuncio de prueba; confirmar recepción de las dos notificaciones por el beneficiario. | **Pendiente — requiere fechas aprobadas, curso y cuentas de prueba**. |
| REQ-20-05 · Alta | Aprobar finalidad, campos y tratamiento de caracterización; configurar y probar el formulario con datos sintéticos y permisos mínimos. | **Bloqueada — aprobación institucional de privacidad y datos**. |
| REQ-21-01 · Alta | Con la cuenta de Interventoría, comprobar el listado por grupo y los campos permitidos; validar que no pueda modificar datos ni calificaciones. | **Pendiente — requiere cuenta autorizada y datos sintéticos**. |
| REQ-22-01 · Alta | Confirmar formalmente el rol SENA; con cuenta de prueba, validar el listado y el alcance por grupo sin revelar campos no autorizados. | **Bloqueada — rol y cuenta SENA no disponibles en la evidencia del Excel**. |
| REQ-22-02 · Alta | Con rol SENA de prueba, comprobar consulta de calificaciones y avance, incluidos los límites de acceso autorizados. | **Bloqueada — depende de aprobación y cuenta SENA**. |
| REQ-22-03 · Media | Con rol SENA de prueba, comprobar mensajería de ida y vuelta sin privilegios de modificación académica. | **Bloqueada — depende de aprobación y cuenta SENA**. |
| REQ-23-01 · Alta | Aprobar proveedor de videoconferencia y retención; probar grabación, publicación y permisos de apertura por cada rol exigido. | **Bloqueada — proveedor, sesión de prueba y política de grabaciones pendientes**. |
| REQ-23-02 · Alta | En una sesión de prueba autorizada, comprobar chat, cámara y micrófono de beneficiario y capacitador en perfiles soportados. | **Bloqueada — proveedor y sesión de prueba pendientes**. |
| REQ-23-03 · Alta | Comprobar que la sesión de prueba registra asistentes y actividades con una fuente verificable y exportable. | **Bloqueada — requiere definir proveedor y evidencia de asistencia aceptada**. |
| REQ-24-01 · Alta | Obtener y aprobar las columnas del Anexo 12; generar XLSX sintético de beneficiarios y verificar columnas, filtros y permisos. | **Bloqueada — falta diccionario oficial de campos y aceptación de Interventoría**. |
| REQ-24-02 · Alta | Definir el consolidado por acción, grupo, registrados y culminados; producir XLSX sintético y reconciliar totales. | **Bloqueada — faltan definición de culminación y columnas aprobadas**. |
| REQ-24-03 · Alta | Probar el reporte XLSX de asistencia por sesión, grupo y acción contra registros sintéticos cuya fuente esté aprobada. | **Pendiente — el plugin tiene exportación de sesiones/asistencia optimizada y una prueba PHPUnit añadida, pero no ejecutada; el staging no está disponible y falta el formato institucional aprobado**. |
| REQ-24-04 · Alta | Aprobar campos y significado del reporte de tiempos del capacitador; generar XLSX de prueba y verificar fuente, trazabilidad y permisos. | **Pendiente — el plugin exporta intervalos programados/reportados/validados, pero falta aprobación del formato, test funcional de staging y reglas institucionales**; los inicios de sesión no prueban tiempo trabajado. |

## Dependencias comunes

1. **Confirmar el objeto evaluado:** identificar si ASOCODEM-AF1 y aulas.prospectiva.edu.co corresponden a este repositorio/instalación o a otro proyecto. Obtener autorización separada antes de cualquier prueba o cambio de plataforma.
2. **Disponer de staging autorizado:** Moodle de prueba, cuenta administradora y perfiles sintéticos de beneficiario, capacitador, Interventoría y SENA. En producción solo se admite el CRUD sintético expresamente autorizado, tras aceptación de staging y verificación documentada de respaldo/reversión, ventana y categoría aislada; nunca pruebas de carga.
3. **Aprobar decisiones institucionales:** contenidos, recursos y licencias; modalidad y calendario; esquema de roles/capacidades; campos personales; reportes y reglas de asistencia/tiempo; proveedor de videoconferencia.
4. **Adjuntar evidencia verificable por requisito:** fecha, ambiente, versión, rol sintético, pasos, resultado, errores y artefactos sanitizados. Una prueba local de AF8 no reemplaza la aceptación de AF1.
5. **Actualizar el Excel con el responsable de la evaluación:** no cambiar N/A ni resultados formales unilateralmente. En particular, revisar observaciones contradictorias de disponibilidad y aplicabilidad de recursos complementarios.

## Evidencia existente en el repositorio (no equivale a aprobación del Anexo)

- El catálogo y sus pruebas representan AF8 con cinco unidades, 50 componentes, siete grupos y capacidad planificada de 350; no aportan el contenido ni la configuración de ASOCODEM-AF1.
- Hay un piloto de progresión secuencial para AF8 y una estrategia documentada de pruebas de rendimiento, pero la documentación registra que la carga de aceptación y la evidencia de producción siguen pendientes.
- `local_asodeltolstaff` documenta sesiones, asistencia provisional, validación y exportaciones CSV/XLSX; no demuestra los cuatro reportes institucionales del REQ-24 ni la instalación/configuración en el sitio evaluado.
- La exportación del plugin ahora obtiene sesiones, grupos, nombres y registros de asistencia en lotes, evitando consultas repetidas por cada sesión/persona. Es una optimización del código del repositorio, no una medición de carga ni evidencia de que los reportes del Anexo 12 estén implementados o aceptados.
- La prueba `plugins/local/asodeltolstaff/tests/manager_test.php` genera 20 sesiones sintéticas y fija un máximo de lecturas para detectar regresiones N+1; no pudo ejecutarse porque Docker Engine está apagado, no hay instalación de PHPUnit disponible y la CLI PHP local es 8.3, no la 8.4 objetivo.
- `scripts/anexo14/management-smoke.php` y `scripts/staging/run-anexo14-management.ps1` ejecutaron el recorrido API de curso/grupo/usuario con datos sintéticos en staging local Moodle 5.3/PHP 8.4. Evidencia local: `/var/www/moodledata/temp/anexo14-management-*.json` dentro del volumen Docker `asodeltol-moodle53-moodledata` (no versionada); no se ejecutó en AF1/producción y no cubre UI, permisos web ni campos documentales aprobados.
- No se agregaron contenidos académicos, cuentas, campos personales, roles, servicios ni cambios a Moodle para evitar inventar decisiones o intervenir en una plataforma fuera del alcance autorizado.

## Criterio para cerrar el plan

El plan solo puede declararse terminado cuando todos los requisitos aplicables tengan resultado y evidencia aceptados por el responsable de la revisión, los N/A estén confirmados por quien corresponda y el Excel actualizado ya no reporte incumplimientos. Completar este documento o pasar pruebas de catálogo no satisface ese criterio.
