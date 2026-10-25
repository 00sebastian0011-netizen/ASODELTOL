# Auditoría del despliegue — 25 de octubre de 2026

El workflow publicado anteriormente no está listo para producción. Se incorpora una puerta que falla antes de SSH y de cualquier modificación remota, también en ejecución manual. No es una activación del despliegue.

Defectos comprobados en el código de main 69276abbe:

- Ejecuta upgrade global sin respaldo consistente/verificado de BD y sin inventario de actualizaciones ajenas.
- El checksum contiene `dist/asodeltolstaff.tgz` pero el archivo remoto se carga en `/tmp`.
- El grep rechaza referencias legítimas a rutas protegidas en la propia documentación y no comprueba comportamiento.
- No valida entradas del tar, enlaces simbólicos ni destino resuelto antes de extraer/copiar.
- La copia en caliente puede dejar archivos de versiones mezcladas; no hay recuperación automática previa a migraciones.
- Usa rutas CLI fuera de `public`, sin comprobar la instalación efectiva.
- No fuerza explícitamente StrictHostKeyChecking=yes ni valida la cuenta antes de escribir.
- Usa temporales compartidos y limpieza recursiva; no hay bloqueo remoto.
- Un HTTP 200 no acredita versión, permisos, navegación ni identidad visual.

Antes de retirar la puerta se requieren implementación y ensayo local del mecanismo completo, conexión SSH verificada, respaldo/restauración de BD probado, inventario de versiones, prueba funcional sintética autorizada y evidencia de recuperación. Los requisitos de infraestructura y despliegue siguen pendientes. No se ha accedido al VPS en esta intervención.
