$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$ProgressPreference = 'SilentlyContinue'
$repo = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$url = 'http://127.0.0.1:8053'
$container = 'asodeltol-moodle53-webserver-1'
$raw = & docker exec --user www-data $container php /opt/asodeltol/scripts/anexo14-qa-context.php
if ($LASTEXITCODE -ne 0) { throw 'No se pudo confirmar el staging oculto.' }
$context = ($raw -replace '^ASODELTOL_QA_CONTEXT=', '') | ConvertFrom-Json
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$login = Invoke-WebRequest "$url/login/index.php" -WebSession $session -UseBasicParsing
$token = [regex]::Match($login.Content, 'name="logintoken"\s+value="([^"]+)"')
if (-not $token.Success) { throw 'Falta logintoken.' }
$password = [IO.File]::ReadAllText((Join-Path $repo 'build/staging/moodle53/secrets/student-password.txt')).Trim()
$form = @{username='af8.synthetic.g01'; password=$password; logintoken=$token.Groups[1].Value}
try {
    $auth = Invoke-WebRequest "$url/login/index.php" -Method Post -Body $form -WebSession $session -UseBasicParsing
    if ($auth.Content -match 'loginerrormessage|Invalid login') { throw 'Login sintético rechazado.' }
    $profile = Invoke-WebRequest "$url/user/profile.php" -WebSession $session -UseBasicParsing
    if ($profile.Content -notmatch 'data-userid="[1-9][0-9]*"') { throw 'No se demostró sesión autenticada.' }
    $response = Invoke-WebRequest "$url/course/view.php?id=$($context.course_id)" -WebSession $session -UseBasicParsing
    $denied = $response.Content -match 'coursehidden|This course is currently unavailable|Este curso no está disponible|Este curso no está disponible actualmente'
    $result = [ordered]@{requirement='REQ-18-01'; environment=$url; actor='af8.synthetic.g01'; authenticated=$true; course_hidden=$context.course_hidden; category_hidden=$context.category_hidden; status=$(if($denied){'PASS'}else{'FAIL'}); evidence='Beneficiario autenticado solicita URL directa del curso oculto; Moodle informa curso no disponible.'}
    $dir = Join-Path $repo 'build/staging/anexo14-qa'
    $result | ConvertTo-Json | Set-Content (Join-Path $dir 'hidden-course.json') -Encoding utf8
    $result | ConvertTo-Json | Write-Output
    if (-not $denied) { throw 'No se demostró bloqueo por curso oculto.' }
} finally {
    $password = $null
    $form.password = ''
    & docker exec --user www-data $container php /opt/asodeltol/scripts/anexo14-qa-context.php
}
