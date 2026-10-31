# Shared helpers for the Windows XAMPP scripts. Dot-source this file.
# Compatible with Windows PowerShell 5.1 and PowerShell 7+.

$ErrorActionPreference = 'Stop'
$Script:ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path.TrimEnd('\')
$Script:Fails = 0
$Script:Warnings = 0

function Write-Ok([string]$Message) { Write-Host "  OK    $Message" -ForegroundColor Green }
function Write-Warn([string]$Message) {
    $Script:Warnings++
    Write-Host "  WARN  $Message" -ForegroundColor Yellow
}
function Write-Fail([string]$Message) {
    $Script:Fails++
    Write-Host "  FAIL  $Message" -ForegroundColor Red
}
function Write-Info([string]$Message) { Write-Host "  INFO  $Message" -ForegroundColor Cyan }
function Write-Section([string]$Message) { Write-Host "`n== $Message ==" -ForegroundColor White }

function Find-XamppHome {
    $candidates = @()
    if ($env:XAMPP_HOME) { $candidates += $env:XAMPP_HOME }
    $candidates += @(
        'C:\xampp',
        'D:\xampp',
        'C:\Program Files\xampp',
        'C:\Program Files (x86)\xampp'
    )
    foreach ($candidate in $candidates) {
        if ([string]::IsNullOrWhiteSpace($candidate)) { continue }
        $php = Join-Path $candidate 'php\php.exe'
        if (Test-Path $php) {
            return (Resolve-Path $candidate).Path.TrimEnd('\')
        }
    }
    return $null
}

function Get-XamppPath([string]$XamppHome, [string[]]$RelativePaths) {
    foreach ($relative in $RelativePaths) {
        $candidate = Join-Path $XamppHome $relative
        if (Test-Path $candidate) { return $candidate }
    }
    return $null
}

function Get-ApachePort([string]$XamppHome) {
    $conf = Get-XamppPath $XamppHome @('apache\conf\httpd.conf', 'etc\httpd.conf')
    if ($conf) {
        $match = Select-String -Path $conf -Pattern '^\s*Listen\s+(\d+)\s*$' | Select-Object -First 1
        if ($match) { return [int]$match.Matches[0].Groups[1].Value }
    }
    return 80
}

function Get-MysqlPort([string]$XamppHome) {
    $conf = Get-XamppPath $XamppHome @('mysql\bin\my.ini', 'etc\my.cnf')
    if ($conf) {
        $match = Select-String -Path $conf -Pattern '^\s*port\s*=\s*(\d+)' | Select-Object -First 1
        if ($match) { return [int]$match.Matches[0].Groups[1].Value }
    }
    return 3306
}

function Test-TcpPort([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if ($async.AsyncWaitHandle.WaitOne(1000)) {
            $client.EndConnect($async)
            return $true
        }
        return $false
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Get-XamppProcesses([string]$XamppHome, [string]$NamePattern) {
    $result = @()
    foreach ($process in @(Get-Process -Name $NamePattern -ErrorAction SilentlyContinue)) {
        try {
            if ($process.Path -and $process.Path.StartsWith($XamppHome, [System.StringComparison]::OrdinalIgnoreCase)) {
                $result += $process
            }
        } catch {
            # Process path can be unavailable without elevation; ignore.
        }
    }
    return $result
}

function Test-ApacheRunning([string]$XamppHome) {
    return @(Get-XamppProcesses $XamppHome 'httpd').Count -gt 0
}

function Test-MysqlRunning([string]$XamppHome) {
    return @(Get-XamppProcesses $XamppHome 'mysqld').Count -gt 0
}

function Get-UrlPath([string]$XamppHome) {
    $htdocs = Join-Path $XamppHome 'htdocs'
    $root = $Script:ProjectRoot
    if (-not (Test-Path $htdocs)) { return '' }

    $htdocsFull = (Resolve-Path $htdocs).Path.TrimEnd('\')
    if ($root.Equals($htdocsFull, [System.StringComparison]::OrdinalIgnoreCase)) { return '' }
    if ($root.StartsWith($htdocsFull + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        return '/' + $root.Substring($htdocsFull.Length + 1).Replace('\', '/')
    }

    foreach ($entry in @(Get-ChildItem -LiteralPath $htdocs -Force -ErrorAction SilentlyContinue)) {
        if ($entry.LinkType) {
            try {
                $target = (Resolve-Path -LiteralPath $entry.FullName).Path.TrimEnd('\')
                if ($target.Equals($root, [System.StringComparison]::OrdinalIgnoreCase)) {
                    return '/' + $entry.Name
                }
            } catch { }
        }
    }
    return ''
}

function Get-AppUrl([string]$XamppHome) {
    if ($env:XAMPP_URL) {
        return $env:XAMPP_URL.TrimEnd('/') + '/'
    }
    $port = Get-ApachePort $XamppHome
    $urlPath = Get-UrlPath $XamppHome
    $base = 'http://127.0.0.1'
    if ($port -ne 80) { $base = "${base}:$port" }
    return "$base$urlPath/"
}

function Get-HttpStatus([string]$Url, [bool]$FollowRedirects = $true) {
    try {
        $params = @{
            Uri = $Url
            UseBasicParsing = $true
            TimeoutSec = 10
            ErrorAction = 'Stop'
        }
        if ($FollowRedirects) {
            $params.MaximumRedirection = 5
        } else {
            $params.MaximumRedirection = 0
        }
        $response = Invoke-WebRequest @params
        return [int]$response.StatusCode
    } catch {
        if ($_.Exception.Response) {
            try { return [int]$_.Exception.Response.StatusCode } catch { }
        }
        return 0
    }
}

function Get-WebServerUser([string]$XamppHome) {
    $conf = Get-XamppPath $XamppHome @('apache\conf\httpd.conf', 'etc\httpd.conf')
    if (-not $conf) { return $null }
    $match = Select-String -Path $conf -Pattern '^\s*User\s+(\S+)' | Select-Object -First 1
    if ($match) { return $match.Matches[0].Groups[1].Value }
    return $null
}

function Invoke-AppConsole([string]$XamppHome, [string]$Arguments) {
    $php = Join-Path $XamppHome 'php\php.exe'
    Push-Location $Script:ProjectRoot
    try {
        $output = & $php 'bin\console' $Arguments 2>&1
        return @{ ExitCode = $LASTEXITCODE; Output = ($output -join "`n") }
    } finally {
        Pop-Location
    }
}

function Show-Result {
    Write-Section 'Result'
    if ($Script:Fails -gt 0) {
        Write-Host "$($Script:Fails) failure(s), $($Script:Warnings) warning(s)." -ForegroundColor Red
        exit 1
    }
    if ($env:XAMPP_STRICT -eq '1' -and $Script:Warnings -gt 0) {
        Write-Host "$($Script:Warnings) warning(s) treated as failures (XAMPP_STRICT=1)." -ForegroundColor Red
        exit 1
    }
    Write-Host "XAMPP check passed with $($Script:Warnings) warning(s)." -ForegroundColor Green
}
