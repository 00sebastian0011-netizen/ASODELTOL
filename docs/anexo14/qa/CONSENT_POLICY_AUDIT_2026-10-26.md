# Auditoría de consentimiento y políticas — ASODELTOL

## Alcance

Documento preparatorio para verificar Moodle `tool_policy` en una instalación
autorizada. No contiene acuerdos, datos personales ni activación de producción.

## Estado

La configuración de `sitepolicyhandler`, presencia activa de `tool_policy`,
políticas/versiones/audiencia/obligatoriedad, pantalla de primer acceso,
representación nativa de aceptación/rechazo/pendiente y consulta administrativa
siguen pendientes de verificación autenticada. El preflight público no prueba
ninguno de esos puntos.

El texto `POLITICA_DATOS_AUTORIZACION_ASODELTOL_2026.md` procede de una captura
de otra institución y permanece `PENDIENTE DE VALIDACIÓN INSTITUCIONAL`. Deben
verificarse ASOCODEM, Fundación Prospectiva, SENA, la Convocatoria DSNFT-0001-2026,
los Anexos 12 y 14 y la entidad de interventoría antes de considerarlo aplicable
a ASODELTOL. También faltan responsable, canal, conservación y consecuencias
ante rechazo o silencio.

## Cierre funcional requerido

Con cuentas sintéticas autorizadas en staging, comprobar alta manual, CSV,
aceptación persistente por API nativa, rechazo y silencio sin falsa aceptación,
bloqueo de URL directa y nueva versión. Las pruebas reales siguen `BLOCKED` sin
ese entorno. No publicar el texto ni registrar consentimientos reales.
