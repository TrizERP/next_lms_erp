<#
  Local development: start the backend AND the Question Paper background processing together.

    .\dev-question-papers.ps1            # server on :8000 + question-papers worker + 60 s recovery dispatcher
    .\dev-question-papers.ps1 -Port 8001
    .\dev-question-papers.ps1 -NoServer  # you already run `artisan serve` / serve-fast.ps1: start only the worker
    .\dev-question-papers.ps1 -Stop      # stop a copy that is running somewhere else
    .\dev-question-papers.ps1 -DryRun    # print what would start; start nothing

  Why this exists: `php artisan serve` only answers HTTP. A Question Paper upload is stored and queued, and the
  queue is drained by a separate `queue:work` process, so with only `serve` running every upload sits at
  "Waiting to start" for ever. This script is that process, started for you, kept alive, and stopped with the server.

  What it runs (and nothing else):
    1. php artisan serve                                  (skipped with -NoServer, or when the port already answers)
    2. php artisan queue:work question_papers --queue=question-papers --sleep=3 --tries=6 --timeout=900 --max-time=3600
         restarted if it exits or crashes (--max-time makes it exit hourly on purpose)
    3. php artisan idms:dispatch-question-papers every 60 s   (the recovery pass: re-queues lost or stalled papers)

  What it deliberately does NOT run:
    - a worker for the `default` queue (other features' jobs, e.g. course presentations, stay untouched)
    - `schedule:work` / the full scheduler (that would also run every unrelated scheduled task - notifications,
      Neo4j, alumni, PAL tagging - against whatever database your .env points at)

  Duplicates: one supervisor per checkout (a PID file in storage\framework). Starting it twice is refused, and a
  worker for this checkout that is already running is detected and reused, never doubled.

  Stopping: Ctrl+C in this window (or -Stop from another) ends the server, the worker and the dispatcher, including
  the child processes they started.

  DATABASE WARNING: the worker uses the database in .env. If that is the shared database, a queued paper IS processed
  and writes question rows there. Point .env (or the DB_* environment variables of this shell) at a disposable
  database first if that is not what you want.
#>
param(
    [int]$Port = 8000,
    [switch]$NoServer,
    [switch]$Stop,
    [switch]$DryRun,
    [string]$Php = '',
    [string]$StateDirectory = ''
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$artisan = Join-Path $root 'artisan'
$configuredStateDir = if ($StateDirectory) { $StateDirectory } else { $env:QUESTION_PAPER_WORKER_STATE_DIR }
$stateDir = if ($configuredStateDir) { [IO.Path]::GetFullPath($configuredStateDir) } else { Join-Path $root 'storage\framework' }
$env:QUESTION_PAPER_WORKER_STATE_DIR = $stateDir
$logDir = if ($configuredStateDir) { Join-Path $stateDir 'logs' } else { Join-Path $root 'storage\logs' }
$pidFile = Join-Path $stateDir 'dev-question-papers.json'
$stopFlag = Join-Path $stateDir 'question-papers-worker.stop'
if (-not $Php) { $Php = $env:QUESTION_PAPER_PHP_CLI }
if (-not $Php) { $Php = (Get-Command php -ErrorAction Stop).Source }

function Quote([string]$value) { '"' + $value + '"' }

function Test-Alive([int]$processId) {
    if ($processId -le 0) { return $false }
    return [bool](Get-Process -Id $processId -ErrorAction SilentlyContinue)
}

# Kill a process and everything it started (artisan serve spawns its own php).
function Stop-Tree([int]$processId) {
    if (-not (Test-Alive $processId)) { return }
    # A process that exits between the check and the kill (its parent's tree was just killed) is not an error. Under
    # $ErrorActionPreference = 'Stop', taskkill's "process not found" would otherwise abort the whole stop and leave the PID file.
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'SilentlyContinue'
    try { & taskkill.exe /PID $processId /T /F 2>&1 | Out-Null } finally { $ErrorActionPreference = $previous }
}

# A question-papers worker of THIS checkout, started by anyone (this script, a terminal, another copy).
function Get-OwnWorkers {
    $needle = '*' + $artisan + '*queue:work*question_papers*'
    try {
        $ids = @(Get-Process -Name php -ErrorAction SilentlyContinue | ForEach-Object { $_.Id })
        if ($ids.Count -eq 0) { return @() }
        $filter = ($ids | ForEach-Object { "ProcessId=$_" }) -join ' or '
        return @(Get-CimInstance Win32_Process -Filter $filter -ErrorAction Stop |
            Where-Object { $_.CommandLine -like $needle })
    } catch {
        try {
            $csv = & wmic process where "name='php.exe'" get CommandLine,ProcessId /format:csv 2>$null
            $found = @()
            foreach ($line in ($csv -split "`r?`n")) {
                $trimmed = $line.Trim()
                if ($trimmed -match '^[^,]*,([^,].*),(\d+)\s*$') {
                    $cmd = $Matches[1]
                    $pidVal = [int]$Matches[2]
                    if ($cmd -like $needle) {
                        $found += [PSCustomObject]@{ ProcessId = $pidVal; CommandLine = $cmd }
                    }
                }
            }
            return $found
        } catch {
            return @()
        }
    }
}

function Read-PidFile {
    if (-not (Test-Path $pidFile)) { return $null }
    try { return Get-Content $pidFile -Raw | ConvertFrom-Json } catch { return $null }
}

function Start-Child([string]$name, [string[]]$arguments) {
    $out = Join-Path $logDir "dev-$name.log"
    $err = Join-Path $logDir "dev-$name.err.log"
    return Start-Process -FilePath $Php -ArgumentList $arguments -WorkingDirectory $root -PassThru -WindowStyle Hidden `
        -RedirectStandardOutput $out -RedirectStandardError $err
}

$workerArgs = @((Quote $artisan), 'queue:work', 'question_papers', '--queue=question-papers', '--sleep=3', '--tries=6', '--timeout=900', '--max-time=3600')
$serveArgs = @((Quote $artisan), 'serve', "--port=$Port")
$dispatchArgs = @((Quote $artisan), 'idms:dispatch-question-papers')

# ---- -Stop: end a supervisor running elsewhere ----
if ($Stop) {
    $state = Read-PidFile
    try {
        if ($state) {
            foreach ($id in @($state.supervisor, $state.worker, $state.server)) { if ($id) { Stop-Tree ([int]$id) } }
        }
        foreach ($w in Get-OwnWorkers) { Stop-Tree ([int]$w.ProcessId) }
    }
    finally {
        # Whatever happened above, the record must not outlive the processes it describes.
        Remove-Item $pidFile -ErrorAction SilentlyContinue
    }
    Write-Host 'Stopped the question-papers development processes for this checkout.'
    exit 0
}

# ---- one supervisor per checkout ----
$state = Read-PidFile
if ($state -and (Test-Alive ([int]$state.supervisor)) -and ([int]$state.supervisor -ne $PID)) {
    Write-Error "Already running (supervisor pid $($state.supervisor)). Stop it first with: .\dev-question-papers.ps1 -Stop"
    exit 1
}

$existingWorker = @(Get-OwnWorkers)
$serverUp = [bool](Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
$startServer = (-not $NoServer) -and (-not $serverUp)

if ($DryRun) {
    Write-Host "php:        $Php"
    Write-Host "checkout:   $root"
    Write-Host ("server:     " + $(if ($startServer) { "start  php $($serveArgs -join ' ')" } elseif ($NoServer) { 'skipped (-NoServer)' } else { "port $Port already listening; reuse it" }))
    Write-Host ("worker:     " + $(if ($existingWorker.Count -gt 0) { "already running (pid $($existingWorker[0].ProcessId)); reuse it, start nothing" } else { "start  php $($workerArgs -join ' ')" }))
    Write-Host "dispatcher: every 60 s  php $($dispatchArgs -join ' ')"
    Write-Host 'default queue worker: never started.  Full task scheduler: never started (only the recovery dispatcher above).'
    exit 0
}

New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$children = @{ server = $null; worker = $null }
$ownsWorker = $false
$restarts = 0
$nextDispatch = Get-Date

try {
    if ($startServer) {
        $children.server = Start-Child 'server' $serveArgs
        Write-Host "server       pid $($children.server.Id)  http://127.0.0.1:$Port"
    } else {
        Write-Host "server       not started ($(if ($NoServer) { '-NoServer' } else { "port $Port already listening" }))"
    }

    if ($existingWorker.Count -gt 0) {
        Write-Host "worker       already running (pid $($existingWorker[0].ProcessId)); reusing it"
    } else {
        $children.worker = Start-Child 'worker' $workerArgs
        $ownsWorker = $true
        Write-Host "worker       pid $($children.worker.Id)  queue:work question_papers --queue=question-papers"
    }

    @{ supervisor = $PID; server = $(if ($children.server) { $children.server.Id } else { 0 }); worker = $(if ($children.worker) { $children.worker.Id } else { 0 }) } |
        ConvertTo-Json | Set-Content $pidFile
    Write-Host "dispatcher   every 60 s (idms:dispatch-question-papers)"
    Write-Host "logs         $logDir\dev-*.log     stop: Ctrl+C"

    $workerStartTime = if ($children.worker) { Get-Date } else { $null }

    while ($true) {
        try {
            Start-Sleep -Seconds 2

            # Keep OUR worker alive. --max-time ends it hourly on purpose; a crash is restarted with a growing pause.
            # EXCEPT while an operator has stopped it from the Document Library (the stop flag): that Stop must stay stopped until
            # Start worker is pressed, which removes the flag, and the next pass then brings the worker back.
            $stoppedByOperator = Test-Path $stopFlag
            $workerExited = $false
            try { $workerExited = $ownsWorker -and $children.worker -and $children.worker.HasExited } catch { }
            if ($workerExited -and -not $stoppedByOperator) {
                $exitCode = try { $children.worker.ExitCode } catch { $null }
                $ranSeconds = if ($workerStartTime) { ((Get-Date) - $workerStartTime).TotalSeconds } else { 0 }
                if ($ranSeconds -ge 60 -or ($exitCode -ne $null -and $exitCode -eq 0)) {
                    $restarts = 0
                    $pause = 1
                } else {
                    $restarts++
                    $pause = [Math]::Min(30, [Math]::Pow(2, [Math]::Min($restarts, 5)))
                }
                Write-Host ("{0} worker exited (code {1}, ran {2:N0}s); restarting in {3}s" -f (Get-Date -Format HH:mm:ss), $exitCode, $ranSeconds, $pause)
                Start-Sleep -Seconds $pause
                try {
                    $children.worker = Start-Child 'worker' $workerArgs
                    $workerStartTime = Get-Date
                    try {
                        $currState = Read-PidFile
                        if ($currState) {
                            $currState.worker = $children.worker.Id
                            $currState | ConvertTo-Json | Set-Content $pidFile
                        }
                    } catch { }
                } catch {
                    Write-Warning "Could not restart worker: $_"
                }
            }

            if ((Get-Date) -ge $nextDispatch) {
                $nextDispatch = (Get-Date).AddSeconds(60)
                try { & $Php $artisan idms:dispatch-question-papers 2>&1 | Out-File (Join-Path $logDir 'dev-dispatch.log') -Append -Encoding utf8 } catch { }
            }
        } catch {
            Write-Warning "Supervisor loop encountered error: $_"
            Start-Sleep -Seconds 2
        }
    }
}
finally {
    Write-Host 'Stopping...'
    foreach ($name in @('worker', 'server')) {
        if ($children[$name]) { Stop-Tree ([int]$children[$name].Id) }
    }
    Remove-Item $pidFile -ErrorAction SilentlyContinue
}
