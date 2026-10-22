# Calidad, despliegue, respaldo y reversión

## 1. Entornos

| Entorno | Datos | Finalidad | Cambios permitidos |
|---|---|---|---|
| Desarrollo local | Exclusivamente sintéticos | Código, análisis y pruebas rápidas | Por el desarrollador en su rama. |
| CI efímero | Sintéticos generados | Calidad repetible y matriz de compatibilidad | Sólo pipeline aprobado. |
| Staging | Sintéticos o subconjunto formalmente desidentificado | Integración, carga, restauración y aceptación | Mediante paquetes versionados. |
| Producción | Reales | Servicio | Sólo cambio aprobado, ventana, respaldo y reversión. |

Nunca ejecutar PHPUnit o Behat contra la base o `moodledata` de producción. Moodle usa bases y dataroot alternativos para esas pruebas.

## 2. Estrategia de pruebas

### Estáticas

- Validación de estructura y archivos prohibidos con `scripts/quality/validate-repository.ps1`.
- PHP lint para todo archivo PHP.
- Moodle Coding Style mediante PHPCS/Moodle Code Checker.
- Detección de secretos, dependencias vulnerables y patrones inseguros.
- Validación de XMLDB, idiomas, capacidades y metadatos de plugin.

### PHPUnit

Moodle 5.0 usa PHPUnit 11. Casos prioritarios:

- cálculo puro de días hábiles, límites y zonas horarias;
- persistencia y autorización de excepciones;
- eventos anexables y correlación de correcciones;
- separación entre plan, declaración, evidencia y validación del capacitador;
- consultas de reporte, filtros, paginación y alcance por grupo;
- Privacy API: contextos, exportación y eliminación;
- tareas programadas, idempotencia, bloqueos y reintentos.

Las pruebas específicas deben vivir en `tests/` de cada plugin.

### Behat

- Progresión de un estudiante aprobado y otro no aprobado.
- Vencimiento exacto y mensaje accesible.
- Solicitud/aprobación/revocación de excepción.
- Capacitador limitado a su grupo.
- Interventoría consulta sin poder modificar.
- Registro y corrección de asistencia con fuente visible.
- Registro/validación de disponibilidad sin usar login como prueba.
- Exportación XLSX autorizada y denegación a perfiles no autorizados.
- Fallo o indisponibilidad del proveedor externo.

### No funcionales

- Carga con población de 350 y concurrencia definida, no asumida.
- Exportaciones máximas, ejecución de cron y colas de tareas.
- Accesibilidad de teclado, foco, etiquetas y lector de pantalla.
- Compatibilidad con navegadores soportados por Moodle.
- Restauración de backups y reversión completa en staging.

## 3. Revisión de código

Todo cambio requiere:

1. alcance y archivos asignados sin edición paralela;
2. diff pequeño y trazable a requisito/decisión;
3. autor ejecuta controles y adjunta resultados sin datos sensibles;
4. revisión cruzada de lógica, seguridad, privacidad, rendimiento y pruebas;
5. resolución por el autor; el revisor confirma;
6. integración por el responsable, sin `push` automatizado desde una tarea local.

Cambios de esquema, permisos, proveedor, retención o contrato entre plugins requieren ADR. No aprobar con pruebas omitidas sin riesgo, responsable y fecha de resolución documentados.

## 4. Artefactos y versionado

- Un paquete ZIP por plugin, con el directorio raíz correcto.
- Versión de componente coherente con `version.php` y compatibilidad declarada.
- Manifiesto de release con commit, componentes, versiones y SHA-256.
- Dependencias de terceros por nombre, versión, origen, licencia y checksum.
- Artefactos promovidos de staging a producción: no reconstruir entre entornos.
- `build/` es local/efímero y no se versiona.

`scripts/release/package-plugins.ps1` sólo empaqueta directorios con `version.php`; no instala ni publica.

## 5. Diagnóstico no destructivo del servidor (Guía para el operador)

Para completar el inventario técnico ([DP-016](DECISIONES_PENDIENTES.md)) sin alterar el servidor ni exponer credenciales, el operador autorizado debe ejecutar exclusivamente los siguientes comandos de solo lectura vía terminal en el VPS:

### 1. Motor y versión de base de datos
```bash
# Versión del cliente/servidor MySQL o MariaDB
mariadb --version 2>/dev/null || mysql --version

# Consulta directa de versión y variables sin exponer contraseñas en historial
# (Ejecutar interactivo solicitando contraseña en prompt)
mysql -u root -p -e "SELECT VERSION() AS db_version, @@version_comment AS db_comment;"
```
*Criterio de pase Moodle 5.3:* MariaDB $\ge$ 11.4.0 o MySQL $\ge$ 8.4.0.

