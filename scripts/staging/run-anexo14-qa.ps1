# ASODELTOL / Anexo 14 - QA integral no destructiva de Moodle staging local.
# Ejecutar desde Git Bash: powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/staging/run-anexo14-qa.ps1
# SOLO 127.0.0.1:8053; no crea cursos, no inicializa PHPUnit, no modifica config.php, no accede al VPS.
[CmdletBinding()]
param(
    [ValidateSet('All', 'Preflight', 'Http', 'Phpunit')]
    [string]$Phase = 'All'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$repo = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$container = 'asodeltol-moodle53-webserver-1'
$url = 'http://127.0.0.1:8053'
$reportDir = Join-Path $repo 'build\staging\anexo14-qa'
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$summaryPath = Join-Path $reportDir "qa_$timestamp.json"
$logPath = Join-Path $reportDir "qa_$timestamp.txt"
$secretPath = Join-Path $repo 'build\staging\moodle53\secrets\admin-password.txt'
$checks = New-Object 'System.Collections.Generic.List[object]'
$notes = New-Object 'System.Collections.Generic.List[string]'
$context = $null
$testOutput = @()
$webSession = $null
$phaseFailure = $null

New-Item -ItemType Directory -Path $reportDir -Force | Out-Null

function Record-Check {
    param([string]$Id, [string]$Status, [string]$Detail)
    $checks.Add([pscustomobject]@{ id = $Id; status = $Status; detail = $Detail })
    Write-Host "[$Status] $Id - $Detail"
}
function Assert-Ok {
    param([bool]$Condition, [string]$Message)
    if (-not $Condition) { throw $Message }
}
function Test-ZipXlsx {
    param([string]$Path)
    Add-Type -AssemblyName 'System.IO.Compression' -ErrorAction Stop
    Add-Type -AssemblyName 'System.IO.Compression.FileSystem' -ErrorAction Stop
    $stream = [System.IO.File]::OpenRead($Path)
    try {
        $zip = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Read, $true)
        try {
            $names = @($zip.Entries | ForEach-Object { $_.FullName })
            return ($names -contains '[Content_Types].xml' -and $names -contains 'xl/workbook.xml' -and
                (@($names | Where-Object { $_ -match '^xl/worksheets/sheet\d+\.xml$' }).Count -gt 0))
        } finally { $zip.Dispose() }
    } finally { $stream.Dispose() }
}
function Get-WebBytes {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) { return [byte[]]@() }
    $stream = [System.IO.File]::OpenRead($Path)
    try {
        $buf = [byte[]]::new(8)
        $count = $stream.Read($buf, 0, $buf.Length)
        if ($count -le 0) { return [byte[]]@() }
        return [byte[]]$buf[0..($count - 1)]
    } finally { $stream.Dispose() }
}
function Test-IsZip {
    param([string]$Path)
    $sig = @(Get-WebBytes $Path)
    return $sig.Count -ge 4 -and $sig[0] -eq 80 -and $sig[1] -eq 75 -and $sig[2] -eq 3 -and $sig[3] -eq 4
}

