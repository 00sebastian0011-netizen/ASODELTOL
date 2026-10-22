[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ReleaseDirectory
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$buildRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot 'build'))
$resolvedRelease = (Resolve-Path -LiteralPath $ReleaseDirectory).Path
$buildPrefix = $buildRoot.TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar

if (-not $resolvedRelease.StartsWith($buildPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Por seguridad, la release debe estar dentro de $buildRoot"
}

$manifestPath = Join-Path $resolvedRelease 'manifest.json'
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) {
    throw 'No existe manifest.json en el directorio indicado.'
}

$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
if (-not $manifest.plugins -or $manifest.plugins.Count -eq 0) {
    throw 'El manifiesto no contiene plugins.'
}

Add-Type -AssemblyName System.IO.Compression.FileSystem

foreach ($plugin in $manifest.plugins) {
    if ([string]::IsNullOrWhiteSpace($plugin.archive) -or $plugin.archive -match '[\\/]') {
        throw "Nombre de archivo inválido en el manifiesto: $($plugin.archive)"
    }

    $archivePath = Join-Path $resolvedRelease $plugin.archive
    if (-not (Test-Path -LiteralPath $archivePath -PathType Leaf)) {
        throw "Falta el archivo declarado: $($plugin.archive)"
    }

    $actualHash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualHash -ne $plugin.sha256.ToLowerInvariant()) {
        throw "Checksum incorrecto: $($plugin.archive)"
    }

    $expectedRoot = ($plugin.source -split '/')[-1] + '/'
    $archive = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
    try {
        if ($archive.Entries.Count -eq 0) {
            throw "ZIP vacío: $($plugin.archive)"
        }

        foreach ($entry in $archive.Entries) {
            $entryName = $entry.FullName.Replace('\', '/')
            if ($entryName.StartsWith('/') -or $entryName -match '(^|/)\.\.(/|$)') {
                throw "Ruta insegura en $($plugin.archive): $entryName"
            }
            if (-not $entryName.StartsWith($expectedRoot, [System.StringComparison]::Ordinal)) {
                throw "Raíz inesperada en $($plugin.archive): $entryName"
            }
        }
    }
    finally {
        $archive.Dispose()
    }
}

Write-Host 'Release verificada. Esto no instala ni publica los paquetes.' -ForegroundColor Green

