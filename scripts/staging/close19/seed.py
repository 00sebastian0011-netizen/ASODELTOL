"""Reproducible synthetic media/catalog fixture. Only the independent 8054 instance."""
import json
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parents[3]
WEB = 'asodeltol-close19-webserver-1'


def run(*args):
    subprocess.run(args, check=True)


def main():
    run('docker', 'exec', '--user', 'www-data', WEB, 'php', '/opt/close19/isolation.php')
    available = subprocess.run(['docker', 'exec', WEB, 'ffmpeg', '-version'], capture_output=True)
    if available.returncode:
        run('docker', 'exec', WEB, 'apt-get', 'update', '-qq')
        run('docker', 'exec', WEB, 'apt-get', 'install', '-y', '-qq', '--no-install-recommends', 'ffmpeg')
    run('docker', 'exec', WEB, 'mkdir', '-p', '/tmp/close19-media')
    for name, args in [('test.mp4', ['-f', 'lavfi', '-i', 'testsrc2=size=1280x720:rate=25',
                                  '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100', '-t', '15',
                                  '-c:v', 'libx264', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-movflags', '+faststart']),
                       ('test.mp3', ['-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100', '-t', '15', '-c:a', 'libmp3lame'])]:
        exists = subprocess.run(['docker', 'exec', WEB, 'test', '-f', '/tmp/close19-media/' + name])
        if exists.returncode:
            run('docker', 'exec', WEB, 'ffmpeg', '-hide_banner', '-loglevel', 'error', *args, '/tmp/close19-media/' + name)
    run('docker', 'exec', WEB, 'chown', '-R', 'www-data:www-data', '/tmp/close19-media')
    run('docker', 'cp', str(ROOT / 'catalog/af8/catalog.json'), WEB + ':/tmp/close19-catalog.json')
    run('docker', 'exec', '--user', 'www-data', WEB, 'php', '/opt/close19/fixture.php', *(['--new'] if '--new' in sys.argv else []))
    snapshot = subprocess.check_output(['docker', 'exec', '--user', 'www-data', WEB,
                                        'php', '/opt/close19/context.php', 'refresh'], text=True)
    context = json.loads(snapshot)
    if not context['course_hidden'] or not context['category_hidden']:
        raise RuntimeError('Synthetic staging must remain hidden')
    target = ROOT / 'build/staging/close19/private'
    target.mkdir(parents=True, exist_ok=True)
    for source, name in [('/tmp/close19-fixture.json', 'fixture.json'),
                         ('/tmp/close19-media/material.pdf', 'material.pdf'),
                         ('/tmp/close19-media/test.mp4', 'test.mp4')]:
        run('docker', 'cp', WEB + ':' + source, str(target / name))
    (target.parent / 'context-seeded.json').write_text(snapshot, encoding='utf-8')
    print('SYNTHETIC_FIXTURE_READY; all earlier fixtures preserved and hidden')


if __name__ == '__main__':
    main()
