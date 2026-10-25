# Cierre del bloqueo de despliegue — 25 de octubre de 2026

El bloqueo preventivo del workflow anterior fue sustituido por el agente persistente y firmado incorporado mediante el PR #14. El agente no ejecuta instrucciones recibidas en el webhook: acepta únicamente eventos HMAC SHA-256 del repositorio `00sebastian0011-netizen/ASODELTOL`, la referencia `refs/heads/main` y un SHA hexadecimal que debe pertenecer al `main` remoto.

Controles comprobados:

- servicio ejecutado como `user`, sin privilegios administrativos generales y con endurecimiento de systemd;
- clave GitHub de solo lectura, host SSH verificado y secretos conservados fuera del repositorio;
- clasificación automática `fast`, `standard` y `critical` mediante `scripts/deploy/deploy-policy.json`;
- publicación incremental por componente, manifiesto de hashes, protección de cambios directos en Hostinger y bloqueo solo del componente en conflicto;
- preparación contigua al destino, copia anterior recuperable, comprobación posterior de hashes y reversión automática ante fallo;
- exclusión de esquema, `version.php`, Privacy API, autenticación, infraestructura, pruebas, base de datos, `moodledata` y `config.php` principal de la ruta automática;
- endpoint externo rechazando GET (403) y POST sin firma (401); ping firmado de GitHub aceptado (200);
- evento real de `main` aceptado (202), procesado sin cambios sobre Moodle en 4.142 segundos de extremo a extremo.

El ensayo aislado en el VPS registró CSS 1.731 s, JavaScript 0.177 s, Mustache 0.177 s y PHP sin migración 0.229 s. Las pruebas negativas de divergencia y reversión fueron aprobadas. Al activar el servicio, `theme_rsmax` conservó igualdad exacta con GitHub y las diferencias previas de `local_asodeltolstaff` quedaron protegidas; no se modificaron el tema, el plugin, la base de datos, los cursos ni `moodledata`.

Los cambios clasificados como críticos continúan requiriendo intervención reforzada, respaldo acorde al riesgo y activación manual. El workflow de GitHub registra la clasificación y ejecuta calidad en paralelo, pero no almacena acceso SSH de producción.
