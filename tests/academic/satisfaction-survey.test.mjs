import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import {classifyFiles} from '../../scripts/deploy/classify-changes.mjs';

const service = fs.readFileSync('plugins/local/asodeltolstaff/classes/satisfaction_survey.php', 'utf8');
const controller = fs.readFileSync('plugins/local/asodeltolstaff/satisfaction_survey.php', 'utf8');
const lib = fs.readFileSync('plugins/local/asodeltolstaff/lib.php', 'utf8');
const settings = fs.readFileSync('plugins/local/asodeltolstaff/settings.php', 'utf8');
const spanish = fs.readFileSync('plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php', 'utf8');
const logoSena = fs.readFileSync('plugins/local/asodeltolstaff/pix/pfce_logo_sena.png');
const logoFce = fs.readFileSync('plugins/local/asodeltolstaff/pix/pfce_logo_fce.png');

test('OV-ENCUESTA: versioned PFCE 2026 satisfaction survey includes both institutional logos and all official questions', () => {
  // Valid PNG signatures for both embedded logos from the Excel template.
  assert.deepEqual([...logoSena.subarray(0, 8)], [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  assert.deepEqual([...logoFce.subarray(0, 8)], [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  assert.ok(logoSena.length > 1000);
  assert.ok(logoFce.length > 1000);

  // Header, logos, objective, and closing statements.
  assert.match(service, /Encuesta de satisfacción PFCE 2026 — Versión final/);
  assert.match(service, /pfce_logo_sena\.png/);
  assert.match(service, /pfce_logo_fce\.png/);
  assert.match(service, /Su opinión no modifica la calificación académica\./);
  assert.match(service, /Gracias por su participación\. Esta encuesta recoge satisfacción y aplicabilidad/);

  // 5 training action fields.
  for (const field of [
    'Número del Convenio y Sigla',
    'Nombre de la Acción de formación',
    'Número del Grupo',
    'Modalidad de la Acción de Formación',
    'Capacitador o equipo',
  ]) {
    assert.ok(service.includes(field), `Missing action field: ${field}`);
  }

  // 10 satisfaction questions + scale options.
  for (const option of [
    '1 = Muy insatisfecho',
    '2 = Insatisfecho',
    '3 = Ni satisfecho ni insatisfecho',
    '4 = Satisfecho',
    '5 = Muy satisfecho',
    'No aplica / No puedo valorar',
  ]) {
    assert.ok(service.includes(option), `Missing scale option: ${option}`);
  }
  for (let i = 1; i <= 10; i++) {
    assert.match(service, new RegExp(`'label'\\s*=>\\s*'p${i}'`), `Missing question p${i}`);
  }

  // Improvement opportunities (Questions 11 and 12).
  for (const aspect of [
    'Contenidos',
    'Explicaciones del capacitador',
    'Actividades',
    'Materiales',
    'Cumplimiento del cronograma',
    'Ambiente de aprendizaje',
    'Otro',
    'Ninguno',
  ]) {
    assert.ok(service.includes(aspect), `Missing improvement aspect: ${aspect}`);
  }
  assert.ok(service.includes('11. ¿Cuál es el principal aspecto que debería mejorar en esta formación? Seleccione una sola opción.'));
  assert.ok(service.includes('12. ¿Qué cambio concreto propone para mejorar próximas formaciones? Respuesta opcional.'));

  // Controller, settings, and course prompt integration.
  assert.match(controller, /require_sesskey\(\)/);
  assert.match(settings, /asodeltolsatisfactionsurvey/);
  assert.match(lib, /satisfaction_survey::course_completion_prompt_head_html\(\)/);
  assert.ok(spanish.includes('Encuesta de satisfacción PFCE 2026'));
});

test('OV-ENCUESTA: satisfaction survey files classify as standard automatic deployment', () => {
  const classification = classifyFiles([
    'plugins/local/asodeltolstaff/classes/satisfaction_survey.php',
    'plugins/local/asodeltolstaff/satisfaction_survey.php',
    'plugins/local/asodeltolstaff/lib.php',
    'plugins/local/asodeltolstaff/index.php',
    'plugins/local/asodeltolstaff/settings.php',
    'plugins/local/asodeltolstaff/pix/pfce_logo_sena.png',
    'plugins/local/asodeltolstaff/pix/pfce_logo_fce.png',
    'plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php',
    'plugins/local/asodeltolstaff/lang/en/local_asodeltolstaff.php',
  ]);
  assert.equal(classification.risk, 'standard');
  assert.equal(classification.deployable, true);
  assert.equal(classification.manualRequired, false);
});
