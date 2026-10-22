# ADR-0002 — Core primero y límites de plugins propios

- Estado: Propuesta
- Fecha: 2026-10-21
- Responsables: Pendientes de aprobación técnica/funcional
- Requisitos/decisiones: RF-004, RF-007, RF-008, RF-010

## Contexto

Moodle ya resuelve grupos, matrículas, finalización, calificaciones, restricciones comunes, logs y exportaciones. ASODELTOL presenta brechas específicas en días hábiles, hechos de auditoría, disponibilidad sincrónica y posibles reportes contractuales.

## Opciones consideradas

1. Un único plugin local para toda la solución.
2. Plugins específicos por responsabilidad y uso de core para el resto.
3. Aplicación externa que replique datos de Moodle.

## Decisión propuesta

Adoptar la opción 2:

- `availability_asodeltolwindow` para ventanas hábiles;
- `local_asodeltolaudit` para hechos de negocio propios;
- `local_asodeltolstaff` para disponibilidad/evidencia del capacitador;
- `report_asodeltol` sólo para brechas de reporte.

La asistencia se evalúa primero con `mod_attendance`; XLSX, secuencia, grupos y videoconferencia usan mecanismos estándar cuando satisfagan el requisito.

## Consecuencias

- Menor duplicación y superficie de mantenimiento.
- Contratos entre componentes deben definirse mediante eventos/APIs públicas.
- Hay más de un paquete, pero cada uno puede probarse y retirarse por separado.
- No se implementa un plugin si el piloto demuestra que core cubre la necesidad.

## Verificación

La Fase 1 debe producir evidencia de brecha y la aprobación debe cambiar el estado de esta ADR antes de desarrollar componentes completos.

