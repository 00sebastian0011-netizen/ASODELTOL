import test from 'node:test';
import assert from 'node:assert/strict';
import {activitiesComplete, downloadIdentity, PRODUCTION_ORIGIN, safeProductionUrl} from '../../scripts/anexo14/validation-guards.mjs';

test('production URL guard accepts only the authorized origin', () => {
  assert.equal(safeProductionUrl(`${PRODUCTION_ORIGIN}/course/view.php?id=7`)?.origin, PRODUCTION_ORIGIN);
  assert.equal(safeProductionUrl('https://aulas.prospectiva.edu.co/course/view.php?id=7'), null);
  assert.equal(safeProductionUrl('https://example.invalid/'), null);
  assert.equal(safeProductionUrl(`${PRODUCTION_ORIGIN}/redirect?token=secret`), null);
  assert.equal(safeProductionUrl(`${PRODUCTION_ORIGIN}/redirect#external`), null);
});

test('download identity requires HTTP 200, same-origin URL, MIME, filename and SHA-256', () => {
  const args = {status: 200, url: `${PRODUCTION_ORIGIN}/pluginfile.php/7/material.pdf`, headers: {'content-type': 'application/pdf', 'content-disposition': 'attachment; filename="material.pdf"'}, expectedSha256: 'a'.repeat(64), expectedContentType: 'application/pdf', expectedFilename: 'material.pdf', bytes: 'a'.repeat(64)};
  assert.equal(downloadIdentity(args).ok, true);
  assert.equal(downloadIdentity({...args, headers: {'content-type': 'application/pdf', 'content-disposition': 'attachment; filename=material.pdf'}}).ok, true);
  assert.equal(downloadIdentity({...args, status: 302}).ok, false);
  assert.equal(downloadIdentity({...args, url: 'https://example.invalid/material.pdf'}).ok, false);
  assert.equal(downloadIdentity({...args, headers: {'content-type': 'text/html', 'content-disposition': 'attachment; filename="material.pdf"'}}).ok, false);
  assert.equal(downloadIdentity({...args, bytes: 'b'.repeat(64)}).ok, false);
});

test('activities and block validation fails when an expected selector is absent', () => {
  const checks = [{status: 200, loginRequired: false, errorPage: false, matches: {'.activity': 1, '.block': 1}}];
  assert.equal(activitiesComplete(checks, ['.activity', '.block']), true);
  assert.equal(activitiesComplete([{...checks[0], matches: {'.activity': 1, '.block': 0}}], ['.activity', '.block']), false);
  assert.equal(activitiesComplete([], ['.activity']), false);
});
