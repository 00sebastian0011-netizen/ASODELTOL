import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildProvisioningBundle, csvCell } from '../../scripts/catalog/build-provisioning-bundle.mjs';
import { readJsonFile } from '../../scripts/catalog/lib/catalog-validator.mjs';

const testDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(testDirectory, '..', '..');
const catalogPath = resolve(repositoryRoot, 'catalog', 'af8', 'catalog.json');
const accessMatrixPath = resolve(repositoryRoot, 'catalog', 'af8', 'access-control-matrix.json');

function parseCsv(content) {
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;

  for (let index = 0; index < content.length; index += 1) {
    const character = content[index];
    if (quoted) {
      if (character === '"' && content[index + 1] === '"') {
        field += '"';
        index += 1;
      } else if (character === '"') {
        quoted = false;
      } else {
        field += character;
      }
    } else if (character === '"') {
      assert.equal(field, '', 'Una comilla de apertura sólo puede aparecer al inicio del campo.');
      quoted = true;
    } else if (character === ',') {
      row.push(field);
      field = '';
    } else if (character === '\n') {
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
    } else if (character !== '\r') {
      field += character;
    }
  }

  assert.equal(quoted, false, 'El CSV no puede terminar dentro de un campo entrecomillado.');
  if (field !== '' || row.length > 0) {
    row.push(field);
    rows.push(row);
  }
  return rows;
}

async function createTemporaryRoot(t) {
  const root = await mkdtemp(join(tmpdir(), 'asodeltol-m04-'));
  t.after(async () => {
    const resolved = resolve(root);
    const temporaryBase = `${resolve(tmpdir())}${sep}`;
    assert.equal(resolved.startsWith(temporaryBase), true, 'Sólo se elimina el directorio temporal creado por la prueba.');
    await rm(resolved, { recursive: true, force: true });
  });
  return root;
}

test('genera un paquete oficial reproducible, oculto y sin personas', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);
  const outputDirectory = join(temporaryRoot, 'bundle');
  const result = await buildProvisioningBundle({
    catalogPath,
    accessMatrixPath,
    outputDirectory,
    categoryIdnumber: 'ASODELTOL-STAGING'
  });

  assert.deepEqual(result.files.sort(), [
    'access-control-matrix.json',
    'cohorts-upload.csv',
    'course-structure.json',
    'course-upload.csv',
    'manifest.json',
    'operator-plan.json'
  ]);

  const cohortCsv = await readFile(join(outputDirectory, 'cohorts-upload.csv'), 'utf8');
  const courseCsv = await readFile(join(outputDirectory, 'course-upload.csv'), 'utf8');
  const structure = JSON.parse(await readFile(join(outputDirectory, 'course-structure.json'), 'utf8'));
  const manifest = JSON.parse(await readFile(join(outputDirectory, 'manifest.json'), 'utf8'));
  const plan = JSON.parse(await readFile(join(outputDirectory, 'operator-plan.json'), 'utf8'));

  assert.equal(cohortCsv.trimEnd().split('\n').length, 8);
  assert.match(cohortCsv, /"AF8-G01"/);
  assert.match(cohortCsv, /"AF8-G07"/);
  assert.doesNotMatch(cohortCsv + courseCsv, /username|firstname|lastname|email/i);
  assert.match(courseCsv, /"category_idnumber"/);
  assert.match(courseCsv, /"ASODELTOL-STAGING"/);
  assert.match(courseCsv, /"enrolment_1_addtogroup"/);
  assert.doesNotMatch(courseCsv, /"enrolment_2"/);
  assert.match(courseCsv, /,"0","1","1","1","0",/);
  assert.equal(structure.units.length, 5);
  assert.equal(structure.units.flatMap((unit) => unit.components).length, 50);
  assert.equal(manifest.course_status, 'not_provisioned');
  assert.equal(manifest.production_authorized, false);
  assert.equal(plan.ordered_actions[1].official_mechanism, 'Site administration > Courses > Upload courses');
  assert.equal(result.files.some((name) => name.endsWith('.mbz')), false);
});

test('conserva tildes, comas, comillas y saltos de línea en una celda CSV válida', () => {
  const original = 'Gestión, turismo "rural"\nOrientación e información';
  const serialized = `${csvCell(original, 'academic_title')}\n`;

  assert.equal(serialized, '"Gestión, turismo ""rural""\nOrientación e información"\n');
  assert.deepEqual(parseCsv(serialized), [[original]]);
});

test('rechaza inicios de fórmula sin alterar valores legítimos', () => {
  const dangerousValues = [
    '=1+1',
    '+SUM(A1:A2)',
    '-2+3',
    '@SUM(A1:A2)',
    '  =HYPERLINK("https://example.invalid")',
    '\t+CMD',
    '\n-10+20',
    '\uFEFF@IMPORTDATA("https://example.invalid")',
    '\u200B=1+1'
  ];

  for (const value of dangerousValues) {
    assert.throws(() => csvCell(value, 'provisioning_field'), /Valor CSV peligroso en provisioning_field/);
  }
  assert.throws(() => csvCell('AF8\0G01', 'idnumber'), /carácter nulo/);
  assert.equal(csvCell('AF8-G01', 'idnumber'), '"AF8-G01"');
  assert.equal(csvCell('Información turística', 'fullname'), '"Información turística"');
});

