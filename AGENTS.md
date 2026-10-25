# AGENTS.md — Trabajo ágil y autónomo en ASODELTOL

Estas instrucciones se aplican a Codex, Gemini y demás agentes que trabajen en este repositorio. **Prioridad: ejecutar la solicitud del usuario de forma directa, rápida, completa y comprobable, evitando burocracia, auditorías repetidas y cambios no pedidos.**

## 1. Principios operativos

- **Actúa, no te quedes planificando.** Si la solicitud es clara, inspecciona los archivos pertinentes, implementa y comprueba. No pidas confirmaciones redundantes ni propongas planes extensos para trabajos sencillos.
- **Autonomía por defecto.** Puedes investigar, editar, refactorizar puntualmente, crear archivos, corregir errores y ejecutar pruebas locales necesarias dentro del alcance solicitado, sin autorización adicional por cada paso.
- **Intervención mínima.** Conserva la lógica académica y la funcionalidad que ya existe. No reescribas componentes estables si basta un ajuste localizado.
- **Diagnostica la causa, no acumules parches.** En fallos visuales inspecciona DOM, estilos calculados, JS, rutas de recursos y configuración antes de editar CSS a ciegas.
- **No repitas trabajo acreditado.** Aprovecha pruebas y evidencias previas que sigan vigentes; vuelve a probar únicamente lo afectado y las regresiones indispensables.
- **Termina el trabajo.** Si encuentras defectos directamente relacionados con la petición, corrígelos y comprueba su resultado antes de darla por concluida. Informa los bloqueos reales, sin declarar éxitos no comprobados.
- **Comunicación breve.** Explica en pocas líneas qué cambiaste, qué probaste, dónde quedó y qué requiere decisión externa. Evita reportes largos salvo petición expresa.

## 2. Inicio rápido (sin lectura obligatoria de todo el proyecto)

1. Revisa `git status --short`, la rama activa y el diff relevante. No sobrescribas trabajo de otros agentes o del usuario.
2. Busca primero los archivos, configuraciones y pruebas vinculados a la solicitud. Lee documentación específica **solo cuando sea necesaria**; no hay obligación de releer todos los documentos en cada tarea.
3. Elige el nivel de validación según el riesgo. Haz los cambios dentro del alcance sin esperar nuevas aprobaciones para detalles técnicos rutinarios.
4. Si una instrucción histórica de una misión impide una acción que el usuario acaba de autorizar, distingue la restricción temporal antigua de las salvaguardas vigentes. Aplica esta política actual sin ignorar controles de seguridad o privacidad.

### Vía rápida — ajustes de bajo riesgo

Ejemplos: texto, CSS/SCSS, colores, alineación, logos autorizados, componentes visuales, navegación y ajustes de presentación sin alterar permisos ni datos.

- Inspecciona solo archivos pertinentes y realiza un diff pequeño.
- Ejecuta lint o comprobaciones focalizadas si aplican; verifica en navegador el componente afectado y al menos un tamaño móvil si cambia la interfaz.
- No exijas auditoría integral, Behat completo, PHPUnit global, inventario de dependencias, ADR, lock ni revisión por otro agente **cuando no exista conflicto real ni se hayan tocado áreas de riesgo**.
- Purga cachés de Moodle solo si hace falta y comprueba que el cambio sea visible.
- Cierra con un resumen breve y evidencia suficiente. No prolongues la tarea con cambios cosméticos no solicitados.

### Vía estándar — cambios funcionales acotados

Ejemplos: recursos y cursos de demostración, formularios, navegación con JS, plugins existentes, reportes o automatizaciones sin migraciones.

- Examina contratos y permisos afectados.
- Usa datos sintéticos en staging para altas, modificaciones y pruebas.
- Ejecuta pruebas funcionales del recorrido modificado, comprobaciones de permisos cuando correspondan y una regresión básica.
- Evita ejecutar varias veces baterías idénticas si el código relevante no cambió.
- Documenta únicamente decisiones o procedimientos que de verdad hayan cambiado.

