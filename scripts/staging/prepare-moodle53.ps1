[CmdletBinding()]
param(
    [switch]$SkipPull,
    [switch]$VerifyOnly
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$runtimeRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot 'build\staging\moodle53'))
$moodleSource = Join-Path $runtimeRoot 'moodle'
$artifactsDirectory = Join-Path $runtimeRoot 'artifacts'
$bundleDirectory = Join-Path $artifactsDirectory 'bundle'
$secretsDirectory = Join-Path $runtimeRoot 'secrets'
$evidencePath = Join-Path $runtimeRoot 'evidence.json'
$environmentFile = Join-Path $runtimeRoot 'compose.env'
$composeFile = Join-Path $repoRoot 'infra\local\moodle53\compose.yml'
$moodleCommit = 'aa930e26fbdd08dd8ba70f53298838a8d306ee57'
$moodleRepository = 'https://github.com/moodle/moodle.git'
$projectName = 'asodeltol-moodle53'
$localUrl = 'http://127.0.0.1:8053'
$utf8NoBom = [System.Text.UTF8Encoding]::new($false)

if (-not $runtimeRoot.StartsWith($repoRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'La ruta de ejecución quedó fuera del repositorio.'
}

function Invoke-Checked {
    param(
        [Parameter(Mandatory)] [string]$Program,
        [Parameter(Mandatory)] [string[]]$Arguments
    )

    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Falló $Program con código $LASTEXITCODE."
    }
}

function Invoke-Compose {
    param([Parameter(Mandatory)] [string[]]$Arguments)

    $baseArguments = @(
        'compose',
        '--project-name', $projectName,
        '--env-file', $environmentFile,
        '--file', $composeFile
    )
    Invoke-Checked -Program 'docker' -Arguments ($baseArguments + $Arguments)
}

