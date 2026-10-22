[CmdletBinding()]
param(
    [ValidateSet('All', 'Apply', 'Capture', 'Verify', 'Lockdown', 'Revert')]
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
        throw "Fallo la fase M12 '$Name': $($output -join [Environment]::NewLine)"
    }
    return ($output -join [Environment]::NewLine)
}

function Invoke-M12PhaseSafe {
    param([Parameter(Mandatory)][string]$Name)

    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & docker exec --user www-data $container php $fixture "--phase=$Name" 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
    }

    return [pscustomobject]@{
        output = ($output -join [Environment]::NewLine)
        exitCode = $exitCode
        success = ($exitCode -eq 0)
    }
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

# --- Funciones de cálculo matemático de contraste WCAG 2.1 ---
function Get-RelativeLuminance {
    param([Parameter(Mandatory)][string]$HexColor)

    $clean = $HexColor.Trim().TrimStart('#')
    $r = [Convert]::ToInt32($clean.Substring(0, 2), 16) / 255.0
    $g = [Convert]::ToInt32($clean.Substring(2, 2), 16) / 255.0
    $b = [Convert]::ToInt32($clean.Substring(4, 2), 16) / 255.0

    $toLinear = {
        param([double]$c)
        if ($c -le 0.04045) { return $c / 12.92 }
        return [Math]::Pow(($c + 0.055) / 1.055, 2.4)
    }

    $rLin = & $toLinear $r
    $gLin = & $toLinear $g
    $bLin = & $toLinear $b

    return (0.2126 * $rLin + 0.7152 * $gLin + 0.0722 * $bLin)
}

function Get-ContrastRatio {
    param(
        [Parameter(Mandatory)][string]$Color1,
        [Parameter(Mandatory)][string]$Color2
    )

    $lum1 = Get-RelativeLuminance -HexColor $Color1
    $lum2 = Get-RelativeLuminance -HexColor $Color2
    $lighter = [Math]::Max($lum1, $lum2)
    $darker = [Math]::Min($lum1, $lum2)

    return [Math]::Round(($lighter + 0.05) / ($darker + 0.05), 2)
}

$controls = [System.Collections.Generic.List[object]]::new()
$allPassed = $false