try {
    $branch = (& git -C $repo branch --show-current).Trim()
    Assert-Ok ($LASTEXITCODE -eq 0 -and $branch -eq 'moodle-completo-2026-10-24') 'La rama local no es moodle-completo-2026-10-24.'
    Record-Check 'GIT-BRANCH' 'PASS' $branch

    $running = @(& docker ps --filter 'label=com.docker.compose.project=asodeltol-moodle53' --format '{{.Names}}')
    Assert-Ok ($LASTEXITCODE -eq 0) 'Docker Engine no respondió.'
    Assert-Ok ($running -contains $container) 'El contenedor webserver de ASODELTOL no está activo.'
    Assert-Ok ($running -contains 'asodeltol-moodle53-database-1') 'La base de datos aislada de ASODELTOL no está activa.'
    Record-Check 'DOCKER-LOCAL' 'PASS' 'Contenedores ASODELTOL activos, proyecto Compose verificado.'

    # Script PHP montado sólo desde scripts/staging; no modifica datos.
    $probeOutput = @(& docker exec --user www-data $container php /opt/asodeltol/scripts/anexo14-qa-context.php 2>&1)
    Assert-Ok ($LASTEXITCODE -eq 0) ('El sondeo de Moodle falló: ' + ($probeOutput -join ' '))
    $marker = @($probeOutput | Where-Object { $_ -like 'ASODELTOL_QA_CONTEXT=*' })
    Assert-Ok ($marker.Count -eq 1) 'Moodle no devolvió un resultado JSON identificable.'
    $context = $marker[0].Substring('ASODELTOL_QA_CONTEXT='.Length) | ConvertFrom-Json
    Record-Check 'MOODLE-CONTEXT' 'PASS' ("Moodle {0}; PHP {1}; curso oculto {2}; plugin {3}; sesiones={4}" -f $context.release, $context.php, $context.course_idnumber, $context.plugin_version, $context.session_count)

    if ($Phase -in @('All', 'Preflight', 'Phpunit')) {
        $phpPaths = @(
            '/var/www/html/public/local/asodeltolstaff/classes/manager.php',
            '/var/www/html/public/local/asodeltolstaff/tests/manager_test.php',
            '/var/www/html/public/local/asodeltolstaff/export.php'
        )
        foreach ($phpPath in $phpPaths) {
            $lint = @(& docker exec --user www-data $container php -l $phpPath 2>&1)
            if ($LASTEXITCODE -eq 0) {
                Record-Check ('PHP-LINT:' + ($phpPath.Split('/')[-1])) 'PASS' 'Sintaxis PHP válida dentro del contenedor.'
            } else {
                Record-Check ('PHP-LINT:' + ($phpPath.Split('/')[-1])) 'FAIL' ($lint -join ' ')
            }
        }
        if ($context.phpunit_ready -eq $true) {
            $testPath = '/var/www/html/public/local/asodeltolstaff/tests/manager_test.php'
            $testOutput = @(& docker exec --user www-data --workdir /var/www/html $container php vendor/bin/phpunit -c phpunit.xml $testPath 2>&1)
            if ($LASTEXITCODE -eq 0) {
                Record-Check 'PHPUNIT-MANAGER' 'PASS' 'Prueba Moodle PHPUnit del plugin completada.'
            } else {
                Record-Check 'PHPUNIT-MANAGER' 'FAIL' ('Exit code: ' + $LASTEXITCODE + '; revisar la sección PHPUnit del informe.')
            }
        } else {
            Record-Check 'PHPUNIT-MANAGER' 'BLOCKED' 'La instalación habitual no está habilitada para PHPUnit. Usar scripts/staging/run-isolated-phpunit.py, que verifica cuenta MySQL exclusiva, grants y dataroot privado antes de init.php.'
        }
    }

    if ($Phase -in @('All', 'Http')) {
        # Control de URL: nunca HTTP fuera del localhost de desarrollo.
        $homepage = Invoke-WebRequest -Uri $url -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 30
        Assert-Ok ($homepage.StatusCode -eq 200 -and $homepage.Content -match 'ASODELTOL Moodle 5\.3 Staging') 'El sitio HTTP no coincide con el staging esperado.'
        Record-Check 'HTTP-LOCAL' 'PASS' 'Sitio Moodle local responde HTTP 200.'

        $endpoint = "$url/local/asodeltolstaff/export.php?courseid=$($context.course_id)&format=excel"
        $guestPath = Join-Path $reportDir "guest_$timestamp.dat"
        try {
            $guest = Invoke-WebRequest -Uri $endpoint -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 30 -OutFile $guestPath -PassThru
            if (Test-IsZip $guestPath) {
                Record-Check 'HTTP-GUEST-DENIED' 'FAIL' 'Invitado obtuvo un XLSX protegido sin autenticarse.'
            } elseif ($guest.StatusCode -eq 200 -and (Get-Item $guestPath).Length -gt 0) {
                Record-Check 'HTTP-GUEST-DENIED' 'PASS' 'Sin credenciales se recibió HTML, no un archivo XLSX.'
            } else {
                Record-Check 'HTTP-GUEST-DENIED' 'FAIL' 'Resultado inesperado para petición sin sesión.'
            }
        } catch [System.Net.WebException] {
            $webResponse = $_.Exception.Response
            if ($null -ne $webResponse -and [int]$webResponse.StatusCode -in @(401, 403)) {
                Record-Check 'HTTP-GUEST-DENIED' 'PASS' 'Servidor denegó acceso sin sesión (401/403).'
            } else {
                Record-Check 'HTTP-GUEST-DENIED' 'FAIL' 'Error HTTP inesperado para usuario invitado.'
            }
        }
        if (Test-Path $guestPath) { Remove-Item -LiteralPath $guestPath -Force }

        Assert-Ok (Test-Path -LiteralPath $secretPath -PathType Leaf) 'No existe el archivo de contraseña local del administrador sintético.'
        $password = [System.IO.File]::ReadAllText($secretPath).Trim()
        Assert-Ok ($password.Length -ge 12) 'La contraseña sintética local es inesperadamente corta.'
        $webSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
        $form = $null
        try {
            $loginPage = Invoke-WebRequest -Uri "$url/login/index.php" -WebSession $webSession -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 30
            Assert-Ok ($loginPage.StatusCode -eq 200) 'No fue posible abrir la página de ingreso local.'
            $tokenTag = [regex]::Match($loginPage.Content, '(?is)<input\b[^>]*name="logintoken"[^>]*>')
            $tokenMatch = [regex]::Match($tokenTag.Value, 'value="([^"]+)"')
            Assert-Ok $tokenMatch.Success 'El formulario no devolvió logintoken esperado; comprobar cambios de versión/tema.'
            $form = @{
                username = 'asodeltol_admin'
                password = $password
                logintoken = $tokenMatch.Groups[1].Value
                rememberusername = '0'
            }
            $null = Invoke-WebRequest -Uri "$url/login/index.php" -Method Post -Body $form -WebSession $webSession -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 30
        } finally {
            $password = $null
            if ($null -ne $form) { $form['password'] = '' }
        }
        Record-Check 'HTTP-LOGIN' 'PASS' 'Sesión local sintética enviada sin almacenar el secreto en evidencia (la exportación confirmará autenticación).'

        foreach ($format in @('excel', 'csv')) {
            $ext = if ($format -eq 'excel') { 'xlsx' } else { 'csv' }
            $filename = Join-Path $reportDir ("export_$timestamp.$ext")
            $getUrl = "$url/local/asodeltolstaff/export.php?courseid=$($context.course_id)&format=$format"
            $download = Invoke-WebRequest -Uri $getUrl -WebSession $webSession -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 60 -OutFile $filename -PassThru
            Assert-Ok ($download.StatusCode -eq 200 -and (Get-Item $filename).Length -gt 0) "La exportación $format no devolvió archivo válido."
            if ($format -eq 'excel') {
                if (Test-IsZip $filename -and (Test-ZipXlsx $filename)) {
                    if ([int]$context.session_count -gt 0) {
                        Record-Check 'HTTP-XLSX' 'PASS' 'Archivo XLSX descargado; ZIP/OOXML verificado; existen sesiones sintéticas en staging.'
                    } else {
                        Record-Check 'HTTP-XLSX' 'PARTIAL' 'XLSX estructuralmente válido, pero no existen sesiones para comprobar filas de datos.'
                    }
                } else {
                    Record-Check 'HTTP-XLSX' 'FAIL' 'La respuesta no es un XLSX válido (posible HTML de login/error).'
                }
            } else {
                $content = [System.IO.File]::ReadAllText($filename)
                if ($content -match '(?i)<html|<!doctype html|<form') {
                    Record-Check 'HTTP-CSV' 'FAIL' 'La respuesta CSV contiene HTML (posible sesión no autenticada).'
                } elseif ($content.Length -gt 15 -and $content -match '(?im)trainer|capacitador|student|estudiante') {
                    if ([int]$context.session_count -gt 0) {
                        Record-Check 'HTTP-CSV' 'PASS' 'CSV descargado con encabezados de estudiantes/capacitadores; existen sesiones sintéticas.'
                    } else {
                        Record-Check 'HTTP-CSV' 'PARTIAL' 'CSV con encabezados pero sin sesiones: no demuestra todavía exportación de filas.'
                    }
                } else {
                    Record-Check 'HTTP-CSV' 'FAIL' 'CSV vacío o encabezados no identificables.'
                }
            }
        }
        $notes.Add('Las descargas HTTP se conservaron en build/staging/anexo14-qa. Contienen solo los datos que existan en el staging.')
        $notes.Add('XLSX estructuralmente válido no prueba que las columnas contractuales del Anexo 12 estén aprobadas.')
    }
} catch {
    $phaseFailure = $_.Exception.Message
    Record-Check 'EXECUTION' 'FAIL' $phaseFailure
} finally {
    $c = $checks.ToArray()
    $pass = @($c | Where-Object { $_.status -eq 'PASS' }).Count
    $fail = @($c | Where-Object { $_.status -eq 'FAIL' }).Count
    $blocked = @($c | Where-Object { $_.status -eq 'BLOCKED' }).Count
    $partial = @($c | Where-Object { $_.status -eq 'PARTIAL' }).Count
    $summary = [ordered]@{
        generated_at = (Get-Date).ToString('o')
        project = 'ASODELTOL AF8 / Anexo 14'
        environment = 'local-staging-only'
        phase = $Phase
        target_url = $url
        checks = $c
        pass = $pass
        fail = $fail
        blocked = $blocked
        partial = $partial
        context = $context
        notes = $notes.ToArray()
        phpunit_output = @($testOutput | Select-Object -Last 50 | ForEach-Object { [string]$_ })
        error = $phaseFailure
    }
    [System.IO.File]::WriteAllText($summaryPath, (($summary | ConvertTo-Json -Depth 8) + "`r`n"), ([System.Text.UTF8Encoding]::new($false)))
    $logLines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in @(
        'ASODELTOL / Anexo14 - QA local',
        ('Fecha: ' + (Get-Date).ToString('o')),
        ('Fase: ' + $Phase),
        ('PASS=' + $pass + '; FAIL=' + $fail + '; BLOCKED=' + $blocked + '; PARTIAL=' + $partial),
        ''
    )) { [void]$logLines.Add([string]$line) }
    foreach ($check in $c) { [void]$logLines.Add("[$($check.status)] $($check.id): $($check.detail)") }
    [void]$logLines.Add('')
    [void]$logLines.Add('PHPUNIT:')
    foreach ($line in ($testOutput | Select-Object -Last 50)) { [void]$logLines.Add([string]$line) }
    [void]$logLines.Add('')
    [void]$logLines.Add('OBSERVACIONES:')
    foreach ($line in $notes) { [void]$logLines.Add([string]$line) }
    [void]$logLines.Add('')
    [void]$logLines.Add(('ERROR=' + $phaseFailure))
    [System.IO.File]::WriteAllLines($logPath, $logLines.ToArray(), ([System.Text.UTF8Encoding]::new($false)))
    Write-Host "RESULTADO: PASS=$pass FAIL=$fail BLOCKED=$blocked PARTIAL=$partial"
    Write-Host "INFORME_TXT=$logPath"
    Write-Host "INFORME_JSON=$summaryPath"
    Write-Host 'Sin push, sin alteraciones de cursos, sin escritura en VPS.'
    if ($fail -gt 0) { exit 1 }
    if ($blocked -gt 0 -or $partial -gt 0) { exit 2 }
}
