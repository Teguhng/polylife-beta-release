$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
$phpPath = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
$logPath = Join-Path $projectRoot 'storage\logs\ai-worker.log'
$mutex = [System.Threading.Mutex]::new($false, 'Local\PolyLifeAiQueueWorker')
$ownsMutex = $false

try {
    try {
        $ownsMutex = $mutex.WaitOne(0)
    } catch [System.Threading.AbandonedMutexException] {
        $ownsMutex = $true
    }

    if (-not $ownsMutex) {
        exit 0
    }

    if (-not (Test-Path -LiteralPath $phpPath)) {
        "$(Get-Date -Format o) PHP tidak ditemukan di $phpPath" | Add-Content -LiteralPath $logPath
        exit 1
    }

    Set-Location -LiteralPath $projectRoot
    while ($true) {
        "$(Get-Date -Format o) Worker AI dimulai." | Add-Content -LiteralPath $logPath
        & $phpPath artisan ai:work *>> $logPath
        $workerExitCode = $LASTEXITCODE
        "$(Get-Date -Format o) Worker AI berhenti (kode $workerExitCode); mencoba mulai ulang dalam 5 detik." | Add-Content -LiteralPath $logPath
        Start-Sleep -Seconds 5
    }
} finally {
    if ($ownsMutex) {
        $mutex.ReleaseMutex()
    }
    $mutex.Dispose()
}
