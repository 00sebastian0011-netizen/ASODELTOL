# ASODELTOL Moodle 5.3 dependency overlay 2026.10.22.1

Overlay reproducible para una copia limpia del commit oficial Moodle
`aa930e26fbdd08dd8ba70f53298838a8d306ee57` (`MOODLE_503_STABLE`). No contiene
`vendor`, `config.php`, datos, respaldos, evidencias ni secretos.

## Construccion aislada

1. Obtener una copia limpia del commit oficial indicado.
2. Verificar los SHA-256 de este directorio contra `MANIFEST.sha256`.
3. Sustituir en la copia nueva solamente `composer.json` y `composer.lock` por
   los archivos de este overlay.
4. Usar PHP 8.4 y Composer 2.10.3 con el SHA-256 indicado en el manifiesto.
5. Construir `vendor` desde cero, sin modificar el directorio activo:

   ```text
   php composer.phar install --no-dev --prefer-dist --classmap-authoritative --no-interaction
   php composer.phar validate --no-check-publish
   php composer.phar audit --locked
   php composer.phar check-platform-reqs --no-dev
   ```

6. Ejecutar los controles de Moodle y ASODELTOL sobre la nueva raiz antes de
   cualquier cambio atomico de release.

`runtime-check.php` es una prueba para la copia aislada. Emite exclusivamente
versiones, conteos y resultados booleanos; no forma parte del webroot ni del
paquete de produccion.

## Reversion

La actualizacion no cambia el esquema de base de datos. La reversion prevista
es retirar la nueva raiz completa y reactivar de forma atomica la raiz oficial
anterior, conservando la misma base y `moodledata`. Nunca se restaura `vendor`
archivo por archivo.
