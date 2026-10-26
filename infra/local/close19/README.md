# QA independiente de los 19 pendientes

Sólo `http://127.0.0.1:8054`. No comparte BD, cuenta SQL, dataroot, cachés ni red privada con staging 8053 o el VPS. No publica puertos de MySQL. Las claves aleatorias se generan en `build/staging/close19/private/`, excluido de Git; no se pasan en argumentos de procesos. Nunca usar credenciales reales aquí.

Desde la raíz, en PowerShell:

```powershell
python scripts/staging/close19/prepare.py --archive RUTA_EXPLICITA_AL_ZIP_LOCAL_DE_CODIGO_MOODLE --phpunit
python scripts/staging/close19/seed.py
docker exec --user www-data asodeltol-close19-webserver-1 php /opt/close19/context.php use-rsmax
python scripts/staging/close19/run-unit.py
npm.cmd ci
npx.cmd playwright install chromium firefox webkit
node scripts/staging/close19/browser.mjs supported-windows
node scripts/staging/close19/workflows.mjs
node scripts/staging/close19/links.mjs
node scripts/staging/close19/youtube.mjs
python scripts/staging/close19/load.py
node scripts/staging/close19/render50.mjs
python scripts/staging/close19/verify-candidate.py
python scripts/staging/close19/sanitise.py
```

El ZIP es exclusivamente código previamente empaquetado y confiable, de Moodle 5.3+ con sus dependencias. No se incorpora a Git. El preparador excluye el `config.php` anterior, genera uno exclusivo del nuevo volumen y registra el SHA-256 del archivo fuente. No toca el `config.php` de ninguna instalación existente. Las imágenes PHP/MySQL están fijadas por digest en `compose.yml`.

`seed.py` genera un MP4 H.264/AAC de 720p, MP3 y PDF propios; instala FFmpeg desde Debian sólo en este contenedor si falta. La categoría y ambos cursos se crean ocultos. Conserva fixtures previos; `--new` archiva el manifiesto anterior y agrega otro lote sin borrar nada. Las 50 plantillas se contrastan con el catálogo; los recursos auxiliares no se cuentan como contenido oficial. No hay notas, políticas ni licencias institucionales inventadas.

PHPUnit: `prepare.py` comprueba primero servidor, base, usuario SQL con privilegios limitados, prefijo `unit_`, dataroot separado y denegación efectiva de lectura de `close19.mdl_course`. Sólo entonces puede inicializar la base `close19_unit`. El runner vuelve a comprobar aislamiento y versión y rechaza una suite vacía. Nunca apuntar estos comandos al staging anterior.

Para WebKit en Linux, con sus dependencias multimedia:

```powershell
docker pull mcr.microsoft.com/playwright:v1.64.1-noble
python scripts/staging/close19/linux.py
```

El runner registra el digest y ejecuta el ID inmutable inspeccionado; el contenedor del navegador no recibe socket Docker ni acceso a la red de MySQL. Un relay TCP interno mantiene la URL canónica de Moodle. Las cuentas de prueba se preparan antes mediante APIs nativas y usan identidades distintas de las de Windows para evitar sobrescrituras concurrentes de entregas sintéticas. El contenedor efímero conserva sus evidencias en el directorio local montado. Esto demuestra un navegador en Linux; no representa Safari en macOS ni dispositivos físicos. WebKit/Windows presenta un error de decodificación también con los archivos fuera de Moodle: conservar el diagnóstico, no ocultarlo.

Para seguimiento real:

```powershell
python scripts/staging/close19/monitor.py --count 0 --interval 3600
```

Cada observación tiene UTC, HTTP, presencia del login, duración y hash encadenado. `--count 1` es un chequeo puntual, no prueba de disponibilidad contractual. El proceso inicial de Windows presentó una brecha superior a tres horas entre observaciones; sus registros se conservan y no acreditan continuidad. Se detuvo al sustituirlo por el servicio Docker.

Para un servicio duradero local, añadir `--monitor` al preparador. El perfil Docker `monitoring` ejecuta `monitor-service.py` con reinicio `unless-stopped`, sin claves ni montajes de datos Moodle, y acumula evidencia en `build/staging/close19/monitor/availability.jsonl`. Parar únicamente ese servicio con `docker stop asodeltol-close19-monitor-1`; no requiere reconstruir las variables del preparador ni toca los contenedores de Moodle/MySQL. Su continuidad depende de que el equipo y Docker estén encendidos; no cubre por sí mismo una ventana contractual aún no definida.

Ejecutar cargas de forma secuencial, con memoria suficiente y sin cambiar código/cachés durante la medición. Se conservan también intentos fallidos; distinguir respuestas HTTP, renderizado completo, arranque frío y perfil caliente. Las 50 sesiones usan identidades distintas, archivos completos comprobados por SHA-256 y relojes reales de reproducción. Estos resultados no certifican capacidad del VPS.

Antes de publicar evidencia, ejecutar `sanitise.py`: elimina valores de autenticación de URLs y conserva resultados y huellas anteriores. No publicar `private/`, estados de sesión ni HTML de formularios. La evidencia se genera bajo `build/staging/close19`; el resumen saneado y el índice de hashes se incorporan a `docs/anexo14/evidencia-cierre19/`. Para un log que creció después del índice, comprobar el SHA-256 de los primeros `bytes` indicados en la entrada; la huella corresponde al prefijo observado, no a futuras observaciones.

Esta batería no modifica el despliegue ni publica en producción. Durante el trabajo, `main` recibió por otra misión el agente de despliegue del PR #14 y su documentación del PR #15: el bloqueo histórico de SSH ya no describe esa rama. Se conserva esa actualización y su política de riesgo; versiones, esquema y privacidad siguen fuera de la ruta automática. Esta entrega requiere revisión de la PR y activación manual por su cambio de esquema. No fusionar ni desplegar automáticamente desde esta batería.
