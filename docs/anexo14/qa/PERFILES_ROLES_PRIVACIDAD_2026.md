# Cierre de roles y privacidad de perfiles

## Alcance

`local_asodeltolstaff` aplica el límite de perfil en servidor. El rol
Beneficiario conserva únicamente su propio perfil mediante una vista que emite
nombre, cédula y correo. Las rutas nativas de perfil y edición de otro usuario
se rechazan antes de renderizarse.

Interventoría conserva lectura transversal de AF8 y SENA se crea sin
asignaciones. La actualización copia la definición de capacidades de
Interventoría y aplica las mismas capacidades de lectura y las mismas
prohibiciones de edición y exportación. Ningún usuario se asigna al rol SENA.

## Cambios versionados

- Nueva capacidad `local/asodeltolstaff:viewminimalprofile` para el rol
  `student`.
- Hook `before_standard_head_html_generation` para bloquear perfiles ajenos y
  redirigir el perfil propio del Beneficiario a la vista mínima server-side.
- `profile.php` no consulta ni imprime ciudad, dirección, nacimiento, empresa,
  barrio u otros campos.
- Upgrade `2026102801` crea `sena` mediante la API de roles y copia las
  capacidades de `asodeltol_auditor`; no crea asignaciones.
- Interventoría y SENA reciben lectura de campos ocultos y acceso transversal,
  pero prohibiciones explícitas para editar usuarios, cursos, calificaciones y
  exportar.

## Verificación

La prueba contractual local cubre el hook, el límite propio/ajeno, los tres
campos permitidos y la creación de SENA sin asignaciones. La activación en
producción requiere respaldo de roles y configuración, actualización del
plugin, purga de cachés y pruebas autenticadas con cuentas institucionales.
No se deben asignar cuentas al rol SENA hasta recibir sus identificadores
autorizados.
