<?php
/**
 * ConnectDRRM Test Data Generator
 * Generates synthetic data for stress and load testing.
 */
require_once __DIR__ . '/../config/db.php';

// Set infinite time limit for large data generation
set_time_limit(0);

$totalRecords = isset($_GET['records']) ? (int)$_GET['records'] : 5000;
$incremental = isset($_GET['incremental']) && ($_GET['incremental'] === 'true' || $_GET['incremental'] === '1');

if (php_sapi_name() === 'cli') {
    $totalRecords = isset($argv[1]) ? (int)$argv[1] : 5000;
    // For CLI, if the second argument is "add", it's incremental
    $incremental = isset($argv[2]) && $argv[2] === 'add';
}

echo "ConnectDRRM Exact Data Generator\n";
echo "--------------------------\n";
echo "Target Total Records: " . number_format($totalRecords) . " records\n";
echo "Mode: " . ($incremental ? "Incremental (Adding to existing)" : "Fresh (Clearing old test data first)") . "\n\n";

if (!$incremental) {
    echo "Clearing existing test data (hazards, requests, notifications, reports) to ensure clean benchmark...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE notifications");
    $pdo->exec("TRUNCATE TABLE reports");
    $pdo->exec("TRUNCATE TABLE requests");
    $pdo->exec("TRUNCATE TABLE hazards");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

// Get valid IDs
$drrmos = $pdo->query("SELECT drrmoID FROM drrmo")->fetchAll(PDO::FETCH_COLUMN);
$drrmoNames = $pdo->query("SELECT drrmoID, name FROM drrmo")->fetchAll(PDO::FETCH_KEY_PAIR);
$users = $pdo->query("SELECT userID, drrmoID FROM users WHERE role != 'admin'")->fetchAll();
$resources = $pdo->query("SELECT resourceID, drrmoID FROM resources")->fetchAll();

if (empty($drrmos)) {
    die("Error: No DRRMOs found. Please seed the basic system data first.\n");
}
if (empty($users)) {
    die("Error: No Users found. Please seed users first.\n");
}
if (empty($resources)) {
    die("Error: No Resources found. Please seed resources first.\n");
}

// Load barangay index from config/data/barangay_index.json
$indexFilePath = __DIR__ . '/../config/data/barangay_index.json';
if (!file_exists($indexFilePath)) {
    die("Error: barangay_index.json not found at $indexFilePath\n");
}
$indexData = json_decode(file_get_contents($indexFilePath), true);
if (!$indexData || !isset($indexData['locations'])) {
    die("Error: Invalid barangay_index.json format\n");
}

$barangaysByMunicipality = [];
$allBarangays = [];
foreach ($indexData['locations'] as $loc) {
    if ($loc['type'] === 'barangay') {
        $allBarangays[] = $loc;
        if (isset($loc['municipality'])) {
            $munName = $loc['municipality'];
            $barangaysByMunicipality[$munName][] = $loc;
        }
    }
}
if (empty($allBarangays)) {
    $allBarangays = $indexData['locations'];
}

$now = new DateTime();
$startDate = clone $now;
$startDate->modify("-36 months"); // Randomize over the past 3 years

function randomDate($start, $end) {
    $timestamp = mt_rand($start->getTimestamp(), $end->getTimestamp());
    $randomDate = new DateTime();
    $randomDate->setTimestamp($timestamp);
    return $randomDate->format('Y-m-d H:i:s');
}

$hazardTypes = ['Flood', 'Earthquake', 'Landslide', 'Typhoon', 'Fire', 'Volcanic Eruption'];
$intensities = ['Low', 'Medium', 'High', 'Critical'];
$statuses = ['active', 'monitoring', 'resolved'];
$priorities = ['low', 'medium', 'high'];
$reportTypes = ['overview', 'resources', 'requests', 'hazards', 'analytics'];

// Calculate proportions
$hazardCount = (int)($totalRecords * 0.05); // 5%
$requestCount = (int)($totalRecords * 0.25); // 25%
$notifCount = (int)($totalRecords * 0.65); // 65%
$reportCount = (int)($totalRecords * 0.05); // 5%

// Adjust for rounding to hit exact target
$notifCount += $totalRecords - ($hazardCount + $requestCount + $notifCount + $reportCount);

