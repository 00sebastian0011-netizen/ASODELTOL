# Pruebas locales de ASODELTOL — Anexo 14

Ejecutar desde la raíz del repositorio, rama `moodle-completo-2026-10-24`.
No requieren producción, VPS ni cuentas definitivas. Conservar las evidencias anteriores.

```powershell
python scripts/anexo14/build-compliance-matrix.py
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging/check-hidden-course.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging/run-anexo14-qa.ps1 -Phase All
python scripts/anexo14/check-export-content.py
node scripts/staging/check-staff-browser.mjs
node scripts/staging/check-staff-browser.mjs --browser=chrome
node scripts/staging/check-staff-browser.mjs --help-only --learner
node scripts/staging/check-staff-browser.mjs --files-only
python scripts/staging/retest-isolated-phpunit.py
python scripts/staging/check-additional-reports.py
python scripts/staging/check-native-course.py
```

La generación de la matriz usa `openpyxl` ya disponible, los tres documentos en Descargas y hashes SHA256. No altera los originales. La salida está en `build/staging/anexo14-compliance/` y `docs/anexo14/MATRIZ_ASODELTOL_82.md`.

El QA general devuelve código 2 cuando PHPUnit está bloqueado en la instalación habitual. Eso no es un PASS de PHPUnit. La ejecución aislada es independiente y guarda su propio resumen.

El runner aislado crea una base y una cuenta **nuevas por ejecución**, con permisos exclusivamente sobre esa base. No modifica el usuario MySQL habitual, ni `config.php` original, ni el dataroot habitual. Prueba que el acceso a `moodle.mdl_course` es rechazado antes de ejecutar `init.php`. También valida los grants y que la base nueva esté vacía. El código se copia a un directorio privado; `test_` se usa únicamente allí. Composer no se actualiza.

La configuración privada contiene una contraseña aleatoria, queda dentro del contenedor, con propietario `www-data` y modo 0600. No se incluye en ZIP ni se imprime. La copia ZIP excluye el `config.php` original. Las bases, cuentas, archivos de pruebas y evidencias se conservan; este runner no implementa borrados. El dataroot y la copia privada son temporales del contenedor y no sobreviven a su recreación. Los informes se conservan en el host bajo `build/staging/anexo14-isolated/`.

En Windows se usan rutas largas y lecturas paralelas por lotes para empaquetar el código; no se altera el árbol original. La suite trata notices y deprecaciones PHPUnit como fallos y rechaza una suite vacía. La suite ampliada completó 42 pruebas y 150 aserciones sin incidencias. El JUnit y el resumen fechado son la fuente del resultado actual.

Los tests de Moodle pueden limpiar sus tablas y entidades sintéticas **dentro de la base aislada** como parte del reinicio de fixtures. La cuenta MySQL carece de permiso sobre la base de staging y sus tablas `mdl_`.

`anexo14-qa-context.php` no habilita PHPUnit por la mera presencia de un prefijo y XML: requiere el runner que verifica grants. El curso y la categoría se comprueban ocultos antes y después. Para repetir PHPUnit usar `retest-isolated-phpunit.py`: verifica el runtime inicializado, grants exclusivos, rechazo de acceso a staging y rutas privadas; no ejecuta init. `run-isolated-phpunit.py` queda reservado para crear un runtime nuevo si no existe el anterior.

La comprobación de exports abre CSV y XLSX, compara todas las celdas, comprueba ambos grupos sintéticos y analiza las marcas UTC. No acredita el Anexo 12 ni presencia efectiva.

La prueba de navegador utiliza Edge o Chrome headless local, inicia sesión por el formulario real y comprueba listado de sesiones, reportes, citas y fechas guardadas del formulario de sesión en escritorio, tablet y móvil emulados. `--help-only --learner` prueba el enlace visible y clic real de ayuda como beneficiario. Guarda capturas y errores HTTP/JavaScript. No equivale a probar Firefox, Safari, hardware móvil u otros sistemas operativos. El perfil privado se conserva local y está excluido de Git porque puede contener cookies.

`--files-only` usa el beneficiario G01 existente para cargar pequeños PNG sintéticos con el selector nativo de archivos privados, guardar, recargar y descargar. Compara SHA256 de los bytes y del clic nativo de descarga. Conserva los archivos anteriores y nuevos; no necesita mostrar el curso ni crear cuentas. Usa selectores por nombre para el formulario dinámico, cuyos IDs Moodle son aleatorios.

`check-native-course.py` usa solo identidades sintéticas existentes. Conserva cinco recursos de prueba, una página, dos entregas/calificaciones sintéticas del piloto M07 y un hilo G02. No sobrescribe notas existentes. Comprueba bytes SHA256 descargados, denegación de G02 a recursos G01, foro separado, notas propias, capacitador de su grupo, Interventoría de lectura y porcentajes nativos. Necesita una breve ventana de visibilidad del curso local; usa `finally` para ocultar curso y categoría, y verifica el estado final. No ejecutar junto a otro script que cambie visibilidad.

`check-office-hours.py` crea o reconoce una única cita sintética; `check-native-messaging.php` conserva dos mensajes sintéticos con validación bidireccional. El contexto de mensajería restaura visibilidad mediante `finally` y manejador de cierre. No son horario oficial ni conversaciones institucionales.

`check-concurrent-local.py` prueba 50 sesiones independientes de una sola identidad sintética existente. No acredita 50 beneficiarios distintos. El primer lote tuvo dos errores HTTP en curso; la repetición de esa ruta no tuvo errores. Mantener ambas evidencias y no cerrar el criterio de rendimiento completo.
