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
    $drrmoID = $_SESSION['municipality_id'] ?? null;
    if (!$drrmoID) {
        $err('bad_request', 'Missing municipality id', 400);
    }
    
    // Get current user's DRRM name for comparison
    $stmt = $pdo->prepare("SELECT name FROM drrmo WHERE drrmoID = ?");
    $stmt->execute([$drrmoID]);
    $currentUserDRRMOName = $stmt->fetchColumn();

    $sql = "
        SELECT 
            r.requestID,
            r.quantity,
            r.status,
            r.requestDate,
            r.responseDate,
            r.priority,
            r.notes,
            r.returnRequestedAt,
            r.returnedAt,
            r.returnedQty,
            r.requestGroupId,
            from_d.name AS fromMunicipality,
            COALESCE(orig_d.name, to_d.name) AS toMunicipality,
            res.resourceName AS resourceType,
            res.description,
            res.category,
            res.availableStock,
            res.unit,
            CASE 
                WHEN r.fromDRRMO = ? THEN 'outgoing'
                WHEN r.toDRRMO = ? THEN 'incoming'
                ELSE 'other'
            END AS requestType
        FROM requests r
        JOIN drrmo from_d ON r.fromDRRMO = from_d.drrmoID
        JOIN drrmo to_d ON r.toDRRMO = to_d.drrmoID
        LEFT JOIN drrmo orig_d ON r.originalToDRRMO = orig_d.drrmoID
        JOIN resources res ON r.resourceID = res.resourceID
        WHERE (r.fromDRRMO = ? OR r.toDRRMO = ? OR r.originalToDRRMO = ?)
        AND (
            LOWER(COALESCE(r.status, '')) NOT IN ('pending_head_approval', 'group_approved_pending', 'group_rejected_pending')
            OR r.fromDRRMO = ?
        )
        ORDER BY r.requestDate DESC
        LIMIT 1000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$drrmoID, $drrmoID, $drrmoID, $drrmoID, $drrmoID, $drrmoID]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $requests = array_map(function($r) use ($currentUserDRRMOName) {
        $derivedStatus = isset($r['status']) && $r['status'] !== '' ? $r['status'] : 'pending';
        $isOwnRequest = ($r['fromMunicipality'] === $currentUserDRRMOName);
        return [
            'id' => (int)$r['requestID'],
            'name' => $r['resourceType'],
            'municipality' => $r['fromMunicipality'],
            'toMunicipality' => $r['toMunicipality'],
            'category' => $r['category'],
            'quantity' => (int)$r['quantity'],
            'unit' => $r['unit'],
            'status' => $derivedStatus,
            'description' => $r['description'],
            'requestDate' => $r['requestDate'],
            'responseDate' => $r['responseDate'] ?? null,
            'priority' => $r['priority'],
            'notes' => $r['notes'],
            'requestType' => $r['requestType'],
            'isIncomingRequest' => ($r['requestType'] === 'incoming'),
            'isOwnRequest' => $isOwnRequest,
            'returnRequestedAt' => $r['returnRequestedAt'] ?? null,
            'returnedAt' => $r['returnedAt'] ?? null,
            'returnedQty' => isset($r['returnedQty']) ? (int)$r['returnedQty'] : null,
            'requestGroupId' => $r['requestGroupId'] ?? null
        ];
    }, $rows);

    $ok(['requests' => $requests]);
} catch (Throwable $e) {
    error_log('[get_requests_for_municipality] ' . $e->getMessage());
    $err('server_error', 'Server error', 500);
}
?>


