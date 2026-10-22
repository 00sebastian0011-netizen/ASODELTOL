#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { access, mkdir, readFile, writeFile } from 'node:fs/promises';
import { constants as fsConstants } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { pathToFileURL } from 'node:url';
import { readJsonFile, validateAccessMatrix, validateCatalog } from './lib/catalog-validator.mjs';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, '..', '..');
const CATEGORY_IDNUMBER_PATTERN = /^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/;
const CSV_FORMULA_PREFIX_PATTERN = /^[\s\uFEFF\u200B-\u200F\u202A-\u202E\u2060-\u206F]*[=+\-@]/u;

export function csvCell(value, fieldName = 'unknown') {
  const text = String(value ?? '');
  if (text.includes('\0')) {
    throw new Error(`Valor CSV inválido en ${fieldName}: contiene un carácter nulo.`);
  }
  if (typeof value === 'string' && CSV_FORMULA_PREFIX_PATTERN.test(text)) {
    throw new Error(`Valor CSV peligroso en ${fieldName}: inicia con un indicador de fórmula.`);
  }
  return `"${text.replaceAll('"', '""')}"`;
}

function csvRow(values, fieldNames = []) {
  return `${values.map((value, index) => csvCell(value, fieldNames[index] ?? `column_${index + 1}`)).join(',')}\n`;
}

async function pathExists(path) {
  try {
    await access(path, fsConstants.F_OK);
    return true;
  } catch (error) {
    if (error.code === 'ENOENT') {
      return false;
    }
    throw error;
  }
}

function sha256(content) {
  return createHash('sha256').update(content).digest('hex');
}

function resolveCourseStructure(catalog) {
  const templates = new Map(catalog.component_templates.map((template) => [template.template_id, template]));
  return {
    schema_version: catalog.schema_version,
    source_catalog_id: catalog.catalog_id,
    import_status: 'blueprint_requires_operator_configuration_in_staging',
    general_section: catalog.general_resources,
    units: catalog.units.map((unit) => ({
      code: unit.code,
      stable_id: unit.stable_id,
      position: unit.position,
      title: unit.title,
      duration_hours: unit.duration_hours,
      depends_on_unit: unit.depends_on_unit,
      passing_grade: unit.passing_grade,
      temporal_rule: unit.temporal_rule,
      components: unit.components.map((component) => {
        const template = templates.get(component.template_id);
        return {
          ...component,
          title: template.title,
          moodle_type_options: template.moodle_type_options,
          selected_moodle_type: template.selected_moodle_type,
          required_for_unit_completion: template.required_for_unit_completion
        };
      })
    }))
  };
}

function buildCohortCsv(catalog) {
  const headers = ['name', 'idnumber', 'description', 'descriptionformat', 'visible'];
  let content = csvRow(headers, headers);
  for (const group of catalog.groups) {
    const values = [
      group.code,
      group.cohort_idnumber,
      `Cohorte académica prevista para ${group.code}; capacidad prevista ${group.planned_capacity}.`,
      2,
      0
    ];
    content += csvRow(values, headers);
  }
  return content;
}

function buildCourseCsv(catalog, categoryIdnumber) {
  const baseHeaders = [
    'shortname',
    'fullname',
    'idnumber',
    'category_idnumber',
    'format',
    'visible',
    'groupmode',
    'groupmodeforce',
    'enablecompletion',
    'newsitems'
  ];
  const baseValues = [
    catalog.course.shortname,
    catalog.course.fullname,
    catalog.course.idnumber,
    categoryIdnumber,
    catalog.course.format,
    0,
    1,
    1,
    1,
    0
  ];
  // Moodle indexes enrolment data by plugin name, so multiple `cohort` methods
  // in one row overwrite each other. Its supported representation is one row
  // per method, repeating the course identity (as in Moodle's own fixtures).
  const enrolmentHeaders = [
    'enrolment_1',
    'enrolment_1_cohortidnumber',
    'enrolment_1_role',
    'enrolment_1_addtogroup'
  ];
  const headers = [...baseHeaders, ...enrolmentHeaders];
  let content = csvRow(headers, headers);
  for (const group of catalog.groups) {
    content += csvRow([...baseValues, 'cohort', group.cohort_idnumber, 'student', 1], headers);
  }
  return content;
}

