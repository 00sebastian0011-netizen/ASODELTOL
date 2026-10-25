[CmdletBinding()]
param(
    [ValidateSet('Validate', 'Execute', 'Cleanup')]
    [string]$Phase = 'Validate'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$prepareScript = Join-Path $PSScriptRoot 'prepare-moodle53.ps1'
$managementScript = '/opt/asodeltol/anexo14/management-smoke.php'
$container = 'asodeltol-moodle53-webserver-1'

$prepareArguments = @(
    '-NoProfile',
    '-ExecutionPolicy', 'Bypass',
    '-File', $prepareScript,
    '-VerifyOnly',
    '-SkipPull'
)
& powershell.exe @prepareArguments
if ($LASTEXITCODE -ne 0) {
    throw "No se pudo validar/iniciar el staging local (código $LASTEXITCODE)."
}

$arguments = @('exec', '--user', 'www-data', $container, 'php', $managementScript, '--mode=staging')
switch ($Phase) {
    'Execute' {
        $arguments += @('--write')
    }
    'Cleanup' {
        $arguments += @('--cleanup')
    }
}

& docker @arguments
if ($LASTEXITCODE -ne 0) {
    if ($Phase -eq 'Execute') {
        throw "Falló la prueba o su limpieza. Revise moodledata/temp/anexo14-management-smoke y ejecute -Phase Cleanup (código $LASTEXITCODE)."
    }
    throw "Falló la fase Anexo 14 '$Phase' (código $LASTEXITCODE)."
}
