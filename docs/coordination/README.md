# Coordinación proporcional entre agentes

La política vigente es [AGENTS.md](../../AGENTS.md). Evitar conflictos **sin crear burocracia para cada corrección**.

1. Revisa `git status --short`, rama, PR abiertos y modificaciones locales antes de editar. No sobrescribas trabajo ajeno.
2. Si dos agentes afectan **los mismos archivos**, divide responsabilidades o usa ramas/worktrees independientes; acuerda quién integra primero. No edites simultáneamente el archivo compartido.
3. Los archivos en `docs/coordination/locks/` son **opcionales**, útiles cuando hay solapamiento real o varias sesiones de larga duración. Si se usan, anota responsable, tarea, rama, rutas y fecha. Respeta un lock activo y verifica antes de retirar uno aparentemente huérfano.
4. Para el traspaso basta: objetivo/REQ, SHA, archivos, prueba y entorno, resultado y pendiente. Usa [plantilla](../agents/README.md), no copies logs ni la matriz completa.
5. En cambios de arquitectura, privacidad, contratos o esquema, documenta la decisión compartida y revisa el diff. Un ajuste de texto o CSS aislado no requiere ADR ni revisor obligatorio.

Los artefactos históricos de coordinación se conservan como evidencia y no deben interpretarse como instrucciones que obligan a crear un lock para cada archivo.