function New-RandomSecret {
    $bytes = [byte[]]::new(48)
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }
    return 'Aa1!' + ([BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
}

function Initialize-SecretFile {
    param([Parameter(Mandatory)] [string]$Path)

    if (Test-Path -LiteralPath $Path -PathType Leaf) {
        if ([string]::IsNullOrWhiteSpace([System.IO.File]::ReadAllText($Path))) {
            throw "El archivo secreto existe pero está vacío: $Path"
        }
        return
    }
    [System.IO.File]::WriteAllText($Path, (New-RandomSecret) + "`n", $utf8NoBom)
}

function Convert-ToComposePath {
    param([Parameter(Mandatory)] [string]$Path)
    return [System.IO.Path]::GetFullPath($Path).Replace('\', '/')
}

$docker = Get-Command docker -ErrorAction SilentlyContinue
if ($null -eq $docker) {
    throw 'Docker CLI no está disponible. No se instaló ningún programa automáticamente.'
}

$serverOs = & docker info --format '{{.OSType}}' 2>$null
if ($LASTEXITCODE -ne 0 -or $serverOs.Trim() -ne 'linux') {
    throw 'Docker Engine Linux no está disponible. Inicie Docker Desktop/WSL2 y repita.'
}

$listener = Get-NetTCPConnection -LocalPort 8053 -State Listen -ErrorAction SilentlyContinue
$existingWebContainer = & docker ps --filter "label=com.docker.compose.project=$projectName" --filter 'label=com.docker.compose.service=webserver' --format '{{.ID}}'
if ($listener -and [string]::IsNullOrWhiteSpace(($existingWebContainer -join ''))) {
    throw 'El puerto local 8053 está ocupado por otro proceso.'
}

New-Item -ItemType Directory -Force -Path $runtimeRoot, $artifactsDirectory, $secretsDirectory | Out-Null

if (-not (Test-Path -LiteralPath (Join-Path $moodleSource '.git') -PathType Container)) {
    if (Test-Path -LiteralPath $moodleSource) {
        throw 'La ruta del núcleo Moodle existe pero no es un clon Git verificable.'
    }
    New-Item -ItemType Directory -Path $moodleSource | Out-Null
    Invoke-Checked -Program 'git' -Arguments @('-C', $moodleSource, 'init')
    Invoke-Checked -Program 'git' -Arguments @('-C', $moodleSource, 'remote', 'add', 'origin', $moodleRepository)
    Invoke-Checked -Program 'git' -Arguments @('-C', $moodleSource, 'fetch', '--depth', '1', 'origin', $moodleCommit)
    Invoke-Checked -Program 'git' -Arguments @('-C', $moodleSource, 'switch', '--detach', $moodleCommit)
}

$actualOrigin = (& git -C $moodleSource remote get-url origin).Trim()
$actualCommit = (& git -C $moodleSource rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $actualOrigin -ne $moodleRepository -or $actualCommit -ne $moodleCommit) {
    throw 'El clon local de Moodle no coincide con el repositorio y commit oficiales fijados.'
}
if (-not (Test-Path -LiteralPath (Join-Path $moodleSource 'public\version.php') -PathType Leaf)) {
    throw 'El clon de Moodle no contiene la raíz web public requerida por Moodle 5.3.'
}

# Docker needs this nested bind-mount target before the parent Moodle source is mounted read-only.
# The empty directory exists only inside the ignored local runtime clone.
$staffPluginMountPoint = Join-Path $moodleSource 'public\local\asodeltolstaff'
New-Item -ItemType Directory -Path $staffPluginMountPoint -Force | Out-Null

$secretPaths = [ordered]@{
    DB_PASSWORD_FILE = Join-Path $secretsDirectory 'database-password.txt'
    DB_ROOT_PASSWORD_FILE = Join-Path $secretsDirectory 'database-root-password.txt'
    ADMIN_PASSWORD_FILE = Join-Path $secretsDirectory 'admin-password.txt'
    STUDENT_PASSWORD_FILE = Join-Path $secretsDirectory 'student-password.txt'
}
foreach ($secretPath in $secretPaths.Values) {
    Initialize-SecretFile -Path $secretPath
}

$config = @'
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$dbpassword = trim((string) file_get_contents('/run/secrets/db_password'));
if ($dbpassword === '') {
    throw new RuntimeException('Missing local database secret.');
}

$CFG->dbtype = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'database';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = $dbpassword;
$CFG->prefix = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => false,
    'dbport' => '',
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
];
unset($dbpassword);

$CFG->wwwroot = 'http://127.0.0.1:8053';
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 0770;
$CFG->noemailever = true;
$CFG->smtphosts = '';
$CFG->noreplyaddress = 'noreply@example.invalid';
$CFG->supportemail = 'support@example.invalid';
$CFG->site_is_public = false;
$CFG->disableupdatenotifications = true;
$CFG->disableupdateautodeploy = true;
$CFG->cronclionly = true;
$CFG->cookiehttponly = true;
$CFG->sessioncookie = 'ASODELTOLSTG53';

require_once(__DIR__ . '/lib/setup.php');

// Moodle 5.3's CLI course uploader validates cohort context without loading
// the cohort API first. Load it only for that native CLI entry point.
if (PHP_SAPI === 'cli'
        && isset($_SERVER['argv'][0])
        && str_ends_with((string) $_SERVER['argv'][0], '/admin/tool/uploadcourse/cli/uploadcourse.php')) {
    require_once($CFG->dirroot . '/cohort/lib.php');
}
'@
[System.IO.File]::WriteAllText((Join-Path $moodleSource 'config.php'), $config, $utf8NoBom)

$environmentLines = [System.Collections.Generic.List[string]]::new()
$environmentLines.Add('MOODLE_PORT=8053')
$environmentLines.Add('MOODLE_SOURCE=' + (Convert-ToComposePath $moodleSource))
$environmentLines.Add('STAGING_ARTIFACTS=' + (Convert-ToComposePath $artifactsDirectory))
$environmentLines.Add('STAGING_SCRIPTS=' + (Convert-ToComposePath $PSScriptRoot))
$environmentLines.Add('ASODELTOL_STAFF_PLUGIN=' + (Convert-ToComposePath (Join-Path $repoRoot 'plugins\local\asodeltolstaff')))
foreach ($entry in $secretPaths.GetEnumerator()) {
    $environmentLines.Add($entry.Key + '=' + (Convert-ToComposePath $entry.Value))
}
[System.IO.File]::WriteAllLines($environmentFile, $environmentLines, $utf8NoBom)

Invoke-Compose -Arguments @('config', '--quiet')

if (-not $SkipPull -and -not $VerifyOnly) {
    Invoke-Compose -Arguments @('pull', '--quiet')
}

Invoke-Compose -Arguments @('up', '--detach', '--wait', 'database', 'webserver')

$composeBaseArguments = @(
    'compose', '--project-name', $projectName,
    '--env-file', $environmentFile,
    '--file', $composeFile
)

$installCheckOutput = & docker @composeBaseArguments exec -T --user www-data webserver php /opt/asodeltol/scripts/moodle53-install-status.php
if ($LASTEXITCODE -ne 0) {
    throw 'No fue posible consultar el estado inicial de la base Moodle.'
}
$installStatus = ($installCheckOutput -join '').Trim()
if ($installStatus -notin @('PRESENT', 'ABSENT')) {
    throw "Estado de instalación inesperado: $installStatus"
}
$installed = $installStatus -eq 'PRESENT'

if (-not $installed) {
    if ($VerifyOnly) {
        throw 'Moodle todavía no está instalado; VerifyOnly no puede continuar.'
    }
    Invoke-Compose -Arguments @('exec', '-T', '--user', 'www-data', 'webserver', 'php', '/opt/asodeltol/scripts/moodle53-install.php')
}

if (-not $VerifyOnly) {
    Invoke-Compose -Arguments @('exec', '-T', '--user', 'www-data', 'webserver', 'php', '/opt/asodeltol/scripts/moodle53-provision.php', '--phase=category')

    if (-not (Test-Path -LiteralPath $bundleDirectory -PathType Container)) {
        Invoke-Checked -Program 'node' -Arguments @(
            (Join-Path $repoRoot 'scripts\catalog\build-provisioning-bundle.mjs'),
            '--category-idnumber=ASODELTOL-STAGING',
            "--output=$bundleDirectory"
        )
    }
    $operatorPlan = Get-Content -LiteralPath (Join-Path $bundleDirectory 'operator-plan.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    if ($operatorPlan.approved_category_idnumber_input -ne 'ASODELTOL-STAGING' -or $operatorPlan.production_authorized -ne $false) {
        throw 'El bundle existente no corresponde al staging aislado aprobado.'
    }

    Invoke-Compose -Arguments @('exec', '-T', '--user', 'www-data', 'webserver', 'php', '/opt/asodeltol/scripts/moodle53-provision.php', '--phase=cohorts')

    $courseStatus = & docker @composeBaseArguments exec -T --user www-data webserver php /opt/asodeltol/scripts/moodle53-provision.php --phase=course-status
    if ($LASTEXITCODE -ne 0) {
        throw 'No fue posible consultar el estado del curso AF8.'
    }
    if (($courseStatus -join "`n").Trim() -notin @('ABSENT', 'PRESENT')) {
        throw "Respuesta inesperada al consultar el curso: $($courseStatus -join ' ')"
    }

    Invoke-Compose -Arguments @(
        'exec', '-T', '--user', 'www-data', 'webserver',
        'php', '/var/www/html/public/admin/tool/uploadcourse/cli/uploadcourse.php',
        '--mode=createorupdate', '--updatemode=dataonly',
        '--file=/opt/asodeltol/artifacts/bundle/course-upload.csv',
        '--delimiter=comma', '--encoding=UTF-8'
    )

    $courseStatus = & docker @composeBaseArguments exec -T --user www-data webserver php /opt/asodeltol/scripts/moodle53-provision.php --phase=course-status
    if ($LASTEXITCODE -ne 0 -or ($courseStatus -join "`n").Trim() -ne 'PRESENT') {
        throw 'El cargador oficial terminó sin dejar el curso AF8 en Moodle.'
    }

    Invoke-Compose -Arguments @('exec', '-T', '--user', 'www-data', 'webserver', 'php', '/opt/asodeltol/scripts/moodle53-provision.php', '--phase=post')
}

$verificationOutput = & docker @composeBaseArguments exec -T --user www-data webserver php /opt/asodeltol/scripts/moodle53-verify.php
if ($LASTEXITCODE -ne 0) {
    throw 'Falló la verificación funcional dentro de Moodle.'
}
$moodleEvidenceJson = ($verificationOutput -join "`n").Trim()
$moodleEvidence = $moodleEvidenceJson | ConvertFrom-Json
if ($moodleEvidence.status -ne 'PASS') {
    throw 'Moodle no produjo evidencia satisfactoria.'
}

$response = Invoke-WebRequest -Uri $localUrl -UseBasicParsing -MaximumRedirection 5
if ($response.StatusCode -ne 200 -or $response.Content -notmatch 'ASODELTOL Moodle 5.3 Staging') {
    throw 'La interfaz HTTP local no respondió con el sitio Moodle esperado.'
}

$databaseVolume = & docker volume inspect asodeltol-moodle53-database --format '{{.Name}}'
$moodledataVolume = & docker volume inspect asodeltol-moodle53-moodledata --format '{{.Name}}'
if ($LASTEXITCODE -ne 0) {
    throw 'No fue posible verificar los volúmenes aislados.'
}

$combinedEvidence = [ordered]@{
    status = 'PASS'
    local_url = $localUrl
    source = [ordered]@{
        repository = $moodleRepository
        commit = $actualCommit
    }
    images = [ordered]@{
        web = 'moodlehq/moodle-php-apache:8.4-bookworm@sha256:3e910075c08dc73c8e18732f5e80c8fb9a60ae72eaf52b1ee198bb510e4fd9d6'
        database = 'mysql:8.4@sha256:76f0358884cb92d9a086d24e5671c38048d3fef40f2588703d73eb55392d6b69'
    }
    network = [ordered]@{
        published_endpoint = '127.0.0.1:8053'
        database_port_published = $false
        backend_network_internal = $true
        http_status = $response.StatusCode
    }
    storage = [ordered]@{
        database_volume = ($databaseVolume -join '').Trim()
        moodledata_volume = ($moodledataVolume -join '').Trim()
    }
    moodle = $moodleEvidence
}
[System.IO.File]::WriteAllText(
    $evidencePath,
    ($combinedEvidence | ConvertTo-Json -Depth 12) + "`n",
    $utf8NoBom
)

Write-Host 'STAGING_READY=YES' -ForegroundColor Green
Write-Host "URL=$localUrl"
Write-Host 'ADMIN_USER=asodeltol_admin'
Write-Host 'ADMIN_PASSWORD=stored locally; use scripts/staging/copy-admin-password.ps1'
Write-Host "EVIDENCE=$evidencePath"
