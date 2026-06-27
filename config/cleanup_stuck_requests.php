<?php
/**
 * Cleanup script to remove stuck requests (175, 176, 177)
 * Run this once to clean up the old group that's in a bad state
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Check authentication
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    // Check if user has admin or approving_authority role
    $userRole = $_SESSION['user_type'] ?? '';
    if ($userRole !== 'approving_authority' && $userRole !== 'admin' && $userRole !== 'emergency_coordinator') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
    
    // Get request IDs from query parameter or body
    $requestIds = [];
    if (isset($_GET['ids'])) {
        $requestIds = array_map('intval', explode(',', $_GET['ids']));
    } elseif (isset($_POST['ids'])) {
        $requestIds = array_map('intval', explode(',', $_POST['ids']));
    } else {
        // Default: delete the stuck requests 175, 176, 177
        $requestIds = [175, 176, 177];
    }
    
    if (empty($requestIds)) {
        echo json_encode(['success' => false, 'error' => 'No request IDs provided']);
        exit;
    }
    
    // First, check the current state of these requests
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
    $checkStmt = $pdo->prepare("SELECT requestID, status, requestGroupId, fromDRRMO, toDRRMO FROM requests WHERE requestID IN ($placeholders)");
    $checkStmt->execute($requestIds);
    $existingRequests = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($existingRequests)) {
        echo json_encode(['success' => false, 'error' => 'No requests found with those IDs']);
        exit;
    }
    
    // Delete the requests
    $pdo->beginTransaction();
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM requests WHERE requestID IN ($placeholders)");
        $deleteStmt->execute($requestIds);
        $deletedCount = $deleteStmt->rowCount();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted $deletedCount request(s)",
            'deletedIds' => $requestIds,
            'deletedCount' => $deletedCount,
            'existingRequests' => $existingRequests
        ]);
        
        error_log("Cleanup: Deleted stuck requests: " . implode(', ', $requestIds));
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    error_log("Cleanup error: " . $e->getMessage());
}
?>
