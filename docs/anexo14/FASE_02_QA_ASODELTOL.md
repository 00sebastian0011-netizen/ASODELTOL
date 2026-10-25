# ASODELTOL — Fase 02: QA local de exportaciones y plugin

**Fecha:** 2026-10-24
**Plataforma objetivo de la aceptación final:** `https://aulaasodeltol.online/`
**Entorno de estas pruebas:** Moodle 5.3 local `http://127.0.0.1:8053`, **no producción**.

## Evidencia confirmada antes de ejecutar este lote

Fuente: `diagnostico_staging_asodeltol_20261024_162034.txt` recibido el 24 de octubre.

- Rama `moodle-completo-2026-10-24`, commit base `bcad29afa`; 11 cambios preparados en Git que se deben preservar.
- `npm run quality`: catálogo válido, **32/32** pruebas aprobadas.
- Docker responde; los contenedores `asodeltol-moodle53-webserver-1` y `asodeltol-moodle53-database-1` están activos; MySQL 8.4 saludable; PHP **8.4.27**.
- Archivo `vendor/bin/phpunit` disponible, **sin resultado de ejecución** de `manager_test.php`.
- Catálogo: cinco unidades, 50 componentes enumerados, cuatro recursos generales, siete grupos, 350 cupos y 80 horas.
- Bloqueos académicos: contenidos y criterios de finalización pendientes; nota de aprobación, tiempo por unidad y discrepancias de totales sin resolver.
- `scripts/anexo14/management-smoke.php` fue diseñado para **ASOCODEM-AF1** y restringe producción a **otro dominio**. No usarlo en `aulaasodeltol.online`.

## Trabajo preparado en este lote

**Scripts nuevos, sin alterar archivos versionados anteriores:**

- `scripts/staging/anexo14-qa-context.php`: consulta Moodle, estado oculto de categoría/curso, plugin instalado, sesiones del curso y preparación segura de PHPUnit, exclusivamente localhost.
- `scripts/staging/run-anexo14-qa.ps1`: valida rama, proyecto Docker, PHP lint de plugin, versión de Moodle, acceso de invitado, login sintético local, descarga auténtica CSV/XLSX y estructura ZIP/OOXML. Si la configuración de PHPUnit **ya** dispone de prefijo y dataroot separados y `phpunit.xml`, ejecuta `manager_test.php`; si no, lo declara **BLOCKED** sin lanzar `init.php` ni tocar bases de datos.

**Pruebas HTTP** (reales tras la ejecución en el equipo del usuario):

- `HTTP-LOCAL`: la página del staging local responde 200 y su identidad coincide con el staging ASODELTOL.
- `HTTP-GUEST-DENIED`: un invitado no consigue descargar el informe privado.
- `HTTP-LOGIN`: se envía inicio de sesión con la cuenta sintética local, contraseña leída de archivo ignorado por Git y nunca reflejada en logs. La eficacia del inicio de sesión se confirma con la descarga.
- `HTTP-XLSX`: descarga un archivo que debe tener estructura OOXML válida.
- `HTTP-CSV`: exige respuesta de texto compatible con un CSV de sesiones, sin páginas HTML de error/login.

Un archivo descargado con estructura válida **no certifica** que los registros, filtros, asistencia efectiva o columnas del Anexo 12 satisfagan las condiciones contractuales. Si el curso no tiene sesiones para evaluar, se registra **PARTIAL**.

## Alcance de las decisiones pendientes

- REQ-24-03 y REQ-24-04: estas pruebas sólo añaden evidencia técnica de la capacidad de exportación. Permanecen pendientes la conciliación de filas/datos con fuentes aprobadas, pruebas de separación de grupos/perfiles y la aprobación institucional de formatos.
- RF-007 y RF-008: progreso en el soporte de exportación, sin certificar disponibilidad sincrónica ni minutos reconocidos.
- DP-009, DP-010, DP-012 y DP-013: asistencia, tiempo, columnas, privacidad y tratamiento de datos todavía requieren acuerdo institucional.
- PHPUnit requiere base de prueba prefijada y dataroot separados; su inicialización será una intervención propia, revisada, nunca una ejecución automática del lote de QA.

## Cómo ejecutar

Situar ambos scripts en `scripts/staging/` del repositorio. Desde Git Bash:

```bash
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/staging/run-anexo14-qa.ps1 -Phase All
```

Los informes se guardan bajo `build/staging/anexo14-qa/` y contienen controles `PASS`, `FAIL`, `PARTIAL` o `BLOCKED`. `exit 2` indica bloqueos o cobertura parcial, no necesariamente un fallo técnico; `exit 1` indica al menos un fallo.

No ejecutar scripts para producción, no generar datos reales, no publicar credenciales y no hacer `git push` como parte de esta fase.

## Siguiente decisión técnica

Si PHPUnit sigue bloqueado, configurar **una base de datos y dataroot de pruebas aislados**, previa verificación de colisiones, con comandos explícitos y reversibles. Después verificar `manager_test.php` y preparar pruebas de interfaces por perfil en el staging, dejando creación de roles institucionales del VPS para el final.
