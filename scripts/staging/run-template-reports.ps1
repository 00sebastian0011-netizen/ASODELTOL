[CmdletBinding()]
param(
    [ValidateSet('All', 'Create', 'Reports', 'Verify')]
    [string]$Phase = 'All'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.Net.Http

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$stagingRoot = Join-Path $repositoryRoot 'build\staging\moodle53'
$missionRoot = Join-Path $stagingRoot 'mission-09'
$templateDirectory = Join-Path $missionRoot 'template'
$reportsDirectory = Join-Path $missionRoot 'reports'
$evidencePath = Join-Path $missionRoot 'mission-09-evidence.json'
$adminSecretPath = Join-Path $stagingRoot 'secrets\admin-password.txt'
$container = 'asodeltol-moodle53-webserver-1'
$baseUrl = 'http://127.0.0.1:8053'
$moodleScript = '/opt/asodeltol/scripts/moodle53-template-reports.php'

function Invoke-M09Phase {
    param([Parameter(Mandatory)][string]$Name)

    # Moodle CLI must use the same account as Apache. Running it as container root
    # creates root-owned cache locks in moodledata and breaks subsequent web access.
    $output = & docker exec --user www-data $container php $moodleScript "--phase=$Name" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo la fase local '$Name': $($output -join [Environment]::NewLine)"
    }
    return (($output -join [Environment]::NewLine) | ConvertFrom-Json)
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
    $client.Timeout = [TimeSpan]::FromSeconds(180)

    $loginResponse = $client.GetAsync("$baseUrl/login/index.php").GetAwaiter().GetResult()
    $loginContent = $loginResponse.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    $token = [regex]::Match($loginContent, 'name="logintoken"\s+value="([^"]+)"')
    if (-not $token.Success) {
        throw 'Moodle no entrego el token de acceso local.'
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
    if ($content -match 'Invalid login|Acceso invalido|loginerrormessage') {
        throw 'Moodle rechazo el acceso del administrador local.'
    }
    return $client
}

function Get-MoodleResponse {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpClient]$Session,
        [Parameter(Mandatory)][string]$Path
    )

    for ($attempt = 1; $attempt -le 2; $attempt++) {
        try {
            return $Session.GetAsync("$baseUrl$Path").GetAwaiter().GetResult()
        } catch {
            if ($attempt -eq 2) {
                throw
            }
            Start-Sleep -Seconds 2
        }
    }
}

function Get-Sesskey {
    param([Parameter(Mandatory)][string]$Html)

    $match = [regex]::Match($Html, 'name="sesskey"\s+value="([^"]+)"')
    if (-not $match.Success) {
        $match = [regex]::Match($Html, '"sesskey"\s*:\s*"([^"]+)"')
    }
    if (-not $match.Success) {
        throw 'No fue posible obtener el sesskey de una pagina administrativa local.'
    }
    return $match.Groups[1].Value
}

function Save-NativeDownload {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpResponseMessage]$Response,
        [Parameter(Mandatory)][string]$Path,
        [Parameter(Mandatory)][ValidateSet('xlsx', 'xls')]$Format
    )

    if (-not $Response.IsSuccessStatusCode) {
        throw "La exportacion nativa respondio HTTP $([int]$Response.StatusCode) en $($Response.RequestMessage.RequestUri.AbsolutePath)."
    }
    $mediaType = [string]$Response.Content.Headers.ContentType.MediaType
    if ($mediaType -match 'text/html') {
        $html = $Response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        $title = [regex]::Match($html, '<title>([^<]+)</title>', 'IgnoreCase').Groups[1].Value
        throw "Moodle devolvio HTML en vez de $Format. Pagina: $title"
    }

    $bytes = $Response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
    if ($bytes.Length -lt 8) {
        throw "La exportacion $Format esta vacia o incompleta."
    }
    if ($Format -eq 'xlsx') {
        $valid = $bytes[0] -eq 0x50 -and $bytes[1] -eq 0x4B -and $bytes[2] -eq 0x03 -and $bytes[3] -eq 0x04
    } else {
        $ole = @(0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1)
        $valid = $true
        for ($index = 0; $index -lt $ole.Count; $index++) {
            if ($bytes[$index] -ne $ole[$index]) {
                $valid = $false
                break
            }
        }
    }
    if (-not $valid) {
        throw "La firma binaria de la exportacion $Format no es valida."
    }

    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Force
    }
    [IO.File]::WriteAllBytes($Path, $bytes)
    return [pscustomobject]@{
        path = $Path
        filename = [IO.Path]::GetFileName($Path)
        format = $Format
        media_type = $mediaType
        bytes = $bytes.Length
        sha256 = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
        status_code = [int]$Response.StatusCode
    }
}

