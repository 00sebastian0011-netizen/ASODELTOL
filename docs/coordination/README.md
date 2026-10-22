# Coordinación sin edición simultánea

Este protocolo evita que Codex, Gemini u otros colaboradores escriban el mismo archivo al mismo tiempo.

## Reclamar archivos

1. Trabajar en una rama o `git worktree` con nombre identificable, por ejemplo `codex/DP-004-window-spike`.
2. Para cada archivo objetivo, crear de forma exclusiva un archivo en `docs/coordination/locks/`. Sustituir `/` por `__` y agregar `.lock`.
3. Incluir propietario, tarea, rama, archivos, fecha de inicio y estado.
4. Revisar que no exista otro reclamo para el archivo ni un reclamo de directorio que lo incluya.
5. Editar sólo la lista declarada. Para ampliar el alcance, actualizar primero el reclamo.

Ejemplo para `plugins/local/asodeltolaudit/db/install.xml`:

```text
Archivo de lock: plugins__local__asodeltolaudit__db__install.xml.lock

owner: Codex
task: AUD-001
branch: codex/AUD-001-schema
files:
  - plugins/local/asodeltolaudit/db/install.xml
started_at: 2026-10-21T20:00:00-05:00
status: active
```

La creación debe ser exclusiva; si ya existe el nombre, no sobrescribirlo. Un reclamo puede listar varios archivos sólo si forman una unidad inseparable. No reclamar árboles amplios sin necesidad.

## Entrega y revisión

1. El autor ejecuta pruebas y deja un resumen reproducible.
2. El otro agente revisa el diff sin editar los archivos reclamados.
3. El revisor entrega observaciones; el autor hace los cambios.
4. Tras integrar o abandonar, el propietario elimina el lock y registra el resultado en la tarea.

Para una emergencia o lock huérfano, un responsable humano confirma primero que el propietario no sigue trabajando y documenta la liberación. Nunca se fuerza la toma silenciosamente.

## Archivos compartidos

`README.md`, `AGENTS.md`, `docs/ARQUITECTURA.md`, `docs/REQUERIMIENTOS.md`, `docs/DECISIONES_PENDIENTES.md` y los ADR son archivos de alta contención. Se actualizan al final de una tarea, uno por vez, después de integrar los cambios especializados.

