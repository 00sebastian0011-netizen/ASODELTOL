[CmdletBinding()]
param(
    [ValidateSet('All', 'Setup', 'Exercise', 'Verify', 'Lockdown')]
    [string]$Phase = 'All'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.Net.Http

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$runtimeRoot = Join-Path $repositoryRoot 'build\staging\moodle53'
$evidencePath = Join-Path $runtimeRoot 'mission-11-evidence.json'
$studentSecretPath = Join-Path $runtimeRoot 'secrets\student-password.txt'
$adminSecretPath = Join-Path $runtimeRoot 'secrets\admin-password.txt'
$container = 'asodeltol-moodle53-webserver-1'
$baseUrl = 'http://127.0.0.1:8053'
$fixture = '/opt/asodeltol/scripts/moodle53-attendance-staff.php'

function Invoke-M11Phase {
    param([Parameter(Mandatory)][string]$Name)

    $output = & docker exec --user www-data $container php $fixture "--phase=$Name" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Falló la fase M11 '$Name': $($output -join [Environment]::NewLine)"
    }
    return ($output -join [Environment]::NewLine)
}

function New-MoodleSession {
    param(
        [Parameter(Mandatory)][string]$Username,
        [Parameter(Mandatory)][string]$Password
    )

    $handler = [System.Net.Http.HttpClientHandler]::new()
    $handler.AllowAutoRedirect = $true
    $handler.UseCookies = $true
    $handler.CookieContainer = [System.Net.CookieContainer]::new()
    $client = [System.Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromSeconds(120)
    $login = $client.GetAsync("$baseUrl/login/index.php").GetAwaiter().GetResult()
    $html = $login.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    $token = [regex]::Match($html, 'name="logintoken"\s+value="([^"]+)"')
    if (-not $token.Success) {
        throw "Moodle no entregó token de acceso para '$Username'."
    }
    $values = [System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]]::new()
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('username', $Username))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('password', $Password))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('logintoken', $token.Groups[1].Value))
    $response = $client.PostAsync(
        "$baseUrl/login/index.php",
        [System.Net.Http.FormUrlEncodedContent]::new($values)
    ).GetAwaiter().GetResult()
    $content = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($content -match 'Invalid login|loginerrormessage') {
        $client.Dispose()
        throw "Moodle rechazó la cuenta sintética '$Username'."
    }
    return $client
}

function Get-MoodleResponse {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpClient]$Session,
        [Parameter(Mandatory)][string]$Path
    )

    $response = $Session.GetAsync("$baseUrl$Path").GetAwaiter().GetResult()
    $bytes = $response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
    return [pscustomobject]@{
        StatusCode = [int]$response.StatusCode
        FinalUri = [string]$response.RequestMessage.RequestUri
        ContentType = [string]$response.Content.Headers.ContentType
        Bytes = $bytes
        Text = [System.Text.Encoding]::UTF8.GetString($bytes)
    }
}

function Add-Control {
    param(
        [Parameter(Mandatory)][System.Collections.Generic.List[object]]$Controls,
        [Parameter(Mandatory)][string]$Id,
        [Parameter(Mandatory)][bool]$Passed,
        [Parameter(Mandatory)][string]$Detail
    )

    $Controls.Add([pscustomobject]@{ id = $Id; passed = $Passed; detail = $Detail })
}

function Test-XlsxMagic {
    param([byte[]]$Bytes)
    return $Bytes.Length -gt 4 -and $Bytes[0] -eq 0x50 -and $Bytes[1] -eq 0x4B
}

