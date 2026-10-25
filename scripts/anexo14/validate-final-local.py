"""Focused syntax validation of delivered plugin and QA tools; no Moodle writes."""
import ast
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[2]
checks = []
php = '''<?php
$paths = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/var/www/html/public/local/asodeltolstaff',
        FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') { $paths[] = $file->getPathname(); }
}
foreach (glob('/opt/asodeltol/scripts/*anexo14*.php') as $file) { $paths[] = $file; }
foreach (['anexo14-functional-context.php', 'check-native-messaging.php', 'configure-local-timezone.php'] as $file) {
    $paths[] = '/opt/asodeltol/scripts/' . $file;
}
$count = 0;
foreach (array_unique($paths) as $path) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-l', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { fwrite(STDERR, $output); exit(1); }
    $count++;
}
echo json_encode(['id' => 'PHP-LINT', 'status' => 'PASS', 'files' => $count]);
'''
result = subprocess.run(['docker', 'exec', '-i', '--user', 'www-data',
    'asodeltol-moodle53-webserver-1', 'php', '/dev/stdin'], input=php,
    text=True, capture_output=True, check=True)
checks.append(json.loads(result.stdout))
pythonfiles = list((ROOT / 'scripts/anexo14').glob('*.py'))
pythonfiles += [p for p in (ROOT / 'scripts/staging').glob('*.py')
                if p.name.startswith(('check-', 'run-isolated-', 'retest-', 'build-file-'))]
for path in pythonfiles:
    ast.parse(path.read_text(encoding='utf-8-sig'), filename=path.name)
checks.append({'id': 'PYTHON-SYNTAX', 'status': 'PASS', 'files': len(pythonfiles)})
subprocess.run(['node', '--check', 'scripts/staging/check-staff-browser.mjs'], cwd=ROOT, check=True)
checks.append({'id': 'NODE-SYNTAX', 'status': 'PASS'})
ps = '''$files = @('scripts/staging/run-anexo14-qa.ps1', 'scripts/staging/run-attendance-staff.ps1',
    'scripts/staging/check-hidden-course.ps1')
foreach ($file in $files) {
    $tokens = $null; $errors = $null
    [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path -LiteralPath $file), [ref]$tokens, [ref]$errors) | Out-Null
    if ($errors.Count -gt 0) { throw 'PowerShell parse error' }
}
'''
subprocess.run(['powershell', '-NoProfile', '-Command', ps], cwd=ROOT, check=True, capture_output=True)
checks.append({'id': 'POWERSHELL-SYNTAX', 'status': 'PASS', 'files': 3})
diff = subprocess.run(['git', 'diff', '--check'], cwd=ROOT, text=True, capture_output=True)
checks.append({'id': 'GIT-DIFF-CHECK', 'status': 'PASS' if diff.returncode == 0 else 'FAIL',
               'output': diff.stdout + diff.stderr})
out = ROOT / 'build/staging/anexo14-compliance/static-validation.json'
out.write_text(json.dumps({'checks': checks}, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({'checks': checks}, ensure_ascii=False))
raise SystemExit(0 if all(c['status'] == 'PASS' for c in checks) else 1)