### Vía reforzada — cambios de alto riesgo

Ejemplos: autenticación, autorizaciones, datos personales, calificaciones reales, migraciones de BD, infraestructura, dependencias, Moodle core, restauraciones y operaciones destructivas.

- Confirma alcance, entorno y autorización del usuario para la acción de alto impacto.
- Haz respaldo o verifica recuperación cuando corresponda; utiliza staging, validaciones de seguridad y plan de reversión.
- Prueba rutas autorizadas y denegadas, integridad de datos y comportamiento de la función.
- No eludas controles críticos por ahorrar tiempo; evita, eso sí, repetir auditorías completas sin motivo técnico.

## 3. Autorización para Git, VPS y despliegue

- **Se permite trabajar con Git:** crear ramas, commits, diffs, pruebas, pull requests y `push` cuando sea parte de la tarea solicitada y exista acceso autorizado. No existe una prohibición general de `push`.
- **Se permite trabajar con Hostinger, VPS, SSH y CloudPanel** cuando la petición incluya inspección, corrección o publicación allí y el agente disponga de acceso autorizado. No existe una prohibición general de operar producción.
- La orden explícita «publica», «despliega», «corrige en producción» o equivalente autoriza el flujo técnico de despliegue habitual, **no** la destrucción de datos, cambio de dominio, compra de servicios o modificación de reglas académicas sin autorización específica.
- Antes de editar producción, identifica la instalación activa y verifica respaldo/reversión proporcional al riesgo. Prefiere desplegar artefactos probados desde Git y comprobar el sitio después.
- Si el usuario solo solicita un cambio en el repositorio, **no presupongas permiso para desplegar producción**.
- Nunca hagas `reset --hard`, `clean -fd`, `force push`, borrado de respaldo o sobrescritura de trabajo ajeno sin autorización específica e inspección del impacto.
- Si hay una rama o `worktree` de otra misión con cambios sin integrar, coordina y preserva esos cambios; no los reemplaces silenciosamente.
- No des por sincronizados el repositorio `main`, el árbol local y los archivos desplegados: identifica la versión que realmente estás modificando.

## 4. Colaboración sin burocracia

- **Un solo agente sobre archivos no reclamados:** trabaja directamente; no necesitas crear archivos de bloqueo, pedir revisor independiente ni tramitar aprobaciones internas para cambios rutinarios.
- **Dos o más agentes sobre los mismos archivos:** asigna responsables o ramas/`worktrees` separados y evita ediciones simultáneas. Utiliza `docs/coordination/locks/` solo cuando haya solapamiento o un bloqueo activo que deba respetarse.
- No ignores un lock activo ni lo borres sin verificar que la tarea anterior terminó; los locks huérfanos requieren verificación.
- Las revisiones cruzadas y ADR son recomendables para cambios compartidos de arquitectura, base de datos, contratos o permisos; no son un requisito universal para corregir un botón o un estilo.
- El objetivo es coordinación efectiva, no producir documentación administrativa.

## 5. Contexto técnico y reglas que se deben conservar

- Objetivo actual: **Moodle 5.3 LTS, PHP 8.4, DocumentRoot `/public`**, con MySQL compatible; evita volver a las reglas provisionales de Moodle 5.0.
- Este repositorio versiona extensiones, catálogo, documentación y automatizaciones; **no** debe incluir el núcleo completo de Moodle, `moodledata`, respaldos, volcados de BD ni datos personales.
- Prefiere APIs y mecanismos nativos de Moodle, temas y plugins compatibles. Evita modificar el núcleo cuando exista una alternativa mantenible; cualquier excepción requiere justificación técnica y control reforzado.
- Conserva la estructura oficial del diplomado AF8, cinco unidades, siete grupos, roles y reglas académicas. No inventes contenidos, notas aprobatorias, fechas ni políticas institucionales.
- El control de acceso se basa en capacidades y contextos Moodle, no en comparar nombres textuales de roles. Interventoría aplica mínimo privilegio.
- Los logs de acceso no demuestran por sí solos asistencia efectiva ni tiempo trabajado.
- Usa la Privacy API y las APIs de seguridad/archivos cuando correspondan. No confundas un prototipo que existe en Git con un plugin instalado o validado en producción.

