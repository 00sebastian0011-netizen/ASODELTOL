# Decisiones pendientes

> **SEGUIMIENTO VIGENTE (29/10/2026).** Las decisiones DP de este registro conservan su **estado histórico**, que no debe darse por cerrado ni abierto sin verificación. El informe del VPS reporta Staff 0.7.8 instalado y QA de privacidad PASS, mientras otras decisiones académicas, de asistencia, privacidad institucional y reporte necesitan actas propias. Ver [pendientes postauditoría](anexo14/SEGUIMIENTO_POSTAUDITORIA_2026-10-29.md) y [plan de pruebas](anexo14/PLAN_PRUEBAS_PENDIENTES_2026-10-29.md) antes de actualizar cualquier DP.

No se debe convertir una suposición de esta tabla en código o contenido definitivo. Una decisión cerrada se traslada a una ADR con fecha, responsables, alternativas y consecuencias.

| ID | Prioridad | Decisión necesaria | Evidencia/autoridad requerida | Bloquea | Estado vigente |
|---|---|---|---|---|---|
| DP-001 | Crítica | Resolver cinco unidades × diez ítems = 50 frente al total reportado de 80 ítems. | Inventario académico canónico aprobado. | Carga de contenido, progreso detallado y aceptación. | **Pendiente académica; el catálogo modela sólo los 50 enumerados** |
| DP-002 | Crítica | Resolver cuatro recursos generales enumerados frente a nueve mencionados. | Lista canónica de recursos con identificadores. | Carga de recursos y aceptación. | **Pendiente académica; el catálogo modela sólo los 4 enumerados** |
| DP-003 | Crítica | Definir relación entre unidad, módulo, actividad, recurso e ítem. | Glosario contractual/académico. | Modelo del curso y regla de tiempo. | Pendiente académica |
| DP-004 | Crítica | Definir qué inicia los ocho días hábiles: fecha fija por grupo o finalización de unidad previa. | Responsable académico/contractual. | Plugin temporal. | Misión 10 probó ambos modos sólo en el calculador sintético; el disparador institucional sigue pendiente |
| DP-005 | Crítica | Definir calendario laboral, festivos, zona horaria, hora límite y pausas. | Política aprobada y calendario versionado. | Cálculo y pruebas de vencimiento. | `America/Bogota` y lunes–viernes están modelados; faltan fuente/versionado de festivos, inclusión del día inicial y hora límite |
| DP-006 | Crítica | Definir quién solicita/aprueba/revoca excepciones, motivos permitidos y efecto retroactivo. | Matriz de autoridad. | Auditoría y permisos del plugin temporal. | El core no aporta el historial de excepción requerido; autoridad, motivos y retroactividad siguen pendientes |
| DP-007 | Alta | Elegir un curso con siete grupos o siete cursos; definir aislamiento y asignación de capacitadores. | Operación académica y piloto en staging. | Plantilla, reportes y matriculación. | **Resuelta provisionalmente ([ADR-0005](adr/0005-provisionalidad-grupos-separados-y-piloto.md)):** piloto sintético local superado para G01/G02; la aceptación institucional sigue pendiente |
| DP-008 | Crítica | Definir calificación aprobatoria, intentos, recuperación y reapertura por unidad. | Reglamento académico. | Secuencia nativa y aceptación. | Misión 10 validó la mecánica con umbral sintético 60; la nota y política definitivas siguen pendientes |
| DP-009 | Crítica | Definir método válido de asistencia, estados, correcciones y autoridad. | Reglamento/contrato y prueba de BBB/`mod_attendance`. | Seguimiento de asistencia. | Pendiente; `mod_attendance` no está instalado y los logs no bastan |
| DP-010 | Crítica | Definir “disponibilidad sincrónica”, unidad de tiempo, pausas, redondeo, evidencia y validación. | Contrato/operación. | `local_asodeltolstaff` y reportes. | Modelo de 3 niveles definido en contrato |
| DP-011 | Alta | Elegir proveedor de videoconferencia, capacidad, integración, residencia y retención de grabaciones. | Evaluación técnica, jurídica y económica. | Integración sincrónica. | Pendiente de selección de proveedor |
| DP-012 | Alta | Definir catálogo de reportes: columnas, filtros, corte, audiencia, firma y retención. | Plantillas contractuales aprobadas. | `report_asodeltol`. | **Congelado provisionalmente:** XLSX nativo validado para participantes, progreso y calificaciones; falta especificación contractual |
| DP-013 | Crítica | Designar responsable/encargado, finalidades, base de tratamiento, política y tiempos de retención. | Responsable institucional y asesoría jurídica. | Salida con datos reales. | Pendiente jurídica |
| DP-014 | Alta | Definir RPO, RTO, disponibilidad objetivo y ventanas de mantenimiento. | Dueño del servicio. | Plan operativo y aceptación. | Pendiente de acuerdo de nivel de servicio |
| DP-015 | Crítica | Planificar actualización desde Moodle 5.0, rama fuera de soporte, a Moodle 5.3 LTS. | Inventario de plugins, política de soporte y pruebas en staging. | Mitigación de riesgo de seguridad. | **Cerrada ([ADR-0004](adr/0004-objetivo-moodle-53-lts-y-documentroot-public.md)):** Objetivo formal Moodle 5.3 LTS y `/public` |
| DP-016 | Alta | Inventariar motor/versión de BD, tema, plugins y configuración relevantes de producción. | Ejecución de script de diagnóstico por operador autorizado. | Matriz real de compatibilidad y staging. | Guía de diagnóstico no destructivo preparada en `OPERACION.md` |
| DP-017 | Media | Definir concurrencia esperada, patrón de evaluaciones y tamaño máximo de exportaciones. | Operación y carga esperada. | Pruebas de capacidad. | Métricas para decisión de Redis definidas |
| DP-018 | Alta | Determinar si la auditoría exige inmutabilidad externa o firma, más allá de historial aplicativo. | Contrato/interventoría. | Diseño final de auditoría. | Historial *append-only* en base de datos modelado |
| DP-019 | Alta | Definir fuente maestra de identidad/matrícula y campos únicos permitidos. | Gobierno de datos. | Importación, deduplicación y bajas. | Pendiente institucional |
| DP-020 | Media | Confirmar licencias, propietario y proceso de aprobación para plugins terceros. | Gobierno TI/compras. | Instalación de `mod_attendance` u otros. | Pendiente TI |
| DP-021 | Alta | Aprobar la categoría Moodle y su `idnumber` para AF8 en staging y, por separado, en producción. | Administración Moodle y gobierno del servicio. | Generación/importación del CSV de curso. | Categoría sintética usada sólo en staging local; producción sigue pendiente y el generador exige valor explícito |

