# ConnectDRRM Database Scale to Failure
# Orchestrates a stress test by increasing the database size until failure occurs

$JMETER_PATH = "C:\Program Files\apache-jmeter-5.6.3\bin\jmeter.bat"
$PHP_PATH = "C:\xampp\php\php.exe"
$BASE_DIR = "c:\xampp\htdocs\ConnectDRRM\performance_testing"
$RESULTS_ROOT = "$BASE_DIR\test_results\data_scale_failure_series"

if (!(Test-Path $RESULTS_ROOT)) { New-Item -ItemType Directory -Path $RESULTS_ROOT }

# Test Parameters
$duration = 60 # 1 minute per test increment
$threads = 50  # Constant high traffic
$rampup = 10

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "   ConnectDRRM Database Scale to Breaking Point     " -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "This script will maintain a constant high traffic of $threads users" -ForegroundColor White
Write-Host "while multiplying the database records until the system crashes." -ForegroundColor White

# Define the sequence of database records to test
$recordSequence = @(5000, 10000, 30000, 50000, 100000, 200000, 500000, 1000000)

foreach ($currentRecords in $recordSequence) {
    Write-Host "`n----------------------------------------------------"
    Write-Host ">>> PHASE: Injecting $currentRecords Total Database Records" -ForegroundColor Yellow
    
    # 1. Generate Data
    Write-Host "Generating synthetic data ($currentRecords records)..." -ForegroundColor Gray
    & $PHP_PATH "$BASE_DIR\generate_test_data.php" $currentRecords
    
    $TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
    $LOG_FILE = "$RESULTS_ROOT\scale_failure_${currentRecords}records_$TIMESTAMP.jtl"
    $REPORT_DIR = "$RESULTS_ROOT\scale_failure_report_${currentRecords}records_$TIMESTAMP"
    
    $jmeterStressArgs = @("-n", "-t", "$BASE_DIR\stress_test.jmx", "-l", $LOG_FILE, "-e", "-o", $REPORT_DIR, "-Jthreads=$threads", "-Jrampup=$rampup", "-Jduration=$duration")
    
    Write-Host "Executing JMeter Traffic Attack..." -ForegroundColor Gray
    & $JMETER_PATH $jmeterStressArgs
    
    # Check JTL file for errors (HTTP response codes that are not 200 or 302)
    $errorCount = 0
    $totalSamples = 0
    if (Test-Path $LOG_FILE) {
        $lines = Get-Content $LOG_FILE | Select-Object -Skip 1 # Skip header
        foreach ($line in $lines) {
            $fields = $line -split ','
            if ($fields.Length -ge 8) {
                $totalSamples++
                $success = $fields[7]
                if ($success -eq 'false') {
                    $errorCount++
                }
            }
        }
    }
    
    $errorRate = 0
    if ($totalSamples -gt 0) {
        $errorRate = [math]::Round(($errorCount / $totalSamples) * 100, 2)
    }
    
    Write-Host "Results for $currentRecords records: $errorCount errors out of $totalSamples samples ($errorRate% error rate)." -ForegroundColor Cyan
    
    if ($errorRate -gt 1.0) {
        Write-Host "`n*** BREAKING POINT REACHED! ***" -ForegroundColor Red
        Write-Host "System started failing when the database reached $currentRecords records ($errorRate% error rate)." -ForegroundColor Red
        Write-Host "Please check the report at: $REPORT_DIR to observe failure modes." -ForegroundColor White
        break
    }
}

Write-Host "`n====================================================" -ForegroundColor Cyan
Write-Host "   Database Scale Testing Completed!                " -ForegroundColor Cyan
Write-Host "   All results are in: $RESULTS_ROOT                " -ForegroundColor White
Write-Host "====================================================" -ForegroundColor Cyan
