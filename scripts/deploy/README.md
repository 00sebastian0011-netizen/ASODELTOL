# Despliegue inmediato y controlado

Producción recibe eventos `push` de `main` mediante un webhook GitHub con HMAC SHA-256. El receptor persistente escucha únicamente en `127.0.0.1`, se ejecuta como el usuario sin privilegios `user` y acepta exclusivamente el repositorio, rama y SHA autorizados.

`deploy-policy.json` separa los cambios por componente:

- **fast:** CSS/SCSS, AMD compilado, Mustache, imágenes, fuentes y traducciones;
- **standard:** PHP sin esquema, versión ni Privacy API;
- **critical:** esquema, `version.php`, privacidad o infraestructura. No se publican automáticamente.

El agente compara cada archivo afectado con el manifiesto de producción. Una modificación directa de Hostinger queda protegida como divergencia: no se sobrescribe y no impide desplegar otro componente independiente. Las publicaciones aceptadas se preparan junto al destino, conservan una copia anterior, sustituyen el componente, purgan la caché y verifican hashes y el login público. Un fallo restaura inmediatamente la copia anterior.

GitHub no almacena credenciales SSH de producción. `.github/workflows/deploy-vps.yml` registra la clasificación fuera de la ruta crítica; la suite de calidad continúa ejecutándose en paralelo.

Archivos de instalación del servicio: `scripts/deploy/vps/`. Los secretos, claves, estado, respaldos y manifiestos activos permanecen exclusivamente en el VPS.