## 6. Seguridad esencial sin bloqueos innecesarios

- **Nunca publiques secretos ni datos personales** en Git, logs, capturas o resultados del agente. Protege `.env`, claves, `config.php`, tokens, respaldos y matrículas.
- Se permite crear cuentas de prueba o credenciales temporales **si el usuario lo solicita y el entorno lo permite**; entrega o restablece el acceso por un mecanismo seguro, sin exponer la contraseña en commits, historial, logs ni respuestas compartidas.
- Prueba con datos sintéticos en staging; no hagas cargas masivas o pruebas destructivas con datos reales sin aprobación.
- No cambies permisos, calificaciones oficiales, políticas de privacidad, DNS/dominio, facturación ni servicios con costo sin autorización específica.
- Mantén HTTPS, validación de formularios, `sesskey`, capacidades y protección de datos. Nunca desactives controles de seguridad solo para que pase una prueba.
- Antes de instalar dependencias o plugins nuevos, comprueba versión, procedencia y compatibilidad; no reconstruyas dependencias cuando la tarea no lo requiera.

## 7. Verificación eficiente y definición de terminado

### Protección visual y despliegue

Los recursos institucionales se versionan solo con procedencia comprobada en `assets/branding/asodeltol/`. El agente persistente documentado en `scripts/deploy/` puede publicar desde `main` únicamente `theme_rsmax` y `local_asodeltolstaff`, según la clasificación automática. Protege divergencias hechas directamente en Hostinger, usa respaldos por componente y no toca `config.php` principal, `moodledata`, base de datos, cursos ni archivos ajenos.

CSS, recursos compilados, Mustache y traducciones siguen la ruta rápida; PHP sin migraciones sigue la ruta estándar. Esquema, versiones, privacidad, autenticación e infraestructura quedan bloqueados para intervención reforzada. El workflow de GitHub registra la clasificación en paralelo y no contiene credenciales SSH de producción.

| Cambio | Verificación suficiente por defecto |
| --- | --- |
| Documentación o textos | Diff, ortografía/enlaces, sin secretos |
| Estilo/tema/UI | Revisión localizada, captura/navegación real, escritorio y móvil |
| JavaScript/rutas/menús | Comportamiento real, consola/red sin errores pertinentes, login/logout si los afecta |
| PHP/plugin | Lint y pruebas unitarias/funcionales afectadas, permisos si aplica |
| Datos/roles/seguridad | Staging, pruebas positivas/negativas, integridad, respaldo/reversión |
| Despliegue | Comprobación posterior en la URL real y posibilidad de volver atrás |

- `npm run quality`, `scripts/quality/validate-repository.ps1`, PHPUnit, PHPCS y Behat siguen disponibles, pero **no se ejecutan todos automáticamente por cada cambio**. Selecciona lo relevante y ejecuta la suite completa cuando exista un motivo o sea una entrega de alto impacto.
- No confundas HTTP 200 con funcionalidad verificada: si corriges un desplegable, haz clic y comprueba que abre/cierra.
- No inventes resultados de pruebas. Si no puedes controlar el navegador o no existe acceso al entorno, dilo claramente y proporciona una verificación reproducible.
- Una tarea está terminada cuando el alcance pedido funciona, se ejecutaron las pruebas proporcionales al riesgo, se preservaron los datos y el usuario sabe si quedó en local, rama, GitHub o producción.
- Prioriza resultados utilizables **en la misma intervención**, no rondas interminables de diagnóstico o propuestas sin implementación.

**Regla final:** rapidez y autonomía para desarrollar lo que el usuario pide; cuidado reforzado solo donde el cambio realmente pueda afectar seguridad, datos o continuidad del campus.
