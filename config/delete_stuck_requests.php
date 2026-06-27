<?php
/**
 * Quick script to delete stuck requests 175, 176, 177
 * Run via browser: http://localhost/ConnectDRRM/config/delete_stuck_requests.php
 * Or via command line: php config/delete_stuck_requests.php
 */

require_once __DIR__ . '/db.php';

// Allow both web and CLI access
if (php_sapi_name() !== 'cli') {
    session_start();
    require_once __DIR__ . '/auth.php';
    
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        die('Not authenticated');
    }
    
    $userRole = $_SESSION['user_type'] ?? '';
    if ($userRole !== 'approving_authority' && $userRole !== 'admin' && $userRole !== 'emergency_coordinator') {
        die('Not authorized');
    }
}

try {
    $requestIds = [175, 176, 177];
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
    
    // Check current state
    $checkStmt = $pdo->prepare("SELECT requestID, status, requestGroupId FROM requests WHERE requestID IN ($placeholders)");
    $checkStmt->execute($requestIds);
    $existing = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($existing) . " request(s) to delete:\n";
    foreach ($existing as $req) {
        echo "  - REQ-{$req['requestID']}: status='{$req['status']}', group='{$req['requestGroupId']}'\n";
    }
    
    if (empty($existing)) {
        echo "No requests found. They may have already been deleted.\n";
        exit;
    }
    
    // Delete them
    $pdo->beginTransaction();
    $deleteStmt = $pdo->prepare("DELETE FROM requests WHERE requestID IN ($placeholders)");
    $deleteStmt->execute($requestIds);
    $deletedCount = $deleteStmt->rowCount();
    $pdo->commit();
    
    echo "\nSuccessfully deleted $deletedCount request(s): " . implode(', ', $requestIds) . "\n";
    error_log("Cleanup: Deleted stuck requests: " . implode(', ', $requestIds));
    
    if (php_sapi_name() !== 'cli') {
        echo "<br><br><a href='javascript:history.back()'>Go Back</a>";
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Delete stuck requests error: " . $e->getMessage());
    exit(1);
}
?>
