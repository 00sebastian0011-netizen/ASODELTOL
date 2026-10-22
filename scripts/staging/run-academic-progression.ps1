[CmdletBinding()]
param(
    [ValidateSet('All', 'Setup', 'Verify', 'Lockdown')]
    [string]$Phase = 'All'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.Net.Http

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$stagingRoot = Join-Path $repositoryRoot 'build\staging\moodle53'
$missionRoot = Join-Path $stagingRoot 'mission-10'
$secretPath = Join-Path $stagingRoot 'secrets\student-password.txt'
$container = 'asodeltol-moodle53-webserver-1'
$baseUrl = 'http://127.0.0.1:8053'
$moodleScript = '/opt/asodeltol/scripts/moodle53-academic-progression.php'

function Invoke-M10Phase {
    param(
        [Parameter(Mandatory)][string]$Name,
        [string[]]$Arguments = @()
    )

    $dockerArguments = @(
        'exec', '--user', 'www-data', $container,
        'php', $moodleScript, "--phase=$Name"
    ) + $Arguments
    $output = & docker @dockerArguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Falló la fase local '$Name': $($output -join [Environment]::NewLine)"
    }
    return ($output -join [Environment]::NewLine)
}

function Invoke-M10Grade {
    param(
        [Parameter(Mandatory)][ValidateSet('g01', 'g02')][string]$Actor,
        [Parameter(Mandatory)][ValidateRange(1, 5)][int]$Unit,
        [Parameter(Mandatory)][ValidateRange(0, 100)][double]$Grade
    )

    return Invoke-M10Phase -Name 'grade' -Arguments @(
        "--actor=$Actor",
        "--unit=$Unit",
        "--grade=$Grade"
    )
}