function Export-Participants {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpClient]$Session,
        [Parameter(Mandatory)][int]$CourseId,
        [Parameter(Mandatory)][int[]]$UserIds,
        [Parameter(Mandatory)][string]$Path
    )

    $page = Get-MoodleResponse -Session $Session -Path "/user/index.php?id=$CourseId"
    $html = $page.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    $sesskey = Get-Sesskey -Html $html
    $values = [System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]]::new()
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('id', [string]$CourseId))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('sesskey', $sesskey))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new(
        'formaction',
        'bulkchange.php?operation=download_participants&dataformat=excel'
    ))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('returnto', "/user/index.php?id=$CourseId"))
    foreach ($userId in $UserIds) {
        $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('bulkuser[]', [string]$userId))
    }
    $response = $Session.PostAsync(
        "$baseUrl/user/action_redir.php",
        [System.Net.Http.FormUrlEncodedContent]::new($values)
    ).GetAwaiter().GetResult()
    return Save-NativeDownload -Response $response -Path $Path -Format 'xlsx'
}

function Export-GradeXls {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpClient]$Session,
        [Parameter(Mandatory)][int]$CourseId,
        [Parameter(Mandatory)][int]$GroupId,
        [Parameter(Mandatory)][string]$Path
    )

    $page = Get-MoodleResponse -Session $Session -Path "/grade/export/xls/index.php?id=$CourseId&group=$GroupId"
    $html = $page.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    $sesskey = Get-Sesskey -Html $html
    $itemids = [System.Collections.Generic.HashSet[string]]::new()
    foreach ($match in [regex]::Matches($html, '<input\b[^>]*name="itemids\[(\d+)\]"[^>]*>', 'IgnoreCase')) {
        if ($match.Value -match 'type="checkbox"' -and $match.Value -match '\bchecked') {
            [void]$itemids.Add($match.Groups[1].Value)
        }
    }
    if ($itemids.Count -eq 0) {
        throw 'El formulario nativo no presento elementos de calificacion exportables.'
    }

    $values = [System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]]::new()
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('id', [string]$CourseId))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('sesskey', $sesskey))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('_qf__grade_export_form', '1'))
    foreach ($itemid in $itemids) {
        $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new("itemids[$itemid]", '1'))
    }
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('export_feedback', '0'))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('export_onlyactive', '1'))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('display[real]', '1'))
    $values.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('submitbutton', 'Download'))
    $response = $Session.PostAsync(
        "$baseUrl/grade/export/xls/export.php?id=$CourseId&group=$GroupId",
        [System.Net.Http.FormUrlEncodedContent]::new($values)
    ).GetAwaiter().GetResult()
    return Save-NativeDownload -Response $response -Path $Path -Format 'xlsx'
}

function Add-Check {
    param(
        [Parameter(Mandatory)][AllowEmptyCollection()][System.Collections.Generic.List[object]]$Checks,
        [Parameter(Mandatory)][string]$Id,
        [Parameter(Mandatory)][string]$Control,
        [Parameter(Mandatory)][bool]$Passed,
        [Parameter(Mandatory)][string]$Evidence
    )

    $Checks.Add([pscustomobject]@{
        id = $Id
        control = $Control
        passed = $Passed
        evidence = $Evidence
    })
}

$containerState = & docker inspect --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $container 2>$null
if ($LASTEXITCODE -ne 0 -or $containerState -notin @('running|healthy', 'running|none')) {
    throw "El contenedor Moodle local no esta saludable: '$containerState'."
}
if (-not (Test-Path -LiteralPath $adminSecretPath -PathType Leaf)) {
    throw 'No existe el secreto administrativo local esperado.'
}

New-Item -ItemType Directory -Path $templateDirectory, $reportsDirectory -Force | Out-Null
& git -C $repositoryRoot check-ignore -q -- $missionRoot
if ($LASTEXITCODE -ne 0) {
    throw 'La salida local de Mision 09 no esta protegida por .gitignore.'
}

