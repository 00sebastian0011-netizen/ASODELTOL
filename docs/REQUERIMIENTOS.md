# Requisitos y trazabilidad

## 1. Convenciones

- **Confirmado:** expresado de forma inequívoca en la misión.
- **Pendiente:** falta una decisión o evidencia documental.
- **Propuesto:** diseño técnico sujeto a validación.
- **No aceptable:** comportamiento expresamente descartado.

Este documento no completa vacíos con contenido académico supuesto.

## 2. Requisitos funcionales

| ID | Requisito | Estado | Criterio de aceptación de alto nivel |
|---|---|---|---|
| RF-001 | Gestionar el diplomado AF8 de 80 horas en cinco unidades de 16 horas. | Estructura versionada; contenido pendiente | El catálogo presenta cinco unidades, diez componentes enumerados por unidad y suma 80 horas; conserva abierta la contradicción 50/80 ítems. |
| RF-002 | Matricular 350 beneficiarios distribuidos en siete grupos de 50. | Confirmado | Cada estudiante activo pertenece al grupo correcto; los totales y excepciones de matrícula son reportables. |
| RF-003 | Exigir finalización y aprobación de la unidad anterior para acceder a la siguiente. | Confirmado | Un estudiante que no cumple ambos criterios no accede; quien los cumple sí accede. |
| RF-004 | Limitar cada módulo a un máximo de ocho días hábiles y auditar vencimientos/excepciones. | Pendiente de regla | El cálculo reproduce el calendario aprobado y toda excepción conserva actor, motivo, vigencia e historial. |
| RF-005 | Soportar Administrador, Capacitador, Estudiante e Interventoría/Auditoría. | Confirmado | Las pruebas de permisos demuestran mínimo privilegio y separación entre consulta y modificación. |
| RF-006 | Seguir progreso, matrículas, asistencia y calificaciones. | Confirmado | Cada dimensión tiene fuente de verdad, estado por estudiante/grupo y fecha de corte. |
| RF-007 | Descargar reportes en Excel. | Confirmado; formato pendiente | Usuarios autorizados descargan XLSX con filtros, corte y columnas aprobadas. |
| RF-008 | Registrar actividad y disponibilidad sincrónica verificable de capacitadores. | Confirmado; evidencia pendiente | El registro distingue programación, declaración, evidencia y validación; cada ajuste es trazable. |
| RF-009 | Integrar videoconferencias y grabaciones externas. | Proveedor pendiente | Se accede desde el curso con permisos correctos y retención/privacidad aprobadas. |
| RF-010 | Controlar acceso y proteger datos personales. | Confirmado | Se aplican capacidades por contexto, minimización, privacidad, retención y registro de acciones sensibles. |

## 3. Reglas negativas y restricciones

| ID | Regla |
|---|---|
| RN-001 | No inferir asistencia efectiva exclusivamente de inicio/cierre de sesión, logs de página o estado en línea. |
| RN-002 | No inferir tiempo trabajado exclusivamente de inicio/cierre de sesión. |
| RN-003 | No desarrollar otro LMS ni reinstalar Moodle. |
| RN-004 | No modificar el núcleo de Moodle. |
| RN-005 | No acceder al VPS, producción o credenciales desde esta misión. |
| RN-006 | No almacenar secretos, respaldos, bases de datos o información personal en Git. |
| RN-007 | No inventar contenidos, ítems, recursos, umbrales o políticas no suministradas. |
| RN-008 | No ejecutar `push` ni despliegues durante esta misión. |

## 4. Requisitos no funcionales

| ID | Requisito | Medida prevista |
|---|---|---|
| RNF-001 | Compatibilidad | Matriz formal **Moodle 5.3 LTS / PHP 8.4 / DocumentRoot `/public` / MariaDB $\ge$ 11.4 o MySQL $\ge$ 8.4** ([ADR-0004](adr/0004-objetivo-moodle-53-lts-y-documentroot-public.md)). |
| RNF-002 | Mantenibilidad | Core primero, plugins separados por responsabilidad, sin parches al núcleo y ADR por decisión significativa. |
| RNF-003 | Seguridad | Revisión OWASP/Moodle, capacidades, `sesskey`, validación de entrada, salida escapada, consultas parametrizadas y secretos externos. |
| RNF-004 | Privacidad | Privacy API, minimización, finalidades, custodia legal (Ley 1581), exportación y supresión según política y obligación legal. |
| RNF-005 | Auditabilidad | Eventos anexables (`\core\event\base`), actor, instante, motivo, contexto, fuente y correlación; reloj del sistema sincronizado. |
| RNF-006 | Rendimiento | Presupuesto y prueba de carga con 350 matrículas y concurrencia acordada; consultas paginadas, tareas en cron y caché MUC. |
| RNF-007 | Accesibilidad | Interfaces basadas en componentes Moodle y pruebas de teclado, foco, etiquetas, contraste y lector de pantalla. |
| RNF-008 | Recuperación | Respaldos consistentes y cifrados de código/BD/moodledata, prueba de restauración y procedimiento de reversión. |
| RNF-009 | Observabilidad | Estado de cron, colas/tareas, errores PHP, espacio, base de datos, tiempos de reporte y fallos de integración. |
| RNF-010 | Portabilidad de datos | XLSX/CSV controlado mediante Report Builder nativo, identificadores estables y diccionario de datos versionado. |

Los objetivos cuantitativos de rendimiento, RPO, RTO, retención y disponibilidad no se fijan sin aprobación del responsable.

## 5. Matriz de trazabilidad de solución

