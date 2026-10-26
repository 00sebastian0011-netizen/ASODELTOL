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

## Administrador visual de enlaces rápidos — ampliación

La edición ya no exige copiar `ID_CURSO|clave|/ruta`. En la **página principal de un curso** un administrador o docente con capacidad `moodle/course:manageactivities` ve el botón **Administrar enlaces rápidos**. También puede abrir:

`/theme/rsmax/quicklinks.php?courseid=ID_REAL_DEL_CURSO`

El estudiante no ve este botón y el controlador rechaza cualquier intento suyo de abrirlo o guardar datos. Un rol de capacitador de ASODELTOL que no tenga capacidad nativa de edición no recibe permisos nuevos implícitos: habrá que asignar la capacidad institucional apropiada siguiendo la política de roles.

### Cómo se cargan materiales para que el estudiante los vea

1. Entrar al **curso correspondiente** con permisos de edición.
2. Hacer clic en **Volver al curso para crear o subir contenido**; activar `Modo de edición` de Moodle.
3. Usar **Añadir una actividad o recurso**:
   - **Avisos:** Foro del curso.
   - **Grabación de sesiones:** Archivo, URL, Página o Carpeta con una grabación real y accesible.
   - **Material compartido:** Carpeta, Archivo, Página, Libro o URL del curso.
   - **Talleres:** Tarea, Cuestionario, H5P, Lección, Taller o Foro.
   - **Sesiones sincrónicas:** BigBlueButton, Zoom o URL aprobada en una actividad del curso.
   - **Centro de Atención (CAU):** el formulario de soporte ASODELTOL Staff si se ha designado un administrador receptor, o una actividad Página/URL existente.
4. Guardar la actividad con **visibilidad y restricciones de grupo correctas**.
5. Regresar a **Administrar enlaces rápidos** y elegir dicha actividad en el selector correspondiente. Guardar cambios.
6. Abrir el curso como un **beneficiario autorizado** y otro de **grupo no autorizado**; verificar que el enlace solo funcione para quien corresponda.

Los seis selectores ofrecen **Automático** (la lógica previa reconoce actividades nativas), **Sin configurar** (botón inactivo) o una actividad del mismo curso, del tipo permitido. No se guardan URLs arbitrarias, archivos ni datos personales en el tema. Si una actividad se borra, mueve, oculta o queda restringida para un alumno, el botón correspondiente no enlaza a ella.

Cada curso tiene su propia configuración en Moodle (`theme_rsmax/coursequicklinks_ID` con IDs de actividades), independiente de otros cursos; no se alteran los grupos o matrículas. Para roles con edición parcial específica del plugin staff, la publicación de recursos sigue realizándose por ese plugin bajo sus propios permisos; la administración global de enlaces no se entrega automáticamente a esos roles.

### Validaciones pendientes para despliegue

- `php -l` del nuevo controlador `quicklinks.php`, formulario y clase de resolución; pruebas estáticas Node en `tests/theme_rsmax/`.
- PHPUnit bajo una **instancia real de Moodle de staging**: formulario guardando opciones, curso distinto rechazado, estudiante sin permiso, enlace retirado/oculto y configuración por curso.
- Navegador en staging: editor, estudiante, capacitador sin edición, administrador, móvil, curso con actividades y sin actividades. Probar también curso con grupos separados. Una prueba de sintaxis o CI no reemplaza estas comprobaciones.
- Antes de publicar en VPS: respaldar código RSMax y configuraciones del sitio, revisar el despliegue de solo este componente, purgar cachés de Moodle, verificar enlaces reales y disponer de reversión.

La configuración histórica de texto en **RSMax → Cursos** se conserva por compatibilidad cuando un curso aún no ha utilizado el nuevo editor. Una vez guardadas elecciones visuales, estas tienen prioridad sobre el texto anterior.