$creation = $null
if ($Phase -in @('All', 'Create')) {
    $creation = Invoke-M09Phase -Name 'create'
}
$snapshotBefore = Invoke-M09Phase -Name 'snapshot'

$hostTemplatePath = Join-Path $templateDirectory $snapshotBefore.template.filename
if (Test-Path -LiteralPath $hostTemplatePath) {
    Remove-Item -LiteralPath $hostTemplatePath -Force
}
& docker cp "${container}:$($snapshotBefore.template.container_path)" $hostTemplatePath | Out-Null
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $hostTemplatePath -PathType Leaf)) {
    throw 'No fue posible conservar la plantilla MBZ en build/ ignorado.'
}
$hostTemplateHash = (Get-FileHash -LiteralPath $hostTemplatePath -Algorithm SHA256).Hash.ToLowerInvariant()
if ($hostTemplateHash -ne [string]$snapshotBefore.template.sha256) {
    throw 'La copia local de la plantilla no coincide con el archivo generado por Moodle.'
}

if ($Phase -eq 'Create') {
    [pscustomobject]@{
        status = $creation.status
        source_integrity = $creation.source_integrity
        template_path = $hostTemplatePath
        demo_course_id = $snapshotBefore.demo.id
    } | ConvertTo-Json
    exit 0
}

