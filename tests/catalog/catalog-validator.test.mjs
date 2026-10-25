import test from 'node:test';
import assert from 'node:assert/strict';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  readJsonFile,
  validateAccessMatrix,
  validateCatalog
} from '../../scripts/catalog/lib/catalog-validator.mjs';

const testDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(testDirectory, '..', '..');
const catalogPath = resolve(repositoryRoot, 'catalog', 'af8', 'catalog.json');
const accessMatrixPath = resolve(repositoryRoot, 'catalog', 'af8', 'access-control-matrix.json');
const schemaPath = resolve(repositoryRoot, 'catalog', 'schema', 'af8-catalog.schema.json');

function clone(value) {
  return structuredClone(value);
}

function hasError(result, code) {
  return result.errors.some((issue) => issue.code === code);
}

test('el catálogo canónico cubre 5 unidades, 50 ítems, 7 grupos y 80 horas', async () => {
  const catalog = await readJsonFile(catalogPath);
  const result = validateCatalog(catalog);

  assert.equal(result.valid, true, JSON.stringify(result.errors));
  assert.deepEqual(result.summary, {
    units: 5,
    components: 50,
    general_resources: 4,
    groups: 7,
    planned_capacity: 350,
    duration_hours: 80
  });
  assert.deepEqual(catalog.units.map((unit) => unit.components.length), [10, 10, 10, 10, 10]);
  assert.equal(result.warnings.some((issue) => issue.code === 'W-ACADEMIC-INCONSISTENCIES'), true);
});

test('conserva las denominaciones académicas oficiales de unidades y recursos generales', async () => {
  const catalog = await readJsonFile(catalogPath);

  assert.deepEqual(
    catalog.units.map((unit) => [unit.code, unit.title]),
    [
      ['UT1', 'Gestión estratégica del turismo rural sostenible y desarrollo de destinos competitivos.'],
      ['UT2', 'Diseño estratégico de experiencias turísticas, calidad del servicio y generación de valor.'],
      ['UT3', 'Marketing estratégico y gestión competitiva de destinos turísticos.'],
      ['UT4', 'Transformación digital, inteligencia artificial y tecnologías emergentes aplicadas al turismo.'],
      ['UT5', 'Desarrollo rural sostenible, economía campesina y sistemas alimentarios.']
    ]
  );
  assert.deepEqual(
    catalog.general_resources.map((resource) => [resource.code, resource.title]),
    [
      ['0.01', 'Video institucional SENA'],
      ['0.02', 'Objetivo general y objetivos específicos'],
      ['0.03', 'Cronograma y orientaciones para el estudiante'],
      ['0.04', 'Foro de avisos y comunicaciones']
    ]
  );
});

test('el esquema JSON es legible y apunta a draft 2020-12', async () => {
  const schema = await readJsonFile(schemaPath);
  assert.equal(schema.$schema, 'https://json-schema.org/draft/2020-12/schema');
  assert.equal(schema.$defs.unit.properties.duration_hours.const, 16);
  assert.equal(schema.$defs.group.properties.planned_capacity.const, 50);
});

test('detecta códigos duplicados', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.units[0].components[1].code = catalog.units[0].components[0].code;

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-DUPLICATE-CODE'), true);
});

test('detecta identificadores estables duplicados', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.units[1].stable_id = catalog.units[0].stable_id;

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-DUPLICATE-STABLE-ID'), true);
  assert.equal(hasError(result, 'E-DUPLICATE-STABLE-ID-GLOBAL'), true);
});

test('detecta identificadores de cohorte duplicados', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.groups[1].cohort_idnumber = catalog.groups[0].cohort_idnumber;

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-DUPLICATE-COHORT-ID'), true);
});

test('detecta recursos generales faltantes', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.general_resources.pop();

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-GENERAL-RESOURCE-COUNT'), true);
});

test('detecta orden incorrecto de componentes', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  [catalog.units[0].components[0], catalog.units[0].components[1]] = [
    catalog.units[0].components[1],
    catalog.units[0].components[0]
  ];

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-COMPONENT-ORDER'), true);
  assert.equal(hasError(result, 'E-ORDER-POSITION'), true);
});

test('detecta tipos de actividad inválidos', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.component_templates[0].moodle_type_options = ['endpoint_ficticio'];

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-ACTIVITY-TYPE-INVALID'), true);
  assert.equal(hasError(result, 'E-ACTIVITY-TYPE-MAPPING'), true);
});

test('detecta duraciones inconsistentes', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.units[3].duration_hours = 15;

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-UNIT-DURATION'), true);
  assert.equal(hasError(result, 'E-DURATION-TOTAL'), true);
});

test('detecta referencias a unidades inexistentes', async () => {
  const catalog = clone(await readJsonFile(catalogPath));
  catalog.units[2].depends_on_unit = 'UT99';

  const result = validateCatalog(catalog);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-UNIT-REFERENCE'), true);
});

test('la matriz cubre cinco perfiles de negocio y pruebas negativas', async () => {
  const matrix = await readJsonFile(accessMatrixPath);
  const result = validateAccessMatrix(matrix);

  assert.equal(result.valid, true, JSON.stringify(result.errors));
  assert.deepEqual(result.summary, { roles: 5, negative_access_tests: 12 });
  assert.equal(matrix.execution_gate.synthetic_data_only, true);
  assert.equal(matrix.execution_gate.production_authorized, false);
});

test('la matriz falla si desaparece el rol auditor', async () => {
  const matrix = clone(await readJsonFile(accessMatrixPath));
  matrix.roles = matrix.roles.filter((role) => role.role_id !== 'auditor');

  const result = validateAccessMatrix(matrix);
  assert.equal(result.valid, false);
  assert.equal(hasError(result, 'E-MISSING-ROLE'), true);
});
