# Seguimiento de pendientes por ID — matriz de 68 requisitos
**Corte:** 29 de octubre de 2026 · **Naturaleza:** mapa para la siguiente prueba, no dictamen oficial.

La clasificación `VERIFICADO LOCAL / PENDIENTE / BLOQUEADO` es la **fotografía técnica del 25/10** preservada de [`TRAZABILIDAD_82_2026-10-25.json`](TRAZABILIDAD_82_2026-10-25.json). No se infiere que todos los bloqueos persistan después de los cambios del 27–29/10. Para el **cierre actual** cada ID debe acreditarse con su criterio completo, evidencia en el entorno autorizado y decisión institucional; las mejoras Staff 0.7.8 aportan evidencia parcial a algunos IDs.

**Resumen histórico del subconjunto de 68:** 34 `VERIFICADO LOCAL`, 2 `PENDIENTE`, 32 `BLOQUEADO`. **Aceptaciones formales identificadas en este corte:** 0, no significa que se haya recibido un rechazo formal.

## 1. Inventario por requisito

| ID | Paquete | Estado 25/10 | Seguimiento 29/10 | Criterio fuente | Próxima comprobación / dependencia |
|---|---|---|---|---|---|
| REQ-01-01 | P13 | VERIFICADO LOCAL | Verificar en producción / formalizar | Inicio, curso, módulo y calificaciones cargan completos en menos de 60 s; un archivo de prueba se descarga y un video empieza a reproducirse en menos de 60 s. | Aceptación y nueva medición en el entorno institucional autorizado. |
| REQ-01-02 | P13 | VERIFICADO LOCAL | Verificar en producción / formalizar | Con N sesiones simultáneas (N = beneficiarios del grupo más grande) el tiempo de respuesta sigue por debajo de 60 s. | Perfil probado caliente y controlado; repetir en infraestructura final y en arranque frío. |
| REQ-02-01 | P12 | PENDIENTE | Ejecutar prueba | El inicio de sesión y el recorrido básico se completan sin errores en los tres sistemas operativos. | QA debe ejecutar el recorrido en macOS; emulación no lo sustituye. |
| REQ-02-02 | P12 | VERIFICADO LOCAL | Verificar en producción / formalizar | El recorrido del curso se completa sin errores visuales ni funcionales en los cuatro navegadores (Safari se prueba con WebKit). | Aceptación institucional; WebKit no equivale a Safari en un Mac físico. |
| REQ-02-03 | P12 | VERIFICADO LOCAL | Verificar en producción / formalizar | En pantallas de escritorio, celular y tablet no hay elementos cortados ni scroll horizontal, y se puede entregar una actividad y enviar un mensaje. | Revisión institucional y dispositivos físicos para la aceptación final. |
| REQ-02-04 | P12 | VERIFICADO LOCAL | Verificar en producción / formalizar | Durante todas las pruebas no aparecen respuestas HTTP 4xx/5xx ni errores en la consola del navegador. | Repetir con contenido definitivo; hallazgos de accesibilidad no se declaran subsanados. |
| REQ-03-01 | P14 | BLOQUEADO | Resolver dependencia / verificar | Todos los chequeos horarios del periodo evaluado devuelven HTTP 200 y la página de inicio de sesión carga. | Interventoría/operación deben fijar periodo, disponibilidad y continuidad del servicio; acumular la ventana completa. |
| REQ-04-01 | P11 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un archivo de prueba de cada tipo (.docx, .xlsx, .pptx, .pdf, .jpg/.png) se visualiza o descarga sin error. | Pruebas técnicas y aprobación |
| REQ-04-02 | P11 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un video embebido, un enlace de YouTube y un audio mp3 inician reproducción (el tiempo de reproducción avanza). | Probar nuevamente los materiales académicos definitivos, sin atribuir licencia institucional al ejemplo. |
| REQ-06-01 | P04 | BLOQUEADO | Resolver dependencia / verificar | Ningún recurso es un escaneo y cada módulo tiene al menos un recurso visual o audiovisual. | Académico, licencia, modalidad o proveedor |
| REQ-06-02 | P04 | BLOQUEADO | Resolver dependencia / verificar | Cada video tiene altura de 720 o 1080 píxeles y audio con códec AAC. | Académico, licencia, modalidad o proveedor |
| REQ-06-03 | P04 | BLOQUEADO | Resolver dependencia / verificar | Cada video tiene pista de subtítulos en español o muestra un intérprete de lengua de señas. | Académico, licencia, modalidad o proveedor |
| REQ-06-04 | P04 | BLOQUEADO | Resolver dependencia / verificar | Cada infografía declara un objetivo de aprendizaje, sigue un orden lógico, tiene información vigente y es legible. | Académico, licencia, modalidad o proveedor |
| REQ-06-05 | P04 | BLOQUEADO | Resolver dependencia / verificar | Hay al menos una actividad interactiva (selección múltiple, arrastrar y soltar, falso/verdadero, etc.) que se ejecuta sin salir de la plataforma y no aparece en el libro de calificaciones. | Académico, licencia, modalidad o proveedor |
| REQ-07-01 | P04 | BLOQUEADO | Resolver dependencia / verificar | El análisis de similitud da 70 % o más de contenido original (máximo 30 % referenciado). | Académico, licencia, modalidad o proveedor |
| REQ-07-02 | P04 | BLOQUEADO | Resolver dependencia / verificar | Cada referencia proviene de una base de datos con licencia o de un recurso abierto (Creative Commons) y sigue un estilo de citación consistente (APA u otro). | Académico, licencia, modalidad o proveedor |
| REQ-08-01 | P11 | VERIFICADO LOCAL | Verificar en producción / formalizar | Todos los enlaces del menú y del curso abren sin error (cero enlaces rotos). | Repetir tras la carga del contenido oficial; alcance actual: curso sintético y rutas ensayadas. |
| REQ-08-02 | P08 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un enlace de ayuda visible abre un recurso con contenido. | Académico, licencia, modalidad o proveedor |
| REQ-08-03 | P04 | BLOQUEADO | Resolver dependencia / verificar | Se ven: nombre de la formación o evento, objetivos, duración en horas, introducción, resultados de aprendizaje e índice de contenido. | Académico, licencia, modalidad o proveedor |
| REQ-08-04 | P04 | BLOQUEADO | Resolver dependencia / verificar | El video está publicado y su transcripción menciona formación, experiencia y temáticas del capacitador. | Académico, licencia, modalidad o proveedor |
| REQ-08-05 | P04 | BLOQUEADO | Resolver dependencia / verificar | Los cinco componentes están presentes en cada módulo. | Dirección académica debe identificar los cinco componentes del Anexo 14, resolver 50/80 y 4/9 y aportar contenido. |
| REQ-08-06 | P04 | BLOQUEADO | Resolver dependencia / verificar | Cada una de esas secciones que el curso use existe y abre. Si el curso no usa ninguna, N/A. | Académico, licencia, modalidad o proveedor |
| REQ-08-07 | P05 | BLOQUEADO | Resolver dependencia / verificar | Entre la apertura y el cierre de cada módulo hay 8 días hábiles o menos (sin contar fines de semana ni festivos de Colombia). | Académico, licencia, modalidad o proveedor |
| REQ-08-08 | P05 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un beneficiario de prueba que no ha completado el módulo 1 no puede abrir el módulo 2. | Académico, licencia, modalidad o proveedor |
| REQ-08-09 | P08 | BLOQUEADO | Resolver dependencia / verificar | Cada acción de formación tiene registrada al menos una hora sincrónica de atención de dudas. | Académico, licencia, modalidad o proveedor |
| REQ-09-02 | P06 | BLOQUEADO | Resolver dependencia / verificar | Cada sesión del reporte tiene grabación y el beneficiario de prueba la puede abrir. | Académico, licencia, modalidad o proveedor |
| REQ-11-04 | P06 | BLOQUEADO | Resolver dependencia / verificar | Existen invitaciones previas y los formatos de registro y asistencia están 100 % diligenciados, cumpliendo el Anexo 12, num. 2.15.2. | Académico, licencia, modalidad o proveedor |
| REQ-12-01 | P08 | VERIFICADO LOCAL | Verificar en producción / formalizar | Desde la plataforma se ve un canal de soporte (formulario, chat, correo o mesa de ayuda). | Designar responsable institucional; no implica SLA ni SMTP externo. |
| REQ-12-02 | P08 | BLOQUEADO | Resolver dependencia / verificar | Un ticket de prueba recibe respuesta y solución dentro del tiempo comprometido por el conviniente, y hay evidencia del servicio de soporte y mantenimiento. | Institución debe designar atención, canal/remitente oficial, SLA y evidencia del servicio; SMTP debe validarse por separado. |
| REQ-13-01 | P10 | VERIFICADO LOCAL | Verificar en producción / formalizar | Se crea, edita, consulta y elimina una acción de formación de prueba sin errores. | Pruebas técnicas y aprobación |
| REQ-13-02 | P10 | VERIFICADO LOCAL | Verificar en producción / formalizar | El formulario incluye y guarda: código, nombre, descripción, estado (disponible / no disponible), fecha de inicio, fecha de terminación, idioma, identificador de rol administrador e identificador de rol interventoría. | Identificadores definitivos y aceptación institucional; los valores de QA no son roles oficiales. |
| REQ-13-03 | P10 | BLOQUEADO | Resolver dependencia / verificar | Existe un glosario que el administrador y el capacitador pueden editar. Es opcional: si no existe, N/A. | Pruebas técnicas y aprobación |
| REQ-14-01 | P10 | VERIFICADO LOCAL | Verificar en producción / formalizar | Se crea, edita, consulta y elimina un grupo de prueba, y se le pueden agregar miembros. | Pruebas técnicas y aprobación |
| REQ-14-02 | P10 | VERIFICADO LOCAL | Verificar en producción / formalizar | El formulario incluye y guarda: nombre y numeración, descripción, estado disponible / no disponible y acción de formación a la que pertenece. | Los metadatos de disponibilidad no suspenden matrículas ni reemplazan restricciones académicas. |
| REQ-14-03 | P05 | PENDIENTE | Ejecutar prueba | El grupo tiene una matriz de evaluación (actividades, exámenes, foros y encuestas) y un libro de calificaciones. | Dirección académica/tercero deben aportar matriz oficial, actividades e instrumentos y reglas aprobadas; no inventar pesos o notas. |
| REQ-14-04 | P10 | VERIFICADO LOCAL | Verificar en producción / formalizar | El grupo tiene al menos un medio de comunicación activo: chat, correo, foros o intercambio de archivos. | Pruebas técnicas y aprobación |
| REQ-15-01 | P03 | VERIFICADO LOCAL | Verificar en producción / formalizar | Se crea, edita, consulta y elimina un usuario de prueba sin errores. | Identidad, caracterización y roles |
| REQ-15-02 | P03 | BLOQUEADO | Resolver dependencia / verificar | El formulario incluye y guarda: tipo y número de documento, nombre(s), apellido(s), correo electrónico, rol, contraseña, lugar de residencia y caracterización o pertenencia a grupo o comunidad. | Identidad, caracterización y roles |
| REQ-16-01 | P03 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un beneficiario de prueba se asigna individualmente a un grupo y aparece en él. | Identidad, caracterización y roles |
| REQ-16-02 | P03 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un archivo o selección con varios beneficiarios queda asignado completo al grupo. | Identidad, caracterización y roles |
| REQ-16-03 | P03 | BLOQUEADO | Resolver dependencia / verificar | Registrar o asignar de nuevo un documento ya existente es rechazado. | Identidad, caracterización y roles |
| REQ-17-01 | P02 | BLOQUEADO | QA parcial reciente; completar alcance | En la gestión de roles existen Administrador, Capacitador, Beneficiario, Interventoría y SENA. | Se debe demostrar el rol y su contexto real; SENA no debe recibir usuarios sin identificación aprobada. |
| REQ-17-02 | P02 | VERIFICADO LOCAL | Verificar en producción / formalizar | Se crea un rol de prueba con nombre, descripción, código y permisos por funcionalidad. | Identidad, caracterización y roles |
| REQ-18-01 | P10 | VERIFICADO LOCAL | Verificar en producción / formalizar | Con la acción en estado no disponible, el beneficiario de prueba no puede entrar a ella. | Pruebas técnicas y aprobación |
| REQ-19-01 | P02 | VERIFICADO LOCAL | QA parcial reciente; completar alcance | El capacitador de prueba no ve grupos que no le fueron asignados. | QA Staff demuestra segregación de grupo; cotejar alcance con cuenta productiva de prueba. |
| REQ-19-02 | P09 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un anuncio y un cronograma publicados por el capacitador son visibles para el beneficiario. | Publicar el cronograma institucional aprobado en su fase autorizada. |
| REQ-19-03 | P09 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un video, un material y una actividad publicados por el capacitador son visibles para el beneficiario. | Cargar material académico autorizado y definir sus reglas de evaluación. |
| REQ-19-04 | P02 | VERIFICADO LOCAL | QA parcial reciente; completar alcance | Una nota registrada por el capacitador aparece en el libro del beneficiario. | QA verifica lectura de nota, pero no demuestra que el Capacitador haya registrado la nota según matriz oficial. |
| REQ-19-05 | P05 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un módulo abierto por el capacitador queda disponible para el grupo. | Aplicar únicamente el calendario y las autorizaciones institucionales definitivas. |
| REQ-19-06 | P02 | VERIFICADO LOCAL | Verificar en producción / formalizar | Se muestra el % de avance de cada beneficiario por módulo y por acción. | Pruebas técnicas y aprobación |
| REQ-20-01 | P09 | VERIFICADO LOCAL | Verificar en producción / formalizar | Se sube y se descarga un archivo de prueba en perfil de escritorio y de celular. | Identidad, caracterización y roles |
| REQ-20-02 | P09 | VERIFICADO LOCAL | Verificar en producción / formalizar | Aparece una notificación por una actividad pendiente y otra por un anuncio nuevo del capacitador. | No equivale a entrega SMTP ni a una aprobación institucional. |
| REQ-20-03 | P02 | VERIFICADO LOCAL | QA parcial reciente; completar alcance | El beneficiario ve sus notas y su % de avance por módulo y por acción. | QA acredita lectura de nota propia; falta verificar porcentaje de progreso por módulo/acción. |
| REQ-20-04 | P09 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un mensaje del beneficiario llega al capacitador o a la interventoría. | Identidad, caracterización y roles |
| REQ-20-05 | P03 | BLOQUEADO | Resolver dependencia / verificar | El beneficiario diligencia y guarda su caracterización o pertenencia a grupo o comunidad. | Identidad, caracterización y roles |
| REQ-21-01 | P02 | BLOQUEADO | QA parcial reciente; completar alcance | Ve el listado de beneficiarios por grupo con su estado activo/inactivo, lugar de residencia y caracterización. | QA acredita ciudad dentro del alcance; falta listado por grupo, estado y caracterización. |
| REQ-21-02 | P02 | VERIFICADO LOCAL | QA parcial reciente; completar alcance | Ve las calificaciones de los beneficiarios y su % de avance por módulo y por acción. | QA acredita lectura de notas; falta porcentaje de avance por módulo y acción. |
| REQ-21-03 | P09 | VERIFICADO LOCAL | Verificar en producción / formalizar | Un mensaje del beneficiario llega a Interventoría y su respuesta llega al beneficiario. | Roles, datos personales y permisos |
| REQ-22-01 | P02 | BLOQUEADO | QA parcial reciente; completar alcance | Ve el listado de beneficiarios por grupo con su estado activo/inactivo, lugar de residencia y caracterización. | QA acredita ciudad dentro del alcance; falta listado por grupo, estado y caracterización. |
| REQ-22-02 | P02 | BLOQUEADO | QA parcial reciente; completar alcance | Ve las calificaciones de los beneficiarios y su % de avance por módulo y por acción. | QA acredita lectura de notas; falta porcentaje de avance por módulo y acción. |
| REQ-22-03 | P09 | BLOQUEADO | QA parcial reciente; completar alcance | Un mensaje del beneficiario llega a SENA y su respuesta llega al beneficiario. | El QA de privacidad NO prueba mensajería bidireccional SENA; realizar escenario específico. |
| REQ-23-01 | P06 | BLOQUEADO | Resolver dependencia / verificar | La sesión de prueba queda grabada, la publica el capacitador y la pueden abrir beneficiario, interventoría y SENA. | Asistencia, proveedor o Anexo 12 |
| REQ-23-02 | P06 | BLOQUEADO | Resolver dependencia / verificar | Beneficiario y capacitador intercambian mensajes de chat y el beneficiario puede activar micrófono y cámara. | Asistencia, proveedor o Anexo 12 |
| REQ-23-03 | P06 | BLOQUEADO | Resolver dependencia / verificar | Tras la sesión de prueba, quedan registrados los asistentes y las actividades realizadas. | Asistencia, proveedor o Anexo 12 |
| REQ-24-01 | P07 | BLOQUEADO | Resolver dependencia / verificar | El reporte se descarga en .xlsx o .xls y trae todas las columnas del Anexo 12, num. 2.14. | Asistencia, proveedor o Anexo 12 |
| REQ-24-02 | P07 | BLOQUEADO | Resolver dependencia / verificar | El reporte se descarga en .xlsx o .xls y trae por acción: grupos, beneficiarios registrados y beneficiarios que culminaron con éxito. | Asistencia, proveedor o Anexo 12 |
| REQ-24-03 | P07 | BLOQUEADO | Resolver dependencia / verificar | El reporte se descarga en .xlsx o .xls con asistencia por sesión, grupo y acción. Si la plataforma no lo genera, debe integrar otra que sí lo haga. | Asistencia, proveedor o Anexo 12 |
| REQ-24-04 | P07 | VERIFICADO LOCAL | Verificar en producción / formalizar | El reporte se descarga en .xlsx o .xls con: inicio y cierre de sesión (fecha y hora), inicio y fin de cada acción de formación, y hora de disponibilidad para dudas. | Asistencia, proveedor o Anexo 12 |