try {
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host " ASODELTOL - MISION 12: EXPERIENCIA VISUAL INSTITUCIONAL  " -ForegroundColor Cyan
    Write-Host " (Propuesta cromatica provisional: Verde Tolima / Oro)    " -ForegroundColor Cyan
    Write-Host "==========================================================" -ForegroundColor Cyan

    if ($Phase -eq 'Revert') {
        Write-Host "`n[Fase: Revert] Restaurando configuracion previa desde snapshot..." -ForegroundColor Yellow
        $revertOut = Invoke-M12Phase -Name 'revert'
        $revertObj = $revertOut | ConvertFrom-Json
        Write-Host "  Estado: $($revertObj.status)"
        Write-Host "  Sitio restaurado a: $($revertObj.site_fullname)"
        Write-Host "  Tema: $($revertObj.theme) | Brandcolor: '$($revertObj.brandcolor)'"
        Write-Host "  Snapshot eliminado tras exito: $($revertObj.snapshot_deleted)"
        return
    }

    if ($Phase -eq 'Lockdown') {
        Write-Host "`n[Fase: Lockdown] Garantizando confinamiento de curso y categoria..." -ForegroundColor Yellow
        $lockOut = Invoke-M12Phase -Name 'lockdown'
        Write-Host $lockOut
        return
    }

    # 1. Aplicacion de la identidad visual (Solo en All o Apply, NO en Verify)
    if ($Phase -in @('All', 'Apply')) {
        Write-Host "`n[Fase: Apply] Tomando snapshot y aplicando tokens de diseno provisionales..." -ForegroundColor Yellow
        $applyJsonRaw = Invoke-M12Phase -Name 'apply'
        $applyResult = $applyJsonRaw | ConvertFrom-Json
        Write-Host "  SCSS inyectado: $($applyResult.scss_bytes) bytes | Brandcolor provisional: $($applyResult.brandcolor)"
    }

    # 2. Inspeccion y capturas multi-dispositivo
    if ($Phase -in @('All', 'Capture')) {
        Write-Host "`n[Fase: Capture] Iniciando captura multi-dispositivo (1440px, 768px, 375px)..." -ForegroundColor Yellow
        
        # Abrir temporalmente para captura de cuentas sinteticas
        $openResult = (Invoke-M12Phase -Name 'open') | ConvertFrom-Json
        
        try {
            # Ejecutar script Node CDP con inspeccion DOM profunda de overflow
            & node $captureScript --mode=after
            if ($LASTEXITCODE -ne 0) {
                throw "Fallo la captura multi-dispositivo via Microsoft Edge headless."
            }
        } finally {
            # Garantizar cierre inmediato tras capturas
            $null = Invoke-M12Phase -Name 'lockdown'
        }
    }

    # 3. Verificacion estricta de Controles de Calidad (Sin re-aplicar cambios)
    if ($Phase -in @('All', 'Verify')) {
        Write-Host "`n[Fase: Verify] Verificando controles funcionales, accesibilidad y privacidad..." -ForegroundColor Yellow
        
        # Consultar estado actual del tema
        $statusRaw = Invoke-M12Phase -Name 'status'
        $statusObj = $statusRaw | ConvertFrom-Json

        Add-Control $controls 'M12-THEME-001' (
            $statusObj.theme -eq 'boost' -and
            $statusObj.brandcolor -eq '#1B4D3E' -and
            $statusObj.scss_length -gt 1000
        ) 'Tema Boost configurado con paleta provisional Tolima (#1B4D3E) y SCSS activo sin alterar el nucleo.'

        Add-Control $controls 'M12-IDENTITY-001' (
            $statusObj.site_fullname -eq 'Aula Virtual ASODELTOL'
        ) 'Nombre provisional del sitio configurado como Aula Virtual ASODELTOL.'

        # Verificar estilos del modulo de staff
        $staffCssPath = Join-Path $repositoryRoot 'plugins\local\asodeltolstaff\styles.css'
        $staffCssExists = Test-Path $staffCssPath
        $staffCssContent = if ($staffCssExists) { Get-Content $staffCssPath -Raw } else { '' }

        Add-Control $controls 'M12-STAFF-001' (
            $staffCssExists -and
            $staffCssContent.Contains('.table-responsive') -and
            $staffCssContent.Contains('.badge-validated') -and
            $staffCssContent.Contains('min-height: 44px')
        ) 'Modulo local_asodeltolstaff integrado con tabla responsive, insignias de estado y tap targets tactiles >= 44px.'

        # Verificar evidencias multi-dispositivo y ausencia de desbordamiento horizontal real
        $afterManifestPath = Join-Path $evidenceDir 'after\capture-manifest.json'
        $hasAfterManifest = Test-Path $afterManifestPath
        $afterManifest = if ($hasAfterManifest) { Get-Content $afterManifestPath -Raw | ConvertFrom-Json } else { $null }

        $allScreenshotsPresent = $false
        $zeroOverflow = $false

        if ($null -ne $afterManifest -and $afterManifest.total_captures -eq 12) {
            $allScreenshotsPresent = $true
            $zeroOverflow = $true
            foreach ($cap in $afterManifest.captures) {
                if (-not (Test-Path $cap.path) -or (Get-Item $cap.path).Length -lt 5000) {
                    $allScreenshotsPresent = $false
                }
                if ($cap.hasHorizontalOverflow) {
                    $zeroOverflow = $false
                }
            }
        }

        Add-Control $controls 'M12-RESPONSIVE-001' (
            $allScreenshotsPresent -and $zeroOverflow
        ) '12 capturas completas en 3 viewports (1440px escritorio, 768px tableta, 375px movil) sin desbordamiento horizontal ni recorte (0px).'

        # Verificacion matematica de contraste WCAG 2.1 AA en pares texto/fondo de la propuesta provisional
        $ratioBrandOnWhite = Get-ContrastRatio -Color1 '#1B4D3E' -Color2 '#FFFFFF'
        $ratioWhiteOnBrand = Get-ContrastRatio -Color1 '#FFFFFF' -Color2 '#1B4D3E'
        $ratioBodyOnBg     = Get-ContrastRatio -Color1 '#1E293B' -Color2 '#F8FAFC'
        $ratioBadgeValid   = Get-ContrastRatio -Color1 '#14532D' -Color2 '#DCFCE7'
        $ratioBadgeObs     = Get-ContrastRatio -Color1 '#92400E' -Color2 '#FEF3C7'

        $allRatiosPassAA = (
            $ratioBrandOnWhite -ge 4.5 -and
            $ratioWhiteOnBrand -ge 4.5 -and
            $ratioBodyOnBg     -ge 4.5 -and
            $ratioBadgeValid   -ge 4.5 -and
            $ratioBadgeObs     -ge 4.5
        )

        Add-Control $controls 'M12-ACCESSIBILITY-001' (
            $allRatiosPassAA
        ) "Verificacion matematica de contraste WCAG AA (>= 4.5:1) en 5 combinaciones provisionales: Tolima/Blanco (${ratioBrandOnWhite}:1), Blanco/Tolima (${ratioWhiteOnBrand}:1), Texto/Fondo (${ratioBodyOnBg}:1), Validado (${ratioBadgeValid}:1), Observado (${ratioBadgeObs}:1). Control parcial de paleta; no es auditoria integral."

        # Verificacion de privacidad y escaneo regex de secretos reales
        $filesToScan = Get-ChildItem -Path (Join-Path $repositoryRoot 'plugins'), (Join-Path $repositoryRoot 'scripts\staging') -Recurse -Include *.php, *.css, *.js, *.mjs
        $privateKeyRegex = '-----BEGIN (RSA|EC|OPENSSH|DSA|PGP)?\s*PRIVATE KEY-----'
        $hardcodedSecretRegex = '(?i)(api[_-]?key|secret[_-]?key|auth[_-]?token)\s*[:=]\s*[''"][a-zA-Z0-9_\-\.]{12,}[''"]'
        
        $leakedSecretsFound = $false
        foreach ($file in $filesToScan) {
            $content = Get-Content -LiteralPath $file.FullName -Raw
            if ($content -match $privateKeyRegex -or $content -match $hardcodedSecretRegex) {
                $leakedSecretsFound = $true
                Write-Host "    [ALERTA DE SEGURIDAD] Coincidencia sospechosa de secreto en: $($file.Name)" -ForegroundColor Red
            }
        }

        Add-Control $controls 'M12-PRIVACY-001' (
            -not $leakedSecretsFound
        ) 'Escaneo regex estatico en plugins y scripts confirmando ausencia de claves privadas o credenciales fijas en codigo fuente.'

        # 4. Pruebas de seguridad y reversibilidad atomica de snapshot (Revert / Apply)
        Write-Host "`n  [Pruebas de Reversibilidad] Validando rechazo estricto y ciclo real de snapshot..." -ForegroundColor DarkYellow

        $snapFileInContainer = '/var/www/moodledata/theme-snapshot-pre-m12.json'
        $statusPreTests = (& docker exec --user www-data $container php $fixture --phase=status) | ConvertFrom-Json

        # --- Negativa 1: Revert con snapshot ausente debe fallar sin alterar Moodle ---
        & docker exec --user www-data $container rm -f $snapFileInContainer
        $missingRes = Invoke-M12PhaseSafe -Name 'revert'
        $statusAfterMissing = (& docker exec --user www-data $container php $fixture --phase=status) | ConvertFrom-Json

        $missingSnapRejected = (
            $missingRes.exitCode -ne 0 -and
            $missingRes.output.Contains('no existe el archivo de snapshot') -and
            $statusAfterMissing.brandcolor -eq $statusPreTests.brandcolor -and
            $statusAfterMissing.site_fullname -eq $statusPreTests.site_fullname -and
            $statusAfterMissing.scss_length -eq $statusPreTests.scss_length
        )
        Add-Control $controls 'M12-REVERT-NEG-001' $missingSnapRejected 'Revert rechaza la operacion con error y sin alterar la configuracion cuando no existe snapshot.'

        # --- Negativa 2: Revert con snapshot corrupto debe fallar sin alterar Moodle ni borrar el archivo ---
        & docker exec --user www-data $container sh -c "echo '{ corrupt_json: invalid }' > $snapFileInContainer"
        $corruptRes = Invoke-M12PhaseSafe -Name 'revert'
        $statusAfterCorrupt = (& docker exec --user www-data $container php $fixture --phase=status) | ConvertFrom-Json
        $corruptFileStillExists = (& docker exec --user www-data $container php -r "echo file_exists('$snapFileInContainer') ? '1' : '0';") -eq '1'

        $corruptSnapRejected = (
            $corruptRes.exitCode -ne 0 -and
            $corruptRes.output.Contains('snapshot') -and
            $corruptRes.output.Contains('corrupto') -and
            $statusAfterCorrupt.brandcolor -eq $statusPreTests.brandcolor -and
            $statusAfterCorrupt.site_fullname -eq $statusPreTests.site_fullname -and
            $corruptFileStillExists
        )
        Add-Control $controls 'M12-REVERT-NEG-002' $corruptSnapRejected 'Revert rechaza la operacion con error, sin alterar la configuracion y conservando el snapshot cuando el archivo esta corrupto.'

        # Limpiar archivo corrupto de prueba
        & docker exec --user www-data $container rm -f $snapFileInContainer

        # --- Negativa 3: Apply rechaza capturar personalizacion M12 como linea base si no hay snapshot ---
        $applyRejectRes = Invoke-M12PhaseSafe -Name 'apply'
        $applyWithoutSnapRejected = (
            $applyRejectRes.exitCode -ne 0 -and
            $applyRejectRes.output.Contains('M12 ya se encuentra aplicada')
        )
        Add-Control $controls 'M12-APPLY-SAFE-001' $applyWithoutSnapRejected 'Apply aborta la operacion si M12 ya esta aplicada y no existe snapshot previo para evitar falsear la linea base.'

        # --- Positiva 4: Ciclo real Revert -> Apply con verificacion integral de configuracion ---
        # Sembrar snapshot canonico de la linea base original
        $null = Invoke-M12Phase -Name 'seed-snapshot'

        # Ejecutar Revert real
        $revertOut = (Invoke-M12Phase -Name 'revert') | ConvertFrom-Json
        $statusReverted = (& docker exec --user www-data $container php $fixture --phase=status) | ConvertFrom-Json
        $snapshotDeletedOnRevert = (& docker exec --user www-data $container php -r "echo file_exists('$snapFileInContainer') ? '1' : '0';") -eq '0'

        $revertSucceeded = (
            $revertOut.status -eq 'reverted' -and
            $revertOut.brandcolor -eq '' -and
            $revertOut.site_fullname -eq 'ASODELTOL Moodle 5.3 Staging' -and
            $statusReverted.brandcolor -eq '' -and
            $statusReverted.scsspre_length -eq 0 -and
            $statusReverted.site_fullname -eq 'ASODELTOL Moodle 5.3 Staging' -and
            $snapshotDeletedOnRevert
        )

        # Ejecutar Apply real desde el estado limpio para restaurar personalización y capturar snapshot original live
        $applyOut = (Invoke-M12Phase -Name 'apply') | ConvertFrom-Json
        $statusApplied = (& docker exec --user www-data $container php $fixture --phase=status) | ConvertFrom-Json
        $snapshotCreatedOnApply = (& docker exec --user www-data $container php -r "echo (file_exists('$snapFileInContainer') && filesize('$snapFileInContainer') > 0) ? '1' : '0';") -eq '1'

        $applySucceeded = (
            $applyOut.status -eq 'applied' -and
            $applyOut.brandcolor -eq '#1B4D3E' -and
            $applyOut.site_fullname -eq 'Aula Virtual ASODELTOL' -and
            $statusApplied.brandcolor -eq '#1B4D3E' -and
            $statusApplied.scss_length -gt 1000 -and
            $statusApplied.site_fullname -eq 'Aula Virtual ASODELTOL' -and
            $snapshotCreatedOnApply
        )

        Add-Control $controls 'M12-REVERT-POS-001' ($revertSucceeded -and $applySucceeded) 'Ciclo real Apply -> Revert -> Apply restaura exactamente la configuracion previa limpia, elimina el snapshot solo tras el exito y re-aplica la UX institucional verificando el nuevo respaldo.'

        $allPassed = @($controls | Where-Object { -not $_.passed }).Count -eq 0

        # Compilar reporte de evidencia oficial
        $evidencePayload = [pscustomobject]@{
            generated_at = (Get-Date).ToUniversalTime().ToString('o')
            scope = 'Moodle 5.3 local aislado; UX institucional provisional, Boost SCSS y local_asodeltolstaff'
            mission = 12
            status = if ($allPassed) { 'PASS' } else { 'FAIL' }
            theme = $statusObj.theme
            brandcolor_provisional = $statusObj.brandcolor
            site_fullname = $statusObj.site_fullname
            contrast_ratios_calculated = [pscustomobject]@{
                tolima_on_white = $ratioBrandOnWhite
                white_on_tolima = $ratioWhiteOnBrand
                body_on_background = $ratioBodyOnBg
                badge_validated = $ratioBadgeValid
                badge_observed = $ratioBadgeObs
            }
            viewports = @('1440x900 (Desktop)', '768x1024 (Tablet)', '375x812 (Mobile)')
            total_evidence_captures = if ($afterManifest) { $afterManifest.total_captures } else { 0 }
            controls = $controls
        }

        $evidenceJson = $evidencePayload | ConvertTo-Json -Depth 6
        Set-Content -LiteralPath $evidencePath -Value $evidenceJson -Encoding utf8
        Set-Content -LiteralPath $evidenceMissionPath -Value $evidenceJson -Encoding utf8
        Write-Host "`nEvidencia oficial guardada en: $evidencePath" -ForegroundColor Green

        # Fallar con codigo no cero si algun control no paso
        if (-not $allPassed) {
            $failedControls = @($controls | Where-Object { -not $_.passed } | ForEach-Object { "$($_.id): $($_.detail)" })
            throw "Fallaron $($failedControls.Count) control(es) de Mision 12: $($failedControls -join '; ')"
        }

        Write-Host "`n==========================================================" -ForegroundColor Cyan
        Write-Host " MISION 12 COMPLETADA CON EXITO: UX INSTITUCIONAL ACTIVA   " -ForegroundColor Cyan
        Write-Host "==========================================================" -ForegroundColor Cyan
    }

} finally {
    # Bloque de seguridad critico e ineludible: el curso y categoria siempre deben quedar ocultos
    Write-Host "`n[Seguridad] Garantizando bloqueo (lockdown) de curso y categoria..." -ForegroundColor Yellow
    $lockdownOut = Invoke-M12Phase -Name 'lockdown'
    $lockdownStatus = $lockdownOut | ConvertFrom-Json
    if (-not $lockdownStatus.course_visible -and -not $lockdownStatus.category_visible) {
        Write-Host "  [OK] Curso (id=2) y categoria verificados en estado OCULTO (visible=0)." -ForegroundColor Green
    } else {
        Write-Host "  [ALERTA CRITICA] No se pudo confirmar el estado oculto del curso/categoria." -ForegroundColor Red
    }
}
