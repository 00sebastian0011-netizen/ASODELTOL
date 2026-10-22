# Roadmap de implementación

El roadmap usa criterios de salida, no fechas ficticias. Ninguna fase autoriza trabajo en producción por sí misma.

Estado tras Misión 09: el catálogo AF8 sigue validado y el Moodle 5.3 local aislado ya demuestra aprovisionamiento, siete grupos, recorrido funcional, plantilla por backup/restore y exportaciones nativas de participantes, progreso y calificaciones por grupo. El curso original y la edición sintética permanecen ocultos. Esta evidencia no sustituye el inventario, respaldo, migración ni aceptación del entorno de producción.

## Fase 0 — Gobierno, diagnóstico y definiciones

Objetivo: cerrar los vacíos que cambian datos, permisos o comportamiento, y verificar el entorno real.

- Resolver las inconsistencias de 50 frente a 80 ítems y cuatro frente a nueve recursos (DP-001 y DP-002).
- Definir “unidad”, “módulo” e “ítem” en el modelo académico (DP-003).
- Aprobar umbrales, intentos, recuperación y condiciones de aprobación (DP-008).
- Aprobar la regla completa de ocho días hábiles con calendario colombiano (Ley Emiliani).
- Definir evidencia de asistencia y disponibilidad del capacitador (DP-010).
- Definir reportes, columnas, audiencia, retención y base legal (DP-012 y DP-013).
- Elegir proveedor de videoconferencia y política de grabaciones (DP-011).
- **Ejecutar diagnóstico no destructivo en servidor:** obtener versión exacta de BD, PHP-FPM, `max_input_vars`, configuración de NGINX y plugins instalados (DP-016).
- **Plan de migración formal a Moodle 5.3 LTS:** adoptado en ADR-0004; ejecutar en staging antes de programar plugins.

Criterio de salida: diagnóstico de servidor completado, decisiones críticas aprobadas, diccionario mínimo de datos y contenido canónico disponibles.

## Fase 1 — Migración a Moodle 5.3 LTS y línea base en Staging aislado

Objetivo: actualizar el entorno a Moodle 5.3 LTS y demostrar cuánto se resuelve sin código propio.

Estado: **demostrado en staging local aislado** para curso, cohortes/grupos, roles, actividad evaluable, plantilla estructural y reportes nativos. La migración de la instalación alojada y su diagnóstico continúan fuera del alcance y requieren operador autorizado.

- Clonar la instalación existente en un staging aislado con la misma pila (Hostinger KVM 2 / Ubuntu 24.04).
- Ajustar configuración NGINX en CloudPanel para apuntar DocumentRoot a `/public` (Moodle 5.1+).
- Actualizar base de datos a MariaDB $\ge$ 11.4 / MySQL $\ge$ 8.4 si el diagnóstico detecta versiones inferiores.
- Ejecutar la actualización de Moodle a 5.3 LTS vía CLI (`php public/admin/cli/upgrade.php`) y validar cron.
- Aprovisionar el curso piloto AF8 oculto con el paquete validado de Misión 04, previsualización oficial, cinco secciones, cohortes `AF8-G01` a `AF8-G07` y grupos separados (ADR-0005).
- Ejecutar la matriz de pruebas de segregación de grupos (participantes, calificaciones, foros y entregas).
- Evaluar Custom Reports nativo de 5.3 LTS frente al catálogo de reportes requerido.
- Evaluar BigBlueButton de core y/o LTI 1.3 con datos sintéticos.

Criterio de salida: Moodle 5.3 LTS funcionando en staging con `/public`, matriz de segregación de grupos superada y catálogo de brechas de reporte confirmado.

## Fase 2 — Spikes técnicos de las brechas

Objetivo: reducir los riesgos de diseño antes de construir plugins completos sobre 5.3 LTS.

- Spike de `availability_asodeltolwindow`: festivos colombianos (Ley Emiliani), inicio por grupo y caché MUC.
- Spike de `local_asodeltolaudit`: eventos `\core\event\base`, observers y fuentes para Report Builder.
- Spike de disponibilidad sincrónica: modelo de 3 niveles (planificado, telemetría BBB y atestación).
- Prueba de carga inicial con 50 usuarios sintéticos concurrentes para evaluar métricas de decisión de Redis.

Criterio de salida: contratos entre plugins consolidados, MUC validado sin degradación $N+1$ y reporte de carga con métricas de CPU/RAM.

## Fase 3 — Desarrollo de plugins mínimos

Orden de desarrollo:

1. `availability_asodeltolwindow` (ventana hábil y excepciones).
2. `local_asodeltolaudit` (hechos de excepción, validación y fuentes de Report Builder).
3. `local_asodeltolstaff` (únicamente si el piloto confirma que BBB nativo y Attendance no bastan).
4. `report_asodeltol` (**congelado**; solo se desarrolla si Report Builder nativo de 5.3 no satisface la necesidad).

