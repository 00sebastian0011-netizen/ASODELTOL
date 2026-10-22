# Aprovisionamiento reproducible de AF8

## 1. Alcance y estado

Este procedimiento transforma el catálogo versionado en insumos aceptados por mecanismos oficiales de Moodle 5.3. El generador es local, no abre conexiones de red y no modifica Moodle.

El aprovisionamiento ya fue demostrado en el **Moodle 5.3 local aislado** con datos sintéticos: existe el curso AF8 piloto oculto y una edición sintética independiente creada desde una plantilla estructural MBZ. El MBZ y las exportaciones permanecen en `build/`, fuera de Git. Esta evidencia local no autoriza ni implica aprovisionamiento en producción.

## 2. Artefactos fuente

- `catalog/af8/catalog.json`: fuente canónica de curso, unidades, componentes, grupos y progresión.
- `catalog/af8/access-control-matrix.json`: contrato de permisos y pruebas negativas; no es un archivo importable de roles.
- `catalog/schema/af8-catalog.schema.json`: forma interoperable del catálogo.
- `scripts/catalog/validate-catalog.mjs`: validaciones estructurales y cruzadas.
- `scripts/catalog/build-provisioning-bundle.mjs`: generador sin dependencias ni conexión a Moodle.

No se deben editar los CSV generados para corregir el catálogo. La corrección se hace en la fuente JSON, se ejecutan las pruebas y se genera un directorio nuevo.

## 3. Validación y generación local

Requisito: Node.js 20 o posterior. No se requiere `npm install` porque las herramientas no tienen dependencias externas.

```powershell
npm run catalog:validate
npm test
node scripts/catalog/build-provisioning-bundle.mjs --category-idnumber=ASODELTOL-STAGING
```

`ASODELTOL-STAGING` es sólo un ejemplo seguro. El operador debe sustituirlo por el `idnumber` de una categoría **ya existente y aprobada**. `--category-idnumber` es obligatorio: el generador no usa `AF8` ni ningún otro valor predeterminado, rechaza un valor ausente o potencialmente peligroso y nunca sobrescribe un directorio existente.

La salida ignorada por Git, `build/provisioning/af8/`, contiene:

| Archivo | Uso |
|---|---|
| `cohorts-upload.csv` | Importar siete cohortes por la interfaz oficial. No contiene usuarios. |
| `course-upload.csv` | Crear el curso oculto, activar finalización, forzar grupos separados y añadir siete sincronizaciones cohorte/grupo. |
| `course-structure.json` | Plano resuelto de secciones y actividades; no se presenta como importador Moodle. |
| `access-control-matrix.json` | Copia exacta de la matriz que debe probarse en staging. |
| `operator-plan.json` | Precondiciones, orden y paradas obligatorias. |
| `manifest.json` | Hashes SHA-256 de fuentes y artefactos, conteos y estado `not_provisioned`. |

El manifiesto no se incluye a sí mismo en su lista de hashes para evitar una referencia circular.

## 4. Precondiciones del staging

Un operador autorizado debe verificar y registrar evidencia de todas estas condiciones antes de importar:

1. La restauración de código, base de datos y `moodledata` fue probada y es consistente.
2. El staging ejecuta Moodle 5.3 con la matriz PHP/Percona validada y DocumentRoot en `/public`.
3. La categoría de destino existe y su `idnumber` coincide con el valor entregado al generador.
4. La matriculación por sincronización de cohortes está habilitada.
5. El operador tiene capacidades mínimas para crear cohortes, cursos y métodos de matriculación.
6. Sólo se usarán cuentas y datos inequívocamente sintéticos.
7. Hay un punto de retorno probado del staging y una ventana aprobada.

Si falla una precondición, no se continúa. Ninguna de ellas autoriza una operación equivalente en producción.

## 5. Flujo oficial de Moodle 5.3

Parámetros obligatorios para ambas cargas CSV:

