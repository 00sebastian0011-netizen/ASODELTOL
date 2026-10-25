$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$deploy = Join-Path $root 'scripts/deploy'

$required = @(
    'deploy-policy.json',
    'classify-changes.mjs',
    'vps/deploy_release.py',
    'vps/receiver.py',
    'vps/asodeltol-deploy.service',
    'vps/nginx-location.conf',
    'vps/install-agent.sh',
    'vps/selftest.sh'
)
foreach ($relative in $required) {
    if (-not (Test-Path -LiteralPath (Join-Path $deploy $relative) -PathType Leaf)) {
        throw "Missing deployment file: $relative"
    }
}

$policy = Get-Content -Raw -LiteralPath (Join-Path $deploy 'deploy-policy.json') | ConvertFrom-Json
$componentIds = @($policy.components | ForEach-Object { $_.id })
foreach ($component in @('theme_rsmax', 'local_asodeltolstaff')) {
    if ($componentIds -notcontains $component) { throw "Deployment policy does not define $component" }
}
foreach ($component in $policy.components) {
    if ($component.critical -notcontains 'version.php') {
        throw "Component $($component.id) does not protect version.php"
    }
    if ($component.excluded -notcontains 'tests/') {
        throw "Component $($component.id) would publish test files"
    }
}

$receiver = Get-Content -Raw -LiteralPath (Join-Path $deploy 'vps/receiver.py')
foreach ($pattern in @('hmac\.compare_digest', 'X-Hub-Signature-256', 'EXPECTED_REPOSITORY', 'EXPECTED_REF')) {
    if ($receiver -notmatch $pattern) { throw "Webhook safeguard missing: $pattern" }
}

$deployer = Get-Content -Raw -LiteralPath (Join-Path $deploy 'vps/deploy_release.py')
foreach ($pattern in @('protected production drift', 'rollback\(', 'Post-deploy hash mismatch', 'merge-base')) {
    if ($deployer -notmatch $pattern) { throw "Deployment safeguard missing: $pattern" }
}

$service = Get-Content -Raw -LiteralPath (Join-Path $deploy 'vps/asodeltol-deploy.service')
foreach ($pattern in @('User=user', 'NoNewPrivileges=true', 'ProtectSystem=strict', 'ProtectHome=read-only')) {
    if ($service -notmatch [regex]::Escape($pattern)) { throw "Service hardening missing: $pattern" }
}
if ($service -match 'User=root') { throw 'Deployment receiver must not run as root.' }

$workflow = Get-Content -Raw -LiteralPath (Join-Path $root '.github/workflows/deploy-vps.yml')
if ($workflow -match 'VPS_SSH_KEY|ssh\s|scp\s') {
    throw 'GitHub Actions must not hold production SSH credentials; the signed VPS receiver owns deployment.'
}

Write-Output 'Deployment safety validation passed.'
