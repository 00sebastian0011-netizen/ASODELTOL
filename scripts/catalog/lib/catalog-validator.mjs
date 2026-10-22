import { readFile } from 'node:fs/promises';

const UNIT_TITLES = [
  'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.',
  'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.',
  'Marketing estratégico y gestión competitiva de destinos turísticos.',
  'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.',
  'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.'
];

const COMPONENT_TEMPLATES = [
  ['Introducción a la unidad temática', ['page']],
  ['Objetivos', ['page']],
  ['Resultados de aprendizaje esperados', ['page']],
  ['Presentación del docente', ['page']],
  ['Hoja de vida del docente', ['resource']],
  ['Material de formación', ['folder', 'resource']],
  ['Recursos educativos digitales', ['url', 'h5pactivity']],
  ['Glosario', ['glossary']],
  ['Evaluación de la unidad', ['quiz']],
  ['Referencias bibliográficas', ['page']]
];

const GENERAL_RESOURCES = [
  ['Video institucional SENA', ['url', 'resource']],
  ['Objetivo general y objetivos específicos', ['page']],
  ['Cronograma y orientaciones para el estudiante', ['page']],
  ['Foro de avisos y comunicaciones', ['forum']]
];

const VALID_MOODLE_TYPES = new Set([
  'page',
  'resource',
  'folder',
  'url',
  'h5pactivity',
  'glossary',
  'quiz',
  'forum'
]);

const REQUIRED_ROLE_IDS = ['administrator', 'trainer', 'student', 'auditor'];

export async function readJsonFile(filePath) {
  const raw = await readFile(filePath, 'utf8');
  try {
    return JSON.parse(raw);
  } catch (error) {
    throw new Error(`JSON inválido en ${filePath}: ${error.message}`);
  }
}

function addIssue(issues, severity, code, path, message) {
  issues.push({ severity, code, path, message });
}

function error(issues, code, path, message) {
  addIssue(issues, 'error', code, path, message);
}

function warning(issues, code, path, message) {
  addIssue(issues, 'warning', code, path, message);
}

function asArray(value) {
  return Array.isArray(value) ? value : [];
}

function sameArray(left, right) {
  return left.length === right.length && left.every((value, index) => value === right[index]);
}

function validateExactOrder(issues, entries, expectedCodes, path, code) {
  const actualCodes = entries.map((entry) => entry?.code);
  if (!sameArray(actualCodes, expectedCodes)) {
    error(
      issues,
      code,
      path,
      `Orden/códigos esperados: ${expectedCodes.join(', ')}; recibidos: ${actualCodes.join(', ')}.`
    );
  }
}

function validateUniqueValues(issues, entries, field, path, code) {
  const seen = new Map();
  entries.forEach((entry, index) => {
    const value = entry?.[field];
    if (typeof value !== 'string' || value.length === 0) {
      error(issues, `${code}-MISSING`, `${path}[${index}].${field}`, `Falta ${field}.`);
      return;
    }
    if (seen.has(value)) {
      error(
        issues,
        code,
        `${path}[${index}].${field}`,
        `Valor duplicado ${value}; ya aparece en ${seen.get(value)}.`
      );
      return;
    }
    seen.set(value, `${path}[${index}].${field}`);
  });
}

function validatePosition(issues, entry, expected, path) {
  if (entry?.position !== expected) {
    error(issues, 'E-ORDER-POSITION', `${path}.position`, `Se esperaba la posición ${expected}.`);
  }
}

function validateTypes(issues, entry, expected, path) {
  const options = asArray(entry?.moodle_type_options);
  if (!sameArray(options, expected)) {
    error(
      issues,
      'E-ACTIVITY-TYPE-MAPPING',
      `${path}.moodle_type_options`,
      `Tipos esperados: ${expected.join('/')}; recibidos: ${options.join('/')}.`
    );
  }
  for (const type of options) {
    if (!VALID_MOODLE_TYPES.has(type)) {
      error(issues, 'E-ACTIVITY-TYPE-INVALID', `${path}.moodle_type_options`, `Tipo Moodle no admitido: ${type}.`);
    }
  }
  const selected = entry?.selected_moodle_type;
  if (selected !== null && selected !== undefined && !options.includes(selected)) {
    error(
      issues,
      'E-ACTIVITY-TYPE-SELECTION',
      `${path}.selected_moodle_type`,
      `El tipo seleccionado ${selected} no pertenece a las opciones declaradas.`
    );
  }
}