function Get-M10Snapshot {
    return (Invoke-M10Phase -Name 'snapshot') | ConvertFrom-Json
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
        throw "Moodle no entregó un token para '$Username'."
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
    if ($content -match 'Invalid login|Acceso inv.lido|loginerrormessage') {
        throw "Moodle rechazó la cuenta sintética '$Username'."
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

function Get-ActorState {
    param(
        [Parameter(Mandatory)]$Snapshot,
        [Parameter(Mandatory)][string]$Actor
    )
    return $Snapshot.actors.PSObject.Properties[$Actor].Value
}

function Get-UnitState {
    param(
        [Parameter(Mandatory)]$Snapshot,
        [Parameter(Mandatory)][string]$Actor,
        [Parameter(Mandatory)][ValidateRange(1, 5)][int]$Unit
    )
    $actorState = Get-ActorState -Snapshot $Snapshot -Actor $Actor
    return $actorState.units.PSObject.Properties[[string]$Unit].Value
}

function Get-VisibleUnits {
    param(
        [Parameter(Mandatory)]$Snapshot,
        [Parameter(Mandatory)][string]$Actor
    )
    $actorState = Get-ActorState -Snapshot $Snapshot -Actor $Actor
    return @(
        $actorState.units.PSObject.Properties |
            Where-Object { $_.Value.section_user_visible -and $_.Value.activity_user_visible } |
            ForEach-Object { [int]$_.Name } |
            Sort-Object
    )
}

function Test-VisibleUnits {
    param(
        [Parameter(Mandatory)]$Snapshot,
        [Parameter(Mandatory)][string]$Actor,
        [Parameter(Mandatory)][int[]]$Expected
    )
    return ((Get-VisibleUnits -Snapshot $Snapshot -Actor $Actor) -join ',') -eq ($Expected -join ',')
}

function Get-UnitPage {
    param(
        [Parameter(Mandatory)][System.Net.Http.HttpClient]$Session,
        [Parameter(Mandatory)]$Snapshot,
        [Parameter(Mandatory)][string]$Actor,
        [Parameter(Mandatory)][ValidateRange(1, 5)][int]$Unit
    )
    $state = Get-UnitState -Snapshot $Snapshot -Actor $Actor -Unit $Unit
    return Get-MoodlePage -Session $Session -Path "/mod/assign/view.php?id=$($state.cm_id)"
}

function Test-PageHasMarker {
    param(
        [Parameter(Mandatory)]$Page,
        [Parameter(Mandatory)][string]$Marker
    )
    return $Page.Content -match [regex]::Escape($Marker)
}

function Test-SequentialRules {
    param([Parameter(Mandatory)]$Snapshot)

    if ([int]$Snapshot.scenario.required_completion_state -ne 2) {
        return $false
    }
    for ($unit = 1; $unit -le 5; $unit++) {
        $section = $Snapshot.course.sections.PSObject.Properties[[string]$unit].Value
        if (-not [bool]$section.official_name_match) {
            return $false
        }
        if ($unit -eq 1) {
            if ($null -ne $section.availability -and [string]$section.availability -ne '') {
                return $false
            }
            continue
        }
        $rule = ([string]$section.availability) | ConvertFrom-Json
        if ($rule.op -ne '&' -or @($rule.c).Count -ne 1) {
            return $false
        }
        if ($rule.c[0].type -ne 'completion' -or [int]$rule.c[0].e -ne 2) {
            return $false
        }
        if ([int]$rule.c[0].cm -ne [int]$section.expected_previous_cm_id) {
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

$containerState = & docker inspect --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $container 2>$null
if ($LASTEXITCODE -ne 0 -or $containerState -notin @('running|healthy', 'running|none')) {
    throw "El contenedor Moodle local no está saludable: '$containerState'."
}
try {
    $health = Invoke-WebRequest -Uri $baseUrl -UseBasicParsing -MaximumRedirection 5
    if ([int]$health.StatusCode -ne 200) {
        throw "HTTP $($health.StatusCode)"
    }
} catch {
    throw "Moodle local no respondió correctamente en $baseUrl. $($_.Exception.Message)"
}
if (-not (Test-Path -LiteralPath $secretPath -PathType Leaf)) {
    throw 'No existe el secreto sintético local esperado.'
}

$lockdownApplied = $false
$studentPassword = $null
try {
    if ($Phase -in @('All', 'Setup')) {
        $setup = (Invoke-M10Phase -Name 'setup') | ConvertFrom-Json
        if ($Phase -eq 'Setup') {
            $lockdown = (Invoke-M10Phase -Name 'lockdown') | ConvertFrom-Json
            $lockdownApplied = $true
            $setupSnapshot = Get-M10Snapshot
            if ($setupSnapshot.runtime.course_visible -or $setupSnapshot.runtime.category_visible) {
                throw 'El curso o la categoría de pruebas quedaron visibles tras la fase Setup.'
            }
            [pscustomobject]@{
                status = $setup.status
                course_id = $setup.course_id
                course_visible = $setupSnapshot.runtime.course_visible
                category_visible = $setupSnapshot.runtime.category_visible
                synthetic_pass_grade = $setup.synthetic_pass_grade
                threshold_is_institutional_policy = $setup.threshold_is_institutional_policy
                course_completion_criteria_unchanged = $setup.course_completion_criteria_unchanged
                synthetic_only = $setup.synthetic_only
                lockdown = $lockdown.status
            } | ConvertTo-Json -Depth 5
            exit 0
        }
    }
    if ($Phase -eq 'Lockdown') {
        $lockdown = (Invoke-M10Phase -Name 'lockdown') | ConvertFrom-Json
        $lockdownApplied = $true
        $lockdown | ConvertTo-Json
        exit 0
    }

    $baseline = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=baseline_ready'
    if ($Phase -eq 'Verify') {
        $baseline | ConvertTo-Json -Depth 12
        $lockdown = (Invoke-M10Phase -Name 'lockdown') | ConvertFrom-Json
        $lockdownApplied = $true
        exit 0
    }

    $studentPassword = (Get-Content -LiteralPath $secretPath -Raw).Trim()
    if ([string]::IsNullOrWhiteSpace($studentPassword)) {
        throw 'El secreto sintético local está vacío.'
    }
    $g01Session = New-MoodleSession -Username 'af8.synthetic.g01' -Password $studentPassword
    $g02Session = New-MoodleSession -Username 'af8.synthetic.g02' -Password $studentPassword
    $studentPassword = $null
    $results = [System.Collections.Generic.List[object]]::new()

    Add-Result $results 'M10-001' 'system' 'Staging y configuración académica de prueba' `
        (($baseline.runtime.release -match '^5\.3') -and $baseline.runtime.completion_enabled `
            -and $baseline.runtime.availability_enabled -and $baseline.runtime.separate_groups `
            -and $baseline.runtime.group_mode_forced -and (Test-SequentialRules $baseline)) `
        'Moodle 5.3 mantiene cinco nombres oficiales y una cadena exacta basada en COMPLETION_COMPLETE_PASS.'

    $g01BaselineUt2 = Get-UnitPage -Session $g01Session -Snapshot $baseline -Actor 'g01' -Unit 2
    $g01BaselineUt5 = Get-UnitPage -Session $g01Session -Snapshot $baseline -Actor 'g01' -Unit 5
    Add-Result $results 'M10-002' 'student_g01' 'Estado inicial sin nota' `
        ((Test-VisibleUnits $baseline 'g01' @(1)) `
            -and -not (Test-PageHasMarker $g01BaselineUt2 (Get-UnitState $baseline 'g01' 2).marker) `
            -and -not (Test-PageHasMarker $g01BaselineUt5 (Get-UnitState $baseline 'g01' 5).marker)) `
        'G01 sólo puede acceder a UT1; las URL directas de UT2 y UT5 no entregan su contenido.'

    $g02BaselineUt2 = Get-UnitPage -Session $g02Session -Snapshot $baseline -Actor 'g02' -Unit 2
    Add-Result $results 'M10-003' 'student_g02' 'Estado inicial independiente' `
        ((Test-VisibleUnits $baseline 'g02' @(1)) `
            -and -not (Test-PageHasMarker $g02BaselineUt2 (Get-UnitState $baseline 'g02' 2).marker)) `
        'G02 inicia de forma independiente y la URL directa de UT2 permanece bloqueada.'

    Invoke-M10Grade -Actor g02 -Unit 1 -Grade 40 | Out-Null
    $afterG02Fail = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=g02_failed_ut1_verified'
    $g02Ut1Fail = Get-UnitState $afterG02Fail 'g02' 1
    $g02FailedUt2 = Get-UnitPage -Session $g02Session -Snapshot $afterG02Fail -Actor 'g02' -Unit 2
    Add-Result $results 'M10-004' 'student_g02' 'Nota recibida y actividad completada sin aprobación' `
        (($g02Ut1Fail.grade -eq 40) -and [int]$g02Ut1Fail.completion_state -eq 3 `
            -and $g02Ut1Fail.activity_completed -and -not $g02Ut1Fail.activity_passed) `
        'Moodle registra 40.00 y COMPLETION_COMPLETE_FAIL: existe nota y finalización, pero no aprobación.'
    Add-Result $results 'M10-005' 'student_g02' 'La finalización reprobada no desbloquea UT2' `
        ((Test-VisibleUnits $afterG02Fail 'g02' @(1)) `
            -and -not (Test-PageHasMarker $g02FailedUt2 (Get-UnitState $afterG02Fail 'g02' 2).marker)) `
        'La condición exige el estado de aprobación exacto; UT2 sigue inaccesible incluso por URL directa.'

    Invoke-M10Grade -Actor g01 -Unit 1 -Grade 80 | Out-Null
    $afterG01Ut1 = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=g01_ut1_passed'
    $g01Ut2Page = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut1 -Actor 'g01' -Unit 2
    $g01LockedUt3 = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut1 -Actor 'g01' -Unit 3
    Add-Result $results 'M10-006' 'student_g01' 'Aprobación UT1 desbloquea únicamente UT2' `
        (((Get-UnitState $afterG01Ut1 'g01' 1).completion_state -eq 2) `
            -and (Test-VisibleUnits $afterG01Ut1 'g01' @(1, 2)) `
            -and (Test-PageHasMarker $g01Ut2Page (Get-UnitState $afterG01Ut1 'g01' 2).marker) `
            -and -not (Test-PageHasMarker $g01LockedUt3 (Get-UnitState $afterG01Ut1 'g01' 3).marker)) `
        'Con 80.00 en UT1, UT2 abre; UT3 continúa bloqueada y no se salta la cadena.'

    Invoke-M10Grade -Actor g01 -Unit 2 -Grade 80 | Out-Null
    $afterG01Ut2 = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=g01_ut2_passed'
    $g01Ut3Page = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut2 -Actor 'g01' -Unit 3
    $g01LockedUt4 = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut2 -Actor 'g01' -Unit 4
    Add-Result $results 'M10-007' 'student_g01' 'Aprobación UT2 desbloquea únicamente UT3' `
        ((Test-VisibleUnits $afterG01Ut2 'g01' @(1, 2, 3)) `
            -and (Test-PageHasMarker $g01Ut3Page (Get-UnitState $afterG01Ut2 'g01' 3).marker) `
            -and -not (Test-PageHasMarker $g01LockedUt4 (Get-UnitState $afterG01Ut2 'g01' 4).marker)) `
        'La apertura avanza un único eslabón; UT4 no responde con su contenido por URL directa.'

    Invoke-M10Grade -Actor g01 -Unit 3 -Grade 80 | Out-Null
    $afterG01Ut3 = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=g01_ut3_passed'
    $g01Ut4Page = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut3 -Actor 'g01' -Unit 4
    $g01LockedUt5 = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut3 -Actor 'g01' -Unit 5
    Add-Result $results 'M10-008' 'student_g01' 'Aprobación UT3 desbloquea únicamente UT4' `
        ((Test-VisibleUnits $afterG01Ut3 'g01' @(1, 2, 3, 4)) `
            -and (Test-PageHasMarker $g01Ut4Page (Get-UnitState $afterG01Ut3 'g01' 4).marker) `
            -and -not (Test-PageHasMarker $g01LockedUt5 (Get-UnitState $afterG01Ut3 'g01' 5).marker)) `
        'UT5 permanece cerrada hasta que UT4 alcance el estado de aprobación.'

    Invoke-M10Grade -Actor g01 -Unit 4 -Grade 80 | Out-Null
    $afterG01Ut4 = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=g01_ut4_passed'
    $g01Ut5Page = Get-UnitPage -Session $g01Session -Snapshot $afterG01Ut4 -Actor 'g01' -Unit 5
    Add-Result $results 'M10-009' 'student_g01' 'Aprobación UT4 desbloquea UT5' `
        ((Test-VisibleUnits $afterG01Ut4 'g01' @(1, 2, 3, 4, 5)) `
            -and (Test-PageHasMarker $g01Ut5Page (Get-UnitState $afterG01Ut4 'g01' 5).marker)) `
        'La quinta unidad sólo entrega su contenido después de aprobar secuencialmente UT1–UT4.'

    Invoke-M10Grade -Actor g01 -Unit 5 -Grade 80 | Out-Null
    $finalAcademic = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=g01_ut5_passed'
    $g01States = 1..5 | ForEach-Object { (Get-UnitState $finalAcademic 'g01' $_).completion_state }
    Add-Result $results 'M10-010' 'student_g01' 'Cinco actividades completadas con aprobación' `
        ((($g01States -join ',') -eq '2,2,2,2,2') -and -not (Get-ActorState $finalAcademic 'g01').course_complete) `
        'Las cinco actividades están aprobadas; el curso no se marca completo porque no se inventaron criterios globales.'

    $g02FinalUt2 = Get-UnitPage -Session $g02Session -Snapshot $finalAcademic -Actor 'g02' -Unit 2
    $g02FinalUt5 = Get-UnitPage -Session $g02Session -Snapshot $finalAcademic -Actor 'g02' -Unit 5
    Add-Result $results 'M10-011' 'student_g02' 'Independencia por estudiante y grupo' `
        ((Test-VisibleUnits $finalAcademic 'g02' @(1)) `
            -and (((Get-ActorState $finalAcademic 'g02').groups -join ',') -eq 'AF8-G02') `
            -and -not (Test-PageHasMarker $g02FinalUt2 (Get-UnitState $finalAcademic 'g02' 2).marker) `
            -and -not (Test-PageHasMarker $g02FinalUt5 (Get-UnitState $finalAcademic 'g02' 5).marker)) `
        'El avance completo de G01 no cambia G02; UT2 y UT5 siguen negadas por URL directa.'

    Add-Result $results 'M10-012' 'system' 'Finalización de curso separada de disponibilidad' `
        (([int]$baseline.course.completion_criteria_count -eq [int]$finalAcademic.course.completion_criteria_count) `
            -and -not (Get-ActorState $finalAcademic 'g01').course_complete `
            -and -not (Get-ActorState $finalAcademic 'g02').course_complete) `
        'La misión no crea criterios globales: Course completion sigue siendo reporte y no la cerradura de las secciones.'

    Add-Result $results 'M10-013' 'system' 'Integridad académica y aislamiento sintético' `
        ((Test-SequentialRules $finalAcademic) `
            -and (((Get-ActorState $finalAcademic 'g01').groups -join ',') -eq 'AF8-G01') `
            -and (((Get-ActorState $finalAcademic 'g02').groups -join ',') -eq 'AF8-G02')) `
        'Las cinco denominaciones oficiales permanecen intactas y cada cuenta conserva un único grupo sintético.'

    $lockdown = (Invoke-M10Phase -Name 'lockdown') | ConvertFrom-Json
    $lockdownApplied = $true
    $postLockdown = Get-M10Snapshot
    Write-Output 'M10_PROGRESS=lockdown_verified'
    $hiddenDirect = Get-UnitPage -Session $g01Session -Snapshot $postLockdown -Actor 'g01' -Unit 1
    Add-Result $results 'M10-014' 'student_g01' 'Curso oculto al finalizar' `
        (($lockdown.status -eq 'locked_down') -and -not $postLockdown.runtime.course_visible `
            -and -not $postLockdown.runtime.category_visible `
            -and -not (Test-PageHasMarker $hiddenDirect (Get-UnitState $postLockdown 'g01' 1).marker)) `
        'Curso y categoría quedan ocultos; incluso la URL directa de UT1 deja de entregar contenido al estudiante.'

    $failed = @($results | Where-Object { -not $_.passed })
    $evidence = [ordered]@{
        generated_at = (Get-Date).ToUniversalTime().ToString('o')
        scope = 'Moodle 5.3 local aislado; únicamente identidades, actividades y notas sintéticas'
        scenario = [ordered]@{
            synthetic_pass_grade = 60
            threshold_is_institutional_policy = $false
            required_completion_state = 'COMPLETION_COMPLETE_PASS'
            course_completion_criteria_added = 0
        }
        runtime = $postLockdown.runtime
        groups = [ordered]@{
            g01 = (Get-ActorState $finalAcademic 'g01').groups
            g02 = (Get-ActorState $finalAcademic 'g02').groups
        }
        final_states = [ordered]@{
            g01 = (Get-ActorState $finalAcademic 'g01').units
            g02 = (Get-ActorState $finalAcademic 'g02').units
        }
        checks = $results
        summary = [ordered]@{
            passed = @($results | Where-Object passed).Count
            failed = $failed.Count
            total = $results.Count
        }
    }

    New-Item -ItemType Directory -Path $missionRoot -Force | Out-Null
    $evidencePath = Join-Path $missionRoot 'mission-10-evidence.json'
    $evidence | ConvertTo-Json -Depth 15 | Set-Content -LiteralPath $evidencePath -Encoding utf8
    $evidence.summary | Format-List | Out-Host
    Write-Output "EVIDENCE_PATH=$evidencePath"
    if ($failed.Count -gt 0) {
        $failed | Format-Table id, actor, control -AutoSize | Out-Host
        throw "$($failed.Count) controles de progresión fallaron."
    }
} finally {
    $studentPassword = $null
    if (-not $lockdownApplied) {
        try {
            Invoke-M10Phase -Name 'lockdown' | Out-Null
            $lockdownApplied = $true
        } catch {
            Write-Warning "No fue posible ocultar automáticamente el curso: $($_.Exception.Message)"
        }
    }
}
