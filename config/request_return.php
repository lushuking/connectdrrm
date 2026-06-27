<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

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
	if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
		$err('unauthorized', 'Not authenticated', 401);
	}
	$fromDrrmo = $_SESSION['municipality_id'] ?? null; // borrower
	if (!$fromDrrmo) {
		$err('no_municipality', 'Municipality not found in session', 400);
	}

	$body = json_decode(file_get_contents('php://input'), true) ?: [];
    $requestId = isset($body['requestId']) ? (int)$body['requestId'] : 0;
    if ($requestId <= 0) {
        $err('bad_request', 'Invalid requestId');
    }

	// Load request
	$stmt = $pdo->prepare('SELECT requestID, fromDRRMO, toDRRMO, resourceID, quantity, status FROM requests WHERE requestID = ? LIMIT 1');
	$stmt->execute([$requestId]);
	$req = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$req) $err('not_found', 'Request not found', 404);
	if ((int)$req['fromDRRMO'] !== (int)$fromDrrmo) {
		$err('forbidden', 'Only the borrower can initiate a return', 403);
	}
	$status = strtolower($req['status'] ?? '');
    if ($status !== 'fulfilled') {
		$err('invalid_state', 'Only fulfilled requests can be returned', 409);
	}
    // Full return: use original borrowed quantity
    $returnQty = (int)$req['quantity'];

	// Update to return pending, set timestamps/qty
	$upd = $pdo->prepare('UPDATE requests SET status = ?, returnRequestedAt = NOW(), returnedQty = ? WHERE requestID = ?');
	$upd->execute(['return pending', $returnQty, $requestId]);

	// Notify provider and borrower using NotificationService
	try {
		require_once __DIR__ . '/notification_service.php';
		$notificationService = new NotificationService($pdo);
		
		// Get request details for better notification
		$reqStmt = $pdo->prepare('SELECT res.resourceName FROM requests r JOIN resources res ON r.resourceID = res.resourceID WHERE r.requestID = ?');
		$reqStmt->execute([$requestId]);
		$reqDetails = $reqStmt->fetch(PDO::FETCH_ASSOC);
		
		if ($reqDetails) {
			$notificationService->createReturnNotification(
				(int)$req['toDRRMO'],  // Provider municipality
				(int)$req['fromDRRMO'], // Borrower municipality
				$reqDetails['resourceName'],
				$requestId
			);
		}
	} catch (Throwable $ne) {
		error_log('[request_return][notif] ' . $ne->getMessage());
	}

    $ok(['requestId' => $requestId, 'status' => 'return pending', 'returnedQty' => $returnQty]);
} catch (Throwable $e) {
	error_log('[request_return] ' . $e->getMessage());
	$err('server_error', 'Server error', 500);
}
?>


