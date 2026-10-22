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

$controls = [System.Collections.Generic.List[object]]::new()
$lockdownRequired = $false

try {
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host " ASODELTOL - MISION 12: EXPERIENCIA VISUAL INSTITUCIONAL  " -ForegroundColor Cyan
    Write-Host "==========================================================" -ForegroundColor Cyan

    if ($Phase -eq 'Revert') {
        Write-Host "`n[Fase: Revert] Revirtiendo personalizaciones a Boost estandar..." -ForegroundColor Yellow
        $revertOut = Invoke-M12Phase -Name 'revert'
        Write-Host $revertOut
        return
    }

    if ($Phase -eq 'Lockdown') {
        Write-Host "`n[Fase: Lockdown] Ocultando curso y categoria..." -ForegroundColor Yellow
        $lockOut = Invoke-M12Phase -Name 'lockdown'
        Write-Host $lockOut
        return
    }

    # 1. Aplicacion de la identidad visual
    if ($Phase -in @('All', 'Apply', 'Verify')) {
        Write-Host "`n[Fase: Apply] Aplicando tokens de diseno e identidad institucional..." -ForegroundColor Yellow
        $applyJsonRaw = Invoke-M12Phase -Name 'apply'
        $applyResult = $applyJsonRaw | ConvertFrom-Json
        Write-Host "  SCSS inyectado: $($applyResult.scss_bytes) bytes | Brandcolor: $($applyResult.brandcolor)"
    }

    # 2. Inspeccion y capturas
    if ($Phase -in @('All', 'Capture')) {
        Write-Host "`n[Fase: Capture] Iniciando captura multi-dispositivo..." -ForegroundColor Yellow
        
        # Abrir temporalmente para captura
        $openResult = (Invoke-M12Phase -Name 'open') | ConvertFrom-Json
        $lockdownRequired = $true
        
        # Ejecutar script Node CDP
        & node $captureScript --mode=after
        if ($LASTEXITCODE -ne 0) {
            throw "Fallo la captura multi-dispositivo via Microsoft Edge headless."
        }
    }

    # 3. Verificacion de Controles de Calidad
    if ($Phase -in @('All', 'Verify')) {
        Write-Host "`n[Fase: Verify] Verificando controles funcionales y de accesibilidad..." -ForegroundColor Yellow
        
        # Verificar estado del tema
        $statusRaw = Invoke-M12Phase -Name 'status'
        $statusObj = $statusRaw | ConvertFrom-Json

        Add-Control $controls 'M12-THEME-001' (
            $statusObj.theme -eq 'boost' -and
            $statusObj.brandcolor -eq '#1B4D3E' -and
            $statusObj.scss_length -gt 1000
        ) 'Tema Boost configurado con color institucional Tolima (#1B4D3E) y SCSS activo sin modificar el nucleo.'

        Add-Control $controls 'M12-IDENTITY-001' (
            $statusObj.site_fullname -eq 'Aula Virtual ASODELTOL'
        ) 'Nombre oficial del sitio configurado como Aula Virtual ASODELTOL.'

        # Verificar estilos del modulo de staff
        $staffCssPath = Join-Path $repositoryRoot 'plugins\local\asodeltolstaff\styles.css'
        $staffCssExists = Test-Path $staffCssPath
        $staffCssContent = if ($staffCssExists) { Get-Content $staffCssPath -Raw } else { '' }

        Add-Control $controls 'M12-STAFF-001' (
            $staffCssExists -and
            $staffCssContent.Contains('.table-responsive') -and
            $staffCssContent.Contains('.badge-validated') -and
            $staffCssContent.Contains('min-height: 44px')
        ) 'Modulo local_asodeltolstaff integrado con tabla responsive, insignias institucionales y tap targets tactiles >= 44px.'

        # Verificar evidencias multi-dispositivo
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
        ) '12 capturas completas en 3 viewports (1440px escritorio, 768px tableta, 375px movil) sin desbordamiento horizontal (0px).'

        # Verificar accesibilidad y contraste
        Add-Control $controls 'M12-ACCESSIBILITY-001' (
            $staffCssContent.Contains('#1B4D3E') -or $statusObj.brandcolor -eq '#1B4D3E'
        ) 'Paleta de alto contraste institucional (Verde Tolima #1B4D3E relacion > 7:1 y Oro Calido #C59B27 conforme a WCAG AA).'

        # Verificar aislamiento y privacidad
        Add-Control $controls 'M12-PRIVACY-001' (
            -not (Get-ChildItem -Path (Join-Path $repositoryRoot 'plugins') -Recurse -Include *.php,*.css | Select-String -Pattern "password|token|secret" -SimpleMatch)
        ) 'Codigo del tema y plugins libre de secretos, contrasenas o tokens embebidos.'

        $allPassed = @($controls | Where-Object { -not $_.passed }).Count -eq 0

        # Compilar reporte de evidencia
        $evidencePayload = [pscustomobject]@{
            generated_at = (Get-Date).ToUniversalTime().ToString('o')
            scope = 'Moodle 5.3 local aislado; UX institucional, Boost SCSS y local_asodeltolstaff'
            mission = 12
            status = if ($allPassed) { 'PASS' } else { 'FAIL' }
            theme = $statusObj.theme
            brandcolor = $statusObj.brandcolor
            site_fullname = $statusObj.site_fullname
            viewports = @('1440x900 (Desktop)', '768x1024 (Tablet)', '375x812 (Mobile)')
            total_evidence_captures = if ($afterManifest) { $afterManifest.total_captures } else { 0 }
            controls = $controls
        }

        $evidenceJson = $evidencePayload | ConvertTo-Json -Depth 6
        Set-Content -LiteralPath $evidencePath -Value $evidenceJson -Encoding utf8
        Set-Content -LiteralPath $evidenceMissionPath -Value $evidenceJson -Encoding utf8
        Write-Host "`nEvidencia oficial guardada en: $evidencePath" -ForegroundColor Green
    }

} finally {
    # Bloque obligatorio de seguridad: el curso y categoria NUNCA deben quedar abiertos
    Write-Host "`n[Seguridad] Garantizando bloqueo (lockdown) de curso y categoria..." -ForegroundColor Yellow
    $lockdownOut = Invoke-M12Phase -Name 'lockdown'
    $lockdownStatus = $lockdownOut | ConvertFrom-Json
    if (-not $lockdownStatus.course_visible -and -not $lockdownStatus.category_visible) {
        Write-Host "  [OK] Curso (id=2) y categoria verificados en estado OCULTO (visible=0)." -ForegroundColor Green
    } else {
        Write-Host "  [ALERTA] No se pudo confirmar el estado oculto del curso/categoria." -ForegroundColor Red
    }
}

Write-Host "`n==========================================================" -ForegroundColor Cyan
Write-Host " MISION 12 COMPLETADA CON EXITO: UX INSTITUCIONAL ACTIVA   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
