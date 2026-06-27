# ConnectDRRM Incremental Benchmarking Series (1yr to 3yrs)
# Orchestrates data generation and test execution across multiple data volumes.

$JMETER_PATH = "C:\Program Files\apache-jmeter-5.6.3\bin\jmeter.bat"
$PHP_PATH = "C:\xampp\php\php.exe"
$BASE_DIR = "c:\xampp\htdocs\ConnectDRRM\performance_testing"
$RESULTS_LOAD = "$BASE_DIR\test_results\load_benchmarking_series"
$RESULTS_STRESS = "$BASE_DIR\test_results\stress_benchmarking_series"

if (!(Test-Path $RESULTS_LOAD)) { New-Item -ItemType Directory -Path $RESULTS_LOAD }
if (!(Test-Path $RESULTS_STRESS)) { New-Item -ItemType Directory -Path $RESULTS_STRESS }

# Define the data volume milestones (in total records)
$milestones = @(5000, 10000, 30000, 50000)

# Test Parameters
$loadThreads = 20
$stressThreads = 60
$duration = 120 # 2 minutes per test

Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "   ConnectDRRM Incremental Benchmarking Series      " -ForegroundColor Cyan
Write-Host "====================================================" -ForegroundColor Cyan
Write-Host "This script will generate exact data milestones and run tests." -ForegroundColor White

foreach ($m in $milestones) {
    Write-Host "`n>>> PHASE: Testing with $m Total Records" -ForegroundColor Yellow
    Write-Host "----------------------------------------------------"
    
    # 1. Generate Data
    Write-Host "Generating synthetic data ($m records)..." -ForegroundColor Gray
    & $PHP_PATH "$BASE_DIR\generate_test_data.php" $m
    
    # 2. Run Load Test
    Write-Host "Running Load Test ($loadThreads users)..." -ForegroundColor Cyan
    $TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
    $LOG_FILE = "$RESULTS_LOAD\load_${m}records_$TIMESTAMP.jtl"
    $REPORT_DIR = "$RESULTS_LOAD\load_report_${m}records_$TIMESTAMP"
    
    $jmeterLoadArgs = @("-n", "-t", "$BASE_DIR\load_test.jmx", "-l", $LOG_FILE, "-e", "-o", $REPORT_DIR, "-Jthreads=$loadThreads", "-Jrampup=20", "-Jduration=$duration")
    & $JMETER_PATH $jmeterLoadArgs
    
    # 3. Run Stress Test
    Write-Host "Running Stress Test ($stressThreads users)..." -ForegroundColor Red
    $LOG_FILE_STRESS = "$RESULTS_STRESS\stress_${m}records_$TIMESTAMP.jtl"
    $REPORT_DIR_STRESS = "$RESULTS_STRESS\stress_report_${m}records_$TIMESTAMP"
    
    $jmeterStressArgs = @("-n", "-t", "$BASE_DIR\stress_test.jmx", "-l", $LOG_FILE_STRESS, "-e", "-o", $REPORT_DIR_STRESS, "-Jthreads=$stressThreads", "-Jrampup=30", "-Jduration=$duration")
    & $JMETER_PATH $jmeterStressArgs
    
    Write-Host "Phase $m Records Completed." -ForegroundColor Green
    Write-Host "Load Report: $REPORT_DIR"
    Write-Host "Stress Report: $REPORT_DIR_STRESS"
}

Write-Host "`n====================================================" -ForegroundColor Cyan
Write-Host "   Benchmarking Series Completed Successfully!      " -ForegroundColor Cyan
Write-Host "   Load results are in: $RESULTS_LOAD" -ForegroundColor White
Write-Host "   Stress results are in: $RESULTS_STRESS" -ForegroundColor White
Write-Host "====================================================" -ForegroundColor Cyan