### 2. Versión de PHP-FPM y alineación con PHP CLI
```bash
# Versión de CLI comprobada
php -v

# Versión y estado del servicio PHP-FPM activo en Ubuntu/CloudPanel
systemctl list-units --type=service | grep -E 'php.*fpm'
php-fpm8.4 -v 2>/dev/null || /opt/cloudpanel/bin/php-fpm -v 2>/dev/null
```
*Criterio de pase:* PHP CLI y PHP-FPM deben compartir exactamente la versión 8.4.x.

### 3. Extensiones PHP obligatorias y `max_input_vars`
```bash
# Comprobación de extensiones críticas para Moodle 5.3
php -m | grep -E '^(sodium|intl|opcache|zip|curl|mbstring|xml|gd|mysqli)$'

# max_input_vars en PHP CLI
php -i | grep 'max_input_vars'

# max_input_vars en PHP-FPM (inspeccionar el pool del sitio)
grep -rn 'max_input_vars' /etc/php/8.4/fpm/ /etc/php/8.4/cli/ 2>/dev/null
```
*Criterio de pase:* `sodium` presente; `max_input_vars >= 5000` en CLI y FPM.

### 4. Configuración del sitio web en NGINX y CloudPanel
```bash
# Ubicación del archivo de configuración del sitio
ls -la /etc/nginx/sites-enabled/

# Inspeccionar directiva root (DocumentRoot) y fastcgi pass
grep -E '^\s*(root|fastcgi_pass|server_name)' /etc/nginx/sites-enabled/*
```
*Criterio para Moodle 5.3:* En Moodle 5.0 apuntaba a `/home/user/htdocs/srv2085040.hstgr.cloud`. En 5.3 **debe apuntar a `/public`**.

### 5. Directorios de Moodle, `moodledata` y permisos
```bash
# Validar estructura del sitio y permisos
ls -ld /home/user/htdocs/srv2085040.hstgr.cloud
ls -la /home/user/htdocs/srv2085040.hstgr.cloud/config.php

# Localizar moodledata (fuera del DocumentRoot web)
grep 'dataroot' /home/user/htdocs/srv2085040.hstgr.cloud/config.php
```
*Regla de seguridad:* **NUNCA** imprimir ni capturar `$CFG->dbpass`, contraseñas ni tokens.

### 6. Inventario de plugins no pertenecientes al core
```bash
# Listar carpetas de plugins locales y reportes actuales
ls -la /home/user/htdocs/srv2085040.hstgr.cloud/local/
ls -la /home/user/htdocs/srv2085040.hstgr.cloud/report/
ls -la /home/user/htdocs/srv2085040.hstgr.cloud/availability/condition/

# Verificación oficial vía CLI de Moodle (como usuario user)
sudo -u user php /home/user/htdocs/srv2085040.hstgr.cloud/admin/cli/plugins_check.php
```

### 7. Estado de servicios del sistema
```bash
systemctl is-active nginx mariadb mysql cron php8.4-fpm
```

---

## 6. Procedimiento técnico de migración: Moodle 5.0 → 5.3 LTS

Este procedimiento aplica tanto al ensayo obligatorio en Staging como a la posterior ventana de mantenimiento en Producción.

### Paso 1: Poner sitio en mantenimiento y detener CRON
```bash
# 1. Mantenimiento en Moodle
sudo -u user php /home/user/htdocs/srv2085040.hstgr.cloud/admin/cli/maintenance.php --enable

# 2. Pausar temporalmente el cron del usuario user
crontab -u user -l > /home/user/crontab.bak
crontab -u user -r
```

### Paso 2: Respaldo frío integral y coherente
*Advertencia de infraestructura:* El snapshot de Hostinger vence el 22 de octubre de 2026. **Un snapshot no sustituye un respaldo consistente de base de datos**.
```bash
# 1. Respaldo consistente de base de datos (con transacciones bloqueadas)
mysqldump --single-transaction --quick --hex-blob -u [dbuser] -p [dbname] > /home/user/backups/moodle_pre_53_$(date +%F).sql

# 2. Respaldo de moodledata
tar -czf /home/user/backups/moodledata_pre_53_$(date +%F).tar.gz -C /home/user moodledata

# 3. Respaldo del código actual y config.php
tar -czf /home/user/backups/moodle_code_pre_53_$(date +%F).tar.gz -C /home/user/htdocs srv2085040.hstgr.cloud
```

### Paso 3: Clonación y prueba en Staging aislado
1. Crear en CloudPanel un sitio de pruebas: `staging.srv2085040.hstgr.cloud`.
2. Restaurar la base de datos en una base independiente de staging (`moodle_staging`).
3. Restaurar `moodledata` en `/home/user/moodledata_staging`.
4. En el staging, descargar el código oficial de Moodle 5.3 LTS:
   ```bash
   git clone -b MOODLE_503_STABLE git://git.moodle.org/moodle.git /home/user/htdocs/staging.srv2085040.hstgr.cloud
   ```