$lockdownApplied = $false
try {
    # Recover safely from an interrupted prior acceptance run before the baseline verifier executes.
    $fixtureAvailable = & docker exec $container test -f $fixture 2>$null
    if ($LASTEXITCODE -eq 0) {
        try {
            Invoke-M11Phase -Name 'lockdown' | Out-Host
            $lockdownApplied = $true
        }
        catch {
            # A first installation has no plugin tables yet; the normal upgrade path follows.
            $lockdownApplied = $false
        }
    }
    powershell -NoProfile -File (Join-Path $PSScriptRoot 'prepare-moodle53.ps1') -VerifyOnly -SkipPull | Out-Host
    $lockdownApplied = $false
    $pluginStatus = (Invoke-M11Phase -Name 'plugin-status') | ConvertFrom-Json
    if (-not $pluginStatus.installed -or [int]$pluginStatus.version -ne [int]$pluginStatus.expected) {
        $previousErrorPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $upgrade = & docker exec --user www-data $container php /var/www/html/admin/cli/upgrade.php --non-interactive 2>&1
        $upgradeExitCode = $LASTEXITCODE
        $ErrorActionPreference = $previousErrorPreference
        if ($upgradeExitCode -ne 0) {
            throw "Moodle no pudo instalar/actualizar local_asodeltolstaff: $($upgrade -join [Environment]::NewLine)"
        }
    }

    if ($Phase -eq 'Lockdown') {
        Invoke-M11Phase -Name 'lockdown' | Out-Host
        $lockdownApplied = $true
        return
    }
    if ($Phase -in @('All', 'Setup')) {
        Invoke-M11Phase -Name 'setup' | Out-Host
    }
    if ($Phase -in @('All', 'Exercise')) {
        Invoke-M11Phase -Name 'exercise' | Out-Host
    }
    if ($Phase -eq 'Setup' -or $Phase -eq 'Exercise') {
        return
    }

    $serviceEvidence = (Invoke-M11Phase -Name 'verify') | ConvertFrom-Json
    if ($serviceEvidence.status -ne 'PASS') {
        throw 'Los controles internos M11 no fueron satisfactorios.'
    }
    $controls = [System.Collections.Generic.List[object]]::new()
    foreach ($control in $serviceEvidence.controls) {
        $controls.Add($control)
    }

    $studentPassword = (Get-Content -LiteralPath $studentSecretPath -Raw).Trim()
    $adminPassword = (Get-Content -LiteralPath $adminSecretPath -Raw).Trim()
    if ([string]::IsNullOrWhiteSpace($studentPassword) -or [string]::IsNullOrWhiteSpace($adminPassword)) {
        throw 'Los secretos exclusivamente locales del staging no están disponibles.'
    }

    $courseId = [int]$serviceEvidence.course.id
    $g01SessionId = [int]$serviceEvidence.sessions.G01.id
    $g02SessionId = [int]$serviceEvidence.sessions.G02.id
    $studentId = [int]$serviceEvidence.actors.student_g01.id
    $trainer = New-MoodleSession -Username 'af8.synthetic.trainer.g01' -Password $studentPassword
    $student = New-MoodleSession -Username 'af8.synthetic.g01' -Password $studentPassword
    $auditor = New-MoodleSession -Username 'af8.synthetic.auditor' -Password $studentPassword
    $admin = New-MoodleSession -Username 'asodeltol_admin' -Password $adminPassword

    try {
        $trainerIndex = Get-MoodleResponse -Session $trainer -Path "/local/asodeltolstaff/index.php?courseid=$courseId"
        Add-Control $controls 'M11-HTTP-001' (
            $trainerIndex.StatusCode -eq 200 -and
            $trainerIndex.Text.Contains('M11-AF8-G01-01') -and
            -not $trainerIndex.Text.Contains('M11-AF8-G02-01')
        ) 'La vista del capacitador G01 no enumera la sesión G02.'

        $trainerOtherHistory = Get-MoodleResponse -Session $trainer -Path "/local/asodeltolstaff/history.php?id=$g02SessionId"
        Add-Control $controls 'M11-HTTP-002' (
            -not $trainerOtherHistory.Text.Contains('evidence.example.invalid/m11/g02') -and
            -not $trainerOtherHistory.Text.Contains('attendance_created')
        ) 'La URL directa del historial G02 no expone evidencia ni cambios al capacitador G01.'

        $studentIndex = Get-MoodleResponse -Session $student -Path "/local/asodeltolstaff/index.php?courseid=$courseId"
        Add-Control $controls 'M11-HTTP-003' (
            $studentIndex.StatusCode -eq 200 -and
            $studentIndex.Text.Contains('M11-AF8-G01-01') -and
            -not $studentIndex.Text.Contains('M11-AF8-G02-01') -and
            -not $studentIndex.Text.Contains('evidence.example.invalid')
        ) 'El estudiante sólo ve su grupo y no recibe referencias de evidencia.'

        $studentAttendance = Get-MoodleResponse -Session $student -Path "/local/asodeltolstaff/attendance.php?id=$g01SessionId"
        $studentStatusField = 'name="status_{0}"' -f $studentId
        Add-Control $controls 'M11-HTTP-004' (
            -not $studentAttendance.Text.Contains($studentStatusField) -and
            -not $studentAttendance.Text.Contains('saveattendance')
        ) 'La URL directa no entrega al estudiante un formulario para alterar asistencia.'

        $auditorIndex = Get-MoodleResponse -Session $auditor -Path "/local/asodeltolstaff/index.php?courseid=$courseId"
        $auditorValidation = Get-MoodleResponse -Session $auditor -Path "/local/asodeltolstaff/validate.php?id=$g01SessionId"
        Add-Control $controls 'M11-HTTP-005' (
            $auditorIndex.Text.Contains('M11-AF8-G01-01') -and
            $auditorIndex.Text.Contains('M11-AF8-G02-01') -and
            $auditorIndex.Text.Contains('evidence.example.invalid/m11/g01') -and
            -not $auditorValidation.Text.Contains('name="validationstatus"')
        ) 'Auditoría consulta ambos grupos, pero no recibe el formulario administrativo de validación.'

        $adminHistory = Get-MoodleResponse -Session $admin -Path "/local/asodeltolstaff/history.php?id=$g01SessionId"
        Add-Control $controls 'M11-HTTP-006' (
            $adminHistory.StatusCode -eq 200 -and
            $adminHistory.Text.Contains('attendance_updated') -and
            $adminHistory.Text.Contains('validation_validated') -and
            $adminHistory.Text.Contains('evidence.example.invalid')
        ) 'Administración consulta el historial y la referencia de evidencia autorizada.'

        $beforeCsrfHistory = [int]$serviceEvidence.history_count_g01
        $csrfValues = [System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]]::new()
        $csrfValues.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('id', "$g01SessionId"))
        $csrfValues.Add([System.Collections.Generic.KeyValuePair[string,string]]::new("status_$studentId", 'reported_absent'))
        $csrfValues.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('reason', 'M11 missing sesskey negative test'))
        $null = $trainer.PostAsync(
            "$baseUrl/local/asodeltolstaff/attendance.php?id=$g01SessionId",
            [System.Net.Http.FormUrlEncodedContent]::new($csrfValues)
        ).GetAwaiter().GetResult()
        $afterCsrfEvidence = (Invoke-M11Phase -Name 'verify') | ConvertFrom-Json
        Add-Control $controls 'M11-CSRF-001' (
            [int]$afterCsrfEvidence.history_count_g01 -eq $beforeCsrfHistory -and
            [int]$afterCsrfEvidence.summary.failed -eq 0
        ) 'El POST sin sesskey no creó cambios y la asistencia permaneció intacta.'

        $trainerCsv = Get-MoodleResponse -Session $trainer -Path "/local/asodeltolstaff/export.php?courseid=$courseId&format=csv"
        Add-Control $controls 'M11-EXP-001' (
            $trainerCsv.Text.Contains('M11-AF8-G01-01') -and
            -not $trainerCsv.Text.Contains('M11-AF8-G02-01')
        ) 'La exportación CSV del capacitador conserva el filtro G01.'

        $auditorXlsx = Get-MoodleResponse -Session $auditor -Path "/local/asodeltolstaff/export.php?courseid=$courseId&format=excel"
        Add-Control $controls 'M11-EXP-002' (Test-XlsxMagic -Bytes $auditorXlsx.Bytes) (
            'Moodle generó un libro XLSX para la auditoría autorizada.'
        )

        $adminCsv = Get-MoodleResponse -Session $admin -Path "/local/asodeltolstaff/export.php?courseid=$courseId&format=csv"
        Add-Control $controls 'M11-EXP-003' (
            $adminCsv.Text.Contains('M11-AF8-G01-01') -and
            $adminCsv.Text.Contains('M11-AF8-G02-01') -and
            $adminCsv.Text.Contains('reported_present') -and
            $adminCsv.Text.Contains('reported_absent')
        ) 'El reporte administrativo contiene sesión, grupo y asistencia de ambos escenarios sintéticos.'

        $studentXlsx = Get-MoodleResponse -Session $student -Path "/local/asodeltolstaff/export.php?courseid=$courseId&format=excel"
        Add-Control $controls 'M11-EXP-004' (-not (Test-XlsxMagic -Bytes $studentXlsx.Bytes)) (
            'El estudiante no puede descargar el reporte institucional.'
        )
    }
    finally {
        $trainer.Dispose()
        $student.Dispose()
        $auditor.Dispose()
        $admin.Dispose()
        $studentPassword = $null
        $adminPassword = $null
    }

    $failed = @($controls | Where-Object { -not $_.passed })
    $evidence = [ordered]@{
        generated_at = (Get-Date).ToUniversalTime().ToString('o')
        scope = 'Moodle 5.3 local aislado; sesiones, cuentas y datos exclusivamente sintéticos'
        policy_definitive = $false
        plugin = 'local_asodeltolstaff 0.1.1'
        service = $serviceEvidence
        controls = $controls
        summary = [ordered]@{
            passed = @($controls | Where-Object passed).Count
            failed = $failed.Count
            total = $controls.Count
        }
    }
    New-Item -ItemType Directory -Path $runtimeRoot -Force | Out-Null
    $evidence | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $evidencePath -Encoding utf8
    $evidence.summary | Format-List | Out-Host
    Write-Output "EVIDENCE_PATH=$evidencePath"
    if ($failed.Count -gt 0) {
        $failed | Format-Table id, detail -AutoSize | Out-Host
        throw "$($failed.Count) controles M11 fallaron."
    }
}
finally {
    if (-not $lockdownApplied) {
        try {
            Invoke-M11Phase -Name 'lockdown' | Out-Host
            $lockdownApplied = $true
        }
        catch {
            Write-Error "No fue posible ocultar el staging al finalizar. $($_.Exception.Message)"
        }
    }
    if ($lockdownApplied) {
        $status = (Invoke-M11Phase -Name 'status') | ConvertFrom-Json
        if ($status.course_visible -or $status.category_visible) {
            Write-Error 'El curso o la categoría de staging quedaron visibles.'
        }
        else {
            Write-Output 'STAGING_LOCKDOWN=PASS'
        }
    }
}
