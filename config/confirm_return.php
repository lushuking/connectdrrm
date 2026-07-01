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
    // Accept as return-pending: status is exactly 'return pending', or return was requested and not yet confirmed
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

    // ── Damage assessment inputs ──────────────────────────────────────
    $goodQty     = isset($body['goodQty'])     ? max(0, (int)$body['goodQty'])     : null;
    $damagedQty  = isset($body['damagedQty'])  ? max(0, (int)$body['damagedQty'])  : null;
    $damageNotes = isset($body['damageNotes']) ? trim((string)$body['damageNotes']) : null;

    // If the caller did not send assessment data, fall back to 100% good (legacy behaviour)
    if ($goodQty === null && $damagedQty === null) {
        $goodQty    = $returnQty;
        $damagedQty = 0;
    }
    if ($goodQty    === null) $goodQty    = 0;
    if ($damagedQty === null) $damagedQty = 0;

    // Validate: good + damaged must equal total returned qty
    if (($goodQty + $damagedQty) !== $returnQty) {
        $err('bad_request', "Good ({$goodQty}) + Damaged ({$damagedQty}) must equal total returned qty ({$returnQty})");
    }

    // Build assessment summary text
    $assessmentText = "Good: {$goodQty}, Damaged: {$damagedQty}";
    if (!empty($damageNotes)) {
        $assessmentText .= " — {$damageNotes}";
    }

    // ── Transaction ──────────────────────────────────────────────────
    $itemConditions = $body['itemConditions'] ?? [];

    $pdo->beginTransaction();
    try {
        // Find dispatched items
        $dispStmt = $pdo->prepare("SELECT itemID FROM request_dispatched_items WHERE requestID = ?");
        $dispStmt->execute([$requestId]);
        $dispatchedItemIDs = $dispStmt->fetchAll(PDO::FETCH_COLUMN);

        $goodQtyComputed = 0;
        $damagedQtyComputed = 0;

        if (count($dispatchedItemIDs) > 0) {
            $updateItemStatus = $pdo->prepare("UPDATE resource_items SET status = ?, conditionNotes = ? WHERE itemID = ?");
            
            if (!empty($itemConditions) && is_array($itemConditions)) {
                // Map of itemID -> condition/notes
                $condMap = [];
                foreach ($itemConditions as $ic) {
                    $condMap[(int)$ic['itemID']] = [
                        'status' => $ic['condition'] === 'Available' ? 'Available' : 'Damaged / Repairing',
                        'notes' => $ic['notes'] ?? ''
                    ];
                }
                
                foreach ($dispatchedItemIDs as $itemId) {
                    $itemInfo = $condMap[(int)$itemId] ?? ['status' => 'Available', 'notes' => ''];
                    $updateItemStatus->execute([$itemInfo['status'], $itemInfo['notes'], $itemId]);
                    if ($itemInfo['status'] === 'Available') {
                        $goodQtyComputed++;
                    } else {
                        $damagedQtyComputed++;
                    }
                }
            } else {
                // Fallback using goodQty and damagedQty inputs (first X are good, rest are damaged)
                $gLeft = $goodQty;
                foreach ($dispatchedItemIDs as $itemId) {
                    if ($gLeft > 0) {
                        $updateItemStatus->execute(['Available', 'Returned in good condition.', $itemId]);
                        $goodQtyComputed++;
                        $gLeft--;
                    } else {
                        $updateItemStatus->execute(['Damaged / Repairing', 'Returned damaged: ' . $damageNotes, $itemId]);
                        $damagedQtyComputed++;
                    }
                }
            }
            
            // Override the quantities with what we processed
            $goodQty = $goodQtyComputed;
            $damagedQty = $damagedQtyComputed;
            $assessmentText = "Good: {$goodQty}, Damaged: {$damagedQty}";
            if (!empty($damageNotes)) {
                $assessmentText .= " — {$damageNotes}";
            }
        }

        // Restock ONLY the good-condition items back to availableStock
        if ($goodQty > 0) {
            $restock = $pdo->prepare('UPDATE resources SET availableStock = availableStock + ? WHERE resourceID = ?');
            $restock->execute([$goodQty, (int)$req['resourceID']]);
        }

        // Increment damagedStock for damaged items (NOT restocked)
        if ($damagedQty > 0) {
            $damaged = $pdo->prepare('UPDATE resources SET damagedStock = COALESCE(damagedStock, 0) + ? WHERE resourceID = ?');
            $damaged->execute([$damagedQty, (int)$req['resourceID']]);
        }

        // Mark request as returned and persist assessment
        $upd = $pdo->prepare(
            'UPDATE requests SET status = ?, returnedAt = NOW(), damagedQty = ?, damageAssessment = ? WHERE requestID = ?'
        );
        $upd->execute(['returned', $damagedQty, $assessmentText, $requestId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    // Notify provider and borrower
    try {
        $provMsg = $damagedQty > 0
            ? "You confirmed the return. {$goodQty} item(s) restocked; {$damagedQty} flagged as damaged."
            : 'You confirmed the return and restocked the resource.';
        $borMsg  = $damagedQty > 0
            ? "Your return was confirmed. {$goodQty} item(s) accepted; {$damagedQty} flagged as damaged."
            : 'Your return was confirmed by the provider.';

        $insProvider = $pdo->prepare('INSERT INTO notifications (userID, message, isRead, createdAt) SELECT u.userID, ?, 0, NOW() FROM users u WHERE COALESCE(u.drrmoID, u.municipalityID) = ?');
        $insProvider->execute([$provMsg, (int)$toDrrmo]);
        $insBorrower = $pdo->prepare('INSERT INTO notifications (userID, message, isRead, createdAt) SELECT u.userID, ?, 0, NOW() FROM users u WHERE COALESCE(u.drrmoID, u.municipalityID) = (SELECT fromDRRMO FROM requests WHERE requestID = ?)');
        $insBorrower->execute([$borMsg, $requestId]);
    } catch (Throwable $ne) {
        error_log('[confirm_return][notif] ' . $ne->getMessage());
    }

    $ok([
        'requestId'  => $requestId,
        'status'     => 'returned',
        'goodQty'    => $goodQty,
        'damagedQty' => $damagedQty,
        'hasDamage'  => $damagedQty > 0
    ]);
} catch (Throwable $e) {
    error_log('[confirm_return] ' . $e->getMessage());
    $err('server_error', 'Server error', 500);
}
?>
