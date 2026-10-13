# Local dev server for next_lms_erp with opcache switched on.
#
# `php artisan serve` runs PHP's built-in server with opcache OFF, so every request recompiles
# the whole framework: about 4 s for a request that does no database work (measured), against
# about 0.4 s with opcache on. The LMS front end fires several API calls per page, and this
# server handles one at a time, so the difference is the whole "login is slow" experience.
#
#   .\serve-fast.ps1            # port 8000
#   .\serve-fast.ps1 -Port 8001
#
# Needs a PHP with the opcache extension (Herd's php84 has it). Stop any other server already
# on the port first: two servers on one port take turns answering, and a PHP without the GD
# extension will fail some requests.
param(
    [int]$Port = 8000,
    [string]$Php = "$env:USERPROFILE\.config\herd\bin\php84\php.exe"
)

$root = $PSScriptRoot
$router = Join-Path $root 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'

if (-not (Test-Path $Php)) { $Php = (Get-Command php -ErrorAction Stop).Source }
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
    Write-Error "Port $Port is already in use. Stop that server first."
    exit 1
}

Write-Host "next_lms_erp on http://127.0.0.1:$Port (opcache on) using $Php"
Set-Location (Join-Path $root 'public')
& $Php -d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.memory_consumption=256 `
    -d opcache.max_accelerated_files=20000 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=2 `
    -S "127.0.0.1:$Port" $router
