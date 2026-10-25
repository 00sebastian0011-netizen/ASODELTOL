import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
const file = 'scripts/deploy/deploy-asodeltol-plugin.ps1';
const source = fs.readFileSync(file, 'utf8');
test('deployment is restricted to the plugin and requires an explicit apply/backup', () => {
  assert.match(source, /public[\\/]local[\\/]asodeltolstaff/);
  assert.match(source, /BackupPath/);
  assert.match(source, /Apply/);
  assert.doesNotMatch(source, /git\s+(pull|clean|reset)|rsync\s+.*--delete|robocopy\s+.*(MIR|PURGE)|Remove-Item|rm\s+-rf/i);
});
