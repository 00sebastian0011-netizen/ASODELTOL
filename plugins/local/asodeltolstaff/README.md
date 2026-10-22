# `local_asodeltolstaff` — contrato de diseño

Estado: propuesta no instalable.

## Responsabilidad

Gestionar, si el piloto en Moodle 5.3 confirma la brecha frente a `mod_attendance` o BigBlueButton nativo, el seguimiento de la labor sincrónica del capacitador mediante un modelo diferenciado de tres niveles:

### 1. Horario Planificado (*Scheduled*)
- Agenda de bloques sincrónicos programados para cada uno de los 7 grupos en el Calendario oficial de Moodle.
- Datos: Grupo (`AF8-G01` a `AF8-G07`), fecha, hora inicio/fin UTC, tema y enlace a la sesión sincrónica.

### 2. Presencia Observada (*Observed*)
- Telemetría técnica capturada por la plataforma o el conector de videoconferencia (BigBlueButton de core o LTI 1.3).
- Registro objetivo de instantes de conexión, permanencia en sala y actividad registrada.
- **Regla negativa:** El inicio/cierre de sesión en Moodle o una pestaña abierta en el navegador son indicios auxiliares; **no constituyen por sí solos prueba de presencia sincrónica**.

### 3. Cumplimiento Validado (*Validated*)
- Atestación o validación formal realizada por un actor autorizado (Coordinador Académico o Interventoría).
- La validación aprueba, ajusta o rechaza el bloque de horas impartidas contrastando la planificación con la evidencia técnica.
- Todo ajuste posterior conserva historial inmutable con referencia al registro original, motivo y usuario firmante.

## Reglas técnicas y mitigación de sobre-ingeniería

- **Evaluar primero BigBlueButton de core:** Moodle 5.3 incluye BigBlueButton nativo, que registra participantes, moderadores y tiempos de reunión. Si BBB o `mod_attendance` cubren la toma de evidencia, este plugin se limitará a la atestación de supervisión (Nivel 3).
- **Idempotencia:** Tareas programadas de conciliación tolerantes a fallos en caso de desconexión del proveedor externo de videoconferencia.
- **Segregación:** Capacitadores solo declaran sobre su grupo asignado; Interventoría consulta y valida sin acceso a editar contenidos.
- **Privacy API:** Cumplimiento de derechos de titulares y minimización de metadatos de conexión.

No implementar hasta completar la migración a Moodle 5.3 LTS y cerrar DP-010 y DP-011.