## Evidencia técnica de Misión 10 que no cierra decisiones

- Moodle nativo puede encadenar las cinco secciones dentro de un curso mediante finalización automática por calificación y una restricción que exige `COMPLETION_COMPLETE_PASS` en la actividad inmediatamente anterior.
- Recibir una nota, completar una actividad y aprobarla son estados distintos. El escenario sintético reprobado quedó en `COMPLETION_COMPLETE_FAIL` y no abrió la unidad siguiente.
- La finalización del curso es un mecanismo de seguimiento, no la barrera de acceso. La misión no agregó criterios globales porque su definición depende de DP-003 y DP-008.
- Las restricciones nativas de fecha y grupo sirven para ventanas absolutas ya calculadas, pero no calculan días hábiles colombianos, no versionan una fuente de festivos y no ofrecen por sí solas excepciones auditables por grupo.
- `availability_asodeltolwindow` continúa siendo la propuesta mínima para calcular y evaluar la ventana por estudiante/grupo. Debe recibir una política aprobada —disparador, inclusión del día inicial, calendario versionado, hora límite y zona— y registrar las excepciones sin sobrescribir el historial.

## Evidencia técnica de Misión 11 que no cierra decisiones

- `local_asodeltolstaff` demostró en staging sesiones por unidad/grupo/capacitador, asistencia provisional, correcciones anexables y validación administrativa separada.
- Los reportes CSV/XLSX usan los formatos nativos de Moodle y respetan grupos y capacidades; no tienen valor contractual aprobado.
- La regla sintética sólo acepta minutos positivos que no superen el intervalo reportado. No define redondeos, pausas, asistencia válida, remuneración ni obligación contractual.
- `mod_attendance` no fue instalado: el directorio oficial consultado no declaraba compatibilidad con Moodle 5.3 al 22 de octubre de 2026.
- Siguen pendientes DP-009, DP-010, DP-011, DP-012, DP-013, DP-018 y DP-020 antes de usar datos o evidencias reales.

## Formato para cerrar una decisión

```text
ID:
Fecha:
Estado: aceptada | rechazada | sustituida
Responsables que aprueban:
Fuentes:
Alternativas consideradas:
Decisión:
Consecuencias y riesgos:
Archivos/requisitos afectados:
ADR:
```
