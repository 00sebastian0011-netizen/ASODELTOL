param(
    [Parameter(Mandatory=$true)][string]$Source,
    [Parameter(Mandatory=$true)][string]$Destination,
    [string]$BackupPath,
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'
$sourcePath = (Resolve-Path -LiteralPath $Source).Path
if (-not (Test-Path -LiteralPath (Join-Path $sourcePath 'version.php'))) { throw 'Source must be the local/asodeltolstaff plugin directory.' }
$resolvedDestination = [IO.Path]::GetFullPath($Destination)
if ($resolvedDestination -notmatch '(?i)[\\/]public[\\/]local[\\/]asodeltolstaff$') { throw 'Destination must end in public/local/asodeltolstaff.' }
if ($resolvedDestination -match '(?i)(moodledata|theme[\\/]rsmax|config\.php)') { throw 'Protected Moodle paths cannot be deployment targets.' }
$files = Get-ChildItem -LiteralPath $sourcePath -File -Recurse | Where-Object { $_.FullName -notmatch '(?i)(\.git|build|\.env|config\.php|moodledata|\.sql$|\.key$|\.pem$)' }
if (-not $files) { throw 'No deployable plugin files found.' }
if (-not $Apply) { Write-Output ("DRY RUN: {0} files would be copied to {1}. Re-run with -Apply and an explicit -BackupPath." -f $files.Count,$resolvedDestination); exit 0 }
if ([string]::IsNullOrWhiteSpace($BackupPath)) { throw '-BackupPath is required for an applying deployment.' }
$backup = [IO.Path]::GetFullPath($BackupPath)
if ($backup -eq $resolvedDestination -or $backup.StartsWith($resolvedDestination + [IO.Path]::DirectorySeparatorChar)) { throw 'BackupPath cannot be inside the deployment target.' }
New-Item -ItemType Directory -Force -Path $backup | Out-Null
if (Test-Path -LiteralPath $resolvedDestination) { Copy-Item -LiteralPath $resolvedDestination -Destination (Join-Path $backup 'asodeltolstaff') -Recurse -Force }
foreach ($file in $files) {
    $relative = $file.FullName.Substring($sourcePath.Length).TrimStart('\\','/')
    $target = Join-Path $resolvedDestination $relative
    $parent = Split-Path -Parent $target
    New-Item -ItemType Directory -Force -Path $parent | Out-Null
    Copy-Item -LiteralPath $file.FullName -Destination $target -Force
}
Write-Output ("Applied allowlisted plugin copy: {0} files. No delete, sync, Git, config.php, moodledata or theme changes performed." -f $files.Count)
