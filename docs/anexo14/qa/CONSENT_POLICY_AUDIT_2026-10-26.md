# Auditoría de consentimiento y políticas — ASODELTOL

## 1. Estado observado

La versión declarada por los archivos instalados en producción es Moodle
`2026100500.06`, RSMax `1.2.5` y Staff `0.4.2`. NGINX, PHP-FPM y los agentes
diagnóstico/despliegue aparecen activos. Esto no demuestra upgrades de la base
de datos, configuración activa de Moodle ni funcionamiento de `tool_policy`.

La comprobación pública read-only de `aulaasodeltol.online` observó:

- `/login/index.php`: HTTP 200.
- `/admin/tool/policy/index.php`: redirección pública al inicio.
- `/admin/tool/policy/user.php` y configuración `toolpolicy`: redirección al login.
- La página de login contiene un marcador genérico de privacidad, pero no
  contiene un marcador `tool_policy` ni consentimiento de datos.

La evidencia está en `build/anexo14/production/policy-public-probe-20261026.json`.
Sin una sesión administrativa autorizada, el estado de `sitepolicyhandler`,
`tool_policy`, políticas, versiones, audiencia y acuerdos nativos es `BLOCKED`,
no `FAIL` ni `PASS`.

## 2. Código local y duplicaciones

RSMax no almacena acuerdos. `theme_rsmax\\local\\course_utilities::policy_url()`
solo devuelve `/admin/tool/policy/user.php` cuando:

```php
$CFG->sitepolicyhandler === 'tool_policy'
&& (int) get_config('tool_policy', 'version') > 0
```

El aviso institucional es transversal a páginas autenticadas, pero es
informativo y muestra un estado pendiente cuando la política oficial no está
habilitada. No sustituye el gestor nativo, no prueba aceptación y no debe
duplicar una política completa en el tema.

## 3. Configuración nativa recomendada

1. Confirmar que `tool_policy` esté instalado y activo en la instalación real.
2. Publicar una política institucional aprobada para ASODELTOL, con versión,
   fecha efectiva, finalidad, datos tratados, responsables/encargados,
   destinatarios, conservación, derechos y canal de consultas/reclamos.
3. Separar claramente:
   - condiciones de uso del servicio;
   - conocimiento/consulta de la política de privacidad;
   - autorización expresa para finalidades que requieran consentimiento.
4. Configurar obligatoriedad solo después de la decisión jurídica sobre rechazo,
   ausencia de respuesta, revocación y continuidad mínima del servicio.
5. Verificar en Moodle los registros nativos de aceptación/rechazo, versión,
   fecha y usuario mediante la interfaz administrativa autorizada, sin exportar
   datos personales reales al MCP.
6. Mantener RSMax como enlace/contexto accesible; no crear tabla paralela.

## 4. Texto institucional pendiente

No se encontró en el repositorio un texto jurídico aprobado específicamente
para ASODELTOL. La captura de `aulas.prospectiva.edu.co` no autoriza copiar
entidades, convenios o finalidades de ASOCODEM, Fundación Prospectiva u otra
organización. Antes de publicar deben validar formalmente:

- responsable del tratamiento y encargados;
- nombre jurídico de ASODELTOL y datos de contacto;
- finalidades obligatorias y opcionales;
- categorías de datos, incluidos datos sensibles o de caracterización;
- transferencias, subencargados, grabaciones y proveedores externos;
- plazo y fundamento de conservación;
- procedimiento de consulta, rectificación, supresión, revocación y reclamo;
- consecuencias concretas de rechazar o no responder;
- código de convocatoria/convenio y fecha/versionado.

## 5. Casos preparados

Se añadieron ocho casos `POLICY-01` a `POLICY-08` al catálogo. Los casos que
aceptan, rechazan, cambian versión o revocan tienen riesgo
`synthetic_write_requires_authorization` y el MCP los bloquea hasta disponer
de staging, autorización, respaldo y recuperación. Los casos de consulta,
pendiente, acceso requerido y evidencia administrativa son read-only, pero
requieren perfiles sintéticos y configuración privada del ejecutor.

No se otorgó ni revocó consentimiento, no se consultaron acuerdos reales y no
se modificó ninguna política de producción.

## 6. Decisiones pendientes

- ¿La autorización es obligatoria para todo el acceso o solo para finalidades
  opcionales?
- ¿Qué ocurre operativamente ante rechazo o silencio?
- ¿Qué datos de caracterización son necesarios y cuáles son sensibles?
- ¿Quién puede consultar evidencia y con qué minimización?
- ¿Cómo se gestiona revocación sin borrar registros cuya conservación sea
  obligatoria?
- ¿Cuál es el texto, versión y responsable institucional aprobados?

## 7. Bloqueos administrativos restantes

Siguen sin verificarse en `aulaasodeltol.online`, por falta de una sesión
administrativa autorizada y de exportación sanitaria específica:

- presencia/versión activa de `tool_policy` y valor efectivo de
  `$CFG->sitepolicyhandler`;
- políticas publicadas, estado borrador/publicada, audiencia y obligatoriedad;
- política vigente, versión, fecha efectiva y comportamiento ante una nueva
  versión;
- representación nativa de aceptación, rechazo, pendiente y revocación;
- pantalla que Moodle muestra al iniciar sesión y bloqueo de páginas exigidas;
- capacidades para que un administrador consulte evidencia sin exponer datos
  personales;
- ausencia de una actividad Consulta separada que suplante al gestor nativo.

Los diagnósticos VPS disponibles solo informan versiones de archivos, servicios
y estado del agente. No prueban configuración de Moodle ni registros de
acuerdos. Por tanto, no se debe declarar cierre con la evidencia pública actual.

## 8. Procedimiento mínimo de auditoría autenticada

