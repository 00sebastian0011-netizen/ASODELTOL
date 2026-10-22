# Scripts locales

Estos scripts preparan calidad y artefactos; ninguno se conecta al VPS, instala plugins, ejecuta `push` o despliega.

- `quality/validate-repository.ps1`: comprueba estructura, patrones de archivos prohibidos, reglas básicas de `.gitignore` e indicadores de copia accidental del núcleo.
- `catalog/validate-catalog.mjs`: valida conteos, códigos, orden, tipos, duraciones, referencias, grupos y la matriz de acceso AF8.
- `catalog/build-provisioning-bundle.mjs`: genera CSV oficiales de curso/cohortes, plano estructural y manifiesto en `build/`; no se conecta a Moodle y nunca sobrescribe una salida.
- `release/package-plugins.ps1`: crea un directorio de release único, un ZIP por plugin que ya tenga `version.php`, checksums SHA-256 y manifiesto JSON.
- `deploy/verify-release.ps1`: verifica localmente el manifiesto, los checksums y la estructura de los ZIP antes de que un operador considere el despliegue.

La futura automatización de despliegue debe diseñarse después del inventario del entorno y requerir aprobación explícita. No se guardarán hosts, usuarios, rutas de producción ni credenciales en estos scripts.

Las herramientas del catálogo requieren Node.js 20 o posterior y no tienen dependencias externas. Ejecución recomendada:

```powershell
npm run catalog:validate
npm test
```
