# Seguridad y protección de datos

## 1. Alcance

Este documento define controles técnicos mínimos. No reemplaza una evaluación jurídica ni la política institucional de tratamiento de datos. El responsable del tratamiento, el encargado, las finalidades, la base jurídica y los tiempos de retención deben ser designados formalmente.

En Colombia, la referencia general es la Ley 1581 de 2012 y su reglamentación compilada, incluida la política exigida por el Decreto 1074 de 2015. Deben aplicarse legalidad, finalidad, libertad, veracidad, transparencia, acceso restringido, seguridad y confidencialidad. Las fuentes oficiales se enlazan en `FUENTES.md`.

## 2. Clasificación mínima de información

| Clase | Ejemplos | Tratamiento |
|---|---|---|
| Pública | Descripción pública del diplomado | Publicable tras aprobación. |
| Interna | Configuración no sensible, manuales operativos | Acceso al equipo autorizado. |
| Confidencial | Matrículas, calificaciones, asistencia, actividad, IP, reportes | Cifrado en tránsito, acceso mínimo, trazabilidad y retención. |
| Secreta | Contraseñas, tokens, claves SSH/LTI/API, `config.php` | Gestor de secretos; nunca Git, tickets, chat, logs o exportaciones. |
| Sensible/especial | Datos biométricos, salud u otras categorías legales | No recolectar salvo necesidad y autorización/base legal explícitas. |

No se usarán datos de producción para desarrollo. La seudonimización no convierte automáticamente los datos en públicos ni elimina el riesgo de reidentificación.

## 3. Identidad y acceso

- Una cuenta nominativa por persona; prohibidas las cuentas compartidas para administración o auditoría.
- Administradores limitados; tareas normales con rol Manager reducido.
- MFA para cuentas privilegiadas y para otros perfiles si la instalación lo soporta.
- Altas, cambios y bajas con responsable, fecha y revisión periódica.
- Capacidades por contexto de sistema, categoría, curso y módulo.
- Grupos separados y retirada de `moodle/site:accessallgroups` cuando un capacitador no deba ver otros grupos.
- Interventoría derivada de mínimo privilegio: lectura de reportes/evidencia, sin editar notas, usuarios, cursos o registros.
- Pruebas de acceso tanto por interfaz como por URL directa y exportación.
- Sesiones seguras, HTTPS y cookies según las recomendaciones de Moodle; tiempos de sesión acordes al riesgo.

Los plugins comprueban `require_login`/`require_course_login`, contexto y `require_capability`; nunca autorizan comparando el nombre textual del rol.

## 4. Desarrollo seguro de plugins

- Entradas mediante Forms API o `required_param`/`optional_param` con tipo correcto.
- Operaciones mutables por POST con `sesskey` y confirmación proporcional al impacto.
- Salida por Output API y escapado apropiado; no concatenar HTML o JavaScript con entrada de usuario.
- Base de datos por DML/XMLDB y parámetros; no concatenar SQL.
- Archivos por File API y validación de tipo/tamaño; nunca rutas suministradas por el usuario.
- Sin llamadas de shell con entrada externa.
- Paginación y límites en informes para evitar denegación de servicio.
- Tareas programadas idempotentes, con bloqueos y reintentos limitados.
- Capacidades anotadas con riesgos y pruebas positivas/negativas.
- Eventos sin secretos ni cargas personales excesivas.
- Privacy API para metadatos, exportación y eliminación; null provider sólo si es verdadero.
- Backup/Restore API para datos que deban seguir al curso; datos de auditoría sujetos a política separada.
- Protección contra inyección de fórmulas en XLSX/CSV cuando una celda controlada por usuario empieza por `=`, `+`, `-` o `@`.

## 5. Secretos y repositorio

`.gitignore` excluye `.env`, `config.php`, claves, `moodledata`, bases, respaldos, volcados y formatos usuales de datos/exportación. Esto reduce accidentes, pero no sustituye controles:

- usar un gestor de secretos o configuración del entorno;
- aplicar escaneo de secretos antes de revisión e integración;
- revisar `git diff --cached` antes de cada commit;
- si un secreto llega a Git, revocarlo/rotarlo primero y luego sanear el historial mediante un procedimiento aprobado;
- no conservar secretos en archivos de ejemplo;
- no usar `git add -f` sobre archivos ignorados sin revisión de seguridad.

## 6. Datos personales y retención