function buildOperatorPlan(catalog, categoryIdnumber) {
  return {
    schema_version: '1.0.0',
    target: 'moodle_5_3_staging',
    production_authorized: false,
    source_catalog_id: catalog.catalog_id,
    approved_category_idnumber_input: categoryIdnumber,
    course_visibility_after_upload: 'hidden',
    prerequisites: [
      'Restauración local o de staging validada con datos sintéticos.',
      'Moodle 5.3 y PHP/BD compatibles verificados.',
      `Categoría existente con idnumber ${categoryIdnumber}.`,
      'Plugin de matriculación por sincronización de cohortes habilitado.',
      'Operador con capacidades de crear cohortes, cursos y métodos de matriculación.'
    ],
    ordered_actions: [
      {
        order: 1,
        artifact: 'cohorts-upload.csv',
        official_mechanism: 'Site administration > Users > Accounts > Cohorts > Upload cohorts',
        mode: 'preview_then_import',
        result: 'Crea las siete cohortes por idnumber; no crea personas.'
      },
      {
        order: 2,
        artifact: 'course-upload.csv',
        official_mechanism: 'Site administration > Courses > Upload courses',
        cli_equivalent: 'php public/admin/tool/uploadcourse/cli/uploadcourse.php --mode=createorupdate --updatemode=dataonly --file=course-upload.csv --delimiter=comma --encoding=UTF-8',
        mode: 'preview_in_web_ui_before_any_cli_execution',
        result: 'Crea AF8 oculto, fuerza grupos separados, activa completion y crea siete sincronizaciones/grupos.'
      },
      {
        order: 3,
        artifact: 'course-structure.json',
        official_mechanism: 'Edición estándar del curso o curso plantilla validado y restauración/importación oficial',
        mode: 'operator_configuration_and_independent_audit',
        result: 'Configura secciones y actividades únicamente cuando tipo, contenido y finalización estén aprobados.'
      },
      {
        order: 4,
        artifact: 'access-control-matrix.json',
        official_mechanism: 'Roles, capacidades y contextos nativos de Moodle',
        mode: 'synthetic_security_pilot',
        result: 'Ejecuta todas las pruebas negativas antes de considerar publicación.'
      }
    ],
    non_automated_reasons: [
      'No existe un endpoint externo uniforme que cree y configure con su contenido todos los tipos de actividad solicitados.',
      'core_courseformat_new_module sólo permite creación rápida para módulos que declaran FEATURE_QUICKCREATE; no sustituye formularios ni criterios completos.',
      'No hay una plantilla o MBZ real, restaurada y validada que pueda versionarse o reutilizarse todavía.'
    ],
    hard_stops: [
      'No publicar el curso.',
      'No cargar usuarios reales.',
      'No ejecutar en producción.',
      'No definir notas, calendarios, fechas, excepciones o contenidos ausentes.',
      'No tratar este paquete como evidencia de que Moodle ya fue aprovisionado.'
    ]
  };
}

