> **Inventario histórico (24 de octubre de 2026).** Las afirmaciones sobre versión instalada, cron, tema o ausencia de plugins describen esa captura, **no la producción actual**. Antes de operar el VPS, usar la [política de despliegue](../scripts/deploy/README.md), [AGENTS.md](../AGENTS.md) y verificar el servidor real. No corregir esta línea base retrospectivamente.

# Sincronización de producción con Git

## Línea base comprobada el 24 de octubre de 2026

- Producción ejecuta Moodle `5.3+ (Build: 20261021)`, rama `503`, commit oficial `aa930e26fbdd08dd8ba70f53298838a8d306ee57`.
- El DocumentRoot de NGINX apunta a `/home/user/htdocs/srv2085040.hstgr.cloud/public`.
- El único plugin no incluido en el commit oficial encontrado en producción es `theme_rsmax`. `local_asodeltolstaff` no está instalado en producción.
- Los 100 archivos fuente de `theme_rsmax` recuperados previamente coinciden byte por byte con Git. Producción tenía además 16 artefactos AMD compilados; ahora también se versionan bajo `plugins/theme/rsmax/amd/build/`.
- La comparación de 67.615 archivos del paquete oficial no detectó ediciones directas al núcleo. Las únicas diferencias oficiales son `composer.json` y `composer.lock`, correspondientes al overlay de seguridad aprobado. `githash.php` identifica el paquete y `.gitignore` no se distribuye en la raíz productiva.
- El `composer.json` y `composer.lock` productivos coinciden exactamente con `overlays/moodle/5.3/asodeltol-m53-deps-2026.10.22.1/`.
- El tema activo es `rsmax`. Moodle conserva 74 ajustes del tema en la base de datos; no se copiaron sus valores ni archivos asociados al repositorio.

## Elementos que permanecen fuera de Git

Por diseño no se versionan:

- `config.php` principal y cualquier credencial, token o clave;
- base de datos, matrículas, calificaciones y datos personales;
- `moodledata`, logos o archivos administrados desde Moodle;
- `vendor`, cachés, sesiones, logs, volcados y respaldos;
- certificados y claves TLS;
- configuración activa administrada por CloudPanel: virtual host NGINX, PHP-FPM y tareas cron.

El virtual host activo contiene el DocumentRoot `/public`, enrutamiento Moodle 5.3 mediante `r.php`, HTTPS/HSTS y bloqueos para `vendor`, `node_modules`, archivos Composer y archivos internos. Estos valores se inventarían como plantilla solo después de separar directivas de CloudPanel, rutas locales y certificados; el archivo activo no debe copiarse ni reemplazarse desde Git.

Observación pendiente: el cron productivo invoca PHP 8.3, aunque el CLI predeterminado y el objetivo del proyecto son PHP 8.4. No se modificó porque esta tarea fue exclusivamente de comparación. Debe corregirse en una ventana operativa con respaldo y comprobación posterior del cron.

## Flujo para cambios futuros

1. La ruta preferida es rama separada → pruebas → Pull Request → revisión → merge → despliegue reversible. Dos personas que trabajen simultáneamente usan ramas o worktrees distintos.
2. Una corrección urgente hecha primero en Hostinger se congela después de comprobarla; no se vuelve a editar hasta capturarla.
3. Desde una rama creada sobre `origin/main`, se hace inventario de solo lectura y se comparan hashes contra el commit oficial de Moodle y contra Git.
4. Solo se recuperan plugins propios, temas, scripts, recursos estáticos y configuraciones sanitizadas. La lista de exclusión anterior es obligatoria.
5. Los cambios recuperados se revisan para detectar secretos y datos personales, se prueban localmente y se publican mediante Pull Request. Nunca se sobrescribe producción durante la captura.
6. Después del merge, `main` se convierte en la línea base de código. El siguiente despliegue compara primero la versión activa para impedir que una diferencia remota no capturada sea reemplazada.
7. Los valores almacenados en base de datos se protegen mediante respaldos y procedimientos de administración; no se convierten en volcados versionados.

`rsmax` es el diseño institucional principal. Ningún flujo de sincronización debe restaurar temas o estilos anteriores sobre sus archivos.
