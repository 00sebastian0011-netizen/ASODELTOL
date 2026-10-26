# Solicitud: avisos y enlaces para toda la plataforma

## Interpretación funcional de la captura
Los avisos de gratuidad y tratamiento de datos son institucionales, no exclusivos
del rol de Interventoría. Deben mostrarse en todas las páginas autenticadas
renderizadas por RSMax: portada, Mis cursos, categorías, administración,
curso, sección y actividad. El login mantiene su aviso transitorio.

Los seis botones son accesos a contenidos **del curso actual** y se muestran en
curso, sección y actividad a los perfiles autorizados para ver dicho curso,
sin depender del rol Interventoría. Una grabación inexistente, una sesión no
programada o un centro de atención no configurado no se convierten en
funcionalidades reales simplemente por mostrar un botón. El enlace permanece
inactivo hasta configurar una actividad accesible en ese curso.

## Seguridad y gobierno
- No eliminar ni modificar el rol Interventoría: se mantiene como auditor según
  la definición institucional. El panel de información no está condicionado a él.
- No dar acceso transversal a materiales de otros cursos o grupos: cada URL
  resuelta debe respetar la visibilidad nativa de Moodle (`uservisible`).
- Solo quien tenga `moodle/course:manageactivities` administra los destinos.
- Consentimiento Ley 1581: la firma real requiere una política institucional
  aprobada y publicada con `tool_policy`. RSMax no registra consentimientos.

## Matriz mínima para cierre en staging y producción
1. Confirmar que RSMax sea el tema efectivo para las audiencias examinadas.
2. Probar portada general, Mis cursos, curso A, sección A, actividad A,
   curso B, sección B y actividad B como administrador, capacitador,
   beneficiario e Interventoría.
3. Verificar avisos en cada página y seis enlaces en contextos de curso,
   sin excepciones por rol.
4. Configurar actividades reales en cursos A y B desde el editor, comprobar
   destinos como beneficiarios autorizados y vínculos inactivos si faltan recursos.
5. Probar alumnado restringido por grupo, curso sin matrícula y recursos ocultos.
   Ningún enlace debe saltarse la autorización de Moodle.
6. Verificar `tool_policy` activo/inactivo, sin firmas inventadas.
7. Ejecutar tests JS, `php -l` del PHP modificado, PHPUnit con Moodle real,
   navegador de escritorio y móvil en staging.
8. Antes de producción: respaldo y reversión, confirmar el despliegue RSMax,
   purgar caché, comparar SHA/versiones del VPS, y completar pruebas de humo
   con los cuatro perfiles.

**Estado**: cambio de código propuesto. Este documento NO certifica aceptación
funcional en producción, configuración de seis destinos en cada curso ni política
de datos institucional aprobada. No requiere modificar ASODELTOL Staff ni los
11 archivos de Staff protegidos.
