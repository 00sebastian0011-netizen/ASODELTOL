# Configuración de desarrollo

Este directorio contiene configuración versionable y sin secretos para herramientas. No contiene configuración de una instalación Moodle.

## PHPCS y Composer

`phpcs.xml.dist` apunta a `plugins/` y valida el estándar oficial `moodle`. Las dependencias de desarrollo (`squizlabs/php_codesniffer` y `moodlehq/moodle-cs`) se gestionan de forma reproducible mediante el archivo raíz `composer.json`.

Para instalar herramientas de calidad localmente:
```bash
composer install
composer run lint
```

## Entorno Moodle

Las rutas, URL, credenciales y bases de pruebas pertenecen a la configuración local no versionada de Moodle. No crear `.env` ni `config.php` dentro de este repositorio. PHPUnit y Behat se inicializan desde una instalación de desarrollo separada y nunca contra producción.

## Versiones objetivo formales (ADR-0004)

- **Moodle:** **5.3 LTS** (soporte oficial de seguridad hasta octubre de 2029).
- **Estructura Web:** DocumentRoot del servidor web apuntando obligatoriamente a `/public`.
- **PHP CLI / PHP-FPM:** **PHP 8.4** (mínimo soportado en 5.3 es PHP 8.3.0; 8.4 totalmente soportado). Ambas interfaces (CLI y FPM) deben estar estrictamente alineadas en la misma versión menor.
- **PHP Extensiones requeridas:** `sodium` (obligatoria), `intl`, `opcache`, `zip`, `curl`, `mbstring`, `xml`, `gd`, `mysqli`.
- **PHP `max_input_vars`:** $\ge 5000$ (en `php.ini` tanto de CLI como de FPM).
- **PHPUnit:** 11.x según la matriz de pruebas de Moodle 5.3.
- **Base de datos relacional:**
  - **MariaDB:** mínimo **11.4.0** (incrementado en Moodle 5.3).
  - **MySQL:** mínimo **8.4** (incrementado en Moodle 5.0).
  - *Nota:* Soporte para Oracle fue retirado desde Moodle 5.0.

La rama Moodle 5.0 fue declarada oficialmente fuera de soporte de seguridad el 5 de octubre de 2026. La migración a 5.3 LTS se ejecutará en staging antes de iniciar el desarrollo de plugins.

