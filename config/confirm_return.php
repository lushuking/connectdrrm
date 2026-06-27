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
	$toDrrmo = $_SESSION['municipality_id'] ?? null; // provider
	if (!$toDrrmo) {
		$err('no_municipality', 'Municipality not found in session', 400);
	}

	$body = json_decode(file_get_contents('php://input'), true) ?: [];
	$requestId = isset($body['requestId']) ? (int)$body['requestId'] : 0;
	if ($requestId <= 0) {
		$err('bad_request', 'Invalid requestId');
	}

	// Load request (include quantity for fallback when returnedQty missing on bugged rows)
	$stmt = $pdo->prepare('SELECT requestID, toDRRMO, resourceID, quantity, status, returnedQty, returnRequestedAt, returnedAt FROM requests WHERE requestID = ? LIMIT 1');
	$stmt->execute([$requestId]);
	$req = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$req) $err('not_found', 'Request not found', 404);
	if ((int)$req['toDRRMO'] !== (int)$toDrrmo) {
		$err('forbidden', 'Only the provider can confirm return', 403);
	}
	$status = strtolower(trim($req['status'] ?? ''));
	$returnRequestedAt = $req['returnRequestedAt'] ?? null;
	$returnedAt = $req['returnedAt'] ?? null;
	// Accept as return-pending: status is exactly 'return pending', or return was requested and not yet confirmed (fallback if ENUM/DB lost the value)
	$isReturnPending = ($status === 'return pending') || (!empty($returnRequestedAt) && empty($returnedAt) && $status !== 'returned');
	if (!$isReturnPending) {
		$err('invalid_state', 'Only return-pending requests can be confirmed', 409);
	}
	$returnQty = (int)($req['returnedQty'] ?? 0);
	if ($returnQty <= 0) {
		$returnQty = (int)($req['quantity'] ?? 0);
	}
	if ($returnQty <= 0) {
		$err('bad_request', 'Missing returned quantity');
	}

	// Transaction: restock resource and finalize request
	$pdo->beginTransaction();
	try {
		$restock = $pdo->prepare('UPDATE resources SET availableStock = availableStock + ? WHERE resourceID = ?');
		$restock->execute([$returnQty, (int)$req['resourceID']]);

		$upd = $pdo->prepare('UPDATE requests SET status = ?, returnedAt = NOW() WHERE requestID = ?');
		$upd->execute(['returned', $requestId]);

		$pdo->commit();
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) { $pdo->rollBack(); }
		throw $e;
	}

	// Notify provider and borrower (use drrmoID; fallback to municipalityID for legacy)
	try {
		$insProvider = $pdo->prepare('INSERT INTO notifications (userID, message, isRead, createdAt) SELECT u.userID, ?, 0, NOW() FROM users u WHERE COALESCE(u.drrmoID, u.municipalityID) = ?');
		$insProvider->execute(['You confirmed the return and restocked the resource.', (int)$toDrrmo]);
		$insBorrower = $pdo->prepare('INSERT INTO notifications (userID, message, isRead, createdAt) SELECT u.userID, ?, 0, NOW() FROM users u WHERE COALESCE(u.drrmoID, u.municipalityID) = (SELECT fromDRRMO FROM requests WHERE requestID = ?)');
		$insBorrower->execute(['Your return was confirmed by the provider.', $requestId]);
	} catch (Throwable $ne) {
		error_log('[confirm_return][notif] ' . $ne->getMessage());
	}

	$ok(['requestId' => $requestId, 'status' => 'returned']);
} catch (Throwable $e) {
	error_log('[confirm_return] ' . $e->getMessage());
	$err('server_error', 'Server error', 500);
}
?>


