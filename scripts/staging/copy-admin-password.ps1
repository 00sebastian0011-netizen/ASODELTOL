[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$passwordFile = Join-Path $repoRoot 'build\staging\moodle53\secrets\admin-password.txt'

if (-not (Test-Path -LiteralPath $passwordFile -PathType Leaf)) {
    throw 'La credencial local no existe. Ejecute primero scripts/staging/prepare-moodle53.ps1.'
}

$password = [System.IO.File]::ReadAllText($passwordFile).Trim()
if ([string]::IsNullOrWhiteSpace($password)) {
    throw 'La credencial local está vacía.'
}

Set-Clipboard -Value $password
$password = $null
Write-Host 'Contraseña del administrador local copiada al portapapeles; no se mostró en pantalla.' -ForegroundColor Green
