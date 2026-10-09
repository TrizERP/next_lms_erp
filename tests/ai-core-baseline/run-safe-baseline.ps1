<#
  Runs the PHPUnit suite WITHOUT touching any real database, cache, queue, mail server or AI
  provider, and compares the result with the recorded baseline.

  Why this exists: phpunit.xml has its sqlite lines commented out and `.env` holds the real
  database settings, so a plain `phpunit` run connects to the real database. This script
  overrides every connection setting at the process level (real environment variables win over
  `.env`), points the default connection at in-memory sqlite, makes any stray MySQL/Redis
  connection fail fast on a closed port, and blanks every AI provider key so no paid call can be
  made.

  What the baseline is: the set of tests that PASS in this environment. Many tests need the real
  schema and error here; that is expected and recorded as "not passing", not as a defect. The gate
  for a refactoring step is that nothing in `passing-tests.txt` stops passing and no new failure
  appears beyond `known-failures.txt`.

  Usage (from the repository root):
    pwsh tests/ai-core-baseline/run-safe-baseline.ps1            # compare with the baseline
    pwsh tests/ai-core-baseline/run-safe-baseline.ps1 -Update    # re-record it (review the diff!)
#>
param([switch]$Update)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$baselineDir = $PSScriptRoot
Set-Location $root

$safe = @{
  APP_ENV = 'testing'
  DB_CONNECTION = 'sqlite'; DB_DATABASE = ':memory:'
  DB_HOST = '127.0.0.1'; DB_PORT = '1'; DB_USERNAME = 'none'; DB_PASSWORD = 'none'
  REDIS_HOST = '127.0.0.1'; REDIS_PORT = '1'; REDIS_PASSWORD = ''
  CACHE_DRIVER = 'array'; QUEUE_CONNECTION = 'sync'; SESSION_DRIVER = 'array'
  MAIL_MAILER = 'array'; MAIL_DRIVER = 'array'
  GEMINI_API_KEY = ''; OPENAI_API_KEY = ''; OPENROUTER_API_KEY = ''; DEEPSEEK_API_KEY = ''; ANTHROPIC_API_KEY = ''
}
$previous = @{}
foreach ($key in $safe.Keys) {
  $previous[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
  [Environment]::SetEnvironmentVariable($key, $safe[$key], 'Process')
}

$junit = Join-Path ([IO.Path]::GetTempPath()) ("phpunit-baseline-{0}.xml" -f [guid]::NewGuid())

try {
  php vendor/bin/phpunit --log-junit $junit --no-coverage | Out-Null
  [xml]$report = Get-Content $junit

  $passing = New-Object System.Collections.Generic.List[string]
  $failing = New-Object System.Collections.Generic.List[string]

  foreach ($case in $report.SelectNodes('//testcase')) {
    $id = "{0}::{1}" -f $case.classname, $case.name
    if ($case.SelectSingleNode('failure')) { $failing.Add($id) }
    elseif (-not $case.SelectSingleNode('error') -and -not $case.SelectSingleNode('skipped')) { $passing.Add($id) }
  }

  $passing = $passing | Sort-Object -Unique
  $failing = $failing | Sort-Object -Unique

  if ($Update) {
    [IO.File]::WriteAllLines((Join-Path $baselineDir 'passing-tests.txt'), $passing)
    [IO.File]::WriteAllLines((Join-Path $baselineDir 'known-failures.txt'), $failing)
    Write-Host "Baseline re-recorded: $($passing.Count) passing, $($failing.Count) known failures."
    exit 0
  }

  $expectedPassing = Get-Content (Join-Path $baselineDir 'passing-tests.txt')
  $expectedFailing = Get-Content (Join-Path $baselineDir 'known-failures.txt')

  $lost = $expectedPassing | Where-Object { $_ -notin $passing }
  $newFailures = $failing | Where-Object { $_ -notin $expectedFailing }
  $gained = $passing | Where-Object { $_ -notin $expectedPassing }

  Write-Host "Passing: $($passing.Count) (baseline $($expectedPassing.Count))   Failing: $($failing.Count) (baseline $($expectedFailing.Count))"
  if ($gained) { Write-Host "Newly passing (fine; re-record when intended): $($gained.Count)" }

  if ($lost -or $newFailures) {
    if ($lost) { Write-Host "REGRESSION - no longer passing:"; $lost | ForEach-Object { Write-Host "  $_" } }
    if ($newFailures) { Write-Host "REGRESSION - new failures:"; $newFailures | ForEach-Object { Write-Host "  $_" } }
    exit 1
  }

  Write-Host 'Backend baseline preserved.'
  exit 0
}
finally {
  foreach ($key in $safe.Keys) { [Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process') }
  Remove-Item $junit -ErrorAction SilentlyContinue
}
