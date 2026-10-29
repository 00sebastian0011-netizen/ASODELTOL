# ASODELTOL AF8 — Seguimiento postauditoría y pendientes para pruebas
**Corte documental:** 29 de octubre de 2026 (America/Bogota) · **Estado:** plan de cierre en preparación; pruebas pendientes **NO ejecutadas** en esta actualización.

> **Documento operativo vigente** para organizar la siguiente ronda de verificación. No reemplaza las observaciones oficiales del 27 de octubre ni altera las evidencias históricas.
>
> **Fuentes:** [MATRIZ_ASODELTOL_82.md](MATRIZ_ASODELTOL_82.md), [TRAZABILIDAD_82_2026-10-25.json](TRAZABILIDAD_82_2026-10-25.json), preauditoría ASODELTOL de 82 requisitos (24/10), matriz de seguimiento de 68 (26/10), estructura académica AF8 y formulario AF8G1 recibidos para revisión; capturas del 27/10; GitHub/QA al 29/10. **Falta el acta institucional completa de la visita del 27/10**.

## 1. Estado probado y límites

- **Código aprobado:** [`main` `9cda0a937cfa4e27548e519ef6571fa88dd6df4e`](https://github.com/00sebastian0011-netizen/ASODELTOL/commit/9cda0a937cfa4e27548e519ef6571fa88dd6df4e), Staff **0.7.8 / 2026102908**. Según el **informe del agente VPS** aportado, esta versión está instalada tanto en archivos como en base, Moodle actualizó y Nginx/PHP funcionan; la revisión documental no constituye una nueva conexión a Hostinger.
- **QA ya realizado sobre privacidad:** [20/20 pruebas HTTP autenticadas PASS en entorno aislado](https://github.com/00sebastian0011-netizen/ASODELTOL/actions/runs/40875977687), **52/52 pruebas contractuales** reportadas y [CI de calidad `main`](https://github.com/00sebastian0011-netizen/ASODELTOL/actions/runs/40876374401) exitoso. Verifica principalmente perfil, ciudad, segregación de grupos, notas, evidencias y denegación de edición/exportación; **NO equivale a 68/68 ni a cierre de toda la caracterización, progresión, comunicaciones y reportes**.
- **Estado de base (25/10, no actual):** sobre 82 criterios, 34 `VERIFICADO LOCAL`, 2 `PENDIENTE`, 46 `BLOQUEADO`, **0 aceptaciones oficiales**. Entre los **68** de la matriz más reciente: **34 verificaciones locales, 2 pendientes de prueba y 32 bloqueados**. Los **14** adicionales son condicionales/no incluidos en esa matriz y no se dan por descartados sin acta.
- **Informe de auditoría del 27/10:** se dispone de capturas y de indicaciones posteriores, **no de una lista firmada de hallazgos por ID**. Por ello no se atribuyen a la auditoría conclusiones que no constan en sus fuentes.
- **Materiales académicos:** la matriz histórica deja abierta la conciliación de **50/80 ítems**, **4/9 recursos generales**, los cinco componentes por módulo y las reglas académicas. El catálogo GitHub conserva estado borrador; verificar el contenido real en AF8 antes de inferir ausencia.
- **Inscripciones:** el XLSX AF8G1 tiene respuestas y posibles repeticiones/negativas de autorización; su consolidación **no es una matrícula ni una aceptación `tool_policy`**. Conciliar internamente mediante resultados agregados, sin incluir nombres/documentos en Git.
- **CAU:** las capturas del 27 indicaban que el receptor de soporte no estaba configurado entonces; verificar el estado **actual** antes de tratarlo como hallazgo abierto.

## 2. Secuencia de trabajo

**Fase A · primero**, sin escritura en producción: P01 (acta), P02 (roles y privacidad por criterio), P03 (datos y consentimiento), P04 (contenido), P05 (progresión), P06 (sesiones), P07 (reportes), P08 (CAU). Revisar configuración, fuentes y evidencia existente; priorizar decisiones humanas que bloquean pruebas.

**Fase B · después**, pruebas sintéticas y autorizadas: P09–P14 (comunicaciones, administración, multimedia, compatibilidad, rendimiento, continuidad), y los escenarios pendientes de P02–P08. No cargar la producción ni alterar AF8 sin ventana y respaldo.

**Fase C · cierre**: P15 (14 aplicabilidades), trazabilidad por ID, dictamen institucional y firma. El resultado de cada criterio debe ser `PASS`, `FAIL`, `BLOCKED` o `N/A APROBADO` **con evidencia**, separado del estado técnico histórico.

## 3. Paquetes de trabajo (15)

| Paquete | Prioridad | Requisitos asignados | Frente | Responsable sugerido | Acción y alcance | Evidencia exigida |
|---|---|---:|---|---|---|---|
| P01 | P0 | Transversal | Acta del 27/10 y reconciliación documental | Coordinación / Interventoría | Conseguir acta original, inventario exacto de hallazgos y responsables; relacionar cada observación con un ID y evidencia posterior. | Acta, hallazgos y aprobación de criterio/fecha por requisito. |
| P02 | P0 | 10 | Roles, privacidad y seguimiento Staff | Moodle / Seguridad / Interventoría | Vincular Staff 0.7.8 a cada requisito; repetir perfiles y grupos con usuarios sintéticos; verificar notas, porcentajes de progreso, listado/estado y caracterización sin accesos globales. | Pruebas positivas y negativas autenticadas, capacidades efectivas y aceptación por rol. |
| P03 | P0 | 6 | Matrículas, identidad, autorización y caracterización | Gobierno de datos / Matrícula / Moodle | Conciliar respuestas AF8G1 con Moodle, documentos repetidos, altas y bajas y consentimiento tool_policy; comprobar campos autorizados, caracterización, unicidad, asignación manual/masiva y G01–G07. | Conciliación anonimizada, política vigente, auditoría de consentimiento y prueba de asignación sin afectar matrículas reales. |
| P04 | P0 | 11 | Estructura y material académico AF8 | Dirección académica / Contenidos | Contrastar las cinco UT reales con estructura aprobada; resolver 50/80 ítems, 4/9 recursos y cinco componentes; revisar videos, subtítulos, infografías, licencias, originalidad y bibliografía. | Inventario académico aprobado, evidencia multimedia, derechos y pantallas del curso. |
| P05 | P0 | 4 | Evaluación, progresión y ocho días hábiles | Dirección académica / Moodle | Aprobar disparador y calendario festivos de Colombia, umbral de nota, intentos y recuperación; comprobar bloqueo/apertura real de unidades y matriz de evaluación. | Reglas firmadas y pruebas positiva/negativa de calendarios y finalización. |
| P06 | P0 | 5 | Sesiones sincrónicas y asistencia | Operación académica / Proveedor | Acordar modalidad; validar enlace, grabación, chat, micrófono, cámara, invitaciones previas, asistencia efectiva y actividad reportada, si aplican. | Sesión piloto, grabación/registro, acceso por roles y conciliación asistencia real. |
| P07 | P0 | 4 | Reportes oficiales Anexo 12 | Reportería / Interventoría | Contrastar los cuatro reportes XLSX por columnas, filtros, fuente de datos, grupos, asistencias, culminación y horas; asegurar lectura restringida y no exportación de Interventoría/SENA donde aplique. | Plantillas contractuales y cuatro exportaciones revisadas campo por campo con aceptación. |
| P08 | P0 | 4 | CAU, ayuda y horarios de atención | Soporte institucional / Administración | Comprobar receptor de casos, CAU, ticket/estado/notificaciones; definir SLA y horario de dudas; comprobar la navegación en móvil. | Caso sintético cerrado, notificaciones verificadas, responsable y SLA aprobados. |
| P09 | P1 | 7 | Operación de capacitador y comunicaciones | QA / Capacitadores | Probar anuncios, cronograma, archivos, actividades, avisos de pendiente y mensajería bidireccional por grupo con cuentas sintéticas. | Evidencia de envío/recepción, denegaciones entre grupos y móvil/escritorio. |
| P10 | P1 | 7 | Administración de acciones y grupos | Administración Moodle / QA | Verificar formularios y CRUD en un entorno aislado; prueba de curso no disponible, metadatos, glosario solo si aplica. | Flujos y errores documentados, con roles y criterios contractuales revisados. |
| P11 | P1 | 3 | Enlaces, medios y navegación RSMax | RSMax / QA / Contenidos | Verificar enlaces reales, archivos Office/PDF, video/audio/YouTube, permisos de enlaces rápidos y CAU en producción con cuentas autorizadas. | Inventario de enlaces sin roturas y evidencia de reproducción/acceso correcto. |
| P12 | P1 | 4 | Navegadores, dispositivos y accesibilidad | QA / RSMax | Completar recorridos en Windows, Linux y macOS, Chrome/Edge/Firefox/Safari, escritorio/tablet/móvil, consola y accesibilidad. | Matriz de SO y navegador, registros de errores y recorridos funcionales aprobados. |
| P13 | P1 | 2 | Rendimiento y simultaneidad | Operación VPS / QA carga | Con una ventana controlada, repetir N igual al grupo mayor y medir rutas reales, carga inicial, descargas y reproducción sin degradar producción. | Resultados con percentiles, errores y tiempos bajo umbrales pactados. |
| P14 | P1 | 1 | Disponibilidad y continuidad | Operación VPS / Interventoría | Fijar periodo y SLA objetivo; registrar monitoreo horario real, incidentes, respaldo y restauración durante la ventana. | Monitoreo completo del periodo y aprobación del objetivo de disponibilidad. |
| P15 | P0 | Transversal | 14 condicionales y firma institucional | Coordinación / Interventoría | Pronunciamiento explícito sobre aplicabilidad de los 14; completar matriz y acta de cierre de los 82 sin asumir N/A. | Decisión APLICA/N/A con fundamento y responsable; acta firmada. |

## 4. Criterios de entrada para pruebas

1. Aceptación del responsable de la plataforma, alcance y ventana, más autorización para cuentas **sintéticas** cuando se necesiten.
2. Inventario leído desde producción (Moodle/Staff/RSMax, roles, grupos, config de políticas/CAU y backups); revisar los cambios contra `main` sin imprimir secretos ni datos personales.
3. Para cualquier operación de escritura, **respaldo verificable y rollback practicable**; sin ello, pruebas solo de lectura en producción y pruebas CRUD en staging.
4. Para pruebas académicas, disponer de **material aprobado, reglas de progreso, modalidad, calendario y Anexo 12**. Las pruebas simuladas no deciden la política institucional.
5. Cada resultado: fecha, entorno, commit/versiones, rol y alcance, dato sintético, pasos, esperado, observado, captura/log saneado, responsable, incidencia y estado final.

## 5. Próximos documentos

- [Matriz de 68 requisitos con siguiente acción y estado por ID](MATRIZ_PENDIENTES_68_2026-10-29.md).
- [Plan de pruebas por escenarios, roles y puertas de seguridad](PLAN_PRUEBAS_PENDIENTES_2026-10-29.md).
- La matriz [MATRIZ_ASODELTOL_82.md](MATRIZ_ASODELTOL_82.md) es un **corte histórico al 25/10**. No se modifica retrospectivamente.
- [Decisiones pendientes](../DECISIONES_PENDIENTES.md) y [roadmap histórico](../ROADMAP.md) describen políticas, no acreditaciones nuevas.

**No ejecutado en esta entrega documental:** pruebas HTTP nuevas, creación de cuentas, actualización de roles, cambios en AF8, despliegue, informes contractuales ni aceptación institucional.
