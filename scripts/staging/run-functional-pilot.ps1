[CmdletBinding()]
param(
    [ValidateSet('All', 'Setup', 'Exercise', 'Verify', 'Lockdown')]
    [string]$Phase = 'All'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.Net.Http

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$evidenceDirectory = Join-Path $repositoryRoot 'build\staging\moodle53'
$secretPath = Join-Path $evidenceDirectory 'secrets\student-password.txt'
$container = 'asodeltol-moodle53-webserver-1'
$baseUrl = 'http://127.0.0.1:8053'
$pilotScript = '/opt/asodeltol/scripts/moodle53-functional-pilot.php'

function Invoke-PilotPhase {
    param([Parameter(Mandatory)][string]$Name)

    $output = & docker exec $container php $pilotScript "--phase=$Name" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Fallo la fase local '$Name': $($output -join [Environment]::NewLine)"
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
    $client.Timeout = [TimeSpan]::FromSeconds(180)

    $loginResponse = $client.GetAsync("$baseUrl/login/index.php").GetAwaiter().GetResult()
    $loginContent = $loginResponse.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    $match = [regex]::Match($loginContent, 'name="logintoken"\s+value="([^"]+)"')
    if (-not $match.Success) {
        throw "Moodle no entrego un token de acceso para la cuenta sintetica '$Username'."
    }

    $formValues = [System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]]::new()
    $formValues.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('username', $Username))
    $formValues.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('password', $Password))
    $formValues.Add([System.Collections.Generic.KeyValuePair[string,string]]::new('logintoken', $match.Groups[1].Value))
    $formContent = [System.Net.Http.FormUrlEncodedContent]::new($formValues)
    $response = $client.PostAsync("$baseUrl/login/index.php", $formContent).GetAwaiter().GetResult()
    $responseContent = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($responseContent -match 'Invalid login|Acceso invalido|loginerrormessage') {
        throw "Moodle rechazo el acceso de la cuenta sintetica '$Username'."
    }
    return $client
}

function Get-MoodlePage {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpClient]$Session,
        [Parameter(Mandatory)][string]$Path
    )

    for ($attempt = 1; $attempt -le 2; $attempt++) {
        try {
            $response = $Session.GetAsync("$baseUrl$Path").GetAwaiter().GetResult()
            return [pscustomobject]@{
                Content = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
                StatusCode = [int]$response.StatusCode
                FinalUri = [string]$response.RequestMessage.RequestUri
            }
        } catch {
            if ($attempt -eq 2) {
                throw
            }
            Start-Sleep -Seconds 2
        }
    }
}

function Test-ContainsAll {
    param([string]$Content, [string[]]$Markers)
    foreach ($marker in $Markers) {
        if ($Content -notmatch [regex]::Escape($marker)) {
            return $false
        }
    }
    return $true
}

function Test-ContainsNone {
    param([string]$Content, [string[]]$Markers)
    foreach ($marker in $Markers) {
        if ($Content -match [regex]::Escape($marker)) {
            return $false
        }
    }
    return $true
}

function Add-Result {
    param(
        [Parameter(Mandatory)][AllowEmptyCollection()][System.Collections.Generic.List[object]]$Results,
        [Parameter(Mandatory)][string]$Id,
        [Parameter(Mandatory)][string]$Actor,
        [Parameter(Mandatory)][string]$Control,
        [Parameter(Mandatory)][bool]$Passed,
        [Parameter(Mandatory)][string]$Evidence
    )

    $Results.Add([pscustomobject]@{
        id = $Id
        actor = $Actor
        control = $Control
        passed = $Passed
        evidence = $Evidence
    })
}

$containerState = (& docker inspect --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $container 2>$null)
if ($LASTEXITCODE -ne 0 -or $containerState -notin @('running|healthy', 'running|none')) {
    throw "El contenedor Moodle local no esta saludable: '$containerState'."
}
try {
    $healthResponse = Invoke-WebRequest -Uri $baseUrl -UseBasicParsing -MaximumRedirection 5
    if ([int]$healthResponse.StatusCode -ne 200) {
        throw "HTTP $($healthResponse.StatusCode)"
    }
} catch {
    throw "Moodle local no respondio correctamente en $baseUrl. $($_.Exception.Message)"
}
if (-not (Test-Path -LiteralPath $secretPath -PathType Leaf)) {
    throw 'No existe el secreto sintetico local esperado. No se generara ni mostrara una contrasena alternativa.'
}