Antes de recolectar un campo se documentan finalidad, fuente, obligatoriedad, audiencia, transferencia, retención y eliminación. Los reportes deben usar el identificador mínimo y evitar documento, correo, IP u otros campos cuando no sean necesarios.

Cada plugin propio debe:

- declarar tablas, campos y subsistemas en Privacy API;
- permitir localizar y exportar los datos del titular;
- implementar eliminación o anonimización cuando sea legalmente procedente;
- respetar conservación legal y litigio/retención aprobados;
- registrar accesos o cambios sensibles sin copiar el dato completo al log.

Las grabaciones son datos de alto impacto. Se requiere aviso/autorización o base aplicable, control de acceso, duración, ubicación, subencargados, procedimiento de retiro y eliminación verificable.

## 7. Evidencia, logs y auditoría

Los logs de Moodle indican eventos técnicos y pueden incluir usuario, hora, acción, contexto e IP. No demuestran por sí solos atención, permanencia, asistencia efectiva o trabajo realizado.

La evidencia académica/operativa debe conservar:

- fuente y método de captura;
- actor que declaró y actor que validó;
- instante UTC y zona de presentación;
- contexto de curso/grupo/sesión;
- motivo y referencia de cada corrección;
- relación con evidencia externa sin replicarla innecesariamente.

La corrección no borra el hecho anterior: crea un evento compensatorio. El acceso a evidencia se registra y se limita por capacidad.

## 8. Infraestructura y operación

Controles a validar por el operador autorizado, no ejecutados en esta misión:

- Moodle, PHP, Ubuntu, servidor web, base de datos y plugins en versiones soportadas y parcheadas.
- PHP de CLI y FPM alineados; extensiones y `max_input_vars` conforme a requisitos de Moodle.
- `moodledata` fuera del webroot y `config.php` no descargable.
- TLS vigente, redirección HTTPS y encabezados seguros compatibles con integraciones.
- Permisos mínimos de sistema de archivos y usuario de servicio sin shell innecesario.
- Base de datos no expuesta públicamente y credenciales independientes.
- Cron ejecutado por el usuario correcto, sin secretos impresos y con alertas por retraso.
- Backups cifrados, fuera del mismo disco/sistema, con restauración probada.
- Centralización y rotación de logs; reloj sincronizado.
- Límites de carga, tasa y tamaño de reportes; monitorización de CPU, RAM, I/O y disco.
- Entorno staging separado, sin datos reales y sin enviar correo/notificaciones a destinatarios reales.

## 9. Dependencias y cadena de suministro

Antes de instalar un plugin tercero:

1. confirmar compatibilidad declarada con la versión exacta de Moodle/PHP;
2. revisar mantenedor, actividad, incidencias, licencia, Privacy API y permisos;
3. fijar versión y SHA-256 del paquete obtenido de una fuente oficial;
4. revisar cambios y ejecutar comprobaciones de código;
5. instalar y probar primero en staging;
6. registrar propietario, propósito, datos tratados, versión y plan de retirada.

`mod_attendance` es candidato, no dependencia aprobada. BigBlueButton de core/LTI tampoco autoriza un proveedor o envío de datos sin evaluación.

## 10. Respuesta a incidentes

Procedimiento mínimo a aprobar:

1. contener sin destruir evidencia;
2. preservar hora, logs y alcance con acceso restringido;
3. rotar credenciales comprometidas;
4. evaluar datos, titulares, proveedor y obligación de notificación;
5. recuperar desde artefactos y respaldos verificados;
6. documentar causa, impacto, decisiones y acciones;
7. probar la corrección y actualizar controles.

No incluir datos personales o secretos en tickets de incidente. Los contactos, plazos legales y autoridad de declaración quedan pendientes.

## 11. Checklist antes de liberar

- [ ] Rama de Moodle soportada o excepción de riesgo formal vigente.
- [ ] Inventario de dependencias y checksums aprobado.
- [ ] Sin secretos ni PII en Git y artefactos.
- [ ] Revisión de capacidades, grupos e Interventoría aprobada.
- [ ] Privacy API y retención probadas.
- [ ] Análisis estático, coding style, PHPUnit y Behat superados.
- [ ] Prueba de carga y límites de reportes aceptados.
- [ ] Respaldo completo y restauración ensayada.
- [ ] Integración externa y acuerdo de datos aprobados.
- [ ] Plan de reversión, monitorización y responsables confirmados.