5. Adaptar `config.php` en staging apuntando a la nueva base, dataroot y URL de staging.
6. **Ajuste obligatorio de NGINX/CloudPanel (Moodle 5.1+):**
   - En la interfaz de CloudPanel del sitio staging > Site > Root Directory: cambiar `/` por `/public`.
   - La directiva `root` en NGINX queda: `/home/user/htdocs/staging.srv2085040.hstgr.cloud/public;`.

### Paso 4: Ejecución de la actualización por CLI
```bash
cd /home/user/htdocs/staging.srv2085040.hstgr.cloud
sudo -u user php admin/cli/upgrade.php --non-interactive
```
Si el script reporta éxito, ejecutar purga de cachés:
```bash
sudo -u user php admin/cli/purge_caches.php
```

### Paso 5: Batería de validación post-upgrade en Staging
1. Verificar que la pantalla de administración no muestre errores de DocumentRoot.
2. Confirmar que el informe de entorno (`admin/environment.php`) marque todos los ítems en verde.
3. Ejecutar una pasada manual de CRON y validar ausencia de excepciones:
   ```bash
   sudo -u user php admin/cli/cron.php
   ```
4. Probar inicio de sesión, creación de cohortes, grupos separados y libro de calificaciones.

---

## 7. Plan de reversión (Rollback Runbook)

Si la actualización a Moodle 5.3 LTS falla en el entorno productivo:

1. Mantener el modo de mantenimiento activo.
2. Detener servicios de aplicación si es necesario.
3. Restaurar la base de datos previa desde el volcado:
   ```bash
   mysql -u [dbuser] -p [dbname] < /home/user/backups/moodle_pre_53_*.sql
   ```
4. Restaurar el código de Moodle 5.0.10+:
   ```bash
   rm -rf /home/user/htdocs/srv2085040.hstgr.cloud/*
   tar -xzf /home/user/backups/moodle_code_pre_53_*.tar.gz -C /home/user/htdocs/
   ```
5. Si NGINX fue cambiado a `/public`, revertir la directiva `root` en CloudPanel a la raíz del sitio:
   `/home/user/htdocs/srv2085040.hstgr.cloud`
6. Restaurar `moodledata` si se generaron escrituras durante la migración.
7. Purgar cachés: `sudo -u user php admin/cli/purge_caches.php`.
8. Reinstalar el crontab del usuario `user`: `crontab -u user /home/user/crontab.bak`.
9. Desactivar mantenimiento: `sudo -u user php admin/cli/maintenance.php --disable`.

---

## 8. Verificación posterior de CRON

El cron de Moodle es el motor de cálculo de finalización, tareas diferidas y entrega de notificaciones:
- Debe ejecutarse exactamente cada minuto:
  `* * * * * /usr/bin/php8.4 /home/user/htdocs/srv2085040.hstgr.cloud/admin/cli/cron.php >/dev/null`
- **Puntos de auditoría tras upgrade a 5.3 LTS:**
  1. Verificar que el usuario ejecutor sea estrictamente `user` (nunca `root` para no corromper permisos en `moodledata`).
  2. Verificar en la interfaz web de Moodle (*Administración del sitio > Servidor > Tareas programadas*) que ninguna tarea del núcleo se encuentre en estado de fallo (*Fail delay*).
  3. Comprobar que el log de cron no registre warnings de memoria o variables de PHP.

---

## 9. Pruebas de humo mínimas

- Administrador accede a la consola sin advertencias de seguridad sobre DocumentRoot.
- Estudiante navega en el curso piloto viendo únicamente su grupo asignado.
- Docente accede al libro de calificaciones y solo visualiza a sus 50 participantes.
- Interventoría accede al Report Builder y genera vista consolidada de los 7 grupos en modo lectura.
- BigBlueButton nativo abre sala de reunión sin errores de certificado o websocket.
- Cron corre sin excepciones.

---

## 10. Observabilidad y métricas para evaluar Redis

En el VPS Hostinger KVM 2, vigilar:
1. `htop` o `top`: Carga de CPU promedio $< 1.5$.
2. Memoria RAM disponible $> 1.5$ GB libres en el sistema.
3. Consultas lentas de MySQL (`slow_query_log = 1`, `long_query_time = 2`).
4. **Disparador para habilitar Redis:** Si durante la prueba de carga con 50 estudiantes en staging se detectan bloqueos recurrentes en `mdl_sessions` o degradación de I/O disk $> 15\%$, se activará Redis en CloudPanel dedicando máximo 256 MB para sesiones y almacén MUC.