## 2. Otros 14 criterios: decidir aplicabilidad formal

Los 14 siguientes pertenecen al universo histórico de **82** pero no al subconjunto operativo de **68**. Son **condicionales** para esta organización, no automáticamente `N/A` ni eliminados del contrato. La interventoría/institución debe registrar si aplica cada uno y su justificación. Cuando sí aplique, probarlo igual que el resto.

| ID | Criterio del inventario de 82 | Decisión actual | Próximo paso |
|---|---|---|---|
| REQ-05-01 | Existe declaración del conviniente. Si NO usa IA, marcar todos los requisitos del ítem como N/A. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-05-02 | Ninguna función de IA dicta la formación ni asigna notas; todas las calificaciones las registra un capacitador. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-05-03 | El presupuesto del proyecto no incluye rubros de IA. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-09-01 | La duración registrada de cada sesión es de 60 minutos o más. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-10-01 | La transmisión o su grabación tiene resolución de 720p o más y audio AAC. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-10-02 | La transmisión tiene subtítulos en español o muestra un intérprete. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-10-03 | Con perfiles de escritorio, celular y tablet se llega a la transmisión en pocos pasos y sin errores. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-10-04 | El reporte de conexión muestra transmisión en vivo continua durante toda la duración programada. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-10-05 | El reporte de conexión del evento se descarga correctamente. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-10-06 | Existen invitaciones enviadas antes del evento y los formatos de registro y asistencia están 100 % diligenciados (Anexo 12, num. 2.14) con el porcentaje de asistencia exigido (num. 3.4.6). | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-11-01 | Con perfiles de escritorio, celular y tablet se llega a la transmisión en pocos pasos y sin errores. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-11-02 | El reporte de conexión muestra transmisión en vivo continua durante toda la duración programada. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-11-03 | El reporte de conexión se descarga correctamente. | Por decidir: aplica / N/A autorizado | Académico, licencia, modalidad o proveedor |
| REQ-19-07 | El capacitador crea una sesión sincrónica que el grupo puede ver. N/A si la acción no tiene sesiones sincrónicas. | Por decidir: aplica / N/A autorizado | Pruebas técnicas y aprobación |

## 3. Cómo acreditar el cierre

- `PASS`: evidencia positiva **y negativa** pertinente; datos/rol correctos; cumplimiento literal del criterio; firma/aprobación institucional cuando corresponda.
- `FAIL`: resultado observado contradice el criterio; abrir incidencia con ruta, entorno, rol y reproducción anonimizada.
- `BLOCKED`: falta entrada externa, permiso, insumo, política o periodo de medición; registrar quién debe desbloquear y qué documento.
- `N/A APROBADO`: sólo cuando la institución/interventoría confirme formalmente la inaplicabilidad.
- `VERIFICADO LOCAL`: conservar como **evidencia histórica**, nunca transformar automáticamente en `PASS PRODUCTIVO`.

La ejecución está preparada en [PLAN_PRUEBAS_PENDIENTES_2026-10-29.md](PLAN_PRUEBAS_PENDIENTES_2026-10-29.md). Para iniciar, usar los paquetes P01–P08 del [seguimiento postauditoría](SEGUIMIENTO_POSTAUDITORIA_2026-10-29.md).
