[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$errors = [System.Collections.Generic.List[string]]::new()
$publishableFiles = @()

$requiredFiles = @(
    'README.md',
    'AGENTS.md',
    '.gitignore',
    'docs/ARQUITECTURA.md',
    'docs/REQUERIMIENTOS.md',
    'docs/ROADMAP.md',
    'docs/SEGURIDAD.md',
    'docs/DECISIONES_PENDIENTES.md',
    'docs/OPERACION.md',
    'docs/APROVISIONAMIENTO_AF8.md',
    'plugins/README.md',
    'catalog/af8/catalog.json',
    'catalog/af8/access-control-matrix.json',
    'catalog/schema/af8-catalog.schema.json',
    'scripts/catalog/validate-catalog.mjs'
)

foreach ($relativePath in $requiredFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot $relativePath) -PathType Leaf)) {
        $errors.Add("Falta el archivo requerido: $relativePath")
    }
}

$gitDirectory = Join-Path $repoRoot '.git'
$forbiddenRepositoryPatterns = @(
    '(^|/)\.env($|\.)',
    '(^|/)config\.php$',
    '(^|/)moodledata(/|$)',
    '\.(sql|dump|backup|bak|mbz)(\..*)?$',
    '\.(pem|key|ppk|p12|pfx|jks)$',
    '(^|/)(id_rsa|id_ed25519)(\..*)?$',
    '\.(csv|tsv|xls|xlsx|ods)$',
    '(^|/)(private-data|personal-data|pii)(/|$)',
    '^(imports|exports|reports-output)(/|$)'
)

if (-not (Test-Path -LiteralPath $gitDirectory)) {
    $errors.Add('El directorio no parece ser un repositorio Git.')
}
else {
    $publishableOutput = & git -C $repoRoot ls-files --cached --others --exclude-standard
    if ($LASTEXITCODE -ne 0) {
        $errors.Add('No fue posible consultar los archivos que Git publicaría.')
    }
    else {
        $publishableFiles = @($publishableOutput)
    }

    $ignoreSamples = @(
        '.env',
        'config.php',
        'moodledata/filedir/example',
        'backup.sql',
        'students.xlsx',
        'data_export.csv',
        'id_rsa',
        'course.mbz'
    )

    foreach ($sample in $ignoreSamples) {
        & git -C $repoRoot check-ignore --quiet --no-index $sample
        if ($LASTEXITCODE -ne 0) {
            $errors.Add(".gitignore no cubre el ejemplo sensible: $sample")
        }
    }
}

foreach ($relativePath in $publishableFiles) {
    if ($relativePath -match '^tests/fixtures/[^/]+\.csv$') {
        continue
    }
    foreach ($pattern in $forbiddenRepositoryPatterns) {
        if ($relativePath -match $pattern) {
            $errors.Add("Archivo sensible/prohibido publicable: $relativePath")
            break
        }
    }
}

$coreIndicators = @(
    'version.php',
    'admin/cli/install.php',
    'lib/moodlelib.php',
    'course/view.php'
)

foreach ($relativePath in $coreIndicators) {
    if (Test-Path -LiteralPath (Join-Path $repoRoot $relativePath) -PathType Leaf) {
        $errors.Add("Posible copia del núcleo Moodle en la raíz: $relativePath")
    }
}

$textExtensions = @('*.md', '*.php', '*.js', '*.ts', '*.css', '*.scss', '*.xml', '*.yml', '*.yaml', '*.json', '*.ps1')
$candidateFiles = Get-ChildItem -LiteralPath $repoRoot -Recurse -File -Include $textExtensions |
    Where-Object { $_.FullName -notmatch '[\\/](\.git|build|vendor|node_modules)[\\/]' }

foreach ($file in $candidateFiles) {
    $lineNumber = 0
    foreach ($line in [System.IO.File]::ReadLines($file.FullName)) {
        $lineNumber++
        if ($line -match '^(<{7}|={7}|>{7})([^=<>]|$)') {
            $relativePath = $file.FullName.Substring($repoRoot.Length).TrimStart('\', '/')
            $errors.Add("Marcador de conflicto en ${relativePath}:$lineNumber")
        }
    }
}

$nodeCommand = Get-Command node -ErrorAction SilentlyContinue
if ($null -eq $nodeCommand) {
    $errors.Add('Node.js no está disponible; se requiere versión 20 o posterior para validar el catálogo AF8.')
}
else {
    $nodeVersionOutput = & node --version
    if ($LASTEXITCODE -ne 0 -or $nodeVersionOutput -notmatch '^v(?<major>\d+)\.') {
        $errors.Add('No fue posible determinar la versión de Node.js.')
    }
    elseif ([int]$Matches.major -lt 20) {
        $errors.Add("Node.js 20 o posterior es obligatorio; versión detectada: $nodeVersionOutput")
    }

    & node (Join-Path $repoRoot 'scripts/catalog/validate-catalog.mjs')
    if ($LASTEXITCODE -ne 0) {
        $errors.Add('Falló la validación funcional del catálogo AF8.')
    }
}

if ($errors.Count -gt 0) {
    Write-Error ("Validación fallida:`n - " + ($errors -join "`n - "))
    exit 1
}

Write-Host 'Validación del repositorio completada sin errores.' -ForegroundColor Green
