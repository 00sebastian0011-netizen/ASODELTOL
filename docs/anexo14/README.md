# Anexo 14 — ASODELTOL AF8

## Documentación vigente para el cierre técnico/institucional (corte 29/10/2026)

1. **[Seguimiento postauditoría y 15 paquetes de trabajo](SEGUIMIENTO_POSTAUDITORIA_2026-10-29.md):** prioridades, responsables, evidencia nueva de Staff 0.7.8, bloqueos, dependencias y condiciones para iniciar pruebas.
2. **[Matriz de pendientes por ID (68 + 14 condicionales)](MATRIZ_PENDIENTES_68_2026-10-29.md):** cada criterio, estado histórico 25/10, evidencia parcial reciente, siguiente comprobación y alcance real.
3. **[Plan de pruebas por escenarios](PLAN_PRUEBAS_PENDIENTES_2026-10-29.md):** 16 escenarios, puertas de seguridad, casos positivos/negativos, evidencias requeridas y primer lote a ejecutar.

### Fuentes históricas (NO sustituir ni reetiquetar)

- [Matriz histórica de 82 criterios del 25/10](MATRIZ_ASODELTOL_82.md) y [JSON trazable](TRAZABILIDAD_82_2026-10-25.json): **34 verificados locales, 2 pendientes, 46 bloqueados; 0 aceptaciones formales al corte**.
- [Cierre local de 19 pendientes](CIERRE_19_2026-10-25.md): es evidencia de *staging*, no de producción o acta SENA.
- [Decisiones pendientes](../DECISIONES_PENDIENTES.md): políticas e insumos sujetos a aprobación.
- [CAU y navegación (27/10)](CAU_NAVEGACION_UNIFICADA_2026-10-27.md): propuesta técnica histórica, revalidar estado actual antes de abrir incidencias.
- Los anexos de referencia de **ASOCODEM-AF1** no son evidencia de **ASODELTOL AF8**.

### Estado confirmado para trabajar

- En GitHub `main` está Staff **0.7.8 / 2026102908** en commit `9cda0a937cfa4e27548e519ef6571fa88dd6df4e`.
- Según el informe del agente que inspeccionó el VPS, producción también opera con Staff 0.7.8. No se ha obtenido en esta actualización documental una nueva sesión de diagnóstico productivo.
- 20/20 QA HTTP de privacidad en Moodle aislado y 52/52 pruebas contractuales reportadas, con CI de calidad en verde. No extrapolar a los 68 requisitos.
- **El acta institucional completa de la auditoría del 27/10 debe incorporarse** antes de calificar oficialmente el cierre.

### Regla principal

**Código aprobado / QA PASS ≠ requisito contractual completo aprobado.** Las pruebas nuevas comienzan **después** de esta actualización documental, con identidades sintéticas autorizadas y sin alterar datos reales de AF8.
