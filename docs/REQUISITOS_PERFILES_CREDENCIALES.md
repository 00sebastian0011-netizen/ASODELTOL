# ASODELTOL — Requisitos de perfiles y credenciales de estudiantes

*Estado:* Implementado en `local_asodeltolstaff` y validado con pruebas sintéticas (`REQ-USR-01` a `REQ-USR-05`).

## REQ-USR-01. Municipio de residencia administrado institucionalmente

El municipio de residencia es un dato controlado por ASODELTOL y administrado únicamente por personal autorizado.

*Comportamiento requerido:*
- El estudiante no podrá visualizar ni modificar el campo «Ciudad/Pueblo» (`city`) en su perfil.
- El administrador autorizado podrá consultar y actualizar el municipio de residencia.
- Los valores se cargarán desde los registros verificados de inscripción.
- Las correcciones no modificarán matrículas, cursos, calificaciones ni otros datos del estudiante.
- Los municipios dudosos o no identificados quedarán pendientes de verificación (`pending_verification`).

*Criterio de aceptación:* Un estudiante no puede consultar ni modificar ese dato (`city` en `hiddenuserfields` y `field_lock_city = locked` en `auth_manual`), mientras un administrador autorizado sí puede administrarlo mediante `\local_asodeltolstaff\student_credentials::update_municipality()`.

## REQ-USR-02. Credenciales iniciales individuales

Cada estudiante tendrá una cuenta Moodle individual y una contraseña inicial asignada institucionalmente.

*Solicitud original:* Utilizar el formato `sena+primernombre+5678$`.

*Ajuste de seguridad implementado:* Utilizar contraseñas iniciales únicas y aleatorias, generadas de forma criptográficamente segura para cada estudiante (`\local_asodeltolstaff\student_credentials::generate_random_password()`), evitando y rechazando explícitamente patrones deducibles a partir de nombres (`is_deducible_password()`).

*Criterio de aceptación:* Cada estudiante puede acceder únicamente a su propia cuenta con credenciales individuales.

## REQ-USR-03. Control institucional de contraseñas

ASODELTOL administra centralmente las contraseñas de los estudiantes e impide cambios no autorizados.

*Comportamiento implementado:*
- Los administradores autorizados (`moodle/site:config`) pueden generar credenciales iniciales y restablecer contraseñas desde `/local/asodeltolstaff/credentials.php`.
- No se permiten cambios por personal sin autorización ni por el propio estudiante cuando la política institucional está activa (`CAP_PREVENT` sobre `moodle/user:changeownpassword` en el rol `student`).
- Se mantiene un procedimiento seguro de restablecimiento administrativo y verificación de identidad.
- Se rechazan contraseñas permanentes compartidas o deducibles.

*Criterio de aceptación:* El acceso a las cuentas se puede recuperar de forma segura sin comprometer los datos académicos.

## REQ-USR-04. Registro de entrega de credenciales

ASODELTOL administra la entrega de los usuarios y sus contraseñas iniciales desde `/local/asodeltolstaff/credentials.php`.

*Comportamiento implementado:*
- Listado administrativo de usuarios, identificación de cuenta (`username`, `idnumber`, nombre), curso, grupo, municipio (`city` y estado de verificación) y estado de entrega (`pending`, `generated`, `delivered`, `reset`).
- Generación de credencial inicial individual por cuenta, mostrada una única vez en memoria al administrador para su entrega por canal privado y controlado.
- Prohibición estricta de conservar o exportar contraseñas en texto legible como registro permanente en base de datos, configuración o archivos CSV/XLSX.
- Registro de marcas de tiempo (`timegenerated`, `timedelivered`, `timereset`), canal privado utilizado y administrador responsable, sin almacenar el contenido de la contraseña.

*Criterio de aceptación:* El administrador puede consultar qué estudiantes recibieron sus accesos y gestionar restablecimientos, sin exponer las contraseñas.

## REQ-USR-05. Validación y trazabilidad

Flujo obligatorio antes y durante modificaciones masivas (`\local_asodeltolstaff\student_credentials`):

1. Comprobar usuarios existentes y método de autenticación (`preflight_check()`).
2. Respaldar la configuración afectada (`backup_policy_state()` y reversión `restore_policy_state()`).
3. Probar los cambios con cuentas sintéticas de administrador y estudiante (`plugins/local/asodeltolstaff/tests/student_credentials_test.php`).
4. Validar que los estudiantes no puedan modificar campos restringidos (`city` bloqueado/oculto y `moodle/user:changeownpassword` restringido).
5. Comprobar inicio de sesión, recuperación de acceso y conservación intacta de matrículas y calificaciones.
6. Registrar los resultados de la implementación.
