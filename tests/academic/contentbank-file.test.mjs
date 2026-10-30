import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const base = 'plugins/contenttype/asodeltolfile';
const type = fs.readFileSync(`${base}/classes/contenttype.php`, 'utf8');
const access = fs.readFileSync(`${base}/db/access.php`, 'utf8');
const version = fs.readFileSync(`${base}/version.php`, 'utf8');
const es = fs.readFileSync(`${base}/lang/es/contenttype_asodeltolfile.php`, 'utf8');
const en = fs.readFileSync(`${base}/lang/en/contenttype_asodeltolfile.php`, 'utf8');

const list = (name) => {
  const m = type.match(new RegExp(`${name} = \\[([^\\]]*)\\]`));
  assert.ok(m, `missing ${name}`);
  return [...m[1].matchAll(/'([a-z0-9]+)'/g)].map((x) => x[1]);
};

test('contentbank file type accepts PDF and video and never inlines active formats', () => {
  assert.match(version, /contenttype_asodeltolfile/);
  assert.match(type, /extends \\core_contentbank\\contenttype/);
  assert.match(type, /self::CAN_UPLOAD/);
  const inline = [...list('PDF_EXTENSIONS'), ...list('VIDEO_EXTENSIONS'), ...list('AUDIO_EXTENSIONS'), ...list('IMAGE_EXTENSIONS')];
  for (const ext of ['pdf', 'mp4', 'mp3', 'png']) assert.ok(inline.includes(ext), ext);
  for (const bad of ['html', 'htm', 'svg', 'js', 'php', 'xhtml', 'h5p']) {
    assert.ok(!inline.includes(bad), `${bad} must not be inline`);
    assert.ok(!list('DOWNLOAD_EXTENSIONS').includes(bad), `${bad} must not be accepted`);
  }
  assert.match(type, /send_stored_file\(\$file, 0, 0, !\$inline/);
  assert.match(access, /contenttype\/asodeltolfile:access/);
  assert.match(access, /contenttype\/asodeltolfile:upload/);
  for (const s of ['pluginname', 'privacy:metadata', 'asodeltolfile:upload', 'downloadfile']) {
    assert.ok(es.includes(`['${s}']`) && en.includes(`['${s}']`), s);
  }
});