$exports = [System.Collections.Generic.List[object]]::new()
$completionExport = [pscustomobject]@{
    supported = $false
    reason = 'No ejecutado en esta fase.'
}
if ($Phase -in @('All', 'Reports')) {
    $adminPassword = (Get-Content -LiteralPath $adminSecretPath -Raw).Trim()
    if ([string]::IsNullOrWhiteSpace($adminPassword)) {
        throw 'El secreto administrativo local esta vacio.'
    }
    $session = New-MoodleSession -Username 'asodeltol_admin' -Password $adminPassword
    $adminPassword = $null
    $courseId = [int]$snapshotBefore.source.id
    $sourceUserIds = @($snapshotBefore.source.enrolled_users | ForEach-Object { [int]$_.id })
    $groupG01 = $snapshotBefore.source.groups | Where-Object idnumber -eq 'AF8-G01'
    $groupG02 = $snapshotBefore.source.groups | Where-Object idnumber -eq 'AF8-G02'

    $exports.Add((Export-Participants -Session $session -CourseId $courseId -UserIds $sourceUserIds `
        -Path (Join-Path $reportsDirectory 'participants-af8.xlsx')))

    $progressResponse = Get-MoodleResponse -Session $session `
        -Path "/report/progress/index.php?course=$courseId&dataformat=excel"
    $exports.Add((Save-NativeDownload -Response $progressResponse `
        -Path (Join-Path $reportsDirectory 'activity-progress-af8.xlsx') -Format 'xlsx'))

    $exports.Add((Export-GradeXls -Session $session -CourseId $courseId -GroupId ([int]$groupG01.id) `
        -Path (Join-Path $reportsDirectory 'grades-af8-g01.xlsx')))
    $exports.Add((Export-GradeXls -Session $session -CourseId $courseId -GroupId ([int]$groupG02.id) `
        -Path (Join-Path $reportsDirectory 'grades-af8-g02.xlsx')))

    $courseCompletionResponse = Get-MoodleResponse -Session $session `
        -Path "/report/completion/index.php?course=$courseId&dataformat=excel"
    $completionMediaType = [string]$courseCompletionResponse.Content.Headers.ContentType.MediaType
    if ($courseCompletionResponse.IsSuccessStatusCode -and $completionMediaType -notmatch 'text/html') {
        $completionFile = Save-NativeDownload -Response $courseCompletionResponse `
            -Path (Join-Path $reportsDirectory 'course-completion-af8.xlsx') -Format 'xlsx'
        $exports.Add($completionFile)
        $completionExport = [pscustomobject]@{
            supported = $true
            reason = 'Moodle genero el reporte nativo de finalizacion del curso.'
        }
    } else {
        $completionExport = [pscustomobject]@{
            supported = $false
            reason = 'El curso no tiene criterios globales de finalizacion aprobados; Moodle mantiene disponible el reporte de progreso por actividad.'
        }
    }
    $session.Dispose()
}

$snapshotAfter = Invoke-M09Phase -Name 'snapshot'
$checks = [System.Collections.Generic.List[object]]::new()
$sourceUsers = @($snapshotAfter.source.enrolled_users | ForEach-Object username)
$demoUsers = @($snapshotAfter.demo.enrolled_users | ForEach-Object username)
$overlap = @($sourceUsers | Where-Object { $demoUsers -contains $_ })
$sourceAssignmentGrades = @($snapshotAfter.source.grades | Where-Object idnumber -eq 'ASODELTOL-AF8-M07-ASSIGN')
$allSettingsDisabled = $true
if ($creation -and $creation.backup_settings) {
    foreach ($property in $creation.backup_settings.PSObject.Properties) {
        if ([bool]$property.Value) {
            $allSettingsDisabled = $false
        }
    }
}

Add-Check $checks 'TPL-001' 'AF8 original intacto' `
    ($snapshotAfter.source.id -eq 2 -and -not $snapshotAfter.source.visible -and `
        $snapshotAfter.source.official_units_match -and $snapshotAfter.source.groups.Count -eq 7 -and `
        $sourceAssignmentGrades.Count -eq 2 -and ($sourceAssignmentGrades.grade -contains 83) -and `
        ($sourceAssignmentGrades.grade -contains 97)) `
    'AF8 conserva identidad, visibilidad, cinco unidades, siete grupos y las calificaciones sinteticas 83/97.'
Add-Check $checks 'TPL-002' 'Identidad independiente del curso nuevo' `
    ($snapshotAfter.demo.id -ne $snapshotAfter.source.id -and `
        $snapshotAfter.demo.idnumber -eq 'ASODELTOL-AF8-TEMPLATE-DEMO' -and `
        $snapshotAfter.demo.shortname -eq 'ASODELTOL-AF8-TPL-DEMO' -and -not $snapshotAfter.demo.visible) `
    'La edicion sintetica tiene id, idnumber y shortname propios y permanece oculta.'
Add-Check $checks 'TPL-003' 'Estructura academica reutilizada' `
    ($snapshotAfter.demo.official_units_match -and $snapshotAfter.demo.units.Count -eq 5 -and `
        $snapshotAfter.demo.format -eq $snapshotAfter.source.format -and `
        $snapshotAfter.demo.groupmode -eq $snapshotAfter.source.groupmode -and `
        $snapshotAfter.demo.groupmodeforce -eq $snapshotAfter.source.groupmodeforce -and `
        $snapshotAfter.demo.enablecompletion -eq $snapshotAfter.source.enablecompletion) `
    'La plantilla conserva las cinco unidades y las configuraciones generales aprobadas.'
Add-Check $checks 'TPL-004' 'Sin arrastre de identidades' `
    ($overlap.Count -eq 0 -and $demoUsers.Count -eq 2) `
    'No existe interseccion entre participantes del original y de la edicion sintetica.'
Add-Check $checks 'TPL-005' 'Sin arrastre de actividad academica personal' `
    ($snapshotAfter.demo.activity_count -eq 0 -and $snapshotAfter.demo.grades.Count -eq 0 -and `
        $snapshotAfter.demo.submission_count -eq 0 -and $snapshotAfter.demo.completion_record_count -eq 0) `
    'El curso nuevo no contiene actividades, entregas, notas ni finalizaciones del original.'
Add-Check $checks 'TPL-006' 'Asignaciones posteriores independientes' `
    ($snapshotAfter.demo.groups.Count -eq 1 -and $snapshotAfter.demo.group_membership_count -eq 2 -and `
        @($snapshotAfter.demo.enrolment_methods | Where-Object cohort -eq 'AF8-TEMPLATE-G01').Count -eq 1) `
    'Grupo, cohorte, estudiante y capacitador se asignaron despues de restaurar la estructura limpia.'
Add-Check $checks 'TPL-007' 'Plantilla minimizada y fuera de Git' `
    ((-not $creation -or $allSettingsDisabled) -and $hostTemplateHash -eq $snapshotAfter.template.sha256 -and `
        (Test-Path -LiteralPath $hostTemplatePath -PathType Leaf)) `
    'El MBZ local excluye usuarios y datos relacionados; su hash coincide y build/ esta ignorado.'
Add-Check $checks 'RPT-001' 'Excel nativo habilitado' `
    ($snapshotAfter.reports.excel_enabled -and $snapshotAfter.reports.enabled_dataformats -contains 'excel') `
    'El formato de datos Excel esta habilitado en Moodle 5.3.'
Add-Check $checks 'RPT-002' 'Capacidades administrativas de reportes' `
    (-not @($snapshotAfter.reports.admin_capabilities.PSObject.Properties | Where-Object { -not [bool]$_.Value }).Count) `
    'El administrador puede consultar participantes, notas y finalizacion.'
Add-Check $checks 'RPT-003' 'Exportaciones nativas verificables' `
    ($Phase -eq 'Verify' -or ($exports.Count -ge 4 -and -not @($exports | Where-Object { $_.bytes -lt 8 }).Count)) `
    'Participantes, progreso y calificaciones por G01/G02 se descargaron con firma binaria valida.'
Add-Check $checks 'RPT-004' 'Asistencia no inferida desde sesiones' `
    (-not $snapshotAfter.reports.attendance_plugin_installed -and `
        $snapshotAfter.reports.attendance_evidence_rule -match 'no prueban asistencia efectiva') `
    'No se presenta navegacion como asistencia; falta una fuente de asistencia aprobada.'
$reportAccess = $snapshotAfter.reports.access_matrix
$studentScope = @($reportAccess.student_g01.participant_scope)
$trainerScope = @($reportAccess.trainer_g01.participant_scope)
Add-Check $checks 'RPT-005' 'Reportes limitados por capacidad y grupo' `
    ($reportAccess.admin.access_all_groups -and $reportAccess.admin.export_grades_xlsx -and `
        $reportAccess.admin.view_activity_completion -and `
        $reportAccess.student_g01.view_participants -and $studentScope.Count -eq 1 -and `
        $studentScope[0] -eq 'AF8-G01' -and -not $reportAccess.student_g01.export_grades_xlsx -and `
        -not $reportAccess.student_g01.view_activity_completion -and `
        $reportAccess.trainer_g01.export_grades_xlsx -and `
        $reportAccess.trainer_g01.view_activity_completion -and `
        -not $reportAccess.trainer_g01.access_all_groups -and $trainerScope.Count -eq 1 -and `
        $trainerScope[0] -eq 'AF8-G01' -and $reportAccess.auditor.access_all_groups -and `
        -not $reportAccess.auditor.export_grades_xlsx -and `
        -not $reportAccess.auditor.view_activity_completion) `
    'Administrador transversal; estudiante sin exportacion academica; capacitador limitado a G01; auditor transversal de solo lectura sin exportar.'
Add-Check $checks 'SEC-001' 'Staging oculto y correo deshabilitado' `
    (-not $snapshotAfter.source.visible -and -not $snapshotAfter.demo.visible -and `
        $snapshotAfter.runtime.outbound_mail_disabled) `
    'Ambos cursos permanecen ocultos y el correo saliente esta deshabilitado.'

$failed = @($checks | Where-Object { -not $_.passed })
$evidence = [ordered]@{
    generated_at = (Get-Date).ToUniversalTime().ToString('o')
    scope = 'Moodle 5.3 local aislado; unicamente datos sinteticos'
    branch = (git -C $repositoryRoot branch --show-current)
    runtime = $snapshotAfter.runtime
    source = $snapshotAfter.source
    demo = $snapshotAfter.demo
    template = [ordered]@{
        filename = [IO.Path]::GetFileName($hostTemplatePath)
        local_path = $hostTemplatePath
        bytes = (Get-Item -LiteralPath $hostTemplatePath).Length
        sha256 = $hostTemplateHash
        backup_settings = if ($creation) { $creation.backup_settings } else { $null }
    }
    native_reports = [ordered]@{
        capability_snapshot = $snapshotAfter.reports
        exports = $exports
        course_completion = $completionExport
    }
    checks = $checks
    summary = [ordered]@{
        passed = @($checks | Where-Object passed).Count
        failed = $failed.Count
        total = $checks.Count
    }
}
$evidence | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $evidencePath -Encoding utf8

$evidence.summary | Format-List | Out-Host
Write-Output "EVIDENCE_PATH=$evidencePath"
Write-Output "TEMPLATE_PATH=$hostTemplatePath"
if ($failed.Count -gt 0) {
    $failed | Format-Table id, control -AutoSize | Out-Host
    throw "$($failed.Count) controles de plantilla o reportes fallaron."
}
