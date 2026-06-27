<?php
/**
 * Simple ConnectDRRM Stress Test Script (Browser Version)
 */
header('Content-Type: text/html');
echo "<html><head><title>ConnectDRRM Stress Test</title><style>body{font-family:monospace; background:#1e1e1e; color:#d4d4d4; padding:20px;} .success{color:#4ec9b0;} .error{color:#f44747;} .header{color:#569cd6; font-size:1.2em; border-bottom:1px solid #333; padding-bottom:10px; margin-bottom:10px;}</style></head><body>";

$baseUrl = "http://localhost/ConnectDRRM";
$loginUrl = "$baseUrl/login.php";
$dashboardUrl = "$baseUrl/pdrrmo.php";

$testUsers = [
    ['username' => 'tester@connectdrrm.com', 'password' => 'password123'],
];

$concurrency = 5; 
$totalRequests = 10;

echo "<div class='header'>Starting Stress Test for ConnectDRRM...</div>";
echo "Target: $baseUrl<br>";
echo "Concurrency: $concurrency (sequential in this browser version)<br>";
echo "----------------------------------------<br><br>";
echo "----------------------------------------\n";

function runRequest($url, $postData = null, $cookieFile = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [
        'time' => $endTime - $startTime,
        'code' => $info['http_code'],
        'success' => ($info['http_code'] >= 200 && $info['http_code'] < 400)
    ];
}

$results = [];
$totalTime = 0;

for ($i = 0; $i < $totalRequests; $i++) {
    $user = $testUsers[0]; // Just use the first one for now
    $cookieFile = tempnam(sys_get_temp_dir(), 'DRRM_TEST_');
    
    echo "Request " . ($i + 1) . ": Logging in... ";
    $loginResult = runRequest($loginUrl, [
        'username' => $user['username'],
        'password' => $user['password'],
        'login' => ''
    ], $cookieFile);
    
    if ($loginResult['success']) {
        echo "<span class='success'>Success</span> (" . round($loginResult['time'], 3) . "s). Accessing dashboard... ";
        $dashResult = runRequest($dashboardUrl, null, $cookieFile);
        
        if ($dashResult['success']) {
            echo "<span class='success'>Success</span> (" . round($dashResult['time'], 3) . "s)<br>";
            $results[] = $dashResult['time'];
            $totalTime += $dashResult['time'];
        } else {
            echo "<span class='error'>FAILED</span> (HTTP " . $dashResult['code'] . ")<br>";
        }
    } else {
        echo "<span class='error'>FAILED</span> (HTTP " . $loginResult['code'] . ")<br>";
    }
    
    @unlink($cookieFile);
}

echo "<br>----------------------------------------<br>";
echo "<div class='header'>Test Finished.</div>";
echo "Total Successful Dashboard Views: " . count($results) . "<br>";
if (count($results) > 0) {
    echo "Average Response Time: <span class='success'>" . round($totalTime / count($results), 3) . "s</span><br>";
    echo "Min Response Time: " . round(min($results), 3) . "s<br>";
    echo "Max Response Time: " . round(max($results), 3) . "s<br>";
}
echo "</body></html>";
?>
