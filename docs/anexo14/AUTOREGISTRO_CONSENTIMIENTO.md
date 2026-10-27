# Autorregistro: casilla en español y enlace público a la política

## Cambio puntual

En `local_asodeltolstaff`, `extend_signup_form` agrega una casilla
`asodeltol_policy_consent` **desmarcada por defecto**, independiente del campo
nativo `policyagreed` que Moodle agrega **después** del callback local.

- Texto: «He leído y acepto la Política de Tratamiento de Datos Personales de ASODELTOL».
- Enlace separado y visible: «Leer la Política de Tratamiento de Datos Personales de ASODELTOL».
- URL generada mediante `moodle_url('/admin/tool/policy/view.php', ['versionid' => ...])`
  tomando la versión activa y obligatoria de `tool_policy`. La página nativa de
  consulta admite visitantes sin sesión cuando la versión está activa.
- Se prefiere una única política activa obligatoria de tipo privacidad; si no
  existe, solo se usa la política obligatoria si es única. Ante ambigüedad,
  no se inventa un destino ni se enlazan borradores.
- `validate_extend_signup_form` impide continuar sin la aceptación, incluso
  si se omite la validación JavaScript. `post_signup_requests` verifica
  nuevamente antes del alta y conserva el campo nativo `policyagreed` para
  la aceptación versionada por el observador de `tool_policy`.

El cambio **no** modifica el núcleo, políticas publicadas, cuentas ni esquema.
No desactiva la pantalla previa de aceptación del gestor nativo
`tool_policy`; la posible doble solicitud debe comprobarse antes del cierre.

## Comprobación en el dominio oficial

1. Con ventana privada, abrir
   `https://aulaasodeltol.online/login/signup.php`.
2. Comprobar que exista exactamente un
   `input[name="asodeltol_policy_consent"][type="checkbox"]` visible,
   desmarcado y con texto en español.
3. Abrir el enlace «Leer la Política...» en una nueva ventana privada **sin
   iniciar sesión**; debe mostrar el texto de la versión publicada con HTTP 200,
   sin regresar al formulario de registro.
4. Dejar la casilla sin marcar: el servidor debe rechazar la creación de cuenta.
   No ejecutar altas reales para probarlo; usar un entorno de prueba aislado
   con datos sintéticos autorizados.
5. En staging, con cuenta sintética, verificar el alta consentida y la versión
   y fecha de aceptación registradas por la API nativa `tool_policy`.
6. Comprobar si `tool_policy_pre_signup_requests` ya requirió aceptar la
   misma política antes de abrir el formulario; no declarar ausencia de
   consentimiento duplicado sin observar todo el recorrido.

## Criterio de despliegue

La rama y el PR contienen solo cambios de código propio y pruebas.
Tras el merge, validar el hash efectivo de
`public/local/asodeltolstaff/lib.php` en el **DocumentRoot del dominio
oficial** y purgar cachés. No confundir un PR fusionado con un despliegue
comprobado. No modificar `config.php`, Moodle core ni la política activa
para instalar este ajuste.
