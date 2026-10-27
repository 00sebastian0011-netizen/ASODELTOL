# Navegación unificada de ayuda y Centro de Atención (CAU) — ASODELTOL

## Decisión de interfaz (27 de octubre de 2026)

- En la navegación superior de usuarios autenticados: **Ayuda y soporte**.
  - **Guía de la plataforma** → `/local/asodeltolstaff/help.php` (contenido de ayuda para REQ-08-02).
  - **Centro de Atención (CAU)** → `/local/asodeltolstaff/support.php` (soporte y casos de REQ-12-01/12-02).
- **Reportes y descargas** permanece como entrada independiente, sometida a sus permisos actuales.
- La herramienta externa de IA no es parte del CAU y no se modifica en este cambio.
- El acceso rápido **CAU** de RSMax apunta al **mismo** `support.php` en todos los cursos. No se puede reemplazar por una Página/URL distinta desde el editor del curso. Los enlaces rápidos restantes conservan su configuración por curso y sus controles de acceso.

Se reutilizan las tablas y notificaciones de ASODELTOL Staff. No se crea otra mesa de ayuda, no se duplican casos, ni se altera el sistema nativo de consentimiento.

## Habilitación del servicio existente

ASODELTOL Staff solo permite crear casos si el administrador selecciona un **destinatario de soporte** válido en la configuración del plugin `local_asodeltolstaff/supportuserid`. El código comprueba que el destinatario sea administrador del sitio activo. La opción no se infiere de otras cuentas ni se configura automáticamente en este cambio.

Hasta que se configure, la guía continúa disponible y el CAU del curso no se presenta como operativo. Esto no cierra REQ-12-01 ni REQ-12-02.

## Pruebas necesarias

1. Ejecutar `node --test tests/anexo14/help-cau-navigation.test.mjs` y las pruebas del tema RSMax. Comprobar sintaxis de los PHP modificados.
2. En staging con ASODELTOL Staff y RSMax instalados, revisar el desplegable **Ayuda y soporte** en escritorio y móvil; verificar Guía y CAU, y comprobar que **Reportes y descargas** siga separado. Si el renderizador del tema no muestra correctamente los hijos de navegación primaria, ajustar la plantilla **sin** modificar Moodle core.
3. Con un administrador receptor configurado, iniciar sesión como beneficiario autorizado: entrar al CAU desde la navegación global y desde dos cursos distintos; confirmar que llevan al mismo formulario y que un caso se guarda una sola vez con referencia, historial, respuesta y estado.
4. Sin destinatario, comprobar que RSMax no ofrece un formulario de ticket ficticio ni un enlace habilitado sin servicio. Con un destinatario suspendido, el resultado también debe permanecer cerrado.
5. Comprobar que no se pierde visibilidad de la guía en móviles, que los enlaces de curso siguen respetando `uservisible`, y que los reportes siguen restringidos.
6. En producción, crear una solicitud sintética autorizada, responder y resolver desde el administrador receptor. Verificar notificaciones y registro de fechas. **Los plazos SLA de REQ-12-02 requieren definición institucional y evidencia en el periodo comprometido**; una prueba técnica no los acredita.

## Despliegue por MCP SSH, después del PR

- Leer versión instalada de Staff y RSMax, y registrar configuración del destinatario; respaldar exclusivamente los archivos que se van a sustituir, y comparar las divergencias protegidas antes de desplegar.
- Publicar las modificaciones versionadas después de fusionar el PR. Si hay divergencias no evaluadas, no sobrescribirlas: generar un parche específico y revisarlo.
- Ejecutar los pasos de upgrade de Moodle solo si la versión de plugin lo exige, purgar cachés con la CLI oficial en `/home/user/htdocs/srv2085040.hstgr.cloud/admin/cli/purge_caches.php`, con usuario `user` y PHP 8.4.
- Validar desde navegador la navegación, el formulario de soporte y los permisos del beneficiario y del administrador.
- No cambiar políticas `tool_policy`, consentimientos, roles globales ni configuración ajena al CAU.

**Estado de este documento:** propuesta de código; no certifica despliegue o cumplimiento productivo.
