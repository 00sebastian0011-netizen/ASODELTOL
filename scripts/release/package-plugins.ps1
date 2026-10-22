[CmdletBinding()]
param(
    [string]$OutputRoot
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$pluginsRoot = (Resolve-Path (Join-Path $repoRoot 'plugins')).Path

if ([string]::IsNullOrWhiteSpace($OutputRoot)) {
    $OutputRoot = Join-Path $repoRoot 'build'
}

$resolvedParent = [System.IO.Path]::GetFullPath($OutputRoot)
$expectedBuildRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot 'build'))
$buildPrefix = $expectedBuildRoot.TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
$isBuildRoot = $resolvedParent.Equals($expectedBuildRoot, [System.StringComparison]::OrdinalIgnoreCase)
$isWithinBuild = $resolvedParent.StartsWith($buildPrefix, [System.StringComparison]::OrdinalIgnoreCase)
if (-not $isBuildRoot -and -not $isWithinBuild) {
    throw "Por seguridad, OutputRoot debe estar dentro de $expectedBuildRoot"
}

& (Join-Path $repoRoot 'scripts\quality\validate-repository.ps1')
if (-not $?) {
    throw 'La validación del repositorio falló; no se generaron paquetes.'
}

$versionFiles = Get-ChildItem -LiteralPath $pluginsRoot -Recurse -File -Filter 'version.php'
if ($versionFiles.Count -eq 0) {
    throw 'No hay plugins instalables: ningún directorio contiene version.php.'
}

$releaseId = 'release-{0}' -f (Get-Date -Format 'yyyyMMdd-HHmmss')
$releaseDirectory = Join-Path $resolvedParent $releaseId
New-Item -ItemType Directory -Path $releaseDirectory -Force:$false | Out-Null

$manifestEntries = [System.Collections.Generic.List[object]]::new()

foreach ($versionFile in $versionFiles) {
    $pluginDirectory = $versionFile.Directory.FullName
    $relativePluginPath = $pluginDirectory.Substring($pluginsRoot.Length).TrimStart('\', '/').Replace('\', '/')

    if ($relativePluginPath -notmatch '^(local|report)/[^/]+$' -and
        $relativePluginPath -notmatch '^availability/condition/[^/]+$') {
        throw "Ruta de plugin no reconocida: $relativePluginPath"
    }

    $pluginName = Split-Path $pluginDirectory -Leaf
    $typePath = Split-Path $relativePluginPath -Parent
    $safeType = $typePath.Replace('\', '-').Replace('/', '-')
    $archiveName = "$safeType-$pluginName.zip"
    $archivePath = Join-Path $releaseDirectory $archiveName

    Compress-Archive -LiteralPath $pluginDirectory -DestinationPath $archivePath -CompressionLevel Optimal
    $hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()

    $manifestEntries.Add([pscustomobject]@{
        source = "plugins/$relativePluginPath"
        archive = $archiveName
        sha256 = $hash
    })
}

$commit = (& git -C $repoRoot rev-parse HEAD 2>$null)
if ($LASTEXITCODE -ne 0) {
    $commit = 'uncommitted'
}

$manifest = [ordered]@{
    release = $releaseId
    generated_at_utc = (Get-Date).ToUniversalTime().ToString('o')
    git_commit = $commit
    plugins = $manifestEntries
}

$manifestPath = Join-Path $releaseDirectory 'manifest.json'
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $manifestPath -Encoding utf8

Write-Host "Artefactos creados en: $releaseDirectory" -ForegroundColor Green
Write-Host 'Este script no instaló ni publicó ningún paquete.' -ForegroundColor Yellow
