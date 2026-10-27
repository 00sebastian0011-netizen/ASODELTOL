# Cuentas manuales y consentimiento obligatorio

## Comportamiento

Las cuentas se crean manualmente por personal autorizado. La actualización
`2026102700` de `local_asodeltolstaff` desactiva el autorregistro mediante la
configuración nativa `registerauth` y no modifica Moodle core.

El plugin no agrega campos de consentimiento al formulario de alta. La única
autoridad de consentimiento es `tool_policy`:

- la política debe estar activa, ser obligatoria y aplicar a usuarios autenticados;
- cada cuenta manual nueva conserva `policyagreed=0`;
- en el primer inicio de sesión Moodle muestra el documento y bloquea el acceso
  hasta que el usuario marque la aceptación;
- la aceptación queda versionada en `tool_policy_acceptances`;
- los ingresos posteriores no vuelven a solicitar la misma versión ya aceptada.

## Prueba local realizada

Con el usuario sintético `qa_policy_firstlogin_20261027` se verificó que:

1. antes del primer acceso tenía `policyagreed=0` y ninguna aceptación;
2. Moodle mostró la versión 2 de la política al iniciar sesión;
3. no permitió continuar con la casilla sin marcar;
4. después de aceptar registró `status=1`, idioma `es` y `policyagreed=1`;
5. el segundo inicio de sesión entró directamente al área personal.

La cuenta y la política usadas en esta comprobación pertenecen exclusivamente
al entorno local `http://127.0.0.1:8000`.

## Despliegue

El merge no equivale a un despliegue. Al instalar la versión `0.6.0`, Moodle
desactivará el autorregistro. Antes de actualizar producción se debe confirmar
que existe una política activa y obligatoria en `tool_policy`; después se deben
purgar cachés y validar el primer acceso con una cuenta manual sintética.
