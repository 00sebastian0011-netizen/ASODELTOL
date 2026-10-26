import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import {classifyFiles} from '../scripts/deploy/classify-changes.mjs';

test('classifies visual theme assets as fast deploy', () => {
  for (const file of [
    'plugins/theme/rsmax/style/custom.css',
    'plugins/theme/rsmax/templates/header.mustache',
    'plugins/theme/rsmax/pix/logo.svg',
    'plugins/theme/rsmax/lang/es/theme_rsmax.php',
  ]) {
    const result = classifyFiles([file]);
    assert.equal(result.risk, 'fast', file);
    assert.equal(result.components.theme_rsmax.risk, 'fast', file);
  }
});

test('requires a compiled AMD build beside a changed source module', () => {
  const rejected = classifyFiles(['plugins/theme/rsmax/amd/src/navigation.js']);
  assert.equal(rejected.risk, 'critical');
  assert.equal(rejected.manualRequired, true);

  const accepted = classifyFiles([
    'plugins/theme/rsmax/amd/src/navigation.js',
    'plugins/theme/rsmax/amd/build/navigation.min.js',
  ]);
  assert.equal(accepted.risk, 'fast');
  assert.equal(accepted.manualRequired, false);
});

test('classifies PHP without schema changes as standard deploy', () => {
  const result = classifyFiles(['plugins/local/asodeltolstaff/classes/support.php']);
  assert.equal(result.risk, 'standard');
  assert.equal(result.manualRequired, false);
});

test('blocks database, version and privacy changes as critical', () => {
  for (const file of [
    'plugins/theme/rsmax/version.php',
    'plugins/local/asodeltolstaff/db/upgrade.php',
    'plugins/local/asodeltolstaff/classes/privacy/provider.php',
  ]) {
    const result = classifyFiles([file]);
    assert.equal(result.risk, 'critical', file);
    assert.equal(result.manualRequired, true, file);
  }
});

test('does not deploy tests, documentation or unrelated files', () => {
  const result = classifyFiles([
    'plugins/theme/rsmax/tests/theme_test.php',
    'plugins/local/asodeltolstaff/tests/report_test.php',
    'docs/ROADMAP.md',
  ]);
  assert.equal(result.risk, 'none');
  assert.equal(result.deployable, false);
});

test('deployment infrastructure changes require manual activation', () => {
  const result = classifyFiles(['scripts/deploy/vps/receiver.py']);
  assert.equal(result.risk, 'critical');
  assert.equal(result.manualRequired, true);
  assert.equal(result.deployable, false);
});

test('receiver validates HMAC and the deployer supports drift and rollback state', () => {
  const receiver = fs.readFileSync('scripts/deploy/vps/receiver.py', 'utf8');
  const deployer = fs.readFileSync('scripts/deploy/vps/deploy_release.py', 'utf8');
  const service = fs.readFileSync('scripts/deploy/vps/asodeltol-deploy.service', 'utf8');
  assert.match(receiver, /hmac\.compare_digest/);
  assert.match(receiver, /EXPECTED_REPOSITORY/);
  assert.match(receiver, /EXPECTED_REF/);
  assert.match(receiver, /payload\.get\("before"/);
  assert.match(receiver, /"--before", before_sha, "--sha", sha/);
  assert.match(deployer, /protected production drift/);
  assert.match(deployer, /rollback\(/);
  assert.match(deployer, /Post-deploy hash mismatch/);
  assert.match(service, /User=user/);
  assert.match(service, /NoNewPrivileges=true/);
  assert.doesNotMatch(service, /User=root/);
});

test('deployment evaluates the exact GitHub push range instead of accumulated component history', () => {
  const deployer = fs.readFileSync('scripts/deploy/vps/deploy_release.py', 'utf8');
  assert.match(deployer, /changed_paths\(repo, change_base_sha, target_sha/);
  assert.match(deployer, /target_sha = requested_sha/);
  assert.match(deployer, /Webhook before commit is not an ancestor/);
  assert.doesNotMatch(deployer, /changed_paths\(repo, base_sha, target_sha/);
});