export async function buildProvisioningBundle({
  catalogPath = resolve(repositoryRoot, 'catalog', 'af8', 'catalog.json'),
  accessMatrixPath = resolve(repositoryRoot, 'catalog', 'af8', 'access-control-matrix.json'),
  outputDirectory = resolve(repositoryRoot, 'build', 'provisioning', 'af8'),
  categoryIdnumber
} = {}) {
  if (!CATEGORY_IDNUMBER_PATTERN.test(categoryIdnumber ?? '')) {
    throw new Error('categoryIdnumber es obligatorio y debe usar 1-100 caracteres seguros: letras, números, punto, guion o guion bajo.');
  }
  if (await pathExists(outputDirectory)) {
    throw new Error(`El directorio de salida ya existe y no se sobrescribirá: ${outputDirectory}`);
  }

  const [catalog, accessMatrix] = await Promise.all([
    readJsonFile(catalogPath),
    readJsonFile(accessMatrixPath)
  ]);
  const catalogResult = validateCatalog(catalog);
  const accessResult = validateAccessMatrix(accessMatrix);
  if (!catalogResult.valid || !accessResult.valid) {
    const codes = [...catalogResult.errors, ...accessResult.errors].map((issue) => issue.code).join(', ');
    throw new Error(`No se genera el paquete porque la fuente es inválida: ${codes}`);
  }

  const sourceCatalogRaw = await readFile(catalogPath, 'utf8');
  const sourceAccessRaw = await readFile(accessMatrixPath, 'utf8');
  const files = new Map();
  files.set('cohorts-upload.csv', buildCohortCsv(catalog));
  files.set('course-upload.csv', buildCourseCsv(catalog, categoryIdnumber));
  files.set('course-structure.json', `${JSON.stringify(resolveCourseStructure(catalog), null, 2)}\n`);
  files.set('access-control-matrix.json', sourceAccessRaw.endsWith('\n') ? sourceAccessRaw : `${sourceAccessRaw}\n`);
  files.set('operator-plan.json', `${JSON.stringify(buildOperatorPlan(catalog, categoryIdnumber), null, 2)}\n`);

  const manifest = {
    schema_version: '1.0.0',
    generator: 'scripts/catalog/build-provisioning-bundle.mjs',
    source: {
      catalog_path: relative(repositoryRoot, catalogPath).replaceAll('\\', '/'),
      catalog_sha256: sha256(sourceCatalogRaw),
      access_matrix_path: relative(repositoryRoot, accessMatrixPath).replaceAll('\\', '/'),
      access_matrix_sha256: sha256(sourceAccessRaw)
    },
    target: 'moodle_5_3_staging',
    production_authorized: false,
    course_status: 'not_provisioned',
    counts: catalogResult.summary,
    warnings: catalogResult.warnings.map((issue) => issue.code),
    files: [...files.entries()].map(([name, content]) => ({ name, sha256: sha256(content) }))
  };
  files.set('manifest.json', `${JSON.stringify(manifest, null, 2)}\n`);

  await mkdir(outputDirectory, { recursive: true });
  await Promise.all([...files.entries()].map(([name, content]) => writeFile(join(outputDirectory, name), content, 'utf8')));
  return { outputDirectory, manifest, files: [...files.keys()] };
}

function parseArguments(argv) {
  const options = {};
  for (const argument of argv) {
    if (argument.startsWith('--category-idnumber=')) {
      options.categoryIdnumber = argument.slice('--category-idnumber='.length);
    } else if (argument.startsWith('--catalog=')) {
      options.catalogPath = resolve(argument.slice('--catalog='.length));
    } else if (argument.startsWith('--access-matrix=')) {
      options.accessMatrixPath = resolve(argument.slice('--access-matrix='.length));
    } else if (argument.startsWith('--output=')) {
      options.outputDirectory = resolve(argument.slice('--output='.length));
    } else if (argument === '--help' || argument === '-h') {
      options.help = true;
    } else {
      throw new Error(`Argumento no reconocido: ${argument}`);
    }
  }
  return options;
}

function printHelp() {
  console.log(`Uso: node scripts/catalog/build-provisioning-bundle.mjs --category-idnumber=ID [opciones]

Genera exclusivamente archivos locales en build/; no se conecta a Moodle.

Opciones:
  --category-idnumber=ID  ID number de una categoría existente y aprobada (obligatorio).
  --catalog=RUTA          Catálogo alternativo.
  --access-matrix=RUTA   Matriz alternativa.
  --output=RUTA           Directorio nuevo de salida.
  -h, --help              Muestra esta ayuda.

El directorio de salida no puede existir: el generador nunca sobrescribe artefactos.`);
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  if (options.help) {
    printHelp();
    return;
  }
  const result = await buildProvisioningBundle(options);
  console.log(`Paquete local generado en: ${result.outputDirectory}`);
  for (const file of result.files) {
    console.log(`  - ${file}`);
  }
  console.log('Estado: curso NO aprovisionado; producción NO autorizada.');
}

const invokedDirectly = process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href;
if (invokedDirectly) {
  main().catch((error) => {
    console.error(`Paquete no generado: ${error.message}`);
    process.exitCode = 1;
  });
}
