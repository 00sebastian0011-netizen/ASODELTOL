---
name: asodeltol-validacion-produccion
description: Validación funcional Moodle de solo lectura en producción, con evidencias JSON sanitizadas y sin operaciones mutantes.
---

# ASODELTOL — validación de producción

Usa primero los casos de `docs/anexo14/qa/production-test-catalog.json` y el runner `scripts/anexo14/production-validation.mjs`.

## Reglas

- Solo ejecutar con una cuenta autorizada y una sesión Playwright almacenada fuera del repositorio.
- El runner admite navegación, lectura de páginas, reproducción local del elemento `video`, descargas y cálculo SHA-256.
- No sube archivos, crea/edita/elimina datos, envía mensajes, cambia permisos, SQL, shell remoto ni comandos arbitrarios. El caso de subida en producción siempre queda `BLOCKED` hasta disponer de autorización específica, aislamiento y recuperación verificable.
- No guardar contraseñas, cookies, tokens, HTML completo ni contenido descargado en la evidencia. Los resultados contienen únicamente estados, tiempos, tamaños, hashes esperados/observados y rutas sanitizadas.
- Si faltan URL, hash esperado, sesión o actor autorizado, registrar `BLOCKED`, no inferir `PASS`.
- `PASS` demuestra únicamente el recorrido y ambiente consignados; no acredita aceptación institucional ni sincronización con Git.

## Ejecución

1. Ejecuta `node scripts/anexo14/production-validation.mjs --catalog` para comprobar el catálogo.
2. Define las variables de entrada documentadas en el catálogo, usando rutas/URLs de producción y una storage state segura fuera del repo.
3. Ejecuta `node scripts/anexo14/production-validation.mjs --run --ids REQ-06-01,REQ-06-02,REQ-06-03,REQ-06-05,REQ-08-06`.
4. Consulta lotes pequeños con `node scripts/anexo14/query-production-results.mjs --file <resultado.json> --ids ...`.

La evidencia completa queda bajo `build/anexo14/production/`; no se imprime en el chat.