function collectCatalogEntries(catalog) {
  const units = asArray(catalog?.units);
  return [
    ...asArray(catalog?.general_resources),
    ...units,
    ...units.flatMap((unit) => asArray(unit?.components)),
    ...asArray(catalog?.groups)
  ];
}

export function validateCatalog(catalog) {
  const issues = [];
  if (!catalog || typeof catalog !== 'object' || Array.isArray(catalog)) {
    error(issues, 'E-CATALOG-ROOT', '$', 'El catálogo debe ser un objeto JSON.');
    return buildResult(issues, {});
  }

  if (catalog.schema_version !== '1.0.0') {
    error(issues, 'E-SCHEMA-VERSION', '$.schema_version', 'La versión admitida es 1.0.0.');
  }
  if (catalog.catalog_status !== 'draft' && catalog.catalog_status !== 'approved') {
    error(issues, 'E-CATALOG-STATUS', '$.catalog_status', 'Estado de catálogo inválido.');
  }

  const course = catalog.course ?? {};
  const expectedCourse = {
    code: 'AF8',
    shortname: 'AF8',
    idnumber: 'ASODELTOL-AF8',
    target_moodle: '5.3',
    format: 'topics',
    total_hours: 80,
    unit_count: 5,
    item_count: 50,
    enumerated_general_resource_count: 4,
    declared_general_resource_count: 9,
    group_mode: 'separate',
    force_group_mode: true,
    completion_tracking: true
  };
  for (const [field, expected] of Object.entries(expectedCourse)) {
    if (course[field] !== expected) {
      error(issues, 'E-COURSE-CONTRACT', `$.course.${field}`, `Se esperaba ${JSON.stringify(expected)}.`);
    }
  }
  if (course.publication_status !== 'unpublished') {
    error(issues, 'E-PUBLICATION-GATE', '$.course.publication_status', 'El curso debe permanecer unpublished en esta misión.');
  }
  if (course.category_idnumber !== null) {
    warning(
      issues,
      'W-CATEGORY-NOT-APPROVED',
      '$.course.category_idnumber',
      'La categoría quedó informada en borrador; debe aprobarse antes de aprovisionar.'
    );
  }

  const templates = asArray(catalog.component_templates);
  if (templates.length !== 10) {
    error(issues, 'E-TEMPLATE-COUNT', '$.component_templates', `Deben existir 10 plantillas; existen ${templates.length}.`);
  }
  validateUniqueValues(issues, templates, 'template_id', '$.component_templates', 'E-DUPLICATE-TEMPLATE-ID');
  templates.forEach((template, index) => {
    const position = index + 1;
    const [expectedTitle, expectedTypes] = COMPONENT_TEMPLATES[index] ?? ['', []];
    if (template?.template_id !== `C${String(position).padStart(2, '0')}`) {
      error(issues, 'E-TEMPLATE-ORDER', `$.component_templates[${index}].template_id`, `Se esperaba C${String(position).padStart(2, '0')}.`);
    }
    validatePosition(issues, template, position, `$.component_templates[${index}]`);
    if (template?.title !== expectedTitle) {
      error(issues, 'E-TEMPLATE-TITLE', `$.component_templates[${index}].title`, `Título esperado: ${expectedTitle}.`);
    }
    validateTypes(issues, template, expectedTypes, `$.component_templates[${index}]`);
    if (template?.required_for_unit_completion !== null && typeof template?.required_for_unit_completion !== 'boolean') {
      error(issues, 'E-COMPLETION-FLAG', `$.component_templates[${index}].required_for_unit_completion`, 'Debe ser booleano o null.');
    }
  });

  const general = asArray(catalog.general_resources);
  if (general.length !== 4) {
    error(issues, 'E-GENERAL-RESOURCE-COUNT', '$.general_resources', `Deben existir 4 recursos generales; existen ${general.length}.`);
  }
  validateExactOrder(issues, general, ['0.01', '0.02', '0.03', '0.04'], '$.general_resources', 'E-GENERAL-RESOURCE-ORDER');
  validateUniqueValues(issues, general, 'code', '$.general_resources', 'E-DUPLICATE-CODE');
  validateUniqueValues(issues, general, 'stable_id', '$.general_resources', 'E-DUPLICATE-STABLE-ID');
  general.forEach((resource, index) => {
    const [expectedTitle, expectedTypes] = GENERAL_RESOURCES[index] ?? ['', []];
    const expectedStableId = `asodeltol-af8-general-${String(index + 1).padStart(2, '0')}`;
    validatePosition(issues, resource, index + 1, `$.general_resources[${index}]`);
    if (resource?.stable_id !== expectedStableId) {
      error(issues, 'E-STABLE-ID', `$.general_resources[${index}].stable_id`, `Se esperaba ${expectedStableId}.`);
    }
    if (resource?.title !== expectedTitle) {
      error(issues, 'E-GENERAL-RESOURCE-TITLE', `$.general_resources[${index}].title`, `Título esperado: ${expectedTitle}.`);
    }
    validateTypes(issues, resource, expectedTypes, `$.general_resources[${index}]`);
    if (!['pendiente_contenido', 'ready'].includes(resource?.content_status)) {
      error(issues, 'E-CONTENT-STATUS', `$.general_resources[${index}].content_status`, 'Estado de contenido inválido.');
    }
    if (resource?.publication_status !== 'unpublished') {
      error(issues, 'E-PUBLICATION-GATE', `$.general_resources[${index}].publication_status`, 'Todo recurso general debe permanecer unpublished.');
    }
  });

  const units = asArray(catalog.units);
  if (units.length !== 5) {
    error(issues, 'E-UNIT-COUNT', '$.units', `Deben existir 5 unidades; existen ${units.length}.`);
  }
  validateExactOrder(issues, units, ['UT1', 'UT2', 'UT3', 'UT4', 'UT5'], '$.units', 'E-UNIT-ORDER');
  validateUniqueValues(issues, units, 'code', '$.units', 'E-DUPLICATE-CODE');
  validateUniqueValues(issues, units, 'stable_id', '$.units', 'E-DUPLICATE-STABLE-ID');
  const unitCodes = new Set(units.map((unit) => unit?.code));
  let componentCount = 0;
  let durationTotal = 0;
  const components = [];

  units.forEach((unit, unitIndex) => {
    const unitNumber = unitIndex + 1;
    const path = `$.units[${unitIndex}]`;
    validatePosition(issues, unit, unitNumber, path);
    if (unit?.stable_id !== `asodeltol-af8-ut${unitNumber}`) {
      error(issues, 'E-STABLE-ID', `${path}.stable_id`, `Se esperaba asodeltol-af8-ut${unitNumber}.`);
    }
    if (unit?.title !== UNIT_TITLES[unitIndex]) {
      error(issues, 'E-UNIT-TITLE', `${path}.title`, `Título canónico incorrecto para UT${unitNumber}.`);
    }
    if (unit?.duration_hours !== 16) {
      error(issues, 'E-UNIT-DURATION', `${path}.duration_hours`, 'Cada unidad debe declarar 16 horas.');
    }
    durationTotal += Number.isFinite(unit?.duration_hours) ? unit.duration_hours : 0;
    const expectedDependency = unitIndex === 0 ? null : `UT${unitIndex}`;
    if (unit?.depends_on_unit !== expectedDependency) {
      error(
        issues,
        'E-UNIT-DEPENDENCY',
        `${path}.depends_on_unit`,
        `La dependencia secuencial esperada es ${expectedDependency ?? 'null'}.`
      );
    }
    if (unit?.depends_on_unit !== null && !unitCodes.has(unit?.depends_on_unit)) {
      error(issues, 'E-UNIT-REFERENCE', `${path}.depends_on_unit`, `Unidad inexistente: ${unit?.depends_on_unit}.`);
    }
    if (unit?.passing_grade === null) {
      // Aggregated warning below.
    } else if (typeof unit?.passing_grade !== 'number' || unit.passing_grade < 0) {
      error(issues, 'E-PASSING-GRADE', `${path}.passing_grade`, 'La nota aprobatoria debe ser null o un número no negativo.');
    }
    const temporal = unit?.temporal_rule ?? {};
    if (temporal.business_day_limit !== 8) {
      error(issues, 'E-BUSINESS-DAY-LIMIT', `${path}.temporal_rule.business_day_limit`, 'El límite declarado debe ser 8 días hábiles.');
    }
    if (temporal.start_trigger !== null && !['fixed_group_date', 'previous_unit_completion'].includes(temporal.start_trigger)) {
      error(issues, 'E-TEMPORAL-TRIGGER', `${path}.temporal_rule.start_trigger`, 'Disparador temporal inválido.');
    }

    const unitComponents = asArray(unit?.components);
    componentCount += unitComponents.length;
    components.push(...unitComponents);
    if (unitComponents.length !== 10) {
      error(issues, 'E-COMPONENT-COUNT', `${path}.components`, `UT${unitNumber} debe tener 10 componentes; tiene ${unitComponents.length}.`);
    }
    const expectedCodes = Array.from({ length: 10 }, (_, index) => `UT${unitNumber}.${String(index + 1).padStart(2, '0')}`);
    validateExactOrder(issues, unitComponents, expectedCodes, `${path}.components`, 'E-COMPONENT-ORDER');
    unitComponents.forEach((component, componentIndex) => {
      const componentPath = `${path}.components[${componentIndex}]`;
      const expectedTemplate = `C${String(componentIndex + 1).padStart(2, '0')}`;
      const expectedStableId = `asodeltol-af8-ut${unitNumber}-${String(componentIndex + 1).padStart(2, '0')}`;
      validatePosition(issues, component, componentIndex + 1, componentPath);
      if (component?.stable_id !== expectedStableId) {
        error(issues, 'E-STABLE-ID', `${componentPath}.stable_id`, `Se esperaba ${expectedStableId}.`);
      }
      if (component?.template_id !== expectedTemplate) {
        error(issues, 'E-COMPONENT-TEMPLATE-REFERENCE', `${componentPath}.template_id`, `Se esperaba ${expectedTemplate}.`);
      }
      if (!templates.some((template) => template?.template_id === component?.template_id)) {
        error(issues, 'E-TEMPLATE-REFERENCE', `${componentPath}.template_id`, `Plantilla inexistente: ${component?.template_id}.`);
      }
      if (component?.publication_status !== 'unpublished') {
        error(issues, 'E-PUBLICATION-GATE', `${componentPath}.publication_status`, 'Todo componente debe permanecer unpublished.');
      }
      if (!['pendiente_contenido', 'ready'].includes(component?.content_status)) {
        error(issues, 'E-CONTENT-STATUS', `${componentPath}.content_status`, 'Estado de contenido inválido.');
      }
      if (!['pendiente_definicion', 'configured'].includes(component?.completion_status)) {
        error(issues, 'E-COMPLETION-STATUS', `${componentPath}.completion_status`, 'Estado de finalización inválido.');
      }
    });
  });

  if (durationTotal !== 80 || durationTotal !== course.total_hours) {
    error(issues, 'E-DURATION-TOTAL', '$.units', `La suma de horas es ${durationTotal}; debe ser 80 y coincidir con course.total_hours.`);
  }
  if (componentCount !== 50 || componentCount !== course.item_count) {
    error(issues, 'E-ITEM-TOTAL', '$.units', `El total de componentes es ${componentCount}; debe ser 50 y coincidir con course.item_count.`);
  }
  validateUniqueValues(issues, components, 'code', '$.units[*].components', 'E-DUPLICATE-CODE');
  validateUniqueValues(issues, components, 'stable_id', '$.units[*].components', 'E-DUPLICATE-STABLE-ID');
  validateUniqueValues(issues, collectCatalogEntries(catalog), 'stable_id', '$', 'E-DUPLICATE-STABLE-ID-GLOBAL');

  const groups = asArray(catalog.groups);
  if (groups.length !== 7) {
    error(issues, 'E-GROUP-COUNT', '$.groups', `Deben existir 7 grupos; existen ${groups.length}.`);
  }
  const expectedGroupCodes = Array.from({ length: 7 }, (_, index) => `AF8-G${String(index + 1).padStart(2, '0')}`);
  validateExactOrder(issues, groups, expectedGroupCodes, '$.groups', 'E-GROUP-ORDER');
  validateUniqueValues(issues, groups, 'code', '$.groups', 'E-DUPLICATE-GROUP-CODE');
  validateUniqueValues(issues, groups, 'cohort_idnumber', '$.groups', 'E-DUPLICATE-COHORT-ID');
  groups.forEach((group, index) => {
    const expected = expectedGroupCodes[index];
    const expectedStableId = `asodeltol-af8-g${String(index + 1).padStart(2, '0')}`;
    validatePosition(issues, group, index + 1, `$.groups[${index}]`);
    if (group?.stable_id !== expectedStableId) {
      error(issues, 'E-STABLE-ID', `$.groups[${index}].stable_id`, `Se esperaba ${expectedStableId}.`);
    }
    if (group?.cohort_idnumber !== expected || group?.group_name !== expected) {
      error(issues, 'E-GROUP-MAPPING', `$.groups[${index}]`, `Cohorte y grupo deben mapear de forma estable a ${expected}.`);
    }
    if (group?.planned_capacity !== 50) {
      error(issues, 'E-GROUP-CAPACITY', `$.groups[${index}].planned_capacity`, 'La capacidad prevista debe ser 50.');
    }
    if (group?.sync_strategy !== 'cohort_sync') {
      error(issues, 'E-GROUP-SYNC', `$.groups[${index}].sync_strategy`, 'La estrategia declarada debe ser cohort_sync.');
    }
  });
  const plannedCapacity = groups.reduce((sum, group) => sum + (Number.isFinite(group?.planned_capacity) ? group.planned_capacity : 0), 0);
  if (plannedCapacity !== 350) {
    error(issues, 'E-TOTAL-CAPACITY', '$.groups', `La capacidad prevista suma ${plannedCapacity}; debe sumar 350.`);
  }

  const progression = catalog.progression ?? {};
  if (progression.mode !== 'sequential' || progression.requires_previous_unit_completion !== true || progression.requires_previous_unit_passing_grade !== true) {
    error(issues, 'E-PROGRESSION', '$.progression', 'La progresión debe exigir secuencia, finalización y nota aprobatoria previa.');
  }
  const nativeControls = asArray(progression.native_moodle_controls);
  for (const required of ['activity_completion', 'grade_to_pass', 'restrict_access']) {
    if (!nativeControls.includes(required)) {
      error(issues, 'E-NATIVE-CONTROL', '$.progression.native_moodle_controls', `Falta el control nativo ${required}.`);
    }
  }
  if (progression.custom_temporal_plugin_status !== 'not_implemented') {
    error(issues, 'E-TEMPORAL-PLUGIN-STATUS', '$.progression.custom_temporal_plugin_status', 'El plugin temporal no se implementa en esta misión.');
  }

  const inconsistencies = asArray(catalog.academic_inconsistencies);
  const dp001 = inconsistencies.find((item) => item?.decision_id === 'DP-001');
  const dp002 = inconsistencies.find((item) => item?.decision_id === 'DP-002');
  if (dp001?.declared !== 80 || dp001?.enumerated !== 50 || dp001?.status !== 'pending_academic_approval') {
    error(issues, 'E-DP-001', '$.academic_inconsistencies', 'DP-001 debe conservar 80 declarados frente a 50 enumerados como pendiente.');
  }
  if (dp002?.declared !== 9 || dp002?.enumerated !== 4 || dp002?.status !== 'pending_academic_approval') {
    error(issues, 'E-DP-002', '$.academic_inconsistencies', 'DP-002 debe conservar 9 declarados frente a 4 enumerados como pendiente.');
  }

  const pendingContent = [...general, ...components].filter((entry) => entry?.content_status === 'pendiente_contenido').length;
  const pendingCompletion = components.filter((entry) => entry?.completion_status === 'pendiente_definicion').length;
  const pendingGrades = units.filter((unit) => unit?.passing_grade === null).length;
  const pendingTemporal = units.filter((unit) => unit?.temporal_rule?.start_trigger === null || unit?.temporal_rule?.calendar_id === null).length;
  if (pendingContent > 0) {
    warning(issues, 'W-CONTENT-PENDING', '$', `${pendingContent} recursos/componentes siguen en pendiente_contenido.`);
  }
  if (pendingCompletion > 0) {
    warning(issues, 'W-COMPLETION-PENDING', '$', `${pendingCompletion} criterios de finalización siguen pendientes.`);
  }
  if (pendingGrades > 0) {
    warning(issues, 'W-PASSING-GRADE-PENDING', '$.units', `${pendingGrades} unidades no tienen nota aprobatoria aprobada.`);
  }
  if (pendingTemporal > 0) {
    warning(issues, 'W-TEMPORAL-PENDING', '$.units', `${pendingTemporal} unidades esperan disparador/calendario temporal.`);
  }
  warning(issues, 'W-ACADEMIC-INCONSISTENCIES', '$.academic_inconsistencies', 'DP-001 y DP-002 requieren aprobación académica.');

  return buildResult(issues, {
    units: units.length,
    components: componentCount,
    general_resources: general.length,
    groups: groups.length,
    planned_capacity: plannedCapacity,
    duration_hours: durationTotal
  });
}

