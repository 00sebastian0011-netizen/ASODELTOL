[CmdletBinding()]
param(
    [ValidateSet('All', 'Apply', 'Capture', 'Verify', 'TestReversibility', 'Lockdown', 'Revert')]
    [string]$Phase = 'All'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$runtimeRoot = Join-Path $repositoryRoot 'build\staging\moodle53'
$missionRoot = Join-Path $runtimeRoot 'mission-12'
$evidencePath = Join-Path $runtimeRoot 'mission-12-evidence.json'
$evidenceMissionPath = Join-Path $missionRoot 'mission-12-evidence.json'
$evidenceDir = Join-Path $runtimeRoot 'evidence\mission12'
$container = 'asodeltol-moodle53-webserver-1'
$fixture = '/opt/asodeltol/scripts/moodle53-institutional-ux.php'
$captureScript = Join-Path $PSScriptRoot 'capture-moodle53-ux.mjs'

if (-not (Test-Path $missionRoot)) {
    New-Item -ItemType Directory -Path $missionRoot -Force | Out-Null
}

function Invoke-M12Phase {
    param([Parameter(Mandatory)][string]$Name)

    $output = & docker exec --user www-data $container php $fixture "--phase=$Name" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Falló la fase M12 '$Name': $($output -join [Environment]::NewLine)"
    }
    return ($output -join [Environment]::NewLine)
}

function Add-Control {
    param(
        [System.Collections.Generic.List[object]]$List,
        [string]$Id,
        [bool]$Passed,
        [string]$Detail
    )

    $List.Add([pscustomobject]@{
        id = $Id
        passed = $Passed
        detail = $Detail
    })
    $tag = if ($Passed) { '[PASS]' } else { '[FAIL]' }
    $color = if ($Passed) { 'Green' } else { 'Red' }
    Write-Host "  $tag $Id - $Detail" -ForegroundColor $color
}

function Get-RelativeLuminance {
    param([Parameter(Mandatory)][string]$HexColor)

    $clean = $HexColor.Trim().TrimStart('#')
    $red = [Convert]::ToInt32($clean.Substring(0, 2), 16) / 255.0
    $green = [Convert]::ToInt32($clean.Substring(2, 2), 16) / 255.0
    $blue = [Convert]::ToInt32($clean.Substring(4, 2), 16) / 255.0
    $toLinear = {
        param([double]$component)
        if ($component -le 0.04045) { return $component / 12.92 }
        return [Math]::Pow(($component + 0.055) / 1.055, 2.4)
    }
    return (0.2126 * (& $toLinear $red) + 0.7152 * (& $toLinear $green) + 0.0722 * (& $toLinear $blue))
}

function Get-ContrastRatio {
    param(
        [Parameter(Mandatory)][string]$Color1,
        [Parameter(Mandatory)][string]$Color2
    )

    $luminance1 = Get-RelativeLuminance -HexColor $Color1
    $luminance2 = Get-RelativeLuminance -HexColor $Color2
    return [Math]::Round(
        ([Math]::Max($luminance1, $luminance2) + 0.05) /
        ([Math]::Min($luminance1, $luminance2) + 0.05),
        2
    )
}

function Test-M12StatusUnchanged {
    param(
        [Parameter(Mandatory)]$Before,
        [Parameter(Mandatory)]$After
    )

    return (
        $Before.governed_state_sha256 -eq $After.governed_state_sha256 -and
        $Before.theme -eq $After.theme -and
        $Before.brandcolor -eq $After.brandcolor -and
        $Before.scsspre_sha256 -eq $After.scsspre_sha256 -and
        $Before.scss_sha256 -eq $After.scss_sha256 -and
        $Before.site_fullname -eq $After.site_fullname -and
        $Before.site_shortname -eq $After.site_shortname -and
        $Before.snapshot.exists -eq $After.snapshot.exists -and
        $Before.snapshot.sha256 -eq $After.snapshot.sha256 -and
        $Before.snapshot.bytes -eq $After.snapshot.bytes -and
        $Before.course_visible -eq $After.course_visible -and
        $Before.category_visible -eq $After.category_visible
    )
}

$controls = [System.Collections.Generic.List[object]]::new()
$shouldEnforceLockdown = $Phase -in @('All', 'Apply', 'Capture')