$lockdownApplied = $false
try {
if ($Phase -in @('All', 'Setup')) {
    Invoke-PilotPhase -Name 'setup' | Out-Host
}
if ($Phase -in @('All', 'Exercise')) {
    Invoke-PilotPhase -Name 'exercise' | Out-Host
}
if ($Phase -eq 'Lockdown') {
    Invoke-PilotPhase -Name 'lockdown' | Out-Host
    exit 0
}
if ($Phase -in @('Setup', 'Exercise')) {
    exit 0
}

$snapshotJson = Invoke-PilotPhase -Name 'snapshot'
$snapshot = $snapshotJson | ConvertFrom-Json
$syntheticPassword = (Get-Content -LiteralPath $secretPath -Raw).Trim()
if ([string]::IsNullOrWhiteSpace($syntheticPassword)) {
    throw 'El secreto sintetico esta vacio.'
}

$courseId = [int]$snapshot.course.course_id
$assignmentCmId = [int]$snapshot.activities.assignment.cm_id
$discussionId = [int]$snapshot.activities.forum.discussion_id
$g01 = $snapshot.actors.student_g01
$g02 = $snapshot.actors.student_g02
$trainerG01 = $snapshot.actors.trainer_g01
$auditor = $snapshot.actors.auditor
$groupG02 = $snapshot.course.groups | Where-Object code -eq 'AF8-G02'

$studentSession = New-MoodleSession -Username 'af8.synthetic.g01' -Password $syntheticPassword
$trainerSession = New-MoodleSession -Username 'af8.synthetic.trainer.g01' -Password $syntheticPassword
$auditorSession = New-MoodleSession -Username 'af8.synthetic.auditor' -Password $syntheticPassword

$results = [System.Collections.Generic.List[object]]::new()

$ownCourse = Get-MoodlePage -Session $studentSession -Path "/course/view.php?id=$courseId"
Add-Result $results 'POS-001' 'student_g01' 'Curso y cinco unidades visibles' `
    (($ownCourse.StatusCode -eq 200) -and ($snapshot.activities.assignment.section -eq 1) -and -not [bool](@($snapshot.course.units | Where-Object { -not $_.official_name_match }).Count)) `
    'La sesion estudiantil recibe HTTP 200; la actividad pertenece a UT1 y las cinco denominaciones coinciden en Moodle.'

$ownGrade = Get-MoodlePage -Session $studentSession -Path "/grade/report/user/index.php?id=$courseId&userid=$($g01.user_id)"
Add-Result $results 'POS-002' 'student_g01' 'Calificacion propia visible' `
    (($ownGrade.StatusCode -eq 200) -and ([double]$snapshot.submissions.G01.grade -eq 83.0) -and [bool]$snapshot.submissions.G01.gradebook_recorded -and ([int]$snapshot.submissions.G01.completion_state -eq 1)) `
    'El informe propio responde HTTP 200; Moodle registra 83.00 en el libro y finalizacion de la actividad.'

$otherProfile = Get-MoodlePage -Session $studentSession -Path "/user/view.php?id=$($g02.user_id)&course=$courseId"
$otherParticipants = Get-MoodlePage -Session $studentSession -Path "/user/index.php?id=$courseId&group=$($groupG02.group_id)"
Add-Result $results 'NAC-001' 'student_g01' 'Perfil y participantes de G02' `
    ((Test-ContainsNone $otherProfile.Content @('af8-g02@example.invalid')) -and (Test-ContainsNone $otherParticipants.Content @('af8-g02@example.invalid'))) `
    'El perfil directo y el filtro manipulado no exponen el correo sintetico de G02.'

$otherGrade = Get-MoodlePage -Session $studentSession -Path "/grade/report/user/index.php?id=$courseId&userid=$($g02.user_id)"
Add-Result $results 'NAC-002' 'student_g01' 'Calificacion de G02 por URL directa' `
    ((Test-ContainsNone $otherGrade.Content @('af8-g02@example.invalid', '97.00'))) `
    'La URL manipulada no expone la identidad ni la nota 97.00 de G02.'

$trainerOtherGrader = Get-MoodlePage -Session $trainerSession -Path "/mod/assign/view.php?id=$assignmentCmId&action=grader&userid=$($g02.user_id)"
$trainerGradingList = Get-MoodlePage -Session $trainerSession -Path "/mod/assign/view.php?id=$assignmentCmId&action=grading"
Add-Result $results 'NAC-003' 'trainer_g01' 'Entrega/calificacion de G02 por URL directa' `
    ((Test-ContainsNone $trainerOtherGrader.Content @('M07-G02-SUBMISSION-MARKER', '97.00')) -and (Test-ContainsNone $trainerGradingList.Content @('af8-g02@example.invalid'))) `
    'El calificador directo y la lista del capacitador G01 no exponen al estudiante G02.'

$trainerAllParticipants = Get-MoodlePage -Session $trainerSession -Path "/user/index.php?id=$courseId&group=0"
Add-Result $results 'NAC-004' 'trainer_g01' 'Selector Todos los participantes' `
    ((Test-ContainsNone $trainerAllParticipants.Content @('af8-g02@example.invalid'))) `
    'El intento group=0 no expone al estudiante G02.'

$auditorGrader = Get-MoodlePage -Session $auditorSession -Path "/mod/assign/view.php?id=$assignmentCmId&action=grader&userid=$($g01.user_id)"
Add-Result $results 'NAC-005' 'auditor' 'Edicion de calificacion' `
    ((-not [bool]$auditor.can_grade) -and (Test-ContainsNone $auditorGrader.Content @('name="grade"', 'id="id_grade"'))) `
    'La capacidad mod/assign:grade esta denegada y la URL no ofrece controles de guardado.'

$auditorEditActivity = Get-MoodlePage -Session $auditorSession -Path "/course/modedit.php?update=$assignmentCmId&return=1"
Add-Result $results 'NAC-006' 'auditor' 'Edicion de actividad/seccion' `
    ((-not [bool]$auditor.can_manage_activities) -and (Test-ContainsNone $auditorEditActivity.Content @('id="mform1"', 'name="submitbutton"'))) `
    'La capacidad de gestionar actividades esta denegada y no se entrega formulario editable.'

$auditorEditUser = Get-MoodlePage -Session $auditorSession -Path "/user/editadvanced.php?id=$($g01.user_id)&course=$courseId"
Add-Result $results 'NAC-007' 'auditor' 'Edicion de usuario' `
    ((-not [bool]$auditor.can_update_users) -and (Test-ContainsNone $auditorEditUser.Content @('id="id_username"', 'name="submitbutton"'))) `
    'La capacidad de actualizar usuarios esta denegada y no se entrega formulario editable.'

$otherForum = Get-MoodlePage -Session $studentSession -Path "/mod/forum/discuss.php?d=$discussionId"
Add-Result $results 'NAC-008' 'student_g01' 'Discusion privada de G02 por URL directa' `
    ((Test-ContainsNone $otherForum.Content @('M07-G02-PRIVATE-DISCUSSION', 'M07-G02-DIRECT-ACCESS-DENIED-MARKER'))) `
    'La discusion y su mensaje de G02 no aparecen en la sesion G01.'

$studentOtherSubmission = Get-MoodlePage -Session $studentSession -Path "/mod/assign/view.php?id=$assignmentCmId&action=grader&userid=$($g02.user_id)"
Add-Result $results 'NAC-009' 'student_g01' 'Entrega de G02 por URL directa' `
    ((Test-ContainsNone $studentOtherSubmission.Content @('af8-g02@example.invalid', 'M07-G02-SUBMISSION-MARKER', '97.00'))) `
    'La URL directa no expone identidad, texto de entrega ni nota de G02.'

$trainerEditActivity = Get-MoodlePage -Session $trainerSession -Path "/course/modedit.php?update=$assignmentCmId&return=1"
Add-Result $results 'NAC-010' 'trainer_g01' 'Cambio de modo de grupos' `
    ((-not [bool]$trainerG01.can_manage_activities) -and [bool]$snapshot.runtime.group_mode_forced -and (Test-ContainsNone $trainerEditActivity.Content @('id="id_groupmode"', 'name="submitbutton"'))) `
    'El capacitador no recibe el formulario y el curso conserva grupos separados forzados.'

$auditorGrades = Get-MoodlePage -Session $auditorSession -Path "/grade/report/grader/index.php?id=$courseId"
Add-Result $results 'POS-003' 'auditor' 'Consulta transversal de solo lectura' `
    (($auditorGrades.StatusCode -eq 200) -and ($auditorGrades.FinalUri -match '/grade/report/grader/') -and [bool]$auditor.can_view_grades -and [bool]$auditor.can_view_all_course_grades -and [bool]$auditor.can_access_all_groups -and -not [bool]$auditor.can_grade -and ([double]$snapshot.submissions.G01.grade -eq 83.0) -and ([double]$snapshot.submissions.G02.grade -eq 97.0)) `
    'El informe responde HTTP 200; el auditor tiene lectura transversal y no puede calificar; el libro conserva 83.00 y 97.00.'

$snapshotAfterJson = Invoke-PilotPhase -Name 'snapshot'
$snapshotAfter = $snapshotAfterJson | ConvertFrom-Json
$gradesUnchanged = ([double]$snapshotAfter.submissions.G01.grade -eq 83.0) -and ([double]$snapshotAfter.submissions.G02.grade -eq 97.0)
Add-Result $results 'INV-001' 'system' 'Integridad posterior a pruebas negativas' $gradesUnchanged `
    'Las calificaciones sinteticas permanecen en 83.00 y 97.00 despues de los intentos directos.'

$failed = @($results | Where-Object { -not $_.passed })
$lockdownResult = (Invoke-PilotPhase -Name 'lockdown') | ConvertFrom-Json
$lockdownApplied = $true
$postRunSnapshot = (Invoke-PilotPhase -Name 'snapshot') | ConvertFrom-Json
$evidence = [ordered]@{
    generated_at = (Get-Date).ToUniversalTime().ToString('o')
    scope = 'Moodle 5.3 local aislado; unicamente identidades sinteticas'
    runtime = $snapshotAfter.runtime
    academic_units_match = -not [bool](@($snapshotAfter.course.units | Where-Object { -not $_.official_name_match }).Count)
    groups = $snapshotAfter.course.groups
    submissions = $snapshotAfter.submissions
    post_run_lockdown = [ordered]@{
        applied = $lockdownResult.status -eq 'locked_down'
        course_visible = [bool]$postRunSnapshot.runtime.course_visible
        category_visible = [bool]$postRunSnapshot.runtime.category_visible
    }
    checks = $results
    summary = [ordered]@{
        passed = @($results | Where-Object passed).Count
        failed = $failed.Count
        total = $results.Count
    }
}

New-Item -ItemType Directory -Path $evidenceDirectory -Force | Out-Null
$evidencePath = Join-Path $evidenceDirectory 'mission-07-evidence.json'
$evidence | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $evidencePath -Encoding utf8
$syntheticPassword = $null

$evidence.summary | Format-List | Out-Host
Write-Output "EVIDENCE_PATH=$evidencePath"
if ($failed.Count -gt 0) {
    $failed | Format-Table id, actor, control -AutoSize | Out-Host
    throw "$($failed.Count) controles funcionales o de aislamiento fallaron."
}
} finally {
    if ($Phase -in @('All', 'Verify') -and -not $lockdownApplied) {
        try {
            Invoke-PilotPhase -Name 'lockdown' | Out-Null
        } catch {
            Write-Warning "No fue posible restaurar automaticamente la visibilidad: $($_.Exception.Message)"
        }
    }
}
