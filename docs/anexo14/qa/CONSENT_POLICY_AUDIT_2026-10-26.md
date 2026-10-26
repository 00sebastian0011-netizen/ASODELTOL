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
