# QA productivo reutilizable

El catálogo autorizado está en `production-test-catalog.json`. El runner solo permite lecturas autenticadas, descargas con SHA-256, reproducción de un elemento `video`, navegación, enlaces, inspección de actividades/bloques y negativas de permisos.

## Preflight seguro

```powershell
npm.cmd run qa:production:catalog
node scripts/anexo14/production-validation.mjs --run --ids=TC-FILE-DOWNLOAD-SHA256,TC-FILE-UPLOAD-READONLY,TC-VIDEO-PLAYBACK,TC-NAVIGATION-MODULES,TC-ACTIVITIES-BLOCKS --out=production-preflight.json
```

El comando anterior no ejecuta una sesión sin `ASODELTOL_PROD_BASE_URL=https://aulas.prospectiva.edu.co` y una `ASODELTOL_PROD_STORAGE_STATE` fuera del repositorio. El hash esperado y las rutas se suministran por variables de entorno, no se versionan.

Entradas JSON: `ASODELTOL_PROD_MODULE_PATHS`, `ASODELTOL_PROD_QUICKLINK_URLS` (exactamente seis), `ASODELTOL_PROD_ACTIVITY_PATHS`, `ASODELTOL_PROD_BLOCK_SELECTORS`, `ASODELTOL_PROD_NEGATIVE_PATHS` y `ASODELTOL_PROD_RESPONSIVE_PATHS`.

Consulta compacta, máximo cinco casos por lote:

```powershell
node scripts/anexo14/query-production-results.mjs --file=build/anexo14/production/production-preflight.json --ids=REQ-06-02,REQ-06-03 --limit=5
```

La subida en producción se registra siempre como `BLOCKED` hasta que exista autorización específica, aislamiento y recuperación verificable.
