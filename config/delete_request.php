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
    $fromDrrmo = $_SESSION['municipality_id'] ?? null;
    if (!$fromDrrmo) {
        $err('bad_request', 'Missing municipality id', 400);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $requestId = isset($body['requestId']) ? (int)$body['requestId'] : 0;
    if ($requestId <= 0) {
        $err('bad_request', 'Invalid requestId');
    }

    // Load request and validate ownership and state
    $stmt = $pdo->prepare('SELECT requestID, fromDRRMO, toDRRMO, status FROM requests WHERE requestID = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        $err('not_found', 'Request not found', 404);
    }
    if ((int)$req['fromDRRMO'] !== (int)$fromDrrmo) {
        $err('forbidden', 'You can only delete your own requests', 403);
    }
    $status = strtolower($req['status'] ?? '');
    if ($status !== 'pending') {
        $err('invalid_state', 'Only pending requests can be deleted', 409);
    }

    // Delete request
    $del = $pdo->prepare('DELETE FROM requests WHERE requestID = ?');
    $del->execute([$requestId]);

    // Clean up notifications related to this request
    try {
        require_once __DIR__ . '/notification_service.php';
        $notificationService = new NotificationService($pdo);
        $notificationService->cleanupRequestNotifications($requestId);
    } catch (Throwable $ne) {
        error_log('[delete_request][notif_cleanup] ' . $ne->getMessage());
    }

    // Best-effort notify provider that request was cancelled
    try {
        $msg = 'A resource request was cancelled by the requester.';
        $ins = $pdo->prepare('INSERT INTO notifications (userID, message, isRead, createdAt) SELECT u.userID, ?, 0, NOW() FROM users u WHERE u.municipalityID = ?');
        $ins->execute([$msg, (int)$req['toDRRMO']]);
    } catch (Throwable $ne) {
        error_log('[delete_request][notif] ' . $ne->getMessage());
    }

    $ok(['requestId' => $requestId]);
} catch (Throwable $e) {
    error_log('[delete_request] ' . $e->getMessage());
    $err('server_error', 'Server error', 500);
}
?>


