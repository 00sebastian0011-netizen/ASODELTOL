# Protección de identidad visual y despliegue

La identidad visual se conserva como recurso versionado y evidencia de procedencia; no se instala ni cambia automáticamente en Moodle. Cualquier registro en administración del sitio requiere una decisión institucional y una copia de seguridad independiente.

El despliegue automatizado está limitado por `scripts/deploy/deploy-policy.json` a `theme_rsmax` y `local_asodeltolstaff`. Antes de sustituir un archivo compara su hash con el manifiesto activo: cualquier edición hecha directamente en Hostinger se conserva y se registra como conflicto. Cada componente tiene copia recuperable y una publicación independiente, sin tocar `config.php` principal, `moodledata`, base de datos, cursos ni recursos ajenos.

Un `git pull` manual no se utiliza sobre la raíz Moodle. El agente prepara y publica únicamente los archivos autorizados desde su copia aislada, registra el commit y conserva la versión anterior para recuperación.
