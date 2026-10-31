# Read-only XAMPP compatibility check for School ERP on Windows.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\xampp-check.ps1
# Optional: set XAMPP_HOME, XAMPP_URL, XAMPP_STRICT=1.

. (Join-Path $PSScriptRoot 'xampp-lib.ps1')

$xampp = Find-XamppHome
if (-not $xampp) {
    Write-Fail 'XAMPP not found (set XAMPP_HOME to your XAMPP folder)'
    Show-Result
}
$php = Join-Path $xampp 'php\php.exe'
$root = $Script:ProjectRoot

Write-Section 'XAMPP'
Write-Ok "XAMPP found at $xampp"
$version = (& $php -r 'echo PHP_VERSION;').Trim()
Write-Ok "XAMPP PHP: $version"

Write-Section 'PHP 8.2+ and extensions'
& $php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'
if ($LASTEXITCODE -eq 0) {
    Write-Ok "PHP $version meets the 8.2 minimum"
} else {
    Write-Fail "PHP $version is too old; School ERP requires PHP 8.2+"
}
foreach ($ext in @('pdo', 'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'json', 'session', 'ctype', 'iconv')) {
    & $php -r "exit(extension_loaded('$ext') ? 0 : 1);"
    if ($LASTEXITCODE -eq 0) { Write-Ok "extension $ext" } else { Write-Fail "missing required extension: $ext" }
}
& $php -r 'exit(in_array("mysql", PDO::getAvailableDrivers(), true) ? 0 : 1);'
if ($LASTEXITCODE -eq 0) { Write-Ok 'PDO MySQL driver available' } else { Write-Fail 'PDO MySQL driver is not available' }
& $php -r "exit(extension_loaded('gd') ? 0 : 1);"
if ($LASTEXITCODE -eq 0) { Write-Ok 'optional extension gd (avatar thumbnails)' } else { Write-Warn 'optional extension gd is missing; avatars use the fallback' }

Write-Section 'Project and Apache'
if ((Test-Path (Join-Path $root 'public\index.php')) -and (Test-Path (Join-Path $root 'bin\console'))) {
    Write-Ok "project root: $root"
} else {
    Write-Fail "this does not look like the School ERP project root: $root"
}
if ((Test-Path (Join-Path $root '.htaccess')) -and (Test-Path (Join-Path $root 'public\.htaccess'))) {
    Write-Ok 'Apache .htaccess files are present'
} else {
    Write-Fail 'missing root or public/.htaccess'
}
$httpd = Join-Path $xampp 'apache\bin\httpd.exe'
if (Test-Path $httpd) {
    $modules = (& $httpd -M 2>&1) -join "`n"
    if ($modules -match 'rewrite_module') { Write-Ok 'Apache mod_rewrite is enabled' }
    else { Write-Warn 'could not confirm mod_rewrite; enable it in httpd.conf' }
} else {
    Write-Warn 'httpd.exe not found; cannot verify mod_rewrite'
}

Write-Section 'Application URL and subdirectory'
$urlPath = Get-UrlPath $xampp
$appUrl = Get-AppUrl $xampp
if ($urlPath) {
    Write-Ok "application path: $appUrl"
} elseif ($root.StartsWith((Join-Path $xampp 'htdocs'), [System.StringComparison]::OrdinalIgnoreCase)) {
    Write-Ok "application URL: $appUrl"
} else {
    Write-Warn "project is not under $xampp\htdocs; use a junction, Alias, or virtual host"
    Write-Info "expected layout: $xampp\htdocs\$(Split-Path -Leaf $root) -> $root"
}

Write-Section 'Environment and writable storage'
if (Test-Path (Join-Path $root '.env')) {
    Write-Ok '.env exists'
} else {
    Write-Warn '.env is missing; run scripts\xampp-setup.ps1'
}
foreach ($dir in @('storage', 'storage\logs', 'storage\cache', 'storage\sessions', 'storage\uploads', 'storage\uploads\avatars', 'storage\uploads\documents', 'storage\backups')) {
    $path = Join-Path $root $dir
    if (-not (Test-Path $path)) {
        Write-Fail "missing directory: $dir"
        continue
    }
    try {
        $probe = Join-Path $path ".write-test-$PID"
        New-Item -ItemType File -Path $probe -Force | Out-Null
        Remove-Item -LiteralPath $probe -Force
        Write-Ok "writable: $dir"
    } catch {
        Write-Fail "not writable by $env:USERNAME: $dir"
    }
}
$webUser = Get-WebServerUser $xampp
if ($webUser) {
    Write-Info "Apache httpd.conf User: $webUser"
} else {
    Write-Info 'Apache on Windows normally runs as the account that started it (or SYSTEM as a service).'
}

Write-Section 'Database'
if (Test-Path (Join-Path $root '.env')) {
    $status = Invoke-AppConsole $xampp 'install:status'
    if ($status.ExitCode -eq 0) {
        Write-Ok 'database reachable'
        foreach ($line in ($status.Output -split "`n")) {
            if ($line -match 'State\s*:|Users\s*:|Migrations pending\s*:') {
                Write-Info $line.Trim()
            }
        }
        if ($status.Output -match 'Migrations pending\s*:\s*[1-9]') {
            Write-Warn "pending migrations; back up first and run: `"$php`" bin\console upgrade"
        }
        if ($status.Output -match 'EXISTING installation') {
            Write-Ok 'existing installation detected (data will not be reset)'
        }
    } else {
        Write-Fail 'database status failed; run: php bin\console db:preflight'
        $last = ($status.Output -split "`n" | Where-Object { $_.Trim() } | Select-Object -Last 1)
        if ($last) { Write-Info $last.Trim() }
    }
} else {
    Write-Warn 'database check skipped until .env exists'
}

Write-Section 'XAMPP services'
$apachePort = Get-ApachePort $xampp
$mysqlPort = Get-MysqlPort $xampp
if (Test-ApacheRunning $xampp) {
    Write-Ok "XAMPP Apache is running (port $apachePort)"
} elseif (Test-TcpPort $apachePort) {
    Write-Fail "port $apachePort is owned by another web server, not XAMPP Apache"
} else {
    Write-Warn 'XAMPP Apache is not running; use scripts\xampp-start.ps1'
}
if (Test-MysqlRunning $xampp) {
    Write-Ok "XAMPP MySQL/MariaDB is running (port $mysqlPort)"
} elseif (Test-TcpPort $mysqlPort) {
    Write-Fail "port $mysqlPort is already used by another MySQL/MariaDB service"
    Write-Info "stop the other database service or change XAMPP's MySQL port and DB_PORT"
} else {
    Write-Warn 'XAMPP MySQL/MariaDB is not running; use scripts\xampp-start.ps1'
}

Write-Section 'PHP upload limits'
$upload = (& $php -r 'echo ini_get("upload_max_filesize");').Trim()
$post = (& $php -r 'echo ini_get("post_max_size");').Trim()
Write-Info "upload_max_filesize=$upload  post_max_size=$post"
if ($upload -eq '0' -or $post -eq '0') {
    Write-Warn 'uploads may be disabled in XAMPP php.ini'
} else {
    Write-Ok 'uploads are enabled'
}

Write-Section "HTTP checks ($appUrl)"
if (-not $urlPath -and -not $env:XAMPP_URL -and -not $root.StartsWith((Join-Path $xampp 'htdocs'), [System.StringComparison]::OrdinalIgnoreCase)) {
    Write-Warn 'HTTP checks skipped: cannot map the project to an XAMPP URL'
} else {
    if ((Get-HttpStatus $appUrl) -eq 200) { Write-Ok 'application responds with HTTP 200' }
    else { Write-Fail "application did not return HTTP 200 (got $(Get-HttpStatus $appUrl))" }

    if ((Get-HttpStatus "$($appUrl)assets/css/app.css") -eq 200) { Write-Ok 'public assets load through the subdirectory' }
    else { Write-Fail 'assets/css/app.css did not return HTTP 200' }

    if ((Get-HttpStatus "$($appUrl)login") -eq 200) { Write-Ok 'login page loads' }
    else { Write-Fail 'login page did not return HTTP 200' }

    $code = Get-HttpStatus "$($appUrl).env" $false
    if ($code -eq 200) { Write-Fail '.env is publicly downloadable' } else { Write-Ok ".env is not exposed (HTTP $code)" }

    $code = Get-HttpStatus "$($appUrl)storage/installed.lock" $false
    if ($code -eq 200) { Write-Fail 'storage/installed.lock is publicly downloadable' } else { Write-Ok "storage lock is not exposed (HTTP $code)" }

    $code = Get-HttpStatus "$($appUrl).git/config" $false
    if ($code -eq 200) { Write-Fail '.git/config is publicly downloadable' } else { Write-Ok ".git/config is not exposed (HTTP $code)" }
}

Show-Result
