# Aviso de gratuidad y enlaces de cursos — RSMax

## Alcance aislado

Cambio de interfaz en `plugins/theme/rsmax`, propuesto desde `main` en una rama distinta a `codex/close-19-staging` (PR #18). No cambia `local_asodeltolstaff`, scripts `close19`, datos, cursos, Moodle core ni configuración del VPS.

### Inicio de sesión

RSMax sobrescribe únicamente la plantilla `core/login_layout` de Boost para añadir un aviso accesible en HTML, sin sustituir el formulario ni los otros componentes de Moodle. El aviso aparece durante **3 segundos**, deja interactuar con el formulario (sin bloquear clics) y se oculta después. Se puede consultar la declaración de gratuidad de forma permanente en las páginas principales de los cursos.

Texto aprobado en la captura facilitada:

> Las acciones de formación ejecutadas en el marco de la convocatoria DNSFT-0001-FCE son gratuitas para los trabajadores beneficiarios

Esta referencia de convocatoria debe validarse con la institución si sus documentos usan otro código.

### Página principal del curso

RSMax muestra, únicamente para usuarios autenticados que visualizan la página principal del curso y fuera de modo edición:

- Aviso visible y permanente de gratuidad.
- Módulo informativo sobre Ley 1581 de 2012.
- Enlace `Firmar / Consultar Consentimiento de Datos` **solo si** el sitio está utilizando realmente el gestor Moodle `tool_policy`; la firma y su registro son de Moodle, no de RSMax.
- Seis accesos: Avisos, Grabación de sesiones, Material compartido, Talleres, Sesiones sincrónicas y CAU.

La interfaz no inventa archivos o autorizaciones. Enlaces sin destino disponible se muestran **deshabilitados**, no como enlaces vacíos. Las actividades detectadas están filtradas mediante `get_fast_modinfo` y `uservisible`. Se consideran, respectivamente: foro de anuncios tipo `news`, actividad URL titulada como grabación, carpeta `folder`, tarea `assign`, videoconferencia `bigbluebuttonbn` / `zoom`, y soporte ASODELTOL cuando está instalado, autorizado y configurado.

#### Destinos manuales para contenidos existentes

En `Administración del sitio → Apariencia → Temas → RSMax → Cursos`:

- `Mostrar información y enlaces rápidos en cursos`: permite ocultar el panel.
- `Destinos específicos para enlaces rápidos de cursos`: una entrada por línea, con formato `ID_CURSO|clave|/ruta-interna`. Ejemplo `15|recordings|/mod/url/view.php?id=42`.

Claves: `announcements`, `recordings`, `materials`, `workshops`, `live`, `support`.

Solo URLs internas a Moodle. Se prohíben destinos externos, esquemas URL y rutas que intenten salir de la raíz. No apuntar a `prospectiva.edu.co` ni crear actividad ficticia. **Los enlaces configurados manualmente deben verificarse uno por uno**: la configuración no prueba automáticamente su existencia ni permite saltarse los permisos de Moodle.

### Consentimiento pendiente

La captura contiene un código de convenio distinto al de la gratuidad. Este cambio **no** inventa código de convenio ni aviso contractual definitivo ni consentimientos firmados. Para habilitar el botón real, configurar y publicar la política aprobada en el gestor oficial de políticas de Moodle y comprobar el historial de aceptación, versión, identidad y fecha. Si `tool_policy` no está activo, el botón no aparece y se informa de la configuración pendiente.

### Verificación y publicación

Validación automatizada de este PR: `php -l` de PHP cambiado, pruebas de contratos de UI con `node --test tests/theme_rsmax/*.test.mjs`. Estas pruebas no equivalen a un recorrido de navegador o aceptación contractual.

**Pendiente obligatorio antes de desplegar al VPS:**
1. En staging Moodle real con RSMax: verificar la pantalla de login en escritorio/móvil y la desaparición a 3 s, también con movimiento reducido.
2. Abrir un curso autorizado y comprobar diseño responsive, seis enlaces, permisos de beneficiario/capacitador y el caso sin actividades.
3. Activar o desactivar temporalmente `tool_policy` en staging para verificar que el enlace no fabrique consentimientos. Verificar la firma real solo con política institucional aprobada.
4. Confirmar versión instalada de RSMax y que el despliegue de tema no sobrescriba personalizaciones o configuraciones no versionadas; hacer respaldo y plan de reversión.
5. Verificar que no se despliegue el trabajo no finalizado del PR #18 ni Moodle core. Después de sincronizar exclusivamente RSMax, purgar cachés del tema y comprobar los recorridos en la URL real.

**Esta rama es preparación de código y revisión:** fusionar o hacer despliegue al VPS solo cuando se haya cumplido la verificación local y los controles de publicación. La fusión de un PR no confirma que la nueva versión esté instalada en producción.
