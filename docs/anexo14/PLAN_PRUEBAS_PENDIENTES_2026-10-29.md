# ASODELTOL AF8 — Plan de pruebas para cerrar pendientes
**Versión del plan:** 29 de octubre de 2026 · **Estado de esta entrega:** **PREPARADO, NO EJECUTADO**.

Fuente de prioridades: [seguimiento postauditoría](SEGUIMIENTO_POSTAUDITORIA_2026-10-29.md) y [68 criterios con ID](MATRIZ_PENDIENTES_68_2026-10-29.md). La suite Staff 0.7.8 **20/20** es evidencia previa de alcance limitado y no sustituye estas pruebas.

## Puertas antes de ejecutar

- **G0: Identidad del entorno.** Confirmar URL `aulaasodeltol.online`, versión Moodle, Staff **0.7.8 / 2026102908**, versión RSMax, SHA de código/DB y estado de cron/servicios con salida sanitizada. **No corregir ni redeplegar** durante diagnóstico.
- **G1: Seguridad.** Cuentas sintéticas autorizadas, roles/cursos/grupos con ámbito explícito, sin contraseñas en chat ni uso de cuentas reales para pruebas invasivas. No crear, editar ni exportar PII real.
- **G2: Escritura.** CRUD, mensajes, tickets, sesiones, consentimientos o evaluaciones de prueba **solo en staging aislado** por defecto. Para escribir en producción: autorización por operación, ventana, respaldo coherente verificable de DB + plugin y rollback compatible. Nunca modificar AF8 real solo para obtener PASS.
- **G3: Datos fuente.** Inventario académico aprobado, Anexo 12, reglas de evaluación, proveedor de video, responsable de soporte y SLA; ausentes = `BLOCKED`, no evidencia fabricada.
- **G4: Resultado.** Acta por caso: `case_id, req_id, fecha, entorno, versiones, rol, alcance, precondiciones, pasos, esperado, observado, PASS/FAIL/BLOCKED/N/A, evidencias saneadas, dueño, incidencia`.

## Escenarios de aceptación