try {
    Write-Host '==========================================================' -ForegroundColor Cyan
    Write-Host ' ASODELTOL - MISIÓN 12: EXPERIENCIA VISUAL INSTITUCIONAL  ' -ForegroundColor Cyan
    Write-Host ' (Propuesta cromática provisional: Verde Tolima / Oro)    ' -ForegroundColor Cyan
    Write-Host '==========================================================' -ForegroundColor Cyan

    if ($Phase -eq 'Revert') {
        Write-Host "`n[Fase: Revert] Iniciando restauración transaccional y recuperable..." -ForegroundColor Yellow
        $revertResult = (Invoke-M12Phase -Name 'revert') | ConvertFrom-Json
        Write-Host "  Estado: $($revertResult.status)"
        Write-Host "  Semántica: $($revertResult.restoration_semantics)"
        Write-Host "  Sitio restaurado a: $($revertResult.site_fullname)"
        Write-Host "  Snapshot retirado tras verificación: $($revertResult.snapshot_deleted)"
        return
    }

    if ($Phase -eq 'Lockdown') {
        Write-Host "`n[Fase: Lockdown] Garantizando confinamiento de curso y categoría..." -ForegroundColor Yellow
        Write-Host (Invoke-M12Phase -Name 'lockdown')
        return
    }

    if ($Phase -eq 'TestReversibility') {
        Write-Host "`n[Fase: TestReversibility] Ejecutando pruebas únicamente en sandbox desechable..." -ForegroundColor Yellow
        $statusBeforeTest = (Invoke-M12Phase -Name 'status') | ConvertFrom-Json
        $sandboxResult = (Invoke-M12Phase -Name 'snapshot-selftest') | ConvertFrom-Json
        $statusAfterTest = (Invoke-M12Phase -Name 'status') | ConvertFrom-Json

        foreach ($control in $sandboxResult.controls) {
            Add-Control $controls $control.id ([bool]$control.passed) $control.detail
        }
        Add-Control $controls 'M12-ISO-LIVE-001' (
            $sandboxResult.isolated -and
            -not $sandboxResult.live_moodle_configuration_touched -and
            (Test-M12StatusUnchanged -Before $statusBeforeTest -After $statusAfterTest) -and
            -not $statusAfterTest.course_visible -and
            -not $statusAfterTest.category_visible
        ) 'El sandbox no altera tema, nombre, SCSS, snapshot, curso ni categoría del staging cotidiano.'

        $failedSandboxControls = @($controls | Where-Object { -not $_.passed })
        if ($sandboxResult.status -ne 'PASS' -or $failedSandboxControls.Count -gt 0) {
            throw "Fallaron $($failedSandboxControls.Count) controles aislados de reversibilidad."
        }
        Write-Host "`nPruebas de reversibilidad aisladas completadas sin tocar el staging cotidiano." -ForegroundColor Green
        return
    }

    if ($Phase -in @('All', 'Apply')) {
        Write-Host "`n[Fase: Apply] Capturando estado real pre-Apply sin sobrescritura..." -ForegroundColor Yellow
        $applyResult = (Invoke-M12Phase -Name 'apply') | ConvertFrom-Json
        Write-Host "  SCSS: $($applyResult.scss_bytes) bytes | Snapshot verificado: $($applyResult.snapshot_verified)"
    }

    if ($Phase -in @('All', 'Capture')) {
        Write-Host "`n[Fase: Capture] Capturando vistas reales en 1440, 768 y 375 px..." -ForegroundColor Yellow
        $null = Invoke-M12Phase -Name 'open'
        try {
            & node $captureScript --mode=after
            if ($LASTEXITCODE -ne 0) {
                throw 'Falló la captura multi-dispositivo mediante Edge headless.'
            }
        } finally {
            $null = Invoke-M12Phase -Name 'lockdown'
        }
    }

    if ($Phase -in @('All', 'Verify')) {
        Write-Host "`n[Fase: Verify] Inspección de solo lectura; no ejecuta Apply, Revert ni Lockdown..." -ForegroundColor Yellow
        $statusBefore = (Invoke-M12Phase -Name 'status') | ConvertFrom-Json

        Add-Control $controls 'M12-THEME-001' (
            $statusBefore.theme -eq 'boost' -and
            $statusBefore.brandcolor -eq '#1B4D3E' -and
            $statusBefore.scss_length -gt 1000
        ) 'Boost conserva la paleta provisional y el SCSS institucional sin cambios al núcleo.'

        Add-Control $controls 'M12-IDENTITY-001' (
            $statusBefore.site_fullname -eq 'Aula Virtual ASODELTOL'
        ) 'El nombre provisional del sitio permanece configurado como Aula Virtual ASODELTOL.'

        $staffCssPath = Join-Path $repositoryRoot 'plugins\local\asodeltolstaff\styles.css'
        $staffCssExists = Test-Path $staffCssPath
        $staffCssContent = if ($staffCssExists) { Get-Content $staffCssPath -Raw } else { '' }
        Add-Control $controls 'M12-STAFF-001' (
            $staffCssExists -and
            $staffCssContent.Contains('.table-responsive') -and
            $staffCssContent.Contains('.badge-validated') -and
            $staffCssContent.Contains('min-height: 44px')
        ) 'local_asodeltolstaff conserva tabla responsive, estados y objetivos táctiles de 44 px.'

        $afterManifestPath = Join-Path $evidenceDir 'after\capture-manifest.json'
        $hasAfterManifest = Test-Path $afterManifestPath
        $afterManifest = if ($hasAfterManifest) { Get-Content $afterManifestPath -Raw | ConvertFrom-Json } else { $null }
        $allScreenshotsPresent = $false
        $zeroOverflow = $false
        if ($null -ne $afterManifest -and $afterManifest.total_captures -eq 12) {
            $allScreenshotsPresent = $true
            $zeroOverflow = $true
            foreach ($capture in $afterManifest.captures) {
                if (-not (Test-Path $capture.path) -or (Get-Item $capture.path).Length -lt 5000) {
                    $allScreenshotsPresent = $false
                }
                if ($capture.hasHorizontalOverflow) {
                    $zeroOverflow = $false
                }
            }
        }
        Add-Control $controls 'M12-RESPONSIVE-001' (
            $allScreenshotsPresent -and $zeroOverflow
        ) 'Las 12 evidencias existentes cubren 1440, 768 y 375 px sin overflow horizontal detectado.'

        $ratioBrandOnWhite = Get-ContrastRatio -Color1 '#1B4D3E' -Color2 '#FFFFFF'
        $ratioWhiteOnBrand = Get-ContrastRatio -Color1 '#FFFFFF' -Color2 '#1B4D3E'
        $ratioBodyOnBg = Get-ContrastRatio -Color1 '#1E293B' -Color2 '#F8FAFC'
        $ratioBadgeValid = Get-ContrastRatio -Color1 '#14532D' -Color2 '#DCFCE7'
        $ratioBadgeObserved = Get-ContrastRatio -Color1 '#92400E' -Color2 '#FEF3C7'
        $ratiosBelowThreshold = @(@(
            $ratioBrandOnWhite,
            $ratioWhiteOnBrand,
            $ratioBodyOnBg,
            $ratioBadgeValid,
            $ratioBadgeObserved
        ) | Where-Object { $_ -lt 4.5 })
        Add-Control $controls 'M12-ACCESSIBILITY-001' ($ratiosBelowThreshold.Count -eq 0) (
            "Contraste parcial de paleta >= 4.5:1: ${ratioBrandOnWhite}, ${ratioWhiteOnBrand}, " +
            "${ratioBodyOnBg}, ${ratioBadgeValid}, ${ratioBadgeObserved}; no equivale a certificación WCAG."
        )

        $filesToScan = Get-ChildItem -Path (Join-Path $repositoryRoot 'plugins'), (Join-Path $repositoryRoot 'scripts\staging') -Recurse -Include *.php, *.css, *.js, *.mjs, *.ps1
        $privateKeyRegex = '-----BEGIN (RSA|EC|OPENSSH|DSA|PGP)?\s*PRIVATE KEY-----'
        $hardcodedSecretRegex = '(?i)(api[_-]?key|secret[_-]?key|auth[_-]?token)\s*[:=]\s*[''"][a-zA-Z0-9_\-\.]{12,}[''"]'
        $leakedSecretsFound = $false
        foreach ($file in $filesToScan) {
            $content = Get-Content -LiteralPath $file.FullName -Raw
            if ($content -match $privateKeyRegex -or $content -match $hardcodedSecretRegex) {
                $leakedSecretsFound = $true
                Write-Host "    [ALERTA] Coincidencia sospechosa en $($file.FullName)" -ForegroundColor Red
            }
        }
        Add-Control $controls 'M12-PRIVACY-001' (-not $leakedSecretsFound) (
            'Escaneo regex focalizado sin claves privadas ni credenciales fijas en plugins/scripts; no sustituye un escáner dedicado.'
        )

        $fixtureContent = Get-Content (Join-Path $repositoryRoot 'scripts\staging\moodle53-institutional-ux.php') -Raw
        $removedSeedDispatch = "case 'seed" + "-snapshot'"
        Add-Control $controls 'M12-SNAPSHOT-GUARD-001' (
            -not $fixtureContent.Contains($removedSeedDispatch) -and
            $fixtureContent.Contains("fopen(`$path, 'x+b')") -and
            $fixtureContent.Contains('M12_SNAPSHOT_VERSION')
        ) 'No existe seed artificial; la captura nueva es versionada, verificable y de creación exclusiva.'

        Add-Control $controls 'M12-REGRESSION-001' (
            $statusBefore.mission10.restricted_following_units -eq 4 -and
            $statusBefore.mission11.plugin_version -ge 2026102202 -and
            $statusBefore.mission11.session_table_exists -and
            $statusBefore.mission11.synthetic_session_count -ge 2
        ) 'Comprobación de humo de solo lectura: restricciones M10 y datos sintéticos/plugin M11 permanecen presentes.'

        Add-Control $controls 'M12-LOCKDOWN-001' (
            -not $statusBefore.course_visible -and -not $statusBefore.category_visible
        ) 'Curso AF8 y categoría sintéticos permanecen ocultos; Verify no corrige ni cambia su visibilidad.'

        $statusAfter = (Invoke-M12Phase -Name 'status') | ConvertFrom-Json
        Add-Control $controls 'M12-VERIFY-READONLY-001' (
            Test-M12StatusUnchanged -Before $statusBefore -After $statusAfter
        ) 'Verify preserva tema, nombre, SCSS, snapshot y visibilidad byte a byte/hash a hash.'

        $allPassed = @($controls | Where-Object { -not $_.passed }).Count -eq 0
        $evidencePayload = [pscustomobject]@{
            generated_at = (Get-Date).ToUniversalTime().ToString('o')
            scope = 'Verificación M12 de solo lectura sobre Moodle 5.3 local aislado'
            mission = 12
            status = if ($allPassed) { 'PASS' } else { 'FAIL' }
            verify_mutates_moodle = $false
            theme = $statusAfter.theme
            brandcolor_provisional = $statusAfter.brandcolor
            site_fullname = $statusAfter.site_fullname
            snapshot = $statusAfter.snapshot
            viewports = @('1440x900 (Desktop)', '768x1024 (Tablet)', '375x812 (Mobile)')
            total_evidence_captures = if ($afterManifest) { $afterManifest.total_captures } else { 0 }
            controls = $controls
        }
        $evidenceJson = $evidencePayload | ConvertTo-Json -Depth 8
        Set-Content -LiteralPath $evidencePath -Value $evidenceJson -Encoding utf8
        Set-Content -LiteralPath $evidenceMissionPath -Value $evidenceJson -Encoding utf8

        if (-not $allPassed) {
            $failedControls = @($controls | Where-Object { -not $_.passed } | ForEach-Object { "$($_.id): $($_.detail)" })
            throw "Fallaron $($failedControls.Count) controles de Misión 12: $($failedControls -join '; ')"
        }
        Write-Host "`nVerify completado en modo de solo lectura para la configuración Moodle." -ForegroundColor Green
    }
} finally {
    if ($shouldEnforceLockdown) {
        Write-Host "`n[Seguridad] Garantizando bloqueo tras una fase que sí puede mutar el staging..." -ForegroundColor Yellow
        $lockdownStatus = (Invoke-M12Phase -Name 'lockdown') | ConvertFrom-Json
        if (-not $lockdownStatus.course_visible -and -not $lockdownStatus.category_visible) {
            Write-Host '  [OK] Curso y categoría en estado OCULTO.' -ForegroundColor Green
        } else {
            throw 'No se pudo confirmar el confinamiento del curso y la categoría.'
        }
    }
}