Por componente:

- capacidades y contextos;
- esquema XMLDB y actualización;
- APIs Privacy, Events, Tasks, Backup/Restore cuando aplique;
- PHPUnit y Behat;
- accesibilidad y traducciones;
- documentación de administración y operación;
- revisión de seguridad y rendimiento.

Criterio de salida: calidad automatizada en verde, revisión cruzada, paquetes reproducibles y cero hallazgos críticos abiertos.

## Fase 4 — Integración y piloto controlado

Objetivo: validar el proceso completo sin datos reales al comienzo.

- Instalar los paquetes exactos en staging.
- Configurar un curso espejo sin contenido personal.
- Ejecutar rutas de estudiante, capacitador, administrador e interventoría.
- Probar grupos, secuencia, vencimiento, prórroga, asistencia y reportes.
- Simular caída del proveedor de videoconferencia y reintentos de cron.
- Ejecutar prueba de carga, exportaciones grandes y controles de privacidad.
- Restaurar un respaldo para demostrar RPO/RTO medidos.
- Piloto posterior con usuarios autorizados, mínimo dato y soporte reforzado.

Criterio de salida: acta de aceptación, restauración demostrada, checklist de despliegue y decisión go/no-go.

## Fase 5 — Lanzamiento y estabilización

Objetivo: liberar mediante un cambio aprobado y reversible.

- Congelar artefactos y checksums.
- Confirmar respaldo consistente de código, base de datos y `moodledata`.
- Ejecutar ventana aprobada con separación de funciones.
- Realizar pruebas de humo y monitorizar cron, errores, almacenamiento y rendimiento.
- Revisar matrículas, aislamiento de grupos y permisos de reportes.
- Mantener una ventana de reversión definida y registrar toda desviación.

Criterio de salida: servicio estable, métricas dentro de umbrales aprobados y entrega operativa documentada.

## Fase 6 — Operación y mejora

- Parches de seguridad de Moodle, PHP, SO y plugins con cadencia acordada.
- Revisión periódica de cuentas privilegiadas y capacidades.
- Pruebas de restauración y ejercicios de incidente.
- Depuración por retención y atención de derechos de titulares.
- Reevaluación de proveedores, dependencias y compatibilidad.
- Evolución por requisitos aprobados, no por inferencias.

## Riesgos principales

| Riesgo | Probabilidad/impacto inicial | Tratamiento |
|---|---|---|
| Moodle 5.0 fuera de soporte de seguridad | Alto/Crítico | Planificar actualización compatible después de inventario y pruebas; minimizar exposición mientras tanto. |
| Regla de ocho días hábiles ambigua | Alta/Alta | No codificar hasta aprobar inicio, calendario, zona y excepciones. |
| Contenido documental inconsistente | Alta/Alta | Inventario canónico con control de versión y aprobación académica. |
| Confundir logs con asistencia/tiempo | Alta/Alta | Modelo de múltiples evidencias y validación explícita. |
| Exposición de datos por reportes o rol auditor | Media/Crítica | Capacidades mínimas, alcance por contexto/grupo, pruebas negativas y minimización. |
| Dependencia de videoconferencia externa | Media/Alta | Evaluación contractual/técnica, LTI/BBB estándar, degradación controlada y retención definida. |
| Capacidad insuficiente del KVM 2 | Desconocida/Alta | Medición y prueba de carga; no asumir concurrencia por número de matriculados. |
| Plugin tercero incompatible o abandonado | Media/Alta | Due diligence, versión fijada, staging y plan de sustitución. |
| Exportaciones como fuga de información | Media/Alta | Audiencia limitada, campos mínimos, trazabilidad y almacenamiento temporal controlado. |
| Migración de plugin no reversible | Media/Alta | Respaldo consistente, ensayo de actualización y reversión por restauración completa. |
| Deriva entre catálogo y configuración manual del curso | Media/Alta | Identificadores estables, manifiesto, auditoría post-aprovisionamiento y curso plantilla sólo después de validarlo. |

## Próximo ciclo recomendado para Codex y Gemini

Después de liberar los locks y asignar propietarios distintos:

- **Operador humano:** completar la lista visual de Misión 09 en el Moodle local y aprobar o rechazar la plantilla administrativa.
- **Gemini:** revisar de sólo lectura la evidencia sanitizada de Misión 09, sin abrir MBZ, bases de datos ni secretos.
- **Codex:** atender únicamente hallazgos reproducibles y mantener congelado `report_asodeltol` mientras los reportes nativos cubran la necesidad aprobada.

El siguiente desarrollo de plugin continúa condicionado a las decisiones académicas y contractuales abiertas. No se inicia `availability_asodeltolwindow`, asistencia o reportes personalizados sin aprobar primero sus reglas y evidencias.
