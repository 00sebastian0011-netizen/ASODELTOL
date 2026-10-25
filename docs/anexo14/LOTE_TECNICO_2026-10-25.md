# Lote técnico ASODELTOL — 25 de octubre de 2026

Este lote agrega cobertura local verificable y mantiene separados desarrollo, validación técnica y aceptación institucional. No convierte pruebas parciales en requisitos integralmente cumplidos.

## Cambios

- Plugin `local_asodeltolstaff` actualizado a 0.4.4 (`2026102504`). Al publicar una actividad de entrega, Moodle notifica únicamente a beneficiarios activos del grupo autorizado. La actividad exige fecha futura, no establece nota aprobatoria y conserva el alcance por grupos separados.
- Los formularios nativos de cursos y grupos reciben campos administrativos opcionales mediante Custom Fields de Moodle. La disponibilidad de grupo es metadato seleccionado explícitamente; los identificadores de rol son descriptivos y no conceden capacidades.
- Corregida la llamada Moodle 5.3 para mostrar una sección: la API recibe el registro de sección. El test comprueba que abrirla para un grupo conserva las restricciones previas de fecha.
- La matriz de perfiles agrega SENA como propuesta de solo lectura pendiente de aprobación. No asigna roles ni concede capacidades. El validador comprueba 5 perfiles y 12 escenarios negativos.
- Se añadió un verificador de navegación local autenticada. No se ejecutó contra el Moodle compartido porque su plugin instalado todavía reporta `2026102401`, distinto del candidato; sus resultados no representarían este lote.

## Verificación

- PHPUnit: **55 pruebas, 224 aserciones PASS**, sin fallos ni warnings, en Moodle 5.3+, PHP 8.4.27 y MySQL 8.4.13.
- Antes de ejecutarlo se verificaron permisos de la cuenta de pruebas, base de datos privada, `dataroot` PHPUnit separado y denegación explícita de lectura a `moodle.mdl_course`. No se ejecutó `init.php`.
- Node: **33/33 PASS** para el catálogo, pruebas de matriz/perfiles y puerta de seguridad de despliegue.
- PHP lint y `git diff --check`: PASS.
- La comprobación posterior confirmó que el curso AF8 y su categoría permanecen ocultos. El Moodle compartido sigue mostrando la versión instalada `2026102401`; este código no se instaló en staging ni se desplegó al VPS.

Evidencia PHPUnit local: `build/staging/anexo14-isolated/20261025134934-summary.json`, JUnit y salida asociada. El resumen no contiene contraseñas. Los artefactos bajo `build/staging` son locales e ignorados por Git.

## Matriz y límites

Se agrega evidencia técnica parcial a REQ-14-02, REQ-19-03, REQ-19-05 y REQ-20-02, sin cambiar su estado integral. Los conteos de la trazabilidad fuente permanecen **20 verificados localmente, 19 pendientes, 43 bloqueados; 0 aceptaciones formales**. El XLSX original de la matriz no está en este worktree; se preservan IDs, criterios y hash de fuente de la trazabilidad existente. No se modificó ni mezcló el anexo ASOCODEM-AF1.

Quedan fuera de este lote el contenido académico definitivo, decisiones de calendario y evaluación, Anexo 12, modalidad/proveedor, información aprobada de caracterización, identidades y roles institucionales, acceso de VPS y aceptación oficial. Las pruebas sintéticas no sustituyen esos insumos.
