# Prepare School ERP for XAMPP on Windows without touching live data.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\xampp-setup.ps1
#        powershell -ExecutionPolicy Bypass -File scripts\xampp-setup.ps1 -FixPermissions

param(
    [switch]$FixPermissions
)

. (Join-Path $PSScriptRoot 'xampp-lib.ps1')

$xampp = Find-XamppHome
if (-not $xampp) {
    Write-Host 'Error: XAMPP not found. Set XAMPP_HOME to your XAMPP folder.' -ForegroundColor Red
    exit 1
}
$php = Join-Path $xampp 'php\php.exe'
$root = $Script:ProjectRoot

Write-Section 'Environment'
$envFile = Join-Path $root '.env'
if (-not (Test-Path $envFile)) {
    Copy-Item -LiteralPath (Join-Path $root '.env.example') -Destination $envFile
    Write-Ok 'created .env from .env.example'
} else {
    Write-Ok '.env already exists; left unchanged'
}
$envText = Get-Content -LiteralPath $envFile -Raw
if ($envText -notmatch '(?m)^APP_KEY=.+') {
    $keyResult = Invoke-AppConsole $xampp 'key:generate'
    if ($keyResult.ExitCode -eq 0) { Write-Ok 'generated APP_KEY' } else { Write-Fail 'could not generate APP_KEY' }
} else {
    Write-Ok 'APP_KEY is set'
}

Write-Section 'Storage directories'
foreach ($dir in @('storage', 'storage\logs', 'storage\cache', 'storage\sessions', 'storage\temp', 'storage\tmp', 'storage\uploads', 'storage\uploads\avatars', 'storage\uploads\documents', 'storage\uploads\branding', 'storage\backups')) {
    New-Item -ItemType Directory -Path (Join-Path $root $dir) -Force | Out-Null
}
Write-Ok 'storage directories are present'

if ($FixPermissions) {
    Write-Section 'Storage permissions'
    $storage = Join-Path $root 'storage'
    & icacls $storage /grant "$($env:USERNAME):(OI)(CI)M" /T /C | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Ok "granted $env:USERNAME modify access to storage" }
    else { Write-Warn 'could not grant current-user permissions with icacls' }
    & icacls $storage /grant "SYSTEM:(OI)(CI)M" /T /C | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Ok 'granted SYSTEM modify access to storage (XAMPP service mode)' }
    else { Write-Warn 'could not grant SYSTEM permissions with icacls' }
}

Write-Section 'htdocs placement'
$urlPath = Get-UrlPath $xampp
$htdocs = Join-Path $xampp 'htdocs'
if ($urlPath) {
    Write-Ok "project is reachable at $(Get-AppUrl $xampp)"
} elseif (-not (Test-Path $htdocs)) {
    Write-Warn "XAMPP htdocs directory not found: $htdocs"
} else {
    $target = Join-Path $htdocs (Split-Path -Leaf $root)
    if (Test-Path $target) {
        Write-Warn "$target already exists but does not point to this project"
        Write-Info 'remove/repoint it manually, then rerun setup'
    } else {
        & cmd.exe /c mklink /J "$target" "$root" | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Ok "created junction $target -> $root"
            $urlPath = Get-UrlPath $xampp
        } else {
            Write-Warn "could not create $target"
            Write-Info 'run an elevated Command Prompt and create the junction manually'
        }
    }
}

Write-Section 'Database status'
$status = Invoke-AppConsole $xampp 'install:status'
if ($status.ExitCode -eq 0) {
    Write-Ok 'database is reachable'
} else {
    Write-Warn 'database is not reachable yet; create it in phpMyAdmin and set DB_* in .env'
    $apachePort = Get-ApachePort $xampp
    $pma = 'http://127.0.0.1'
    if ($apachePort -ne 80) { $pma = "${pma}:$apachePort" }
    Write-Info "phpMyAdmin: $pma/phpmyadmin/"
}

Write-Section 'Next steps'
$appUrl = Get-AppUrl $xampp
Write-Info '1. Start XAMPP Apache and MySQL: scripts\xampp-start.ps1'
Write-Info "2. Open $appUrl"
Write-Info '3. Finish the browser installer (schema updates are additive).'
Write-Info '4. Verify everything: scripts\xampp-check.ps1'

if ($Script:Fails -gt 0) {
    exit 1
}
