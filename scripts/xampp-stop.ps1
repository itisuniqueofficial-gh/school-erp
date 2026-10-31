# Stop only XAMPP Apache + MySQL/MariaDB on Windows. System services are untouched.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\xampp-stop.ps1

. (Join-Path $PSScriptRoot 'xampp-lib.ps1')

$xampp = Find-XamppHome
if (-not $xampp) {
    Write-Host 'Error: XAMPP not found. Set XAMPP_HOME to your XAMPP folder.' -ForegroundColor Red
    exit 1
}

Write-Section 'Stopping only XAMPP services'

if (Test-ApacheRunning $xampp) {
    $stopBat = Join-Path $xampp 'apache_stop.bat'
    if (Test-Path $stopBat) {
        & cmd.exe /c $stopBat
    } else {
        $httpd = Join-Path $xampp 'apache\bin\httpd.exe'
        if (Test-Path $httpd) { & $httpd -k stop 2>&1 | Out-Null }
    }
    Start-Sleep -Seconds 1
    if (Test-ApacheRunning $xampp) { Write-Warn 'XAMPP Apache is still running; stop it from the XAMPP Control Panel' }
    else { Write-Ok 'XAMPP Apache stopped' }
} else {
    Write-Info 'XAMPP Apache is not running; nothing stopped'
}

if (Test-MysqlRunning $xampp) {
    $stopBat = Join-Path $xampp 'mysql_stop.bat'
    if (Test-Path $stopBat) {
        & cmd.exe /c $stopBat
    } else {
        $mysqlAdmin = Join-Path $xampp 'mysql\bin\mysqladmin.exe'
        if (Test-Path $mysqlAdmin) {
            & $mysqlAdmin --user=root shutdown 2>&1 | Out-Null
        }
    }
    Start-Sleep -Seconds 1
    if (Test-MysqlRunning $xampp) {
        Write-Warn 'XAMPP MySQL/MariaDB is still running'
        Write-Info 'Use the XAMPP Control Panel so MySQL can shut down cleanly; it was not force-killed.'
    } else {
        Write-Ok 'XAMPP MySQL/MariaDB stopped cleanly'
    }
} else {
    Write-Info 'XAMPP MySQL/MariaDB is not running; nothing stopped'
}

Write-Info 'system MySQL/Apache services were not touched'
