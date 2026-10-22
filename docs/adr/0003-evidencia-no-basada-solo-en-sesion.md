# ADR-0003 — Asistencia y tiempo no basados sólo en sesión

- Estado: Aceptada
- Fecha: 2026-10-21
- Responsables: Equipo técnico ASODELTOL
- Requisitos/decisiones: RN-001, RN-002, RF-008

## Contexto

Un inicio de sesión prueba autenticación en un instante; un cierre puede faltar y una sesión abierta no demuestra atención, presencia ni trabajo. El requisito prohíbe esa inferencia exclusiva.

## Opciones consideradas

1. Calcular asistencia/tiempo entre login y logout.
2. Usar múltiples fuentes: horario, registro explícito, evidencia de actividad/reunión y validación.
3. No registrar evidencia digital.

## Decisión

Adoptar la opción 2. Los logs técnicos sólo sirven como evidencia auxiliar. El sistema conserva origen, actor, contexto, instante y correcciones de la evidencia aceptada.

## Consecuencias

- La regla contractual de cómputo debe aprobarse antes de automatizar totales.
- Los reportes distinguen datos declarados, observados y validados.
- Una corrección añade historia en lugar de sobrescribir silenciosamente.

## Verificación

Pruebas unitarias y de aceptación deben demostrar que login/logout aislados no crean asistencia ni tiempo reconocido.

