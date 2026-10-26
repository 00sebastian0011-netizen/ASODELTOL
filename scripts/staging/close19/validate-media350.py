"""Validate both accepted and intentionally defective synthetic QA media."""
import hashlib
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from zipfile import ZipFile

import fitz
from PIL import Image

root = Path(__file__).resolve().parents[3]
assets = root / 'build/staging/close19/private/media350'
manifest = json.loads((assets / 'manifest.json').read_text(encoding='utf8'))
for name, entry in manifest['files'].items():
    path = assets / name
    if hashlib.sha256(path.read_bytes()).hexdigest() != entry['sha256']:
        raise RuntimeError(f'Asset hash mismatch: {name}')

def inspect_video(name):
    source = '/tmp/close19-media/' + ('test.mp4' if name == 'test-720.mp4' else name)
    result = subprocess.run(['docker', 'exec', 'asodeltol-close19-webserver-1', 'ffprobe',
                             '-v', 'error', '-show_entries', 'stream=codec_name,width,height',
                             '-of', 'json', source], capture_output=True, text=True, check=True)
    streams = json.loads(result.stdout)['streams']
    video = next((s for s in streams if s.get('width')), {})
    audio = next((s for s in streams if s.get('codec_name') == 'aac'), None)
    return {'height': video.get('height'), 'width': video.get('width'),
            'h264': video.get('codec_name') == 'h264', 'aac': audio is not None}

valid = {name: inspect_video(name) for name in ['test-720.mp4', 'test-1080.mp4']}
for name, height in [('test-720.mp4', 720), ('test-1080.mp4', 1080)]:
    if valid[name]['height'] != height or not valid[name]['h264'] or not valid[name]['aac']:
        raise RuntimeError(f'Invalid positive video: {name}')
invalid = inspect_video('test-480-muted.mp4')
if invalid['height'] >= 720 or invalid['aac']:
    raise RuntimeError('Defective video was not detected')
vtt = (assets / 'captions-es.vtt').read_text(encoding='utf8')
if not vtt.startswith('WEBVTT\n') or '00:00:00.000 -->' not in vtt:
    raise RuntimeError('Spanish caption file invalid')
if not (assets / 'transcripcion.txt').read_text(encoding='utf8').strip():
    raise RuntimeError('Transcript absent')
for name, marker in [('material.docx', 'word/document.xml'),
                     ('material.xlsx', 'xl/worksheets/sheet1.xml'),
                     ('material.pptx', 'ppt/slides/slide1.xml')]:
    with ZipFile(assets / name) as archive:
        if archive.testzip() is not None or marker not in archive.namelist():
            raise RuntimeError(f'Invalid Office package: {name}')
with fitz.open(assets / 'material.pdf') as pdf:
    valid_pdf_text = sum(len(page.get_text().strip()) for page in pdf)
with fitz.open(assets / 'scan-only.pdf') as pdf:
    scanned_text = sum(len(page.get_text().strip()) for page in pdf)
if valid_pdf_text < 100 or scanned_text != 0:
    raise RuntimeError('Readable/scanned PDF classification failed')
with Image.open(assets / 'infografia.png') as image:
    if image.size != (1600, 900):
        raise RuntimeError('Infographic resolution invalid')
reference = (assets / 'referencias.txt').read_text(encoding='utf8')
if 'CC0' not in reference or '2026' not in reference:
    raise RuntimeError('Synthetic reference missing licence/year')
out = {'status': 'PASS_SIMULATED', 'checked_at': datetime.now(timezone.utc).isoformat(),
       'environment': 'close19-local', 'valid_videos': valid,
       'negative_video_detected': invalid, 'captions': 'Spanish WebVTT valid',
       'document_packages': 3, 'readable_pdf_text_chars': valid_pdf_text,
       'scanned_pdf_text_chars': scanned_text, 'synthetic_reference': True,
       'institutional_content_verified': False}
target = root / 'build/staging/close19/media350-validation.json'
target.write_text(json.dumps(out, indent=2), encoding='utf8')
print(json.dumps({'status': out['status'], 'valid_videos': len(valid),
                  'defective_video_rejected': True, 'scan_detected': True}))
