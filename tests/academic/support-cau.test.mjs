import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const support = fs.readFileSync('plugins/local/asodeltolstaff/classes/support.php', 'utf8');
const form = fs.readFileSync('plugins/local/asodeltolstaff/classes/local/form/support_form.php', 'utf8');
const controller = fs.readFileSync('plugins/local/asodeltolstaff/support.php', 'utf8');
const spanish = fs.readFileSync('plugins/local/asodeltolstaff/lang/es/local_asodeltolstaff.php', 'utf8');

test('CAU offers the five approved help topics and rejects forged categories', () => {
  for (const topic of [
    'Consultas pedagógicas y contenidos de acciones de formación',
    'Incidencias técnicas en evaluaciones y cuestionarios',
    'Peticiones, quejas, reclamos y sugerencias (PQRS)',
    'Solicitud de certificados y calificaciones',
    'Soporte técnico de plataforma LMS (acceso, clave, navegación)',
  ]) {
    assert.ok(spanish.includes(topic));
  }
  assert.match(form, /addElement\('select', 'category'/);
  assert.match(form, /addRule\('category', null, 'required'\)/);
  assert.match(support, /if \(!array_key_exists\(\$category, \$categories\)\)/);
  assert.match(controller, /support::send\(\$data->subject, \$data->body, \$data->category\)/);
});