test('aborta el paquete si un campo de aprovisionamiento inicia una fórmula', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);
  const dangerousCatalogPath = join(temporaryRoot, 'dangerous-catalog.json');
  const outputDirectory = join(temporaryRoot, 'dangerous-output');
  const catalog = structuredClone(await readJsonFile(catalogPath));
  catalog.course.fullname = '=HYPERLINK("https://example.invalid")';
  await writeFile(dangerousCatalogPath, `${JSON.stringify(catalog, null, 2)}\n`, 'utf8');

  await assert.rejects(
    buildProvisioningBundle({
      catalogPath: dangerousCatalogPath,
      accessMatrixPath,
      outputDirectory,
      categoryIdnumber: 'ASODELTOL-STAGING'
    }),
    /Valor CSV peligroso en fullname/
  );
  await assert.rejects(readFile(join(outputDirectory, 'manifest.json'), 'utf8'), /ENOENT/);
});

test('los CSV generados son estructuralmente válidos y preservan los valores canónicos', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);
  const outputDirectory = join(temporaryRoot, 'csv-validation');
  await buildProvisioningBundle({
    catalogPath,
    accessMatrixPath,
    outputDirectory,
    categoryIdnumber: 'ASODELTOL-STAGING'
  });

  const catalog = await readJsonFile(catalogPath);
  const courseRows = parseCsv(await readFile(join(outputDirectory, 'course-upload.csv'), 'utf8'));
  const cohortRows = parseCsv(await readFile(join(outputDirectory, 'cohorts-upload.csv'), 'utf8'));
  assert.equal(courseRows.length, 8);
  assert.equal(courseRows.every((row) => row.length === 14), true);
  assert.equal(courseRows.slice(1).every((row) => row[courseRows[0].indexOf('fullname')] === catalog.course.fullname), true);
  assert.equal(courseRows.slice(1).every((row) => row[courseRows[0].indexOf('category_idnumber')] === 'ASODELTOL-STAGING'), true);
  assert.equal(courseRows.slice(1).every((row) => row[courseRows[0].indexOf('visible')] === '0'), true);
  assert.deepEqual(
    courseRows.slice(1).map((row) => row[courseRows[0].indexOf('enrolment_1_cohortidnumber')]),
    catalog.groups.map((group) => group.cohort_idnumber)
  );
  assert.equal(courseRows.slice(1).every((row) => row[courseRows[0].indexOf('enrolment_1')] === 'cohort'), true);
  assert.equal(courseRows.slice(1).every((row) => row[courseRows[0].indexOf('enrolment_1_role')] === 'student'), true);
  assert.equal(courseRows.slice(1).every((row) => row[courseRows[0].indexOf('enrolment_1_addtogroup')] === '1'), true);
  assert.equal(cohortRows.length, 8);
  assert.equal(cohortRows.every((row) => row.length === 5), true);
  assert.deepEqual(cohortRows.slice(1).map((row) => row[1]), catalog.groups.map((group) => group.cohort_idnumber));
});

test('rechaza categorías ausentes o con caracteres inseguros', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);

  await assert.rejects(
    buildProvisioningBundle({ catalogPath, accessMatrixPath, outputDirectory: join(temporaryRoot, 'missing') }),
    /categoryIdnumber es obligatorio/
  );
  await assert.rejects(
    buildProvisioningBundle({
      catalogPath,
      accessMatrixPath,
      outputDirectory: join(temporaryRoot, 'unsafe'),
      categoryIdnumber: '=IMPORTXML'
    }),
    /caracteres seguros/
  );
});

test('rechaza fuentes inválidas antes de escribir la salida', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);
  const invalidCatalogPath = join(temporaryRoot, 'invalid-catalog.json');
  const catalog = structuredClone(await readJsonFile(catalogPath));
  catalog.groups[6].planned_capacity = 49;
  await writeFile(invalidCatalogPath, `${JSON.stringify(catalog, null, 2)}\n`, 'utf8');

  const outputDirectory = join(temporaryRoot, 'invalid-output');
  await assert.rejects(
    buildProvisioningBundle({
      catalogPath: invalidCatalogPath,
      accessMatrixPath,
      outputDirectory,
      categoryIdnumber: 'ASODELTOL-STAGING'
    }),
    /fuente es inválida/
  );
  await assert.rejects(readFile(join(outputDirectory, 'manifest.json'), 'utf8'), /ENOENT/);
});

test('nunca sobrescribe un directorio existente', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);
  const outputDirectory = join(temporaryRoot, 'existing');
  await mkdir(outputDirectory);
  const sentinelPath = join(outputDirectory, 'sentinel.txt');
  await writeFile(sentinelPath, 'preserve', 'utf8');

  await assert.rejects(
    buildProvisioningBundle({
      catalogPath,
      accessMatrixPath,
      outputDirectory,
      categoryIdnumber: 'ASODELTOL-STAGING'
    }),
    /no se sobrescribirá/
  );
  assert.equal(await readFile(sentinelPath, 'utf8'), 'preserve');
});

test('las mismas entradas producen artefactos idénticos', async (t) => {
  const temporaryRoot = await createTemporaryRoot(t);
  const firstOutput = join(temporaryRoot, 'first');
  const secondOutput = join(temporaryRoot, 'second');
  const options = {
    catalogPath,
    accessMatrixPath,
    categoryIdnumber: 'ASODELTOL-STAGING'
  };
  const first = await buildProvisioningBundle({ ...options, outputDirectory: firstOutput });
  const second = await buildProvisioningBundle({ ...options, outputDirectory: secondOutput });

  assert.deepEqual(first.files, second.files);
  for (const file of first.files) {
    const [left, right] = await Promise.all([
      readFile(join(firstOutput, file)),
      readFile(join(secondOutput, file))
    ]);
    assert.deepEqual(left, right, `${file} debe ser determinista.`);
  }
});
