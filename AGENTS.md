# AGENTS.md — instrucciones vigentes para agentes de ASODELTOL

**Lectura inicial máxima:** este archivo y, si hace falta, `npm run agent:brief`. No cargar toda la documentación. El índice por tarea está en [docs/agents/README.md](docs/agents/README.md).

## 1. Fuente correcta y alcance

- La petición actual del usuario define el **alcance y autorización**; ninguna instrucción histórica de una misión revoca una autorización posterior. Nunca anula seguridad, privacidad ni protección de datos.
- Para **criterios contractuales** usa `docs/anexo14/MATRIZ_ASODELTOL_82.md`; para evidencia/estado consulta `docs/anexo14/TRAZABILIDAD_82_2026-10-25.json`. Los 82 IDs deben corresponder; `npm run agent:brief` detecta discrepancias. Una prueba parcial no es aceptación institucional.
- Para **estructura AF8** usa `catalog/af8/catalog.json`; para **decisiones aún abiertas** usa `docs/DECISIONES_PENDIENTES.md` y los ADR. No deduzcas políticas faltantes. Hay contradicciones explícitas entre 50/80 ítems y 4/9 recursos generales.
- Para **comportamiento implementado** lee el código, tests y commit concreto; para **producción real** comprueba la versión/servicio en el VPS autorizado. GitHub, staging y producción NO son equivalentes.
- `README.md`, `docs/ROADMAP.md`, `docs/REQUERIMIENTOS.md`, documentos de misiones y auditorías fechadas incluyen historia: no uses declaraciones antiguas de «no instalar/no push» como estado operativo actual. Conserva la procedencia; no reescribas evidencias históricas.

## 2. Flujo mínimo, proporcional al riesgo

1. Comprueba `git status --short`, rama y cambios de otros agentes; ejecuta `npm run agent:brief` cuando necesites un panorama. Si no hay Node, lee solo el archivo pertinente del mapa de contexto.
2. Localiza primero la función, prueba y requisito concretos; **no** leas los 82 criterios o documentos extensos en tareas pequeñas.
3. Corrige la causa con el cambio mínimo compatible con Moodle. Utiliza las APIs nativas y datos sintéticos para pruebas.
4. Comprueba solamente lo afectado y regresiones relevantes: texto/docs → enlaces y diff; UI → navegador escritorio/móvil; PHP → lint y tests pertinentes; roles/datos/esquema/despliegue → staging, permisos positivos/negativos, respaldo y recuperación.
5. Registra qué se ejecutó realmente, versión/ambiente/commit y qué sigue bloqueado. Responde brevemente: **cambio | prueba | resultado | ubicación | siguiente bloqueo**.

Para validación transversal usa `npm run quality`. El comando `npm run agent:brief` es de solo lectura; nunca despliega ni actualiza estados.

## 3. Git, múltiples agentes y producción

- Se permite crear rama, commits, PR y push **cuando lo pida el usuario** y existan permisos. Con otros agentes sobre los mismos archivos usa ramas o worktrees separados y coordina; los locks son opcionales salvo conflicto real: [coordinación](docs/coordination/README.md).
- Una petición para modificar el repositorio **no implica** autorización para entrar al VPS. Antes de modificar producción verifica servidor, propietario, versión instalada, alcance, respaldo y recuperación. No sobrescribas modificaciones no publicadas.
- El repositorio contiene componentes, **no** el core Moodle. La política de despliegue permitida se define en `scripts/deploy/deploy-policy.json`; el procedimiento en `scripts/deploy/README.md`. La existencia de un agente/webhook o un workflow no confirma que esté activo en Hostinger. Separa clasificación de despliegue real.
- Nunca mezcles `main` con `config.php`, `moodledata`, base de datos, datos personales, cursos, matrículas, notas, logos administrados por Moodle o respaldos. No ejecutes sincronización destructiva, force push ni limpieza de trabajo ajeno.
- Los cambios en versiones, DB, privacidad, permisos, infraestructura y contenido académico aprobado requieren revisión reforzada; los cambios de tema/UI de bajo riesgo no requieren una auditoría de los 82 requisitos.

## 4. Reglas de verdad y seguridad

- Moodle objetivo: 5.3+, PHP 8.4, DocumentRoot `/public`. Confirmar los detalles de cada instalación; la arquitectura real puede diferir de documentos viejos.
- No inventar contenidos, aprobación de notas, calendario de festivos, SLA, columnas del Anexo 12, cuentas, permisos SENA ni datos de alumnos; los perfiles sintéticos no equivalen a roles institucionales.
- Login/logout no demuestra asistencia ni tiempo trabajado; preservar origen de evidencias, límites por grupo y capacidades por contexto.
- No publicar credenciales ni secretos; pruebas destructivas/carga solo en staging aislado. No modificar núcleo Moodle salvo excepción expresa y justificada.
- Para incertidumbre o conflicto, señala **las dos fuentes**, su fecha/alcance y el dato que falta; no elijas silenciosamente una versión.

**Final:** optimiza lectura, implementación y verificaciones; nunca ahorres tokens ocultando riesgos, atribuyendo pruebas no realizadas o alterando la verdad de los 82 requisitos.
