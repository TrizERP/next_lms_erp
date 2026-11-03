<#
  Local development: keep the Course Presentation background worker running.

    .\dev-course-presentation.ps1            # start the supervisor in this window (Ctrl+C stops it)
    .\dev-course-presentation.ps1 -Hidden    # start it in the background, no window
    .\dev-course-presentation.ps1 -Stop      # stop a copy that is running (also the hidden one)
    .\dev-course-presentation.ps1 -Status    # say whether it is running
    .\dev-course-presentation.ps1 -DryRun    # print what would start; start nothing

  Why this exists: "Create" on the Classroom Presentation card queues a draft on the `course-presentation` queue, and only a
  separate worker process runs it. With nothing running the draft waits and, 30 minutes later, shows "The generation worker
  did not start within 30 minutes". A worker started by hand also stops after an hour (--max-time) and, when any file of the
  generation code is edited, refuses new drafts until it is restarted. This script is that worker, started for you and kept alive.

  What it runs:
    php artisan queue:listen course_presentation --queue=course-presentation --sleep=3 --tries=1 --timeout=7200
  `queue:listen` starts a fresh process for every job, so every draft runs the code as it is on disk NOW. That is why a
  code edit never makes the worker "too old", and why no hourly restart is needed. It is restarted if it ever exits.

  If a course-presentation worker is already running (started by hand), this script does NOT start a second one. It waits and
  takes over when that one exits, so a running draft is never interrupted.

  It does not touch the `default` queue or the question-papers worker (see dev-question-papers.ps1).

  DATABASE NOTE: the worker uses the database in .env. If that is the shared database, a draft is processed and its model
  calls are made against that setup, exactly as when you press Create.

  One supervisor per checkout (a PID file in storage\framework). Starting it twice is refused.
#>
param(
    [switch]$Stop,
    [switch]$Status,
    [switch]$DryRun,
    [switch]$Hidden,
    [string]$Php = '',
    [string]$Connection = 'course_presentation',
    [string]$Queue = 'course-presentation'
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$stateFile = Join-Path $root 'storage\framework\dev-course-presentation.json'
$logFile = Join-Path $root 'storage\logs\course-presentation-worker.log'
if (-not $Php) { $Php = (Get-Command php -ErrorAction Stop).Source }

function Test-Alive([int]$processId) {
    if ($processId -le 0) { return $false }
    return [bool](Get-Process -Id $processId -ErrorAction SilentlyContinue)
}

function Stop-Tree([int]$processId) {
    if (Test-Alive $processId) { & taskkill /PID $processId /T /F | Out-Null }
}

function Read-State {
    if (-not (Test-Path $stateFile)) { return $null }
    try { return Get-Content $stateFile -Raw | ConvertFrom-Json } catch { return $null }
}

# A worker (or listener) for this queue that is already running, started by hand or by another copy. The short-lived
# `queue:work --once` children of OUR listener are not counted.
function Find-Worker {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object {
        $_.CommandLine -match 'queue:(work|listen)' -and
        $_.CommandLine -notmatch '--once' -and
        $_.CommandLine -match [regex]::Escape($Connection) -and
        $_.CommandLine -match [regex]::Escape($Queue)
    } | Select-Object -First 1
}

$state = Read-State

if ($Status) {
    if ($state -and (Test-Alive ([int]$state.supervisor))) { Write-Host "Running: supervisor pid $($state.supervisor), listener pid $($state.listener)."; exit 0 }
    $w = Find-Worker
    if ($w) { Write-Host "A worker for $Queue is running (pid $($w.ProcessId)) but not under this supervisor." } else { Write-Host "No worker for $Queue is running. Drafts will wait until one starts." }
    exit 0
}

if ($Stop) {
    if ($state) { Stop-Tree ([int]$state.listener); Stop-Tree ([int]$state.supervisor); Remove-Item $stateFile -ErrorAction SilentlyContinue; Write-Host 'Stopped.' } else { Write-Host 'No supervisor is running.' }
    exit 0
}

if ($state -and (Test-Alive ([int]$state.supervisor)) -and ([int]$state.supervisor -ne $PID)) {
    Write-Host "Already running (supervisor pid $($state.supervisor)). Use -Stop to stop it."
    exit 1
}

$listenArgs = @('artisan', 'queue:listen', $Connection, "--queue=$Queue", '--sleep=3', '--tries=1', '--timeout=7200')

if ($DryRun) {
    Write-Host "Would run (in $root): $Php $($listenArgs -join ' ')"
    $w = Find-Worker
    if ($w) { Write-Host "A worker for $Queue is already running (pid $($w.ProcessId)); it would wait and take over when that one exits." }
    exit 0
}

if ($Hidden) {
    $self = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ('"' + $PSCommandPath + '"'), '-Php', ('"' + $Php + '"'), '-Connection', $Connection, '-Queue', $Queue)
    $p = Start-Process -FilePath 'powershell.exe' -ArgumentList $self -WindowStyle Hidden -PassThru
    Write-Host "Started in the background (pid $($p.Id)). Stop it with: .\dev-course-presentation.ps1 -Stop"
    exit 0
}

New-Item -ItemType Directory -Force -Path (Split-Path $stateFile), (Split-Path $logFile) | Out-Null
$listener = 0
function Save-State { (@{ supervisor = $PID; listener = $listener; root = $root; started = (Get-Date).ToString('s') } | ConvertTo-Json) | Set-Content $stateFile }
Save-State
Write-Host "Course Presentation worker supervisor running (pid $PID). Ctrl+C or -Stop to end. Log: $logFile"

try {
    while ($true) {
        $existing = Find-Worker
        if ($existing) {
            Write-Host "[$(Get-Date -Format T)] a worker for $Queue is already running (pid $($existing.ProcessId)); waiting to take over when it exits."
            Start-Sleep -Seconds 10
            continue
        }
        Write-Host "[$(Get-Date -Format T)] starting: queue:listen $Connection --queue=$Queue"
        $p = Start-Process -FilePath $Php -ArgumentList $listenArgs -WorkingDirectory $root -WindowStyle Hidden -PassThru -RedirectStandardOutput $logFile -RedirectStandardError "$logFile.err"
        $listener = $p.Id
        Save-State
        Wait-Process -Id $p.Id -ErrorAction SilentlyContinue
        Write-Host "[$(Get-Date -Format T)] the listener exited; restarting in 3 s."
        $listener = 0
        Save-State
        Start-Sleep -Seconds 3
    }
}
finally {
    if ($listener -gt 0) { Stop-Tree $listener }
    Remove-Item $stateFile -ErrorAction SilentlyContinue
}
