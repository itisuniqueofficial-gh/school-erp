# Show XAMPP service and School ERP database status on Windows.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\xampp-status.ps1

. (Join-Path $PSScriptRoot 'xampp-lib.ps1')

$xampp = Find-XamppHome
if (-not $xampp) {
    Write-Host 'Error: XAMPP not found. Set XAMPP_HOME to your XAMPP folder.' -ForegroundColor Red
    exit 1
}

$apachePort = Get-ApachePort $xampp
$mysqlPort = Get-MysqlPort $xampp

Write-Section 'XAMPP services'
if (Test-ApacheRunning $xampp) { Write-Ok "XAMPP Apache is running (port $apachePort)" }
elseif (Test-TcpPort $apachePort) { Write-Fail "port $apachePort is used by another web server" }
else { Write-Warn 'XAMPP Apache is not running' }

if (Test-MysqlRunning $xampp) { Write-Ok "XAMPP MySQL/MariaDB is running (port $mysqlPort)" }
elseif (Test-TcpPort $mysqlPort) { Write-Fail "port $mysqlPort is used by another MySQL/MariaDB service" }
else { Write-Warn 'XAMPP MySQL/MariaDB is not running' }

Write-Section 'Application'
Write-Info "URL: $(Get-AppUrl $xampp)"
if (Test-Path (Join-Path $Script:ProjectRoot '.env')) {
    $status = Invoke-AppConsole $xampp 'install:status'
    if ($status.ExitCode -eq 0) { Write-Host $status.Output }
    else { Write-Warn 'database status unavailable (is MySQL running and .env configured?)' }
} else {
    Write-Warn '.env is missing; run scripts\xampp-setup.ps1'
}

if ($Script:Fails -gt 0) { exit 1 }