// 1. Hazards
echo "Inserting $hazardCount hazards...\n";
$stmt = $pdo->prepare("INSERT INTO hazards (drrmoID, hazardType, intensity, location, latitude, longitude, description, affectedPopulation, reportedBy, reportedAt, status, resolvedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
for ($i = 0; $i < $hazardCount; $i++) {
    $drrmo = $drrmos[array_rand($drrmos)];
    $user = $users[array_rand($users)];
    
    $drrmoName = isset($drrmoNames[$drrmo]) ? $drrmoNames[$drrmo] : '';
    
    $chosenBarangay = null;
    if ($drrmoName !== 'PDRRMO - Zamboanga del Sur' && isset($barangaysByMunicipality[$drrmoName]) && !empty($barangaysByMunicipality[$drrmoName])) {
        $chosenBarangay = $barangaysByMunicipality[$drrmoName][array_rand($barangaysByMunicipality[$drrmoName])];
    } else {
        $chosenBarangay = $allBarangays[array_rand($allBarangays)];
    }
    
    $locationName = isset($chosenBarangay['display_name']) ? $chosenBarangay['display_name'] : $chosenBarangay['name'];
    $latitude = $chosenBarangay['coordinates'][0];
    $longitude = $chosenBarangay['coordinates'][1];
    
    $status = $statuses[array_rand($statuses)];
    $reportedDate = randomDate($startDate, $now);
    
    $resolvedDate = null;
    if ($status === 'resolved') {
        $repTs = strtotime($reportedDate);
        $resTs = $repTs + rand(3600, 5 * 86400); // 1 hour to 5 days resolution time
        $nowTs = time();
        if ($resTs > $nowTs) {
            $resTs = $nowTs;
        }
        $resolvedDate = date('Y-m-d H:i:s', $resTs);
    }
    
    $stmt->execute([
        $drrmo,
        $hazardTypes[array_rand($hazardTypes)],
        $intensities[array_rand($intensities)],
        $locationName,
        $latitude,
        $longitude,
        "Synthetic hazard report for stress testing.",
        rand(50, 10000),
        $user['userID'],
        $reportedDate,
        $status,
        $resolvedDate
    ]);
}

// 2. Requests
echo "Inserting $requestCount requests...\n";
$stmt = $pdo->prepare("INSERT INTO requests (fromDRRMO, toDRRMO, resourceID, quantity, priority, urgency, status, requestDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
for ($i = 0; $i < $requestCount; $i++) {
    $from = $drrmos[array_rand($drrmos)];
    $to = $drrmos[array_rand($drrmos)];
    while($to == $from) $to = $drrmos[array_rand($drrmos)];
    
    $res = $resources[array_rand($resources)];
    
    $stmt->execute([
        $from,
        $to,
        $res['resourceID'],
        rand(5, 100),
        $priorities[array_rand($priorities)],
        $priorities[array_rand($priorities)],
        ['pending', 'approved', 'rejected', 'fulfilled', 'returned'][rand(0, 4)],
        randomDate($startDate, $now)
    ]);
}

// 3. Notifications
echo "Inserting $notifCount notifications...\n";
$stmt = $pdo->prepare("INSERT INTO notifications (userID, message, isRead, createdAt) VALUES (?, ?, ?, ?)");
for ($i = 0; $i < $notifCount; $i++) {
    $user = $users[array_rand($users)];
    $stmt->execute([
        $user['userID'],
        "Stress test notification item $i",
        rand(0, 1),
        randomDate($startDate, $now)
    ]);
}

// 4. Reports
echo "Inserting $reportCount reports...\n";
$stmt = $pdo->prepare("INSERT INTO reports (drrmoID, generatedBy, title, reportType, generatedAt) VALUES (?, ?, ?, ?, ?)");
for ($i = 0; $i < $reportCount; $i++) {
    $drrmo = $drrmos[array_rand($drrmos)];
    $user = $users[array_rand($users)];
    $stmt->execute([
        $drrmo,
        $user['userID'],
        "Stress Analysis " . rand(1000, 9999),
        $reportTypes[array_rand($reportTypes)],
        randomDate($startDate, $now)
    ]);
}

echo "\nSuccess: Exact data generation complete.\n";
echo "Total New Records: " . ($hazardCount + $requestCount + $notifCount + $reportCount) . "\n";
?>
