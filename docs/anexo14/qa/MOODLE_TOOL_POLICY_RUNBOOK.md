# Procedimiento reproducible — política obligatoria ASODELTOL

Este documento prepara la activación de `tool_policy` en Moodle 5.3. No publica
una política, no cambia acuerdos y no contiene datos jurídicos aprobados.

## Confirmación funcional de Moodle 5.3

La implementación nativa está en `admin/tool/policy`. El ajuste global es
`$CFG->sitepolicyhandler = 'tool_policy'`; no se requiere plugin, tabla ni
modificación de Moodle core.

Moodle conserva las versiones en `tool_policy_versions` y las decisiones en
`tool_policy_acceptances`. La API nativa `\tool_policy\api::get_user_acceptances()`
devuelve, para una cuenta autorizada, `policyversionid`, `status`, `lang`,
`timemodified`, `usermodified` y `note`. `status=1` es aceptación, `status=0`
es rechazo explícito y ausencia de fila es falta de respuesta. La comprobación
de persistencia debe hacerse mediante esta API o el servicio nativo
`tool_policy_get_user_acceptances`, nunca mediante SQL arbitrario desde QA.

## Habilitación controlada

1. Confirmar respaldo y recuperación antes de cualquier cambio.
2. En Administración del sitio → Seguridad → Políticas del sitio, seleccionar
   el gestor nativo `tool_policy` y guardar. La alternativa equivalente, solo
   para el operador autorizado, es añadir en `config.php`:
   `$CFG->sitepolicyhandler = 'tool_policy';`.
3. Abrir Administración del sitio → Usuarios → Políticas → Gestionar políticas.
4. Crear una política de tipo privacidad/tratamiento, audiencia **usuarios
   autenticados**, revisión humana y texto aprobado. Marcarla obligatoria solo
   cuando la institución haya aprobado la consecuencia de rechazo y silencio.
5. Guardar primero como borrador. Revisar nombre, resumen, contenido, revisión,
   audiencia y obligatoriedad; publicar mediante la acción nativa de Moodle.
6. Para un cambio material, crear una nueva versión y publicarla; no editar ni
   borrar la versión histórica que sustenta decisiones previas.

No se debe marcar `policyagreed=1` en cargas CSV ni importar ese valor como
sustituto de una decisión. Al crear una cuenta, el evento nativo de usuario
solo genera aceptaciones implícitas si la cuenta ya llega con `policyagreed`
verdadero; por eso las cuentas manuales y CSV de prueba deben conservarlo en
falso/ausente. El autorregistro debe completar el flujo nativo de políticas.

## Verificación con usuarios sintéticos

En staging, con cuentas sin datos reales:

1. Crear manualmente una cuenta sin `policyagreed`; acceder a un curso y a una
   URL directa. Debe aparecer la pantalla de política y el acceso requerido debe
   quedar bloqueado hasta resolverla.
2. Importar por CSV una cuenta sin campo `policyagreed` o con valor falso; no
   considerar la importación como consentimiento y repetir el paso anterior.
3. Aceptar y consultar `tool_policy_get_user_acceptances`; comprobar usuario
   sintético, versión, `status=1` y `timemodified` sin exportar PII.
4. En una cuenta nueva, rechazar y dejar otra sin respuesta. Comprobar que no
   aparece `status=1`. Documentar la consecuencia institucional aprobada; Moodle
   no debe interpretarse como prueba de una aceptación inexistente.
5. Publicar una versión nueva solo en staging. Comprobar que la versión anterior
   permanece histórica y que el usuario vuelve a quedar pendiente de la nueva.
6. Consultar la decisión desde la página nativa del usuario y, con capacidad
   autorizada, desde `admin/tool/policy/acceptances.php`.

Las comprobaciones HTTP o API no sustituyen la comprobación visual de la pantalla
ni la prueba persistente. El preflight incluido solo verifica alcance público y
valida un resumen sanitario suministrado por el operador; no inicia sesión.

## Reversión segura

Si la revisión falla, no borrar versiones ni acuerdos. Mantener el historial,
despublicar mediante la UI nativa o restaurar el respaldo aprobado, y devolver
`sitepolicyhandler` al gestor anterior únicamente con el plan de continuidad
aprobado. Repetir la consulta de versión y acceso con la cuenta sintética.

## Estado de esta entrega

El texto ASODELTOL sigue `PENDIENTE DE APROBACIÓN` en
`CONSENT_POLICY_AUDIT_2026-10-26.md`. No activar producción hasta aprobar
responsable, finalidades, canal, conservación y consecuencias ante rechazo.
