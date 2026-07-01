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

    $requestId = $_GET['requestId'] ?? null;
    if (!$requestId) {
        $err('bad_request', 'Request ID is required', 400);
    }

    // Get request details with municipality names
    // Include originalToDRRMO to show the intended provider municipality (for head approval routing)
    $sql = "
        SELECT 
            r.requestID,
            r.resourceID,
            r.toDRRMO,
            r.quantity,
            r.status,
            r.requestDate,
            r.responseDate,
            r.priority,
            r.notes,
            r.urgency,
            r.deliveryDate,
            r.deliveryLocation,
            r.requestorName,
            r.contactPhone,
            r.contactEmail,
            r.purposeOfRequest,
            r.expectedDuration,
            r.returnDate,
            r.transportationMethod,
            r.approvingAuthority,
            r.head_approval_status,
            r.head_approved_by,
            r.approverTitle,
            r.approverSignature,
            r.originalToDRRMO,
            from_drrmo.name AS fromMunicipality,
            to_drrmo.name AS toMunicipality,
            original_drrmo.name AS originalToMunicipality,
            res.resourceName,
            res.category,
            res.unit,
            res.description
        FROM requests r
        JOIN drrmo from_drrmo ON r.fromDRRMO = from_drrmo.drrmoID
        JOIN drrmo to_drrmo ON r.toDRRMO = to_drrmo.drrmoID
        LEFT JOIN drrmo original_drrmo ON r.originalToDRRMO = original_drrmo.drrmoID
        JOIN resources res ON r.resourceID = res.resourceID
        WHERE r.requestID = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $err('not_found', 'Request not found', 404);
    }

    // Get dispatched items if any
    $dispatchedItems = [];
    $dispStmt = $pdo->prepare("
        SELECT ri.itemID, ri.uniqueIdentifier, ri.status, ri.storageLocation, ri.conditionNotes 
        FROM request_dispatched_items rdi
        JOIN resource_items ri ON rdi.itemID = ri.itemID
        WHERE rdi.requestID = ?
    ");
    $dispStmt->execute([$requestId]);
    $dispatchedItems = $dispStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available items if pending or pending head approval
    $availableItems = [];
    $statusLower = strtolower($request['status'] ?? 'pending');
    if ($statusLower === 'pending' || $statusLower === 'pending_head_approval') {
        $availStmt = $pdo->prepare("
            SELECT itemID, uniqueIdentifier, status, storageLocation, conditionNotes 
            FROM resource_items 
            WHERE resourceID = ? AND status = 'Available'
        ");
        $availStmt->execute([$request['resourceID']]);
        $availableItems = $availStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Clean municipality names (remove DRRMO prefixes/suffixes)
    $cleanName = function($name) {
        $n = (string)($name ?? '');
        // remove prefix variants
        $n = preg_replace('/^(?:[A-Z]{0,3}DRRMO\s+)/', '', $n);
        // remove suffix " DRRMO"
        $n = preg_replace('/\s+DRRMO$/', '', $n);
        // remove leading descriptors
        $n = preg_replace('/^(City of\s+|Municipality of\s+)/i', '', $n);
        // remove trailing " City"
        $n = preg_replace('/\s+City$/i', '', $n);
        return trim($n);
    };

    $requestData = [
        'requestID' => (int)$request['requestID'],
        'resourceID' => (int)$request['resourceID'],
        'resourceName' => $request['resourceName'],
        'category' => $request['category'],
        'unit' => $request['unit'],
        'description' => $request['description'],
        'quantity' => (int)$request['quantity'],
        'priority' => strtolower($request['priority'] ?? 'medium'),
        'status' => strtolower($request['status'] ?? 'pending'),
        'urgency' => strtolower($request['urgency'] ?? 'normal'),
        'requestDate' => $request['requestDate'],
        'responseDate' => $request['responseDate'],
        'notes' => $request['notes'],
        'fromMunicipality' => $cleanName($request['fromMunicipality']),
        // Use originalToMunicipality if available (for head approval routing), otherwise use toMunicipality
        // This ensures the preview shows the intended provider municipality, not the temporary routing
        'toMunicipality' => $cleanName(!empty($request['originalToMunicipality']) ? $request['originalToMunicipality'] : $request['toMunicipality']),
        'deliveryDate' => $request['deliveryDate'],
        'deliveryLocation' => $request['deliveryLocation'],
        'requestorName' => $request['requestorName'],
        'contactPhone' => $request['contactPhone'],
        'contactEmail' => $request['contactEmail'],
        'purpose' => $request['purposeOfRequest'],
        'expectedDuration' => $request['expectedDuration'],
        'returnDate' => $request['returnDate'],
        'transportMethod' => $request['transportationMethod'],
        'approvingAuthority' => $request['approvingAuthority'],
        'headApprovalStatus' => $request['head_approval_status'],
        'headApprovedBy' => $request['head_approved_by'],
        'approverTitle' => $request['approverTitle'],
        'approverSignature' => $request['approverSignature'],
        'dispatchedItems' => $dispatchedItems,
        'availableItems' => $availableItems
    ];

    $ok($requestData);

} catch (Throwable $e) {
    error_log('[get_request_details] ' . $e->getMessage());
    $err('server_error', 'Server error', 500);
}
?>

