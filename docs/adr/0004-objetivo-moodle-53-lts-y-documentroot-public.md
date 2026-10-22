# ADR-0004 — Objetivo Moodle 5.3 LTS y reestructuración de DocumentRoot a /public

- Estado: Aceptada
- Fecha: 2026-10-21
- Responsables: Equipo técnico ASODELTOL
- Requisitos/decisiones: RNF-001, DP-015

## Contexto

El servidor actual ejecuta Moodle 5.0.10+ sobre Ubuntu 24.04 y CloudPanel. La rama Moodle 5.0 finalizó su ciclo de soporte de seguridad el **5 de octubre de 2026**, fecha en la que se publicó oficialmente **Moodle 5.3 LTS**, con ventana de soporte de seguridad garantizada por 36 meses (hasta **octubre de 2029**).

Mantener una versión sin parches de seguridad en un sistema que tratará datos de 350 participantes viola las obligaciones de confidencialidad y seguridad de la Ley 1581 de 2012 de Colombia. Además, programar extensiones sobre una rama obsoleta genera deuda técnica inmediata al interactuar con APIs que evolucionaron en 5.1, 5.2 y 5.3.

Asimismo, **Moodle 5.1 introdujo una reestructuración fundamental del árbol web**: para mitigar la exposición accidental de archivos sensibles, el núcleo de Moodle ya no debe ser la raíz servida por el servidor web. En su lugar, el código raíz aloja `config.php`, `lib/`, `vendor/` y directorios internos, mientras que el servidor web (NGINX/Apache) debe apuntar su `DocumentRoot` (o directiva `root`) exclusivamente al subdirectorio `/public`. Si el servidor web no se actualiza para apuntar a `/public`, Moodle 5.1+ detiene la ejecución con un error bloqueante de seguridad (*"The Moodle root directory must not be publicly accessible"*).

## Opciones consideradas

1. Continuar el desarrollo de plugins sobre Moodle 5.0 y posponer la actualización a una fase posterior a la entrega académica.
2. Actualizar directamente en producción a Moodle 5.3 sin entorno de pruebas previo.
3. Establecer formalmente Moodle 5.3 LTS como versión objetivo, congelar el desarrollo de plugins funcionales hasta que la actualización esté validada en un entorno de staging idéntico y adaptar la directiva `root` de NGINX en CloudPanel al nuevo esquema `/public`.

## Decisión

Adoptar la **Opción 3**:

1. **Objetivo único de versión:** Moodle 5.3 LTS con PHP 8.4 y base de datos relacional compatible (MariaDB $\ge$ 11.4 o MySQL $\ge$ 8.4).
2. **Reconfiguración web:** En la actualización de staging y producción, la configuración del sitio en CloudPanel/NGINX cambiará su raíz web para apuntar a:
   `/home/user/htdocs/srv2085040.hstgr.cloud/public`
   mientras que en `config.php`:
   `$CFG->dirroot = '/home/user/htdocs/srv2085040.hstgr.cloud';`
3. **Orden de trabajo estricto:** No se implementarán plugins funcionales hasta que el entorno de desarrollo y staging esté validado en Moodle 5.3 LTS.

## Consecuencias

- Se garantiza soporte de parches de seguridad hasta octubre de 2029.
- El servidor web protege nativamente `config.php` y librerías internas contra lectura directa por errores de NGINX.
- Requiere verificar el motor de base de datos actual en el VPS; si la base de datos es MariaDB 10.11 (versión previa), deberá actualizarse a MariaDB 11.4 antes del upgrade de Moodle.
- El procedimiento de actualización debe contemplar la parada de servicio, respaldo frío, ensayo en staging y la modificación de la plantilla del sitio en CloudPanel.

## Verificación

1. Comprobación de que la interfaz de administración y `php admin/cli/upgrade.php` reporten versión 5.3+ (Build 202610xx o superior).
2. Inspección de NGINX comprobando que peticiones directas a `/config.php` o `/lib/setup.php` devuelvan 404 (al no estar dentro de `/public`).
3. Verificación de que el instalador y la verificación de entorno de Moodle 5.3 marquen todos los componentes en verde.
