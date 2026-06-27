# ConnectDRRM User Defined Load Test Script
$JMETER_PATH = "C:\Program Files\apache-jmeter-5.6.3\bin\jmeter.bat"
$TEST_FILE = "load_test.jmx"
$RESULTS_DIR = "test_results"

# Prompt User for Parameters
$threads = Read-Host "Enter number of users (threads) [default: 10]"
if ($threads -eq "") { $threads = "10" }

$rampup = Read-Host "Enter ramp-up period in seconds [default: 20]"
if ($rampup -eq "") { $rampup = "20" }

$duration = Read-Host "Enter duration in seconds [default: 120]"
if ($duration -eq "") { $duration = "120" }

$TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
$LOG_FILE = "$RESULTS_DIR/load_log_$TIMESTAMP.jtl"
$REPORT_DIR = "$RESULTS_DIR/load_report_$TIMESTAMP"

if (!(Test-Path $RESULTS_DIR)) { New-Item -ItemType Directory -Path $RESULTS_DIR }

Write-Host "`n--- Running Load Test with User-Defined Parameters ---" -ForegroundColor Cyan
Write-Host "Users: $threads | Ramp-up: $rampup | Duration: $duration" -ForegroundColor White

# Run JMeter with Properties
# Using explicit array for arguments to avoid PowerShell parsing issues
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

Write-Host "Executing: & $JMETER_PATH $($jmeterArgs -join ' ')" -ForegroundColor Gray

& $JMETER_PATH $jmeterArgs

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n--- Load Test Completed Successfully ---" -ForegroundColor Green
    Write-Host "View Report: $REPORT_DIR/index.html"
} else {
    Write-Host "`n--- Load Test Failed ---" -ForegroundColor Red
    Write-Host "If the report failed to generate, check if 'test_users.csv' exists and is correct."
}
