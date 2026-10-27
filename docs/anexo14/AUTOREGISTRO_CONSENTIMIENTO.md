# Autorregistro y consentimiento nativo

La integración usa los callbacks nativos de Moodle para `login/signup.php`:

1. `extend_signup_form` agrega el checkbox desmarcado únicamente cuando
   `sitepolicyhandler=tool_policy` y Moodle devuelve una URL de una política
   publicada. No se construye una URL propia ni se enlaza el borrador pendiente.
2. `validate_extend_signup_form` rechaza en servidor la ausencia de aceptación.
3. `post_signup_requests` vuelve a comprobar la aceptación antes de que el
   autenticador cree la cuenta y establece `policyagreed=1`.
4. El evento nativo de usuario creado de `tool_policy` registra la aceptación
   asociada a la versión publicada; no se crea una tabla ASODELTOL paralela.

## Verificación autorizada

En staging, con cuentas sintéticas y una política aprobada/publicada:

- aceptación: el checkbox se marca, la cuenta se crea y la consulta nativa de
  aceptaciones muestra la versión y fecha;
- rechazo: el checkbox permanece desmarcado, el POST falla en servidor y no se
  crea cuenta;
- enlace: `Ver política` abre la URL devuelta por el gestor nativo y no una URL
  codificada en el plugin.

Estas pruebas funcionales no se ejecutan en producción ni en este commit. Sin
`tool_policy` activo y sin política publicada, el comportamiento correcto es no
mostrar el control ASODELTOL y no inventar un enlace.
