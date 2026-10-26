# Auditor CLI Anexo 14 / AF8

## Ejecución segura

Ejecutar con el usuario de la instalación Moodle, desde una copia probada del
repositorio o el directorio de Moodle:

```bash
php scripts/anexo14/audit-vps.php \
  --matrix=docs/anexo14/MATRIZ_ASODELTOL_82.md \
  --output-dir=/var/lib/asodeltol-audit
```

Primero validar el contrato sin cargar Moodle:

```bash
php scripts/anexo14/audit-vps.php --dry-run \
  --matrix=docs/anexo14/MATRIZ_ASODELTOL_82.md \
  --output-dir=/tmp/asodeltol-audit
```

El auditor solo lee `config.php` para cargar Moodle, consulta APIs/DBD de
lectura nativas y produce JSON/CSV sanitizados. No imprime configuración,
credenciales, nombres de usuarios, matrículas, calificaciones ni registros de
seguimiento individuales. No acepta SQL, comandos, rutas de consulta ni
parámetros de escritura.

`PASS` solo significa que la comprobación concreta se observó. `MANUAL` exige
revisión funcional o institucional; `BLOCKED` indica que el entorno o insumo no
permite comprobarlo; `FAIL` indica una contradicción o error técnico. No se
convierten estados históricos del Excel en PASS productivo.

La herramienta verifica versión Moodle, plugins, permisos observables por el
usuario CLI, curso AF8 por `idnumber`, tablas de seguimiento/reportes y emite
los 82 requisitos. La comprobación de permisos corresponde al usuario CLI, no
impersona PHP-FPM. La creación de cursos, usuarios, actividades, archivos,
calificaciones o reportes queda fuera del alcance.

Revisar los archivos generados y eliminarlos según la política de retención;
preservar únicamente evidencia sanitizada. Ejecutar con respaldo y rollback del
artefacto, nunca con `chmod -R 777`, `chown -R` ni cambios de producción.
