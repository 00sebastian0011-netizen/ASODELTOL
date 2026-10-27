# RSMax — Enlaces rápidos con varios destinos por curso

## Decisión funcional

El panel de **Enlaces rápidos** es únicamente una herramienta de navegación.
El administrador del curso o una persona con la capacidad nativa
`moodle/course:manageactivities` puede asociar **secciones**, **archivos ya
publicados como recurso de Moodle**, y **actividades existentes** a una o varias
categorías: Avisos, Grabaciones, Material compartido, Talleres y Sesiones
sincrónicas.

- Se utiliza el editor nativo multiselección de Moodle en
  `/theme/rsmax/quicklinks.php?courseid=ID`; ninguna carga de archivos ocurre
  allí.
- Con un destino visible: botón directo. Con varios: lista accesible expandible
  por `<details>/<summary>`; sin destinos visibles: botón inactivo.
- El orden de la lista sigue el orden de los elementos seleccionados. Se puede
  quitar y volver a seleccionar un destino para cambiar su posición.
- Cada curso guarda sus decisiones por separado en la configuración de RSMax
  `coursequickgroups_ID`, como versión 1 y tokens `cm:ID` o `section:ID`.
- La validación consulta la estructura auténtica de **ese curso**; no se permiten
  identificadores inventados, recursos de otro curso ni URLs pegadas.
- Durante la visualización se vuelven a comprobar `visible` y `uservisible`
  del módulo y la sección para el usuario actual. Los destinos eliminados,
  ocultos o restringidos no se muestran.
- Las selecciones antiguas de enlace único y las detecciones automáticas se
  conservan **hasta el primer guardado** en el nuevo editor de cada curso. Una
  categoría nueva sin destinos seleccionados permanece deshabilitada.
- **Centro de Atención (CAU)** no es configurable por curso: usa únicamente
  `/local/asodeltolstaff/support.php` si hay destinatario válido.
- **No** se crean archivos, actividades, tickets, usuarios, matrículas ni datos
  de consentimiento; **no** se modifica Moodle core.

## Comprobaciones antes de instalar

1. Ejecutar `node --test tests/theme_rsmax/quicklinks-admin.test.mjs`, la
   batería existente RSMax y la verificación de sintaxis de los PHP afectados.
2. Ejecutar PHPUnit de `plugins/theme/rsmax/tests/course_utilities_test.php`
   en staging con Moodle instalado.
3. Con administrador y capacitador autorizado: seleccionar una sección, un PDF
   Moodle y dos actividades; guardar, recargar y cambiar orden.
4. Con estudiante de cada grupo: una categoría abre directamente su único
   destino; otra despliega la lista de varios; una categoría vacía está inactiva.
5. Ocultar, restringir a grupo, mover o borrar un módulo y comprobar que no se
   filtra por su enlace directo ni por la lista; intentar manipular IDs de otro
   curso y guardar como estudiante (debe fallar).
6. Verificar vista móvil/escritorio, teclado y pantalla de lector, y que los
   otros accesos y CAU central funcionan.
7. Respaldar RSMax y configuración del sitio en el VPS; comparar divergencias
   protegidas. Fusionar primero el PR y desplegar **solo** RSMax por el MCP
   disponible tras revisión. Purgar cachés oficiales y probar en producción.

**Estado:** código propuesto; no se declara desplegado ni validado en Moodle
productivo.