| Requisito | Moodle nativo | Plugin existente | Plugin propio propuesto | Pruebas clave |
|---|---|---|---|---|
| RF-001 | Curso/secciones/calendario | — | — | Validador local: títulos, orden, 5×10 componentes y suma de 80 horas. |
| RF-002 | Cohortes, sync, grupos | — | Reporte sólo si el nativo no cubre | Validador local 7×50; piloto en staging para aislamiento, matrícula, foros y libro de calificaciones. |
| RF-003 | Completion, grade pass, restrict access | — | — | Contrato declarativo y matriz aprobado/no aprobado/completo/incompleto en staging. |
| RF-004 | Restricciones de fecha simples | — | `availability_asodeltolwindow`, auditoría | Festivos Colombia (Ley Emiliani), inicio por grupo, fin de semana, prórrogas e historial. |
| RF-005 | Roles/capacidades/contextos | — | Capacidades específicas dentro de cada plugin | Pruebas positivas y negativas, URL directa y grupos. |
| RF-006 | Completion, enrolment, gradebook | BigBlueButton nativo / `mod_attendance` candidato | Entidades de Report Builder | Exactitud contra fuentes y fecha de corte. |
| RF-007 | Report Builder nativo (Custom reports), Data formats XLSX | — | `report_asodeltol` (congelado de reserva) | Filtros de cohorte, fórmulas, permisos, codificación y volumen. |
| RF-008 | Calendario, telemetría BBB, eventos | Integración de reunión por evaluar | `local_asodeltolstaff` y auditoría | Planificado vs observado vs validado, atestación y corrección. |
| RF-009 | BigBlueButton nativo de 5.3 y LTI | Conector oficial del proveedor si se aprueba | Adaptador sólo si no hay estándar | Roles, acceso, grabación, error del proveedor y privacidad. |
| RF-010 | Access/Privacy/Logging APIs | Revisión de cada tercero | Privacy API y retención legal en cada plugin | Exportar/eliminar datos, custodia legal y mínimo privilegio. |

## 6. Asistencia y tiempo efectivo

Una marca de asistencia debe provenir de una acción o fuente acordada, como toma de lista por el capacitador, auto-registro con controles, importación validada del proveedor de reunión o confirmación posterior autorizada. Debe conservar su fuente y las correcciones.

El tiempo del capacitador requiere, como mínimo, el horario planificado y una evidencia distinta del login. El cálculo contractual de tiempo reconocido, redondeos, pausas y aprobación está pendiente. Los logs sirven para contraste, no para producir automáticamente un total pagable o certificable.

## 7. Inconsistencias documentales abiertas

### INC-001 — Ítems por unidad

- Documento A: cinco unidades con diez ítems cada una.
- Aritmética resultante: 50 ítems.
- Total reportado en otra instrucción: 80 ítems.
- Estado: **PENDIENTE / bloquea la carga definitiva de contenido y la validación de completitud**.
- Acción requerida: fuente académica autorizada debe definir qué significa “ítem”, el inventario por unidad y el total contractual.

### INC-002 — Recursos generales

- Inventario enumerado: cuatro recursos generales.
- Total mencionado: nueve recursos generales.
- Estado: **PENDIENTE / bloquea el inventario definitivo de recursos**.
- Acción requerida: fuente académica autorizada debe entregar lista canónica con identificador, título, tipo y ubicación de los nueve o corregir el total.

No se crean 30 ítems ni cinco recursos ficticios para cuadrar los totales.

## 8. Criterios pendientes antes de aprovisionar o publicar el curso

- Inventario canónico de contenidos y recursos.
- Definición de módulo frente a unidad e ítem.
- Umbral y método de aprobación por unidad.
- Evento que inicia los ocho días hábiles y calendario aplicable.
- Política de intentos, recuperación, prórrogas y reapertura.
- Fuente maestra de usuarios/matrículas y campos identificadores permitidos.
- Método y autoridad para registrar/corregir asistencia.
- Evidencia aceptable de disponibilidad sincrónica.
- Proveedor de videoconferencia y política de grabación.
- Catálogo de reportes, columnas, filtros, audiencia y retención.

## 9. Cobertura funcional acumulada hasta la Misión 09

La estructura aprobada para preparación local se materializa en `catalog/af8/catalog.json`: cinco unidades, 50 componentes enumerados, cuatro recursos generales y siete grupos/cohortes. Esto no resuelve los totales contradictorios ni autoriza contenido adicional.

La aceptación automatizada se ejecuta con `npm run quality` y cubre:

- códigos e identificadores estables sin duplicados;
- recursos requeridos, orden y asociaciones de tipos Moodle;
- 16 horas por unidad y 80 horas totales;
- dependencias UT1 → UT2 → UT3 → UT4 → UT5 sin referencias rotas;
- siete grupos de capacidad prevista 50 y total 350;
- presencia de cuatro roles y pruebas negativas de acceso;
- generación local que valida antes de escribir y nunca sobrescribe salidas.

El Moodle 5.3 local aislado ya contiene el curso AF8 oculto, siete grupos y cuentas sintéticas. El piloto funcional validó entrega, calificación, libro de calificaciones y aislamiento inicial entre G01 y G02. La plantilla de Misión 09 conserva las cinco secciones oficiales y la configuración general, pero excluye actividades, participantes, entregas, calificaciones, finalizaciones, grupos y matrículas del curso fuente antes de asignar objetos sintéticos nuevos e independientes.

También se validaron exportaciones nativas XLSX de participantes, progreso por actividad y calificaciones filtradas para G01 y G02. El reporte global de finalización no está disponible todavía porque no existen criterios globales aprobados. Moodle no tiene `mod_attendance` instalado en este staging y sus logs no se aceptan como evidencia exclusiva de asistencia o tiempo trabajado.

Permanecen pendientes los contenidos definitivos, la nota aprobatoria, fechas/calendario, asistencia válida, disponibilidad sincrónica y aceptación institucional. Ninguna prueba local autoriza publicación ni operación en producción.
