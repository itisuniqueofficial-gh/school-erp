# Start only XAMPP Apache + MySQL/MariaDB on Windows.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\xampp-start.ps1

. (Join-Path $PSScriptRoot 'xampp-lib.ps1')

$xampp = Find-XamppHome
if (-not $xampp) {
    Write-Host 'Error: XAMPP not found. Set XAMPP_HOME to your XAMPP folder.' -ForegroundColor Red
    exit 1
}

Write-Section 'Starting only XAMPP services'
$mysqlPort = Get-MysqlPort $xampp
$apachePort = Get-ApachePort $xampp

if (Test-MysqlRunning $xampp) {
    Write-Ok 'XAMPP MySQL/MariaDB is already running'
} elseif (Test-TcpPort $mysqlPort) {
    Write-Fail "port $mysqlPort is already in use by another MySQL/MariaDB service"
    Write-Info 'XAMPP MySQL was NOT started. Stop the other service or change the XAMPP MySQL port and DB_PORT.'
} else {
    $mysqld = Join-Path $xampp 'mysql\bin\mysqld.exe'
    $myIni = Join-Path $xampp 'mysql\bin\my.ini'
    if (-not (Test-Path $mysqld)) {
        Write-Fail "mysqld.exe not found at $mysqld"
    } else {
        $mysqlArguments = '--standalone'
        if (Test-Path $myIni) { $mysqlArguments = "--defaults-file=`"$myIni`" --standalone" }
        try {
            Start-Process -FilePath $mysqld -ArgumentList $mysqlArguments -WorkingDirectory $xampp -WindowStyle Hidden
            $started = $false
            for ($i = 0; $i -lt 20; $i++) {
                if (Test-TcpPort $mysqlPort) { $started = $true; break }
                Start-Sleep -Seconds 1
            }
            if ($started) { Write-Ok "XAMPP MySQL/MariaDB is listening on port $mysqlPort" }
            else { Write-Warn "MySQL started but port $mysqlPort is not accepting connections yet" }
        } catch {
            Write-Fail "could not start XAMPP MySQL: $($_.Exception.Message)"
        }
    }
}

if (Test-ApacheRunning $xampp) {
    Write-Ok 'XAMPP Apache is already running'
} elseif (Test-TcpPort $apachePort) {
    Write-Fail "port $apachePort is already used by another web server"
    Write-Info 'XAMPP Apache was NOT started. Stop the other web server or change Apache Listen port.'
} else {
    $httpd = Join-Path $xampp 'apache\bin\httpd.exe'
    if (-not (Test-Path $httpd)) {
        Write-Fail "httpd.exe not found at $httpd"
    } else {
        try {
            Start-Process -FilePath $httpd -WorkingDirectory $xampp -WindowStyle Hidden
            $started = $false
            for ($i = 0; $i -lt 20; $i++) {
                if (Test-TcpPort $apachePort) { $started = $true; break }
                Start-Sleep -Seconds 1
            }
            if ($started) { Write-Ok "XAMPP Apache is listening on port $apachePort" }
            else { Write-Warn "Apache started but port $apachePort is not accepting connections yet" }
        } catch {
            Write-Fail "could not start XAMPP Apache: $($_.Exception.Message)"
        }
    }
}

Write-Section 'Application'
Write-Info "URL: $(Get-AppUrl $xampp)"
if ($Script:Fails -gt 0) { exit 1 }
