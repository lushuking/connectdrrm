<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$ok = function(array $data = []) {
    echo json_encode(['success' => true, 'data' => $data, 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
};
$err = function(string $code, string $message, int $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
};

try {
    if (!isLoggedIn()) {
        $err('unauthorized', 'Not authenticated', 401);
    }
    
    // Check if user is approving_authority
    $userRole = $_SESSION['user_type'] ?? '';
    if ($userRole !== 'approving_authority') {
        $err('forbidden', 'Only Head of DRRMO can view approvals', 403);
    }
    
    $drrmoID = $_SESSION['municipality_id'] ?? null;
    if (!$drrmoID) {
        $err('bad_request', 'Missing municipality id', 400);
    }
    
    // Check if originalToDRRMO column exists, create it if it doesn't
    $checkColumn = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'requests' 
        AND COLUMN_NAME = 'originalToDRRMO'
    ");
    $checkColumn->execute();
    $columnExists = $checkColumn->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    // Create column if it doesn't exist
    if (!$columnExists) {
        try {
            $pdo->exec("ALTER TABLE requests ADD COLUMN originalToDRRMO INT NULL AFTER toDRRMO");
            $columnExists = true;
            error_log("Added originalToDRRMO column to requests table");
        } catch (PDOException $e) {
            error_log("Could not add originalToDRRMO column: " . $e->getMessage());
            // Continue with fallback query
        }
    }
    
    // Check if requestGroupId column exists
    $checkGroupCol = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'requests' 
        AND COLUMN_NAME = 'requestGroupId'
    ");
    $checkGroupCol->execute();
    $hasRequestGroupId = $checkGroupCol->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    // Build SQL query based on whether columns exist
    if ($columnExists && $hasRequestGroupId) {
        $sql = "
            SELECT 
                r.requestID,
                r.quantity,
                r.status,
                r.requestDate,
                r.priority,
                r.notes,
                r.originalToDRRMO,
                r.requestGroupId,
                from_d.name AS fromMunicipality,
                to_d.name AS toMunicipality,
                original_d.name AS originalToMunicipality,
                res.resourceName AS resourceType,
                res.description,
                res.category,
                res.availableStock,
                res.unit
            FROM requests r
            JOIN drrmo from_d ON r.fromDRRMO = from_d.drrmoID
            JOIN drrmo to_d ON r.toDRRMO = to_d.drrmoID
            LEFT JOIN drrmo original_d ON r.originalToDRRMO = original_d.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.toDRRMO = ? AND (LOWER(r.status) IN ('pending_head_approval', 'group_approved_pending', 'group_rejected_pending') OR r.status IS NULL OR r.status = '')
            ORDER BY r.requestGroupId IS NULL ASC, r.requestGroupId ASC, r.priority DESC, r.requestDate ASC
        ";
    } elseif ($columnExists) {
        $sql = "
            SELECT 
                r.requestID,
                r.quantity,
                r.status,
                r.requestDate,
                r.priority,
                r.notes,
                r.originalToDRRMO,
                NULL AS requestGroupId,
                from_d.name AS fromMunicipality,
                to_d.name AS toMunicipality,
                original_d.name AS originalToMunicipality,
                res.resourceName AS resourceType,
                res.description,
                res.category,
                res.availableStock,
                res.unit
            FROM requests r
            JOIN drrmo from_d ON r.fromDRRMO = from_d.drrmoID
            JOIN drrmo to_d ON r.toDRRMO = to_d.drrmoID
            LEFT JOIN drrmo original_d ON r.originalToDRRMO = original_d.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.toDRRMO = ? AND (LOWER(r.status) IN ('pending_head_approval', 'group_approved_pending', 'group_rejected_pending') OR r.status IS NULL OR r.status = '')
            ORDER BY r.priority DESC, r.requestDate ASC
        ";
    } else {
        // Fallback query without originalToDRRMO column
        $sql = "
            SELECT 
                r.requestID,
                r.quantity,
                r.status,
                r.requestDate,
                r.priority,
                r.notes,
                NULL AS originalToDRRMO,
                " . ($hasRequestGroupId ? "r.requestGroupId" : "NULL AS requestGroupId") . ",
                from_d.name AS fromMunicipality,
                to_d.name AS toMunicipality,
                NULL AS originalToMunicipality,
                res.resourceName AS resourceType,
                res.description,
                res.category,
                res.availableStock,
                res.unit
            FROM requests r
            JOIN drrmo from_d ON r.fromDRRMO = from_d.drrmoID
            JOIN drrmo to_d ON r.toDRRMO = to_d.drrmoID
            JOIN resources res ON r.resourceID = res.resourceID
            WHERE r.toDRRMO = ? AND (LOWER(r.status) IN ('pending_head_approval', 'group_approved_pending', 'group_rejected_pending') OR r.status IS NULL OR r.status = '')
            ORDER BY " . ($hasRequestGroupId ? "r.requestGroupId IS NULL ASC, r.requestGroupId ASC, " : "") . "r.priority DESC, r.requestDate ASC
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$drrmoID]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $requests = array_map(function($r) {
        $rawStatus = $r['status'] ?? '';
        $status = strtolower($rawStatus);
        
        // Handle empty status items (from old groups before ENUM fix)
        // If status is empty but item is in approval dashboard, treat as pending (not evaluated)
        // The backend will fix these when processing approvals
        $isEvaluated = false;
        if (!empty($rawStatus) && $rawStatus !== '') {
            $isEvaluated = ($status === 'group_approved_pending' || $status === 'group_rejected_pending');
        }
        
        $evaluationStatus = 'pending';
        if ($status === 'group_approved_pending') {
            $evaluationStatus = 'approved';
        } elseif ($status === 'group_rejected_pending') {
            $evaluationStatus = 'rejected';
        } elseif (empty($rawStatus) || $rawStatus === '') {
            // Empty status - treat as pending (will be fixed by backend)
            $evaluationStatus = 'pending';
            $isEvaluated = false;
        }
        
        return [
            'id' => (int)$r['requestID'],
            'name' => $r['resourceType'],
            'fromMunicipality' => $r['fromMunicipality'],
            'toMunicipality' => $r['toMunicipality'],
            'originalToMunicipality' => $r['originalToMunicipality'] ?? null,
            'originalToDRRMO' => isset($r['originalToDRRMO']) ? (int)$r['originalToDRRMO'] : null,
            'requestGroupId' => $r['requestGroupId'] ?? null,
            'category' => $r['category'],
            'quantity' => (int)$r['quantity'],
            'unit' => $r['unit'],
            'status' => $status,
            'description' => $r['description'],
            'requestDate' => $r['requestDate'],
            'priority' => $r['priority'],
            'notes' => $r['notes'],
            'availableStock' => (int)$r['availableStock'],
            'isEvaluated' => $isEvaluated,
            'evaluationStatus' => $evaluationStatus
        ];
    }, $rows);

    $ok(['requests' => $requests, 'count' => count($requests)]);
} catch (Throwable $e) {
    error_log('[get_pending_approvals] ' . $e->getMessage());
    error_log('[get_pending_approvals] Stack trace: ' . $e->getTraceAsString());
    $err('server_error', 'Server error: ' . $e->getMessage(), 500);
}
?>