| Caso | Prioridad | Requisito(s) | Entorno recomendado | Prueba positiva | Prueba negativa / condición de fallo | Evidencia |
|---|---|---|---|---|---|---|
| T01 | P0 | REQ-17-01, REQ-19-01, REQ-21-01, REQ-22-01 | QA sintético + comprobación controlada prod | Beneficiario perfil propio mínimo; Capacitador solo su grupo y ciudad oculta; Interventoría/SENA ciudad y detalles permitidos **en curso autorizado** | Alterar ID/cursos/grupo; intentar perfiles externos; denegar datos fuera del alcance | HTTP autenticado, matrices efectivas y capturas sin PII |
| T02 | P0 | REQ-19-04, REQ-20-03, REQ-21-02, REQ-22-02 | Staging representativo | Notas y % progreso por módulo y acción para sujetos autorizados | Otro grupo o curso; editar/exportar desde roles de solo lectura | Notas sintéticas, reporte y petición HTTP |
| T03 | P0 | REQ-15-01–02, REQ-16-01–03, REQ-20-05 | Staging aislado | Crear/consultar/editar usuario ficticio, asignar 1 y lote a grupos; guardar caracterización consentida | Documento duplicado, consentimiento rechazado o vacío, curso/grupo ajeno | Libro de resultados agregados, sin documentos identificables |
| T04 | P0 | Datos y consentimiento; REQ-15/16/20 | Lectura prod + staging para cambios | Conciliar formulario AF8G1 con matrículas, `tool_policy`, versión y aceptación obligatoria para nuevos usuarios | Rechazo, silencio, URL directa sin consentir, revocación o nueva versión; ninguna aceptación automática | Agregados y pruebas de primer acceso, no registro personal |
| T05 | P0 | REQ-06-*, REQ-07-*, REQ-08-03–06 | Lectura prod + revisión académica | Cinco UT, contenido aprobado, medios 720/1080p/AAC, subtítulos, originalidad, citas y licencias | Recurso faltante, contenido sin derechos, material ilegible o escaneado | Inventario versionado, fichas y capturas autorizadas |
| T06 | P0 | REQ-08-07–08, REQ-14-03, REQ-19-05 | Staging con reglas aprobadas | Apertura y bloqueo secuencial por grupo, ocho días hábiles según calendario aprobado, nota y culminación | Saltarse UT previa; fecha fuera de ventana; usuario de otro grupo | Reglas firmadas, calendarios y trazas Moodle |
| T07 | P0 | REQ-09-02, REQ-11-04, REQ-23-* y condicionales de modalidad | Piloto autorizado | Sesión, conexión, grabación, chat/voz/cámara, invitación y registro de asistencia; acceso permitido por rol | No inferir asistencia de logs; sesión no grabada o inaccesible | Acta de sesión, informes del proveedor y bitácoras saneadas |
| T08 | P0 | REQ-24-* | Staging con datos de ejemplo + revisión contrato | Cuatro XLSX; validar estructura exacta Anexo 12, filtros, corte, totales, grupo, culminación y disponibilidad | Exportar sin permiso; mezclar grupos; confundir acceso y tiempo trabajado | Comparación campo a campo con plantilla aprobada |
| T09 | P0 | REQ-08-02/09, REQ-12-* | Staging + lectura config prod | Guía, receptor CAU, caso único, respuesta, resolución, notificación y horario de dudas | Sin receptor o receptor inactivo; usuario ajeno no puede ver caso; SLA no declarado sin periodo | Ticket ficticio y sello de tiempo, aprobación del SLA |
| T10 | P1 | REQ-19-02–03, REQ-20-01–02/04, REQ-21-03, REQ-22-03 | Staging aislado | Publicar anuncio/material y recibir mensajes de todos los roles designados; notificar actividad pendiente | Otro grupo no ve avisos/material; evitar duplicados; SENA sin asignación no debe tener acceso | Matriz de emisor/receptor y capturas saneadas |
| T11 | P1 | REQ-13-*, REQ-14-01/02/04, REQ-18-01 | Staging aislado | CRUD acciones/grupos, datos obligatorios y estado; curso no disponible bloqueado | Beneficiario no administra ni ingresa a curso oculto | Registros de persistencia y denegación |
| T12 | P1 | REQ-04-*, REQ-08-01 | Lectura prod + materiales licenciados | Enlaces RSMax, enlaces rápidos, ayuda, archivos DOCX/XLSX/PPTX/PDF/PNG y MP4/MP3/YouTube reproducen | Enlaces rotos, destinos ocultos o de otro grupo | Lista de rutas, estados HTTP y capturas de reproducción |
| T13 | P1 | REQ-02-* | Navegadores/dispositivos autorizados | Windows, Linux, macOS + Chrome/Edge/Firefox/Safari; PC/tablet/móvil; actividad y mensajería | Sin 4xx/5xx inesperados, consola JS limpia y sin desplazamiento horizontal | Matriz por entorno y capturas del dispositivo; Safari no se sustituye por emulación sin autorización |
| T14 | P1 | REQ-01-* | Staging representativo; prod solo ventana aprobada | Medir carga con N del grupo más grande, curso, notas, archivo y reproducción, arranque frío/caliente | Errores, p95 o máximos exceden umbral; detener si degrada usuarios | Informe con tamaño de muestra, metodología, tiempos, errores y recursos |
| T15 | P1 | REQ-03-01 | Monitoreo autorizado durante periodo | Verificar HTTP 200 y carga login en toda la ventana contractual definida | Huecos horarios/caídas, ausencia de periodo o SLA aprobado | Serie temporal original, incidentes y firma |
| T16 | P0 | Los 14 condicionales + todas las decisiones | Institución/Interventoría | Registrar aplicabilidad, N/A autorizado o prueba satisfactoria por ID | No convertir ausencia de actividad en N/A sin aprobación | Acta y matriz final firmadas |

## Primer lote al iniciar las pruebas

1. **Solo lectura:** ejecutar G0, recuperar el acta del 27/10 y verificar situación vigente de Staff, CAU, `tool_policy`, AF8 y RSMax.
2. **Conciliación y comparación:** revisar contenidos/estructura por UT, 68 IDs, y el formulario AF8G1 por agregados; registrar bloqueos externos.
3. **QA controlada:** T01 y T02 usando datos sintéticos aprobados, luego T03–T09 cuando existan sus insumos, y T10–T15.
4. **Cierre:** T16 y aprobación institucional, sin fingir resultados no ejecutados.

## Convención para registrar resultados

```text
case_id: Txx
requisito: REQ-xx-xx
entorno: staging | producción-lectura | producción-autorizada
versión: Moodle / Staff / RSMax / commit
rol_grupo: sintético / scope explícito
precondiciones:
resultado_esperado:
resultado_observado:
evidencia_saneada: ruta / SHA-256 / captura sin PII
estado: PASS | FAIL | BLOCKED | N/A APROBADO
incidencia:
responsable:
fecha:
```

**Esta actualización no creó usuarios, matrículas, evaluaciones, tickets, sesiones, documentos ni registros en Moodle o en el VPS.**
