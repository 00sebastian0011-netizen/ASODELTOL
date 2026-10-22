# Instrucciones para agentes de desarrollo

Estas reglas aplican a Codex, Gemini y cualquier colaborador automatizado o humano en todo el repositorio.

## Antes de trabajar

1. Leer `README.md`, `docs/REQUERIMIENTOS.md`, `docs/ARQUITECTURA.md`, `docs/SEGURIDAD.md` y `docs/DECISIONES_PENDIENTES.md`.
2. Revisar `git status` y no sobrescribir cambios ajenos.
3. Confirmar que la tarea no requiere producción, SSH, credenciales, datos reales ni cambios al núcleo de Moodle.
4. Identificar los archivos exactos que se modificarán y seguir el protocolo de coordinación.

## Reglas no negociables

- No copiar, submodular ni modificar el núcleo de Moodle.
- No acceder al VPS o CloudPanel ni ejecutar despliegues o `push`.
- No solicitar, crear, mostrar o almacenar contraseñas, tokens, claves, `config.php`, `.env` o datos personales.
- No usar datos reales de estudiantes, ni siquiera anonimizados de forma informal. Las pruebas usan datos sintéticos.
- No inventar unidades, ítems, recursos, umbrales de aprobación, calendarios ni reglas contractuales.
- Mantener compatibilidad inicial con Moodle 5.0.10+ y PHP 8.4 hasta que una ADR apruebe otro objetivo.
- Usar APIs públicas de Moodle; no leer o escribir tablas del núcleo con supuestos frágiles cuando exista una API.
- Implementar control de acceso mediante capacidades y contextos de Moodle, no mediante nombres fijos de rol.
- Tratar los logs de sesión como indicios técnicos, no como prueba exclusiva de asistencia o tiempo trabajado.
- Todo plugin que trate datos personales debe implementar y probar la Privacy API correspondiente.

## Coordinación Codex/Gemini

Ningún archivo puede ser editado simultáneamente por dos agentes.

1. Cada tarea debe declarar un propietario, una rama o `worktree` y una lista cerrada de archivos.
2. Antes de editar, crear un archivo de reclamo en `docs/coordination/locks/` siguiendo `docs/coordination/README.md`.
3. Si el archivo objetivo ya está reclamado, el segundo agente se detiene o elige archivos distintos; no resuelve el conflicto editando en paralelo.
4. Los cambios compartidos de arquitectura, esquemas, dependencias o contratos se acuerdan primero en una ADR o en `docs/DECISIONES_PENDIENTES.md`.
5. La integración es secuencial: autor, revisión del otro agente, correcciones por el autor y luego incorporación por el responsable humano.
6. El revisor no mezcla una refactorización no solicitada con su revisión.
7. El archivo de reclamo se elimina sólo después de integrar o abandonar de forma explícita la tarea.

## Ubicación del código

- `plugins/local/asodeltolaudit`: auditoría de hechos de negocio propios, no réplica indiscriminada de logs.
- `plugins/local/asodeltolstaff`: disponibilidad y evidencia sincrónica del capacitador.
- `plugins/report/asodeltol`: vistas consolidadas y exportación mediante formatos estándar de Moodle.
- `plugins/availability/condition/asodeltolwindow`: ventana de días hábiles y excepciones.
- Las pruebas unitarias y Behat específicas viven dentro del `tests/` de cada plugin.
- `tests/` en la raíz contiene estrategia, datos sintéticos compartidos y pruebas de aceptación transversales.

No añadir un tipo de plugin genérico `local` si Moodle ofrece un tipo específico apropiado.

## Convenciones

- Idioma de documentación y experiencia funcional: español.
- Código, nombres de clases, componentes y commits: convenciones oficiales de Moodle e inglés técnico cuando lo exija la API.
- Seguir Moodle Coding Style, PHPDoc, namespaces Frankenstyle y APIs de acceso, eventos, tareas, Privacy, Backup y Report builder/Data formats.
- Las migraciones de base de datos son incrementales y reversibles mediante restauración; nunca editar una migración ya publicada.
- Los registros auditables son anexables: una corrección crea otro evento con actor, fecha, motivo y referencia al anterior.
- Las dependencias externas deben fijarse por versión y checksum en el inventario de la versión; no descargar código en tiempo de ejecución.

## Calidad mínima para código futuro

Antes de solicitar revisión:

1. Ejecutar `scripts/quality/validate-repository.ps1`.
2. Ejecutar PHP lint y Moodle Code Checker/PHPCS sobre el plugin.
3. Ejecutar PHPUnit 11 mediante el entorno de Moodle 5.0.
4. Ejecutar los escenarios Behat afectados en una instancia de pruebas aislada.
5. Probar permisos positivos y negativos por contexto y grupo.
6. Probar exportación/eliminación de datos personales y respaldo/restauración cuando aplique.
7. Actualizar documentación, ADR y matriz de trazabilidad.

La ausencia de un entorno local de Moodle no permite declarar que un plugin está validado; debe registrarse como pendiente.

## Definición de terminado

Una tarea termina sólo si su alcance está implementado o documentado, tiene pruebas proporcionales al riesgo, no introduce secretos ni datos personales, conserva compatibilidad, actualiza decisiones relevantes y deja instrucciones verificables de reversión. La aprobación y el despliegue a producción siempre pertenecen a un flujo separado y autorizado.

