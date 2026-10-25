# Protección de identidad visual y despliegue

La identidad visual se conserva como recurso versionado y evidencia de procedencia; no se instala ni cambia automáticamente en Moodle. Cualquier registro en administración del sitio requiere una decisión institucional y una copia de seguridad independiente.

El único despliegue automatizado permitido por este repositorio es `scripts/deploy/deploy-asodeltol-plugin.ps1`, limitado a `public/local/asodeltolstaff`. Exige `-Apply` y `-BackupPath`, rechaza `config.php`, `moodledata`, `theme/rsmax`, la raíz del repositorio y cualquier ruta distinta del plugin, y nunca ejecuta Git, sincronización destructiva ni borrado. Sin `-Apply` solo muestra un plan.

El `git pull` del repositorio actualiza código fuente; no despliega por sí mismo en `/home/user/htdocs/.../public`. No se realizó acceso ni modificación de VPS/producción durante esta entrega.
