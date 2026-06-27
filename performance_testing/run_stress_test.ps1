# ConnectDRRM Stress Test Execution Script
$JMETER_PATH = "C:\Program Files\apache-jmeter-5.6.3\bin\jmeter.bat"
$TEST_FILE = "stress_test.jmx"
$RESULTS_DIR = "test_results"

# Prompt User for Parameters
$threads = Read-Host "Enter Stress Threads (Users) [default: 58]"
if ($threads -eq "") { $threads = "58" }

$rampup = Read-Host "Enter Stress Ramp-up (seconds) [default: 30]"
if ($rampup -eq "") { $rampup = "30" }

$duration = Read-Host "Enter Stress Duration (seconds) [default: 180]"
if ($duration -eq "") { $duration = "180" }

$TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
$LOG_FILE = "$RESULTS_DIR/stress_log_$TIMESTAMP.jtl"
$REPORT_DIR = "$RESULTS_DIR/stress_report_$TIMESTAMP"

if (!(Test-Path $RESULTS_DIR)) { New-Item -ItemType Directory -Path $RESULTS_DIR }

Write-Host "`n--- WARNING: Starting HIGH INTENSITY STRESS TEST ---" -ForegroundColor Yellow
Write-Host "This test will push your server to its limits. Monitor Task Manager!" -ForegroundColor White
Write-Host "Users: $threads | Ramp-up: $rampup | Duration: $duration" -ForegroundColor Red

$jmeterArgs = @(
    "-n",
    "-t", $TEST_FILE,
    "-l", $LOG_FILE,
    "-e",
    "-o", $REPORT_DIR,
    "-Jthreads=$threads",
    "-Jrampup=$rampup",
    "-Jduration=$duration"
)

& $JMETER_PATH $jmeterArgs

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n--- Stress Test Completed ---" -ForegroundColor Green
    Write-Host "View Report: $REPORT_DIR/index.html"
} else {
    Write-Host "`n--- Stress Test Stopped (Server may have reached limit) ---" -ForegroundColor Yellow
}
