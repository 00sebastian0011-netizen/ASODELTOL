"""Package only the local plugin, without operating Git or any remote server."""
import hashlib
import json
import pathlib
import re
import shutil
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'plugins/local/asodeltolstaff'
OUT = ROOT / 'build/staging/anexo14-release'
OUT.mkdir(parents=True, exist_ok=True)
metadata = (SOURCE / 'version.php').read_text(encoding='utf-8')
version = int(re.search(r'\$plugin->version\s*=\s*(\d+)', metadata)[1])
release = re.search(r"\$plugin->release\s*=\s*'([^']+)'", metadata)[1]
archive = OUT / ('local_asodeltolstaff-' + release + '.zip')
files = sorted(path for path in SOURCE.rglob('*') if path.is_file())
for path in files:
    assert path.name not in ('config.php', '.env', 'auth.json') and path.suffix not in ('.sql', '.pem', '.key')
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as bundle:
    for path in files:
        bundle.write(path, 'asodeltolstaff/' + path.relative_to(SOURCE).as_posix())
with zipfile.ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    assert len(bundle.namelist()) == len(files)
manifest = {'component': 'local_asodeltolstaff', 'release': release, 'version': version,
            'target': 'Moodle 5.3+ / PHP 8.4', 'maturity': 'ALPHA',
            'production_deployed': False, 'official_acceptance': False,
            'archive': archive.name, 'archive_sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
            'files': [{'path': path.relative_to(SOURCE).as_posix(),
                       'sha256': hashlib.sha256(path.read_bytes()).hexdigest()} for path in files]}
(OUT / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
shutil.copyfile(ROOT / 'docs/anexo14/ENTREGA_TERCERO_Y_VPS.md', OUT / 'ENTREGA_TERCERO_Y_VPS.md')
print(json.dumps({'archive': archive.name, 'version': version, 'files': len(files),
                  'sha256': manifest['archive_sha256'], 'production_deployed': False}))
