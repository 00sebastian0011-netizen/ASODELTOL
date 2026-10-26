# Procedimiento reproducible — política obligatoria ASODELTOL

Este documento prepara la activación de `tool_policy` en Moodle 5.3. No publica
una política, no cambia acuerdos y no contiene texto jurídico aprobado.

## Configuración nativa

La implementación está en `admin/tool/policy`. El ajuste global es
`$CFG->sitepolicyhandler = 'tool_policy'`; no se requiere plugin ni cambio de
Moodle core. Seleccionar el gestor en Administración del sitio → Seguridad →
Políticas del sitio, o aplicar esa variable solo mediante un operador autorizado.

Crear en Gestionar políticas una política de privacidad/tratamiento para
usuarios autenticados. Mantenerla como borrador hasta validar el texto; marcarla
obligatoria solo después de aprobar las consecuencias de rechazo y silencio.
Cada cambio material debe ser una nueva versión, sin borrar el historial.

Moodle conserva versiones en `tool_policy_versions` y decisiones en
`tool_policy_acceptances`. La API `\tool_policy\api::get_user_acceptances()` o
el servicio `tool_policy_get_user_acceptances` permiten verificar, con una cuenta
autorizada, versión, `status`, idioma, fecha y usuario modificador. `status=1`
es aceptación, `status=0` rechazo y ausencia de fila falta de respuesta. No usar
SQL arbitrario desde QA.

Las cuentas manuales y CSV sintéticas deben conservar `policyagreed` falso o
ausente; no importar `policyagreed=1`. Verificar la redirección de primer acceso,
la URL directa, la consulta persistente y la nueva versión en staging.

## Reversión

No borrar versiones ni acuerdos. Despublicar mediante la UI nativa o restaurar
el respaldo aprobado y devolver `sitepolicyhandler` al gestor anterior solo con
el plan de continuidad aprobado. Repetir la verificación con cuentas sintéticas.

El texto de referencia está en
`POLITICA_DATOS_AUTORIZACION_ASODELTOL_2026.md` como `PENDIENTE DE VALIDACIÓN
INSTITUCIONAL`; no activar producción hasta verificar entidades, convocatoria,
responsable, canal, conservación y consecuencias ante rechazo o silencio.