Cuando el ejecutor QA esté disponible, usar exclusivamente cuentas sintéticas
autorizadas y un `authorization_ref` vigente emitido en el VPS:

1. `validation_catalog` para consultar, en lotes de máximo cinco, los ocho
   casos `TC-POLICY-*` y confirmar riesgo/método.
2. `run_validation` solo con la referencia específica de autorización. Los
   casos `POLICY-03`, `POLICY-04`, `POLICY-06` y `POLICY-07` pueden ejecutarse
   como read-only; aceptación, rechazo, cambio de versión y revocación solo en
   staging o ventana autorizada con respaldo y recuperación.
3. `validation_status` y `validation_results` para recibir estados compactos:
   `PASS`, `FAIL` o `BLOCKED`, método y limitaciones.
4. `validation_evidence` únicamente para metadatos sanitizados: caso, método,
   estado, fecha, tamaño/hash cuando aplique. No solicitar HTML, cookies,
   tokens, nombres reales, exportaciones de acuerdos ni capturas con PII.
5. En la interfaz administrativa autorizada, comprobar manualmente los siete
   puntos de la sección anterior y registrar solo versión, estado, audiencia,
   decisión agregada y fecha. La API o una respuesta HTTP no se considera
   prueba visual de la pantalla de consentimiento.

## 9. Borrador institucional — PENDIENTE DE APROBACIÓN

**No publicar este texto todavía.** Es una plantilla de revisión jurídica y no
constituye autorización ni política vigente.

> **Política de tratamiento de datos personales de ASODELTOL — BORRADOR
> PENDIENTE DE APROBACIÓN**
>
> Responsable: `[RAZÓN SOCIAL Y NIT DE ASODELTOL POR CONFIRMAR]`.
> Contacto para consultas y reclamos: `[CANAL OFICIAL POR CONFIRMAR]`.
> Versión: `[NÚMERO]`; fecha efectiva: `[FECHA]`.
>
> ASODELTOL informa las finalidades concretas para las que tratará los datos
> necesarios para `[FINALIDADES APROBADAS]`, las categorías de datos, los
> encargados y destinatarios autorizados, los plazos de conservación y los
> derechos de los titulares. Las finalidades opcionales se presentarán por
> separado cuando requieran autorización.
>
> El titular puede consultar, actualizar, rectificar, solicitar la supresión
> cuando proceda, revocar autorizaciones aplicables y presentar reclamos por el
> canal oficial. La revocación no elimina automáticamente información cuya
> conservación esté exigida por ley, contrato o defensa de derechos; esa
> excepción debe ser validada por la institución.
>
> Consecuencia de no aceptar o no responder: `[DECISIÓN INSTITUCIONAL Y
> JURÍDICA POR CONFIRMAR]`. Esta consecuencia no debe ocultar información ni
> condicionar servicios que legalmente no requieran consentimiento.

Antes de publicar deben sustituirse todos los corchetes y aprobarse el texto,
las finalidades, la base aplicable, la audiencia y las consecuencias. No se
deben añadir nombres de entidades, convenios, convocatorias o proveedores de
la captura de referencia sin confirmación documental de ASODELTOL.

## 10. Configuración propuesta para cierre

En `tool_policy`, una vez aprobado el texto:

- crear una política versionada de privacidad/tratamiento para la audiencia
  institucional definida por ASODELTOL;
- separar condiciones de uso, información de privacidad y autorizaciones de
  finalidades opcionales;
- marcar obligatoria únicamente la política/ finalidad cuya consecuencia haya
  sido aprobada;
- conservar el historial nativo de versión, decisión, fecha y usuario;
- publicar una nueva versión ante cambios materiales y repetir la prueba de
  pendiente, consulta y acceso requerido;
- mantener RSMax como aviso informativo y enlace a `/admin/tool/policy/user.php`;
  no crear casillas, tablas ni registros paralelos.

## 11. Criterios para declarar cierre funcional

### Addendum — texto suministrado por la institución

El texto recibido posteriormente está conservado literalmente en
`POLITICA_DATOS_AUTORIZACION_ASODELTOL_2026.md`. Esto actualiza el bloqueo de
“texto no identificado”, pero no resuelve la aplicabilidad de las entidades y
convocatoria al sitio ASODELTOL, ni responsable, canal, conservación o
consecuencias ante rechazo/silencio. La publicación sigue bloqueada hasta
confirmar esos parámetros.

El cierre solo puede marcarse cuando todos se cumplan y exista aceptación
institucional documentada:

1. Texto ASODELTOL aprobado, responsable, versión, fecha y canal confirmados.
2. `tool_policy` activo y `sitepolicyhandler` confirmado en la instalación
   real mediante sesión administrativa autorizada.
3. Política, audiencia y obligatoriedad visibles y coherentes con la decisión
   legal; rechazo/silencio/revocación tienen comportamiento aprobado.
4. `POLICY-03`, `POLICY-04`, `POLICY-06` y `POLICY-07` ejecutados con cuentas
   sintéticas y evidencia sanitizada.
5. `POLICY-01`, `POLICY-02`, `POLICY-05` y `POLICY-08` ejecutados en staging o
   producción solo con autorización específica, aislamiento y recuperación.
6. Se demuestra que Moodle registra decisión, versión, fecha y autoría, y que
   la consulta posterior funciona para el usuario sintético.
7. La pantalla de inicio y el acceso exigido se verifican visualmente en
   escritorio/móvil; RSMax no duplica ni suplanta el gestor nativo.
8. Existe respaldo/reversión probado y no se exportan datos personales reales.

Hasta entonces, el estado institucional correcto es `BLOCKED` / `PENDIENTE DE
APROBACIÓN`, no cumplimiento legal ni cierre funcional.
