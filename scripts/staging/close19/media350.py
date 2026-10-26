"""Generate real synthetic academic QA assets; never official learning content."""
import json
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

from PIL import Image, ImageDraw
from openpyxl import Workbook
from pptx import Presentation

ROOT = Path(__file__).resolve().parents[3]
OUT = ROOT / 'build/staging/close19/private/media350'
OUT.mkdir(parents=True, exist_ok=True)
title = 'QA AF8: lectura y navegacion de materiales sinteticos'
paragraphs = [title, 'Objetivo de prueba: comprobar que Moodle entrega formatos completos.',
              'Secuencia: consultar la infografia, reproducir el video y responder la actividad.',
              'Este material es sintético. No contiene aprendizajes ni resultados institucionales.',
              'Referencia de ejemplo: ASODELTOL QA (2026). Material generado en laboratorio (CC0).']

# DOCX is a minimal, valid Office Open XML package with paragraphs and metadata.
from xml.sax.saxutils import escape
document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' + \
    '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' + \
    ''.join('<w:p><w:r><w:t>' + escape(line) + '</w:t></w:r></w:p>' for line in paragraphs) + \
    '<w:sectPr/></w:body></w:document>'
with ZipFile(OUT / 'material.docx', 'w', ZIP_DEFLATED) as z:
    z.writestr('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>' +
               '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
               '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
               '<Default Extension="xml" ContentType="application/xml"/>' +
               '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>')
    z.writestr('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>' +
               '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
               '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>')
    z.writestr('word/document.xml', document)

book = Workbook()
sheet = book.active
sheet.title = 'Prueba sintetica'
sheet.append(['ID', 'Material', 'Licencia', 'Estado'])
for i, value in enumerate(['PDF', 'DOCX', 'XLSX', 'PPTX', 'Video 720p', 'Video 1080p'], 1):
    sheet.append([i, value, 'CC0 (fixture QA)', 'Sintético'])
book.save(OUT / 'material.xlsx')

slides = Presentation()
for line in paragraphs[:3]:
    slide = slides.slides.add_slide(slides.slide_layouts[1])
    slide.shapes.title.text = line
    slide.placeholders[1].text = 'Recurso de prueba sintético; no es contenido académico aprobado.'
slides.save(OUT / 'material.pptx')

img = Image.new('RGB', (1600, 900), '#ffffff')
draw = ImageDraw.Draw(img)
for y, line in zip((80, 260, 440, 620, 790), paragraphs):
    draw.rectangle((55, y-20, 1540, y+70), outline='#194b38', width=4)
    draw.text((85, y), line, fill='#173e31')
img.save(OUT / 'infografia.png')
(OUT / 'captions-es.vtt').write_text('WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nVideo sintético de prueba ASODELTOL.\n', encoding='utf8')
(OUT / 'transcripcion.txt').write_text('Transcripción sintética: demostración del recurso multimedia de QA.\n', encoding='utf8')
(OUT / 'referencias.txt').write_text(paragraphs[-1] + '\n', encoding='utf8')

# FFmpeg is already installed in the isolated staging webserver. Limit the source to two seconds.
args = ['docker', 'exec', '--user', 'www-data', 'asodeltol-close19-webserver-1', 'ffmpeg', '-hide_banner', '-loglevel', 'error',
        '-y', '-f', 'lavfi', '-i', 'testsrc2=size=1920x1080:rate=15', '-f', 'lavfi', '-i',
        'sine=frequency=440:sample_rate=44100', '-t', '2', '-c:v', 'libx264', '-preset', 'ultrafast',
        '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart',
        '/tmp/close19-media/test-1080.mp4']
subprocess.run(args, check=True)
subprocess.run(['docker', 'cp', 'asodeltol-close19-webserver-1:/tmp/close19-media/test-1080.mp4', str(OUT / 'test-1080.mp4')], check=True)
badargs = ['docker', 'exec', '--user', 'www-data', 'asodeltol-close19-webserver-1', 'ffmpeg',
           '-hide_banner', '-loglevel', 'error', '-y', '-f', 'lavfi', '-i',
           'testsrc2=size=640x480:rate=15', '-t', '1', '-c:v', 'libx264',
           '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-an',
           '/tmp/close19-media/test-480-muted.mp4']
subprocess.run(badargs, check=True)
subprocess.run(['docker', 'cp', 'asodeltol-close19-webserver-1:/tmp/close19-media/test-480-muted.mp4',
                str(OUT / 'test-480-muted.mp4')], check=True)
img.save(OUT / 'scan-only.pdf', 'PDF', resolution=150.0)
for original, destination in [('test.mp4', 'test-720.mp4'), ('test.mp3', 'audio.mp3'), ('material.pdf', 'material.pdf')]:
    subprocess.run(['docker', 'cp', 'asodeltol-close19-webserver-1:/tmp/close19-media/' + original,
                    str(OUT / destination)], check=True)
manifest = {'environment': 'close19-local', 'created_at': datetime.now(timezone.utc).isoformat(),
            'source': 'generated synthetic QA assets, CC0 fixture',
            'deliberately_invalid': ['test-480-muted.mp4', 'scan-only.pdf'], 'files': {}}
import hashlib
for path in OUT.iterdir():
    if path.is_file() and path.name != 'manifest.json':
        manifest['files'][path.name] = {'bytes': path.stat().st_size,
                                        'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(OUT / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf8')
print(json.dumps({'status': 'GENERATED', 'files': len(manifest['files']), 'media': '720p and 1080p H.264/AAC'}))
