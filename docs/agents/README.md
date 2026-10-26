# Ruta rápida de contexto para agentes (ASODELTOL)

El propósito de este índice es ahorrar lecturas y evitar que documentación fechada suplante el código o la evidencia. **No es una nueva fuente contractual.** El punto de entrada es [AGENTS.md](../../AGENTS.md).

## Inicio en menos de un minuto

```bash
git status --short
npm run agent:brief
```

`agent:brief` lee fuentes versionadas, compara los 82 IDs de matriz/trazabilidad, muestra la versión declarada del plugin, la estructura AF8 y el commit **local**. No hace llamadas de red ni infiere lo instalado en el VPS. Un fallo por divergencia documental debe corregirse en las fuentes, no ocultarse.

## Leer únicamente según la tarea

| Tarea | Leer primero | Ampliar solo si es necesario |
| --- | --- | --- |
| Reportes, soporte, grupos, navegación | `plugins/local/asodeltolstaff/` y sus tests | `docs/REQUERIMIENTOS.md`, criterio REQ concreto |
| Tema, logos, portada | `plugins/theme/rsmax/`, configuración efectiva del tema activo | `docs/PROTECCION_IDENTIDAD_VISUAL.md` |
| AF8 y 54 fichas enumeradas | `catalog/af8/catalog.json` | `docs/DECISIONES_PENDIENTES.md`, esquema |
| Requisito Anexo 14 | Fila REQ de `docs/anexo14/MATRIZ_ASODELTOL_82.md` | Entrada de `docs/anexo14/TRAZABILIDAD_82_2026-10-25.json`, pruebas |
| Normas sin decidir | `docs/DECISIONES_PENDIENTES.md` | `docs/adr/` y aprobación institucional |
| Despliegue/reversión | `scripts/deploy/deploy-policy.json`, `scripts/deploy/README.md` | `docs/OPERACION.md`; **verificar producción** |
| UI/navegadores/permisos | Código afectado y test focalizado | `docs/anexo14/LOTE_TECNICO_2026-10-25.md` como evidencia fechada |
| Agentes concurrentes | `docs/coordination/README.md` | Locks solo cuando haya colisión |

## Precedencia por dominio (no por antigüedad del archivo)

- **Qué exige la institución:** criterio original de ASODELTOL y decisiones formalmente aprobadas; no inferir a partir de ASOCODEM-AF1.
- **Qué está implementado:** código + tests ejecutados en el mismo commit/versión; test local no demuestra producción.
- **Qué se observó:** evidencia con ambiente, fecha, actores y resultado; nunca convertir parcial en aceptación.
- **Qué está desplegado:** comprobación del ambiente real; README, Git y un HTTP 200 aislado no prueban versión o funcionalidad.
- **Quién autoriza:** petición actual del usuario más privilegios y salvaguardas; prohibiciones en informes antiguos fueron límites de aquella misión.

## Plantilla breve para traspasar trabajo entre agentes

```text
Objetivo/REQ:
Rama + SHA y estado local:
Archivos afectados (sin copiar otros):
Cambio aplicado:
Prueba exacta y entorno + resultado:
Evidencia o ruta verificable:
Bloqueos externos y siguiente paso:
Estado de publicación: local | PR | main | staging | producción verificada
```

No pegar volcados de logs, matrices completas ni documentación irrelevante. Enlazar archivos y citar únicamente el fragmento necesario. Los detalles históricos permanecen en sus documentos originales.
