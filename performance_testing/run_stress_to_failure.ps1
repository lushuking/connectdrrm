# ConnectDRRM Stress Test to Failure
# Orchestrates a stress test by increasing concurrent users until failure occurs

$JMETER_PATH = "C:\Program Files\apache-jmeter-5.6.3\bin\jmeter.bat"
$PHP_PATH = "C:\xampp\php\php.exe"
$BASE_DIR = "c:\xampp\htdocs\ConnectDRRM\performance_testing"
$RESULTS_ROOT = "$BASE_DIR\test_results\gradual_stress_series"

if (!(Test-Path $RESULTS_ROOT)) { New-Item -ItemType Directory -Path $RESULTS_ROOT }

# Test Parameters
$duration = 60 # 1 minute per test increment
$startThreads = 50
$increment = 50
$maxThreads = 500
$rampup = 10

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "   ConnectDRRM Stress Test to Breaking Point        " -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "This script will maintain a consistent database load" -ForegroundColor White
Write-Host "and increase concurrent users until the system breaks." -ForegroundColor White

# 1. Establish Consistent Database Load (e.g., 50,000 records)
$recordCount = 50000
Write-Host "`n>>> Establishing Consistent Database Load ($recordCount records of data)" -ForegroundColor Yellow
Write-Host "Generating synthetic data..." -ForegroundColor Gray
& $PHP_PATH "$BASE_DIR\generate_test_data.php" $recordCount

# 2. Iterate and Increase Load
$threadSequence = @(10, 20, 40, 60, 100)

foreach ($currentThreads in $threadSequence) {
    Write-Host "`n----------------------------------------------------"
    Write-Host ">>> PHASE: Testing with $currentThreads Concurrent Users" -ForegroundColor Yellow
    
    $TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
    $LOG_FILE = "$RESULTS_ROOT\stress_failure_${currentThreads}users_$TIMESTAMP.jtl"
    $REPORT_DIR = "$RESULTS_ROOT\stress_failure_report_${currentThreads}users_$TIMESTAMP"
    
    $jmeterStressArgs = @("-n", "-t", "$BASE_DIR\stress_test.jmx", "-l", $LOG_FILE, "-e", "-o", $REPORT_DIR, "-Jthreads=$currentThreads", "-Jrampup=$rampup", "-Jduration=$duration")
    
    Write-Host "Executing JMeter..." -ForegroundColor Gray
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
    
    Write-Host "Results for $currentThreads users: $errorCount errors out of $totalSamples samples ($errorRate% error rate)." -ForegroundColor Cyan
    
    if ($errorRate -gt 1.0) {
        Write-Host "`n*** BREAKING POINT REACHED! ***" -ForegroundColor Red
        Write-Host "System started failing at $currentThreads concurrent users ($errorRate% error rate)." -ForegroundColor Red
        Write-Host "Please check the report at: $REPORT_DIR to observe failure modes." -ForegroundColor White
        break
    }
}

Write-Host "`nAll specified concurrent user tiers tested!" -ForegroundColor Green

Write-Host "`n====================================================" -ForegroundColor Cyan
Write-Host "   Stress Testing Completed!                        " -ForegroundColor Cyan
Write-Host "   All results are in: $RESULTS_ROOT                " -ForegroundColor White
Write-Host "====================================================" -ForegroundColor Cyan
