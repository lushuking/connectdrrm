# ConnectDRRM JMeter Execution Script
# This script runs the calibration test and generates a HTML report.

$JMETER_PATH = "C:\Program Files\apache-jmeter-5.6.3\bin\jmeter.bat"
$TEST_FILE = "calibration_test.jmx"
$RESULTS_DIR = "test_results"
$TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
$LOG_FILE = "$RESULTS_DIR/log_$TIMESTAMP.jtl"
$REPORT_DIR = "$RESULTS_DIR/report_$TIMESTAMP"

# Create results directory if not exists
if (!(Test-Path $RESULTS_DIR)) {
    New-Item -ItemType Directory -Path $RESULTS_DIR
}

# Verify JMeter Path
if (!(Test-Path $JMETER_PATH)) {
    Write-Host "ERROR: JMeter not found at $JMETER_PATH" -ForegroundColor Red
    Write-Host "Please check the path in run_calibration.ps1"
    exit
}

Write-Host "--- Starting ConnectDRRM Calibration Test (Scaling to 56 Users) ---" -ForegroundColor Cyan
Write-Host "Test File: $TEST_FILE"
Write-Host "Output Log: $LOG_FILE"
Write-Host "Report Dir: $REPORT_DIR"

# Run JMeter in Non-GUI mode
# -n: non-GUI mode
# -t: test file
# -l: log file
# -e: generate report dashboard
# -o: output folder for report
& $JMETER_PATH -n -t $TEST_FILE -l $LOG_FILE -e -o $REPORT_DIR

if ($LASTEXITCODE -eq 0) {
    Write-Host "--- Test Completed Successfully ---" -ForegroundColor Green
    Write-Host "You can view the report by opening: $REPORT_DIR/index.html"
} else {
    Write-Host "--- Test Failed or Interrupted ---" -ForegroundColor Red
}
