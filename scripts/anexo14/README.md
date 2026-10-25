# Prueba CRUD sintética del Anexo 14

`management-smoke.php` verifica parte de los requisitos de gestión de acciones, grupos, usuarios y asignación de beneficiarios mediante las APIs nativas de Moodle. No automatiza la interfaz web ni sustituye la revisión de perfiles, campos contractuales, campos personalizados, capacidades, comunicaciones o formularios del sitio.

## Qué hace

- Crea un curso oculto y temporal en la categoría indicada, consulta y actualiza sus datos.
- Crea una cuenta local `nologin` con nombre sintético y correo `example.invalid`; consulta y actualiza campos nativos.
- Comprueba que Moodle rechace un nombre de usuario duplicado.
- Matricula la cuenta sintética, crea/consulta/actualiza un grupo y comprueba la pertenencia.
- Elimina el grupo y el curso temporal. Moodle elimina lógicamente la cuenta de prueba (tombstone), no borra físicamente su registro histórico.
- Escribe un resumen sin contraseñas bajo `moodledata/temp/anexo14-management-*.json`.
- Guarda primero un manifiesto de recuperación. Si una fase falla, no borra objetos desconocidos: informa la ubicación y permite ejecutar la limpieza por los marcadores e IDs de la ejecución.

No carga contenido académico, no modifica el curso AF1 existente, no reasigna usuarios reales, no asigna roles institucionales, no emite correo ni ejecuta pruebas de carga. Los casos de prueba no tienen inicio de sesión interactivo.

## Primero: staging local

Verifique que Docker Desktop/Engine esté activo. El modo `Validate` no escribe en Moodle. `Execute` escribe únicamente en el Moodle local aislado y en el curso sintético oculto dentro de `ASODELTOL-STAGING`.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/staging/run-anexo14-management.ps1 -Phase Validate
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/staging/run-anexo14-management.ps1 -Phase Execute
```

Si un proceso se interrumpe y conserva el manifiesto, inspeccione primero el staging y ejecute:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/staging/run-anexo14-management.ps1 -Phase Cleanup
```

## Producción: bloqueada hasta cumplir prerrequisitos

La ejecución de escritura en producción solo se admite cuando:

1. El `wwwroot` de Moodle es exactamente el host evaluado `aulas.prospectiva.edu.co`.
2. El responsable identificó una categoría aislada autorizada para crear un curso oculto temporal.
3. El responsable verificó por fuera del script un respaldo y una restauración/reversión utilizable.
4. Hay una ventana de cambio aprobada y una cuenta administrativa operativa para PHP CLI.
5. Se ejecutó y aceptó primero el recorrido completo en staging con la misma versión de Moodle.

El script exige las confirmaciones de producción, categoría, respaldo y ventana; **no verifica técnicamente que el respaldo exista**. No ejecutar mientras falte algún prerrequisito. Se rehúsa a correr producción en cualquier otro hostname y opera solamente sobre registros creados por su propio marcador.

Tras copiar de forma controlada este archivo a `<moodle-root>/scripts/anexo14/management-smoke.php`, desde el directorio raíz Moodle:

```bash
sudo -u www-data php scripts/anexo14/management-smoke.php --mode=production
sudo -u www-data php scripts/anexo14/management-smoke.php --mode=production --categoryid=ID_CATEGORIA --write --confirm-production=ASOCODEM-AF1 --backup-reference=REFERENCIA_RESPALDO --backup-verified=yes --change-window=REFERENCIA_VENTANA
```

La primera orden es una prevalidación de solo lectura; el flag `--write` es obligatorio para cualquier creación. Si la ejecución falla, conservará un manifiesto en `moodledata/temp/anexo14-management-smoke/active-run.json`. Después de revisar el host y el manifiesto, ejecutar el modo `--cleanup` con las mismas confirmaciones requeridas. La eliminación de la cuenta de prueba es lógica y deja el registro sintético de Moodle.

## Cobertura y limitaciones

Los resultados `PASS` son evidencia de las operaciones API ejercitadas en el entorno y versión consignados, no certifican el Anexo 14. `PARTIAL` indica que la prueba técnica no resuelve una regla institucional (por ejemplo, los campos de documento/caracterización o el estado contractual del grupo). No actualice el Excel sin que Interventoría acepte el resultado y su evidencia.

Quedan fuera de esta prueba, entre otros: campos personalizados de identidad, duplicidad de documentos, roles/SENA y separación de capacidades, acciones de formador/beneficiario en navegador, horarios, contenidos/licencias, multimedia, reportes del Anexo 12 y rendimiento. La carga se limita a staging representativo; nunca se ejecuta en producción.