- codificación: **UTF-8**;
- separador: **coma (`comma`, `,`)**;
- crear primero la categoría Moodle de destino;
- generar el paquete con un `--category-idnumber` que coincida **exactamente** con el `idnumber` de esa categoría existente;
- importar y previsualizar primero en el staging aislado; no usar producción;
- mantener el curso oculto y no cargar estudiantes reales.

Una discrepancia de categoría, codificación o separador detiene la operación y exige generar/previsualizar nuevamente; no se corrige editando el CSV a mano.

### 5.1 Cohortes

En `Site administration > Users > Accounts > Cohorts > Upload cohorts`, cargar `cohorts-upload.csv`, seleccionar codificación UTF-8 y separador coma, previsualizar las siete filas y confirmar que los `idnumber` son `AF8-G01` a `AF8-G07`. La carga oficial admite `name`, `idnumber`, `description`, `descriptionformat` y `visible`; el paquete inicial marca las cohortes como no visibles hasta completar el piloto.

No se cargan miembros. La capacidad de 50 es una expectativa del catálogo, no una cuota nativa impuesta por este CSV.

### 5.2 Curso, sincronizaciones y grupos

En `Site administration > Courses > Upload courses`, cargar `course-upload.csv` con codificación UTF-8 y separador coma, seleccionar el modo que crea cursos nuevos y actualiza los existentes, y usar primero la previsualización en staging. Deben aparecer siete filas para el mismo curso: la primera lo crea y las seis restantes lo actualizan para añadir una sincronización distinta por fila. Esta forma sigue el contrato real del cargador nativo de Moodle para varios métodos del mismo tipo. Verificar:

- `shortname=AF8`, `idnumber=ASODELTOL-AF8` y curso oculto;
- formato por temas y seguimiento de finalización activo;
- `groupmode=1` y `groupmodeforce=1`;
- un método `cohort` por fila, con rol `student` y `addtogroup=1`, que en conjunto cubran `AF8-G01` a `AF8-G07`.

Moodle 5.3 crea con `enrolment_N_addtogroup=1` un grupo vinculado a la sincronización y aplica por defecto el nombre `<nombre de cohorte> cohort` (por ejemplo, `AF8-G01 cohort`). Las cohortes deben existir antes.

El CLI oficial también existe en Moodle 5.3:

```text
php public/admin/tool/uploadcourse/cli/uploadcourse.php --mode=createorupdate --updatemode=dataonly --file=course-upload.csv --delimiter=comma --encoding=UTF-8
```

Ese comando es referencia para un operador del staging. La interfaz web se prioriza en la primera prueba porque permite previsualizar filas y errores. No se habilitan borrado, renombrado ni reset. `createorupdate` es necesario porque Moodle procesa secuencialmente las siete filas del mismo curso.

### 5.3 Estructura y actividades

`course-structure.json` no es un endpoint ni un formato de importación de Moodle. Se utiliza como lista de control para una de estas alternativas reales:

1. configurar el curso oculto mediante la edición estándar de Moodle y auditarlo contra el plano;
2. crear y validar en staging un curso plantilla, y después usar la carga de cursos con `templatecourse` o los mecanismos oficiales de backup/restauración;
3. sólo si la repetición y el riesgo lo justifican, implementar posteriormente un componente administrativo Moodle que consuma el catálogo mediante APIs internas soportadas.

La segunda alternativa quedó validada en el staging local durante la Misión 09. La copia reutilizable excluye usuarios, asignaciones de rol, grupos, actividades, archivos, comentarios, finalizaciones, logs e historiales de calificación. Conserva las cinco secciones oficiales y la configuración general del curso. Cada edición debe recibir nuevos `shortname`, `idnumber`, categoría, fechas, cohortes, grupos, métodos de matrícula y capacitadores. El procedimiento administrativo reproducible y las comprobaciones posteriores están en `OPERACION.md`.

No se automatizan aún las actividades porque los componentes Carpeta/PDF, URL/H5P y Video URL/Archivo no tienen tipo aprobado, falta el contenido y no existe una API externa uniforme que configure todos esos módulos. `core_courseformat_new_module` es una operación de creación rápida limitada a módulos que ofrecen `FEATURE_QUICKCREATE`; no reemplaza los formularios completos, archivos, bancos de preguntas ni criterios de finalización.

