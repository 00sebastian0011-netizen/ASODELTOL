$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$deploy = Join-Path $root 'scripts/deploy'
$script = Join-Path $deploy 'deploy-asodeltol-plugin.ps1'
if (-not (Test-Path $script)) { throw 'Deployment script missing.' }
$text = Get-Content -Raw -LiteralPath $script
foreach ($pattern in @('git\s+(pull|clean|reset)', 'rsync\s+.*--delete', 'robocopy\s+.*(/MIR|/PURGE)', 'Remove-Item', 'rm\s+-rf')) { if ($text -match $pattern) { throw "Unsafe deployment pattern detected: $pattern" } }
foreach ($required in @('public[\\/]local[\\/]asodeltolstaff', 'BackupPath', 'moodledata', 'theme', 'rsmax', 'config\.php', '-Apply')) { if ($text -notmatch $required) { throw "Required deployment safeguard missing: $required" } }
Write-Output 'Deployment safety validation passed.'