export function validateAccessMatrix(matrix) {
  const issues = [];
  if (!matrix || typeof matrix !== 'object' || Array.isArray(matrix)) {
    error(issues, 'E-ACCESS-ROOT', '$', 'La matriz de acceso debe ser un objeto JSON.');
    return buildResult(issues, {});
  }
  if (matrix.schema_version !== '1.0.0') {
    error(issues, 'E-ACCESS-SCHEMA-VERSION', '$.schema_version', 'La versión admitida es 1.0.0.');
  }
  if (matrix.importable_role_definition !== false) {
    error(issues, 'E-ACCESS-IMPORTABLE', '$.importable_role_definition', 'La matriz no debe presentarse como definición importable sin validación en staging.');
  }
  if (matrix.architecture?.moodle_groupmode !== 1 || matrix.architecture?.moodle_groupmodeforce !== 1) {
    error(issues, 'E-ACCESS-GROUPMODE', '$.architecture', 'Se requiere groupmode=1 y groupmodeforce=1.');
  }

  const roles = asArray(matrix.roles);
  if (roles.length !== 4) {
    error(issues, 'E-ROLE-COUNT', '$.roles', `Deben existir exactamente 4 roles de negocio; existen ${roles.length}.`);
  }
  validateUniqueValues(issues, roles, 'role_id', '$.roles', 'E-DUPLICATE-ROLE');
  const roleIds = roles.map((role) => role?.role_id);
  for (const roleId of REQUIRED_ROLE_IDS) {
    if (!roleIds.includes(roleId)) {
      error(issues, 'E-MISSING-ROLE', '$.roles', `Falta el rol de negocio ${roleId}.`);
    }
  }
  for (const role of roles) {
    if (asArray(role?.allowed_intents).length === 0) {
      error(issues, 'E-ROLE-ALLOWLIST', `$.roles.${role?.role_id}`, 'Cada rol necesita intenciones permitidas explícitas.');
    }
    if (asArray(role?.scope_controls).length === 0) {
      error(issues, 'E-ROLE-SCOPE', `$.roles.${role?.role_id}`, 'Cada rol necesita controles de alcance explícitos.');
    }
  }

  const tests = asArray(matrix.negative_access_tests);
  validateUniqueValues(issues, tests, 'test_id', '$.negative_access_tests', 'E-DUPLICATE-NEGATIVE-TEST');
  if (tests.length < 8) {
    error(issues, 'E-NEGATIVE-TEST-COUNT', '$.negative_access_tests', 'Se requieren al menos 8 pruebas negativas.');
  }
  const testRoles = new Set(tests.map((test) => test?.actor_role));
  for (const roleId of ['student', 'trainer', 'auditor']) {
    if (!testRoles.has(roleId)) {
      error(issues, 'E-NEGATIVE-TEST-ROLE', '$.negative_access_tests', `Faltan pruebas negativas para ${roleId}.`);
    }
  }
  const scenarios = tests.map((test) => `${test?.scenario ?? ''} ${test?.expected ?? ''}`.toLowerCase()).join(' ');
  const requiredCoverage = [
    ['url', 'navegación directa por URL'],
    ['calific', 'segregación de calificaciones'],
    ['grupo', 'segregación de grupos'],
    ['foro', 'segregación de foros'],
    ['entrega', 'segregación de entregas']
  ];
  for (const [needle, description] of requiredCoverage) {
    if (!scenarios.includes(needle)) {
      error(issues, 'E-NEGATIVE-TEST-COVERAGE', '$.negative_access_tests', `Falta cobertura de ${description}.`);
    }
  }
  if (matrix.execution_gate?.synthetic_data_only !== true || matrix.execution_gate?.production_authorized !== false) {
    error(issues, 'E-ACCESS-EXECUTION-GATE', '$.execution_gate', 'La ejecución debe limitarse a datos sintéticos en staging y no autorizar producción.');
  }

  return buildResult(issues, { roles: roles.length, negative_access_tests: tests.length });
}

function buildResult(issues, summary) {
  const errors = issues.filter((issue) => issue.severity === 'error');
  const warnings = issues.filter((issue) => issue.severity === 'warning');
  return {
    valid: errors.length === 0,
    errors,
    warnings,
    issues,
    summary
  };
}

export const catalogContract = Object.freeze({
  unitTitles: UNIT_TITLES,
  componentTemplates: COMPONENT_TEMPLATES,
  generalResources: GENERAL_RESOURCES,
  validMoodleTypes: [...VALID_MOODLE_TYPES],
  requiredRoleIds: REQUIRED_ROLE_IDS
});