### 5.4 Progresión nativa

Una vez aprobados los materiales y reglas, el operador configura en Moodle:

1. criterios objetivos de finalización en las actividades obligatorias;
2. categoría de calificación por unidad y nota aprobatoria aprobada;
3. finalización de unidad basada en los criterios definidos;
4. restricción de acceso de UT2–UT5 por finalización y aprobación de la unidad anterior;
5. fechas/overrides por grupo sólo cuando exista calendario aprobado.

El catálogo conserva `passing_grade`, `start_trigger`, `calendar_id`, fechas y política de excepciones como pendientes. Los ocho días hábiles están representados y validados, pero no se calculan ni aplican todavía. No se implementó el plugin temporal.

## 6. Navegación y experiencia Moodle

La interfaz se construye con el formato por temas y componentes del tema Moodle aprobado, sin React ni una aplicación paralela:

1. sección general visible como portada: información institucional, objetivos, cronograma/orientaciones y foro;
2. índice del curso y bloque/indicador nativo de finalización para mostrar avance;
3. cinco secciones con el mismo orden de diez componentes y encabezados consistentes;
4. restricciones nativas que expliquen por qué una unidad está bloqueada y qué falta para abrirla;
5. acceso nativo a calificaciones, calendario/próximas sesiones, recursos y enlaces de grabaciones aprobados.

Los contenidos deben usar jerarquía semántica de encabezados, navegación por teclado, texto alternativo, subtítulos/transcripción para video, contraste suficiente y lenguaje claro. Los PDFs deben ser accesibles; si no lo son, se ofrece alternativa HTML. La revisión cubre anchos móvil/escritorio y navegadores soportados por Moodle 5.3. No se oculta información esencial sólo por color o iconografía.

## 7. Verificación posterior en staging

Manteniendo el curso oculto, registrar evidencia de:

- un curso y exactamente cinco unidades en orden UT1–UT5;
- exactamente 50 componentes y cuatro recursos generales, sin contenido inventado;
- siete cohortes, siete grupos homónimos y siete métodos de sincronización;
- `groupmode=1`, `groupmodeforce=1` y seguimiento de finalización activo;
- cero personas reales y curso no visible para participantes ordinarios;
- matriz completa de pruebas negativas con al menos dos grupos sintéticos;
- pruebas de navegación móvil, teclado, lector de pantalla y contraste;
- comparación de identificadores/cantidades con `manifest.json`.

Un fallo de aislamiento bloquea la arquitectura de un solo curso y reactiva la alternativa de siete cursos definida en ADR-0005.

## 8. Reversión y evidencia

La reversión se ensaya sólo en staging: restaurar el punto de retorno validado o retirar mediante la interfaz los objetos creados después de confirmar sus identificadores y que no contienen personas. No se proporcionan scripts de borrado automático.

Conservar fuera del repositorio los logs de operación, capturas y exportaciones que pudieran contener datos. En el repositorio sólo se registra el resultado sanitizado, versiones, hashes, hallazgos y decisión de continuar o revertir.

## 9. Fuentes oficiales

- [Upload courses — Moodle 5.3](https://docs.moodle.org/503/en/Upload_courses)
- [Adding a new course and course templates — Moodle 5.3](https://docs.moodle.org/503/en/Adding_a_new_course)
- [Upload cohorts — Moodle 5.3](https://docs.moodle.org/503/en/cohort/upload)
- [Cohort sync — Moodle 5.3](https://docs.moodle.org/503/en/Cohort_sync)
- [CLI de carga de cursos en `MOODLE_503_STABLE`](https://github.com/moodle/moodle/blob/MOODLE_503_STABLE/public/admin/tool/uploadcourse/cli/uploadcourse.php)
- [Implementación de creación rápida de módulos en `MOODLE_503_STABLE`](https://github.com/moodle/moodle/blob/MOODLE_503_STABLE/public/course/format/classes/external/new_module.php)
- [External Services API — Moodle 5.3](https://moodledev.io/docs/5.3/apis/subsystems/external)
