# Despliegue autorizado

El único despliegue automático permitido es `.github/workflows/deploy-vps.yml`, activado por `main` cuando cambian el plugin autorizado o el propio workflow. Usa el Environment `production`, concurrencia sin cancelación y SSH con `known_hosts` verificado.

Secretos requeridos en GitHub (Environment `production`): `VPS_HOST`, `VPS_PORT`, `VPS_USER`, `VPS_SSH_KEY` y `VPS_KNOWN_HOSTS`. Sus valores nunca se guardan en el repositorio ni se imprimen. La cuenta debe limitarse a la instalación indicada.

El workflow empaqueta y verifica SHA-256, respalda el plugin activo, copia solo `plugins/local/asodeltolstaff/` a `public/local/asodeltolstaff`, ejecuta `upgrade.php` y purga cachés. No modifica `config.php`, `moodledata`, la base completa, `theme/rsmax`, logos, favicon, cursos, usuarios, matrículas ni calificaciones. No usa `git pull`, `git reset`, `git clean` ni `rsync --delete`.

Sin los secretos y una ejecución real exitosa, el despliegue automático no se considera operativo. La primera vinculación debe validar la clave pública del servidor y probar el workflow con un cambio del plugin en `main`.
