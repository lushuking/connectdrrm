<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$__agentLog = function(string $hypothesisId, string $location, string $message, array $data = []) {
    // no-op
};

$ts = (int)(microtime(true) * 1000);
$ok = function(array $data = []) use ($ts) {
    echo json_encode(['success' => true, 'data' => $data, 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
};
$err = function(string $code, string $message, int $status = 400) use ($ts) {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
};

try {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        $err('unauthorized', 'Not authenticated', 401);
    }
    
    // Allow approving_authority (Head) or drrmo_staff (municipality admin receiving the request)
    $userRole = $_SESSION['user_type'] ?? '';
    $canApprove = ($userRole === 'approving_authority' || $userRole === 'drrmo_staff');
    if (!$canApprove) {
        $err('forbidden', 'Only Approving Authority can approve or reject requests', 403);
    }
    
    $toDrrmo = $_SESSION['municipality_id'] ?? null;
    if (!$toDrrmo) {
        $err('no_municipality', 'Municipality not found in session', 400);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $requestId = isset($body['requestId']) ? (int)$body['requestId'] : 0;
    $action = strtolower(trim($body['action'] ?? ''));
    $rejectionReason = isset($body['rejectionReason']) ? trim($body['rejectionReason']) : null;
    $__agentLog('H2', 'config/update_request_status.php:input', 'update_request_status input', [
        'requestId' => $requestId,
        'action' => $action,
        'hasRejectionReason' => !empty($rejectionReason) ? 1 : 0,
        'userRole' => (string)$userRole,
        'toDrrmo_session' => (int)$toDrrmo,
    ]);
    if ($requestId <= 0 || !in_array($action, ['accept', 'reject'], true)) {
        $err('bad_request', 'Invalid requestId or action');
    }

    // Load request with originalToDRRMO and requestGroupId fields
    $stmt = $pdo->prepare('SELECT requestID, fromDRRMO, toDRRMO, originalToDRRMO, resourceID, quantity, status, requestGroupId FROM requests WHERE requestID = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        $err('not_found', 'Request not found', 404);
    }
    
    $requestGroupId = $req['requestGroupId'] ?? null;
    
    // Use textual status directly
    $currentStatus = strtolower($req['status'] ?? '');
    $__agentLog('H1', 'config/update_request_status.php:loaded', 'Loaded request', [
        'requestId' => (int)($req['requestID'] ?? 0),
        'status' => $currentStatus,
        'requestGroupId' => $requestGroupId ? (string)$requestGroupId : '',
        'fromDRRMO' => isset($req['fromDRRMO']) ? (int)$req['fromDRRMO'] : null,
        'toDRRMO' => isset($req['toDRRMO']) ? (int)$req['toDRRMO'] : null,
        'originalToDRRMO' => isset($req['originalToDRRMO']) ? (int)$req['originalToDRRMO'] : null,
    ]);
    
    // Check if request has already been evaluated (read-only)
    if ($currentStatus === 'group_approved_pending' || $currentStatus === 'group_rejected_pending') {
        $err('already_evaluated', 'This request has already been evaluated and cannot be modified. Please wait for all items in the group to be evaluated.', 409);
    }
    
    // Check if this is a Head approval request (pending_head_approval)
    $isHeadApproval = ($currentStatus === 'pending_head_approval');
    $__agentLog('H1', 'config/update_request_status.php:mode', 'Determined approval mode', [
        'isHeadApproval' => $isHeadApproval ? 1 : 0,
        'requestGroupId_present' => !empty($requestGroupId) ? 1 : 0,
    ]);
    
    // Head approval: both Approving Authority and municipality admin (drrmo_staff) can approve/bypass head approval
    
    if ($isHeadApproval) {
        // For Head approval: request must be from same municipality (fromDRRMO = toDRRMO)
        if ((int)$req['fromDRRMO'] !== (int)$toDrrmo || (int)$req['toDRRMO'] !== (int)$toDrrmo) {
            $err('forbidden', 'You cannot act on this request', 403);
        }
    } else {
        // For regular approval: request must be TO this municipality
        if ((int)$req['toDRRMO'] !== (int)$toDrrmo) {
            $err('forbidden', 'You cannot act on this request', 403);
        }
        if ($currentStatus !== 'pending') {
            $err('invalid_state', 'Only pending requests can be updated', 409);
        }
    }

    // Ensure ENUM column has required values BEFORE starting transaction
    // ALTER TABLE will auto-commit any transaction, so do this first
    try {
        $colCheck = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND COLUMN_NAME = 'status'");
        $colCheck->execute();
        $colType = $colCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($colType && strpos(strtoupper($colType['COLUMN_TYPE'] ?? ''), 'ENUM') !== false) {
            $enumValues = $colType['COLUMN_TYPE'];
            $hasApprovedPending = stripos($enumValues, 'group_approved_pending') !== false;
            $hasRejectedPending = stripos($enumValues, 'group_rejected_pending') !== false;
            
            if (!$hasApprovedPending || !$hasRejectedPending) {
                // Need to alter ENUM - this will auto-commit any transaction
                if (preg_match("/ENUM\((.+)\)/i", $enumValues, $matches) && isset($matches[1])) {
                    $existingValues = $matches[1];
                    $valuesToAdd = [];
                    if (!$hasApprovedPending) {
                        $valuesToAdd[] = "'group_approved_pending'";
                    }
                    if (!$hasRejectedPending) {
                        $valuesToAdd[] = "'group_rejected_pending'";
                    }
                    $newValues = $existingValues . ',' . implode(',', $valuesToAdd);
                    $alterSql = "ALTER TABLE requests MODIFY COLUMN status ENUM($newValues)";
                    
                    try {
                        $alterResult = $pdo->exec($alterSql);
                        error_log("Successfully altered status ENUM BEFORE transaction. Added: " . implode(', ', $valuesToAdd));
                    } catch (PDOException $e) {
                        error_log("Failed to alter status ENUM before transaction: " . $e->getMessage() . " | SQL: " . $alterSql);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error checking/altering status ENUM before transaction: " . $e->getMessage());
    }
    
    // Fetch current user position and signature
    $userStmt = $pdo->prepare('SELECT position, signature FROM users WHERE userID = ? LIMIT 1');
    $userStmt->execute([$_SESSION['user_id']]);
    $userProfile = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $approverTitle = $userProfile['position'] ?? '';
    $approverSignature = $userProfile['signature'] ?? '';
    
    // Transaction: handle Head approval or regular approval
    $pdo->beginTransaction();
    try {
        if ($isHeadApproval) {
            // This is a Head approval request
            $approvalType = ($userRole === 'approving_authority') ? 'approved' : 'bypassed';
            if ($action === 'accept') {
                // Head approved: forward to provider municipality
                $originalToDRRMO = isset($req['originalToDRRMO']) && !empty($req['originalToDRRMO']) ? (int)$req['originalToDRRMO'] : null;
                
                if ($originalToDRRMO) {
                    if ($requestGroupId) {
                        // This is part of a group request - check if all requests in the group are evaluated
                        $groupCheckStmt = $pdo->prepare('SELECT requestID, status FROM requests WHERE requestGroupId = ?');
                        $groupCheckStmt->execute([$requestGroupId]);
                        $groupRequests = $groupCheckStmt->fetchAll(PDO::FETCH_ASSOC);
                        $__agentLog('H1', 'config/update_request_status.php:groupCheck(accept)', 'Loaded group requests for accept', [
                            'requestGroupId' => (string)$requestGroupId,
                            'count' => is_array($groupRequests) ? count($groupRequests) : 0,
                            'statuses' => array_map(function($r){ return ['id'=>(int)($r['requestID']??0),'status'=>(string)($r['status']??'')]; }, is_array($groupRequests)?$groupRequests:[]),
                        ]);
                        
                        // Fix old groups with empty status (from before ENUM fix)
                        // If items have empty status but are in approval dashboard, they were likely approved
                        // Set them to 'group_approved_pending' to fix the state
                        $fixedCount = 0;
                        $needsRefetch = false;
                        foreach ($groupRequests as $idx => $groupReq) {
                            $reqStatus = $groupReq['status'] ?? '';
                            // If status is empty and this request is in the approval dashboard (toDRRMO = current municipality)
                            // it means it was approved before but status update failed - fix it
                            if (empty($reqStatus) || $reqStatus === '') {
                                $fixStmt = $pdo->prepare('UPDATE requests SET status = ? WHERE requestID = ? AND toDRRMO = ?');
                                $fixResult = $fixStmt->execute(['group_approved_pending', $groupReq['requestID'], $toDrrmo]);
                                if ($fixResult && $fixStmt->rowCount() > 0) {
                                    $fixedCount++;
                                    $needsRefetch = true;
                                    error_log("Fixed empty status for request {$groupReq['requestID']} in group $requestGroupId - set to group_approved_pending");
                                }
                            }
                        }
                        if ($fixedCount > 0) {
                            error_log("Fixed $fixedCount items with empty status in group $requestGroupId");
                            // Re-fetch group requests to get updated statuses
                            if ($needsRefetch) {
                                $groupCheckStmt->execute([$requestGroupId]);
                                $groupRequests = $groupCheckStmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                        }
                        
                        // Check if ALL OTHER requests (excluding current) are already evaluated
                        // A request is evaluated if its status is NOT 'pending_head_approval'
                        $allOtherEvaluated = true;
                        $hasRejected = false;
                        
                        foreach ($groupRequests as $groupReq) {
                            // Skip the current request being processed
                            if ((int)$groupReq['requestID'] === (int)$requestId) {
                                continue;
                            }
                            
                            $status = strtolower($groupReq['status'] ?? '');
                            $rawStatus = $groupReq['status'] ?? '';
                            
                            // Check if this OTHER request is still pending evaluation
                            // Empty status means not yet evaluated - treat as pending
                            if (empty($rawStatus) || $rawStatus === '' || $status === 'pending_head_approval') {
                                $allOtherEvaluated = false;
                            }
                            
                            // Check if any OTHER request was rejected
                            if ($status === 'group_rejected_pending') {
                                $hasRejected = true;
                            }
                        }
                        
                        // If all OTHER requests are already evaluated, then after approving this one,
                        // ALL requests in the group will be evaluated
                        if ($allOtherEvaluated) {
                            // This is the last request to be evaluated
                            // First, mark this request as approved (intermediate status)
                            $upd = $pdo->prepare('UPDATE requests SET status = ?, head_approval_status = ?, head_approved_by = ?, approverTitle = ?, approverSignature = ? WHERE requestID = ?');
                            $upd->execute(['group_approved_pending', $approvalType, $_SESSION['full_name'] ?? 'System', $approverTitle, $approverSignature, $requestId]);
                            
                            // Now check if all requests in the group were approved (none were rejected)
                            // Re-fetch to get the updated status
                            $groupCheckStmt->execute([$requestGroupId]);
                            $groupRequests = $groupCheckStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $allApproved = true;
                            foreach ($groupRequests as $groupReq) {
                                $status = strtolower($groupReq['status'] ?? '');
                                // If any request in the group was rejected, the whole group should be rejected
                                if ($status === 'rejected' || $status === 'group_rejected_pending') {
                                    $allApproved = false;
                                    break;
                                }
                            }
                            
                            if ($allApproved) {
                                // All requests in the group were approved, move all to provider
                                $upd = $pdo->prepare('UPDATE requests SET toDRRMO = ?, status = ?, originalToDRRMO = NULL WHERE requestGroupId = ?');
                                $upd->execute([$originalToDRRMO, 'pending', $requestGroupId]);
                                $newStatus = 'pending'; // Status is now pending at provider
                                
                                error_log("Head approved all requests in group $requestGroupId. Forwarding to provider municipality $originalToDRRMO");
                            } else {
                                // At least one request in the group was rejected, reject the entire group
                                $upd = $pdo->prepare('UPDATE requests SET status = ?, responseDate = NOW() WHERE requestGroupId = ?');
                                $upd->execute(['rejected', $requestGroupId]);
                                $newStatus = 'rejected';
                                $__agentLog('H1', 'config/update_request_status.php:groupFinalize(accept)', 'Rejected entire group due to at least one rejected', [
                                    'requestGroupId' => (string)$requestGroupId,
                                ]);
                                
                                error_log("Group $requestGroupId rejected because at least one request was rejected");
                            }
                        } else {
                            // Not all OTHER requests evaluated yet - mark this one as group_approved_pending
                            // It will remain visible but read-only in the approval dashboard
                            // Note: ENUM column was already checked and altered before transaction started
                            $statusValue = 'group_approved_pending';
                            
                            $upd = $pdo->prepare('UPDATE requests SET status = ?, head_approval_status = ?, head_approved_by = ?, approverTitle = ?, approverSignature = ? WHERE requestID = ?');
                            $updateResult = $upd->execute([$statusValue, $approvalType, $_SESSION['full_name'] ?? 'System', $approverTitle, $approverSignature, $requestId]);
                            $rowsAffected = $upd->rowCount();
                            $errorInfo = $upd->errorInfo();
                            $newStatus = $statusValue;
                            
                            if (!$updateResult || $rowsAffected === 0 || !empty($errorInfo[2])) {
                                error_log("ERROR: UPDATE failed for request $requestId. Error: " . json_encode($errorInfo));
                                // Try direct SQL as fallback
                                try {
                                    $directSql = "UPDATE requests SET status = " . $pdo->quote($statusValue) . ", head_approval_status = " . $pdo->quote($approvalType) . ", head_approved_by = " . $pdo->quote($_SESSION['full_name'] ?? 'System') . ", approverTitle = " . $pdo->quote($approverTitle) . ", approverSignature = " . $pdo->quote($approverSignature) . " WHERE requestID = " . (int)$requestId;
                                    $directResult = $pdo->exec($directSql);
                                    error_log("Direct SQL UPDATE fallback result: rows affected = " . $directResult);
                                } catch (PDOException $e) {
                                    error_log("Direct SQL UPDATE fallback also failed: " . $e->getMessage());
                                }
                            }
                            
                            if (!$updateResult || $rowsAffected === 0) {
                                error_log("ERROR: Failed to update request $requestId status to group_approved_pending. Error: " . json_encode($upd->errorInfo()));
                            }
                            
                            // Count how many are evaluated (including this one we're about to set)
                            $evaluatedCount = 1; // This request will be evaluated
                            $totalCount = count($groupRequests);
                            foreach ($groupRequests as $groupReq) {
                                if ((int)$groupReq['requestID'] === (int)$requestId) {
                                    continue; // Skip current
                                }
                                $status = strtolower($groupReq['status'] ?? '');
                                $rawStatus = $groupReq['status'] ?? '';
                                // Count as evaluated only if status is NOT empty and NOT pending_head_approval
                                if (!empty($rawStatus) && $rawStatus !== '' && $status !== 'pending_head_approval') {
                                    $evaluatedCount++;
                                }
                            }
                            
                            error_log("Head approved request $requestId in group $requestGroupId. Progress: $evaluatedCount of $totalCount evaluated. Group remains in approval dashboard until all items are evaluated.");
                        }
                    } else {
                        // Single request (not in group) - forward to provider municipality with status 'pending'
                        $upd = $pdo->prepare('UPDATE requests SET toDRRMO = ?, status = ?, originalToDRRMO = NULL, head_approval_status = ?, head_approved_by = ?, approverTitle = ?, approverSignature = ? WHERE requestID = ?');
                        $upd->execute([$originalToDRRMO, 'pending', $approvalType, $_SESSION['full_name'] ?? 'System', $approverTitle, $approverSignature, $requestId]);
                        $newStatus = 'pending'; // Status is now pending at provider
                        
                        error_log("Head approved request $requestId. Forwarding to provider municipality $originalToDRRMO");
                    }
                } else {
                    // No original provider (shouldn't happen, but handle gracefully)
                    $upd = $pdo->prepare('UPDATE requests SET status = ?, responseDate = NOW(), head_approval_status = ?, head_approved_by = ?, approverTitle = ?, approverSignature = ? WHERE requestID = ?');
                    $upd->execute(['approved', $approvalType, $_SESSION['full_name'] ?? 'System', $approverTitle, $approverSignature, $requestId]);
                    $newStatus = 'approved';
                }
            } else {
                // Head rejected: set status to rejected
                if ($requestGroupId) {
                    // This is part of a group request - check if all requests in the group are evaluated
                    $groupCheckStmt = $pdo->prepare('SELECT requestID, status FROM requests WHERE requestGroupId = ?');
                    $groupCheckStmt->execute([$requestGroupId]);
                    $groupRequests = $groupCheckStmt->fetchAll(PDO::FETCH_ASSOC);
                    $__agentLog('H1', 'config/update_request_status.php:groupCheck(reject)', 'Loaded group requests for reject', [
                        'requestGroupId' => (string)$requestGroupId,
                        'count' => is_array($groupRequests) ? count($groupRequests) : 0,
                        'statuses' => array_map(function($r){ return ['id'=>(int)($r['requestID']??0),'status'=>(string)($r['status']??'')]; }, is_array($groupRequests)?$groupRequests:[]),
                    ]);
                    
                    // Check if ALL OTHER requests (excluding current) are already evaluated
                    $allOtherEvaluated = true;
                    
                        foreach ($groupRequests as $groupReq) {
                        // Skip the current request being processed
                        if ((int)$groupReq['requestID'] === (int)$requestId) {
                            continue;
                        }
                        
                        $status = strtolower($groupReq['status'] ?? '');
                        $rawStatus = $groupReq['status'] ?? '';
                        // Check if this OTHER request is still pending evaluation
                        // Empty status means not yet evaluated - treat as pending
                        if (empty($rawStatus) || $rawStatus === '' || $status === 'pending_head_approval') {
                            $allOtherEvaluated = false;
                            break;
                        }
                    }
                    
                    // If all OTHER requests are already evaluated, then after rejecting this one,
                    // ALL requests in the group will be evaluated, so reject the entire group
                    if ($allOtherEvaluated) {
                        // This is the last request to be evaluated
                        // First, mark this request as rejected (intermediate status)
                        $upd = $pdo->prepare('UPDATE requests SET status = ? WHERE requestID = ?');
                        $upd->execute(['group_rejected_pending', $requestId]);
                        
                        // Since at least one request was rejected, reject the entire group
                        $upd = $pdo->prepare('UPDATE requests SET status = ?, responseDate = NOW() WHERE requestGroupId = ?');
                        $upd->execute(['rejected', $requestGroupId]);
                        $newStatus = 'rejected';
                        $__agentLog('H1', 'config/update_request_status.php:groupFinalize(reject)', 'Rejected entire group (all evaluated after this reject)', [
                            'requestGroupId' => (string)$requestGroupId,
                        ]);
                        
                        error_log("Head rejected request $requestId in group $requestGroupId. All items evaluated - rejecting entire group.");
                    } else {
                        // Not all OTHER requests evaluated yet - mark this one as group_rejected_pending
                        // It will remain visible but read-only in the approval dashboard
                        // Note: ENUM column was already checked and altered before transaction started
                        $statusValue = 'group_rejected_pending';
                        
                        $upd = $pdo->prepare('UPDATE requests SET status = ? WHERE requestID = ?');
                        $updateResult = $upd->execute([$statusValue, $requestId]);
                        $rowsAffected = $upd->rowCount();
                        $errorInfo = $upd->errorInfo();
                        $newStatus = $statusValue;
                        
                        if (!$updateResult || $rowsAffected === 0 || !empty($errorInfo[2])) {
                            error_log("ERROR: UPDATE failed for request $requestId. Error: " . json_encode($errorInfo));
                            try {
                                $directSql = "UPDATE requests SET status = " . $pdo->quote($statusValue) . " WHERE requestID = " . (int)$requestId;
                                $directResult = $pdo->exec($directSql);
                                error_log("Direct SQL UPDATE fallback result: rows affected = " . $directResult);
                            } catch (PDOException $e) {
                                error_log("Direct SQL UPDATE fallback also failed: " . $e->getMessage());
                            }
                        }
                        
                        // Count how many are evaluated (including this one we're about to set)
                        $evaluatedCount = 1; // This request will be evaluated
                        $totalCount = count($groupRequests);
                            foreach ($groupRequests as $groupReq) {
                            if ((int)$groupReq['requestID'] === (int)$requestId) {
                                continue; // Skip current
                            }
                            $status = strtolower($groupReq['status'] ?? '');
                            $rawStatus = $groupReq['status'] ?? '';
                            // Count as evaluated only if status is NOT empty and NOT pending_head_approval
                            if (!empty($rawStatus) && $rawStatus !== '' && $status !== 'pending_head_approval') {
                                $evaluatedCount++;
                            }
                        }
                        
                        error_log("Head rejected request $requestId in group $requestGroupId. Progress: $evaluatedCount of $totalCount evaluated. Group remains in approval dashboard until all items are evaluated. Group will be rejected when all items are evaluated.");
                    }
                } else {
                    // Single request (not in group) - set status to rejected
                    $upd = $pdo->prepare('UPDATE requests SET status = ?, responseDate = NOW() WHERE requestID = ?');
                    $upd->execute(['rejected', $requestId]);
                    $newStatus = 'rejected';
                }
            }
        } else {
            // Regular approval (provider municipality approving/rejecting)
            $newStatus = $action === 'accept' ? 'approved' : 'rejected';
            
            if ($newStatus === 'approved') {
                // Deduct stock when provider approves
                $deduct = $pdo->prepare('UPDATE resources SET availableStock = availableStock - ? WHERE resourceID = ? AND availableStock >= ?');
                $deduct->execute([(int)$req['quantity'], (int)$req['resourceID'], (int)$req['quantity']]);
                if ($deduct->rowCount() === 0) {
                    $pdo->rollBack();
                    $err('insufficient_stock', 'Insufficient stock to accept', 409);
                }

                // Sync itemized units if present
                $itemCountStmt = $pdo->prepare("SELECT COUNT(*) FROM resource_items WHERE resourceID = ?");
                $itemCountStmt->execute([(int)$req['resourceID']]);
                $hasItems = $itemCountStmt->fetchColumn() > 0;

                if ($hasItems) {
                    $itemIDs = $body['dispatchedItems'] ?? [];
                    if (!is_array($itemIDs)) {
                        $itemIDs = [];
                    }

                    if (count($itemIDs) !== (int)$req['quantity']) {
                        $pdo->rollBack();
                        $err('items_required', 'You must select exactly ' . $req['quantity'] . ' itemized units for dispatch.', 400);
                    }
                    
                    // Verify that selected items belong to this resource and are Available
                    $placeholders = implode(',', array_fill(0, count($itemIDs), '?'));
                    $verifySql = "SELECT itemID, status FROM resource_items WHERE itemID IN ($placeholders) AND resourceID = ?";
                    $params = array_merge($itemIDs, [(int)$req['resourceID']]);
                    $verifyStmt = $pdo->prepare($verifySql);
                    $verifyStmt->execute($params);
                    $verifiedItems = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($verifiedItems) !== count($itemIDs)) {
                        $pdo->rollBack();
                        $err('invalid_items', 'One or more selected itemized units are invalid or do not belong to this resource.', 400);
                    }
                    
                    foreach ($verifiedItems as $vItem) {
                        if ($vItem['status'] !== 'Available') {
                            $pdo->rollBack();
                            $err('item_not_available', 'One or more selected units are no longer available.', 400);
                        }
                    }
                    
                    // Insert into request_dispatched_items
                    $insertRdi = $pdo->prepare("INSERT INTO request_dispatched_items (requestID, itemID) VALUES (?, ?)");
                    // Update status in resource_items
                    $updateRi = $pdo->prepare("UPDATE resource_items SET status = 'In Use' WHERE itemID = ?");
                    
                    foreach ($itemIDs as $itemId) {
                        $insertRdi->execute([$requestId, $itemId]);
                        $updateRi->execute([$itemId]);
                    }
                }
            }

            // Update status, responseDate, and approver details
            $upd = $pdo->prepare('UPDATE requests SET status = ?, responseDate = NOW(), approvingAuthority = ?, approverTitle = ?, approverSignature = ? WHERE requestID = ?');
            $upd->execute([$newStatus, $_SESSION['full_name'] ?? 'System', $approverTitle, $approverSignature, $requestId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    // Notify relevant parties using NotificationService
    try {
        require_once __DIR__ . '/notification_service.php';
        $notificationService = new NotificationService($pdo);
        
        // Only send notifications for actual status changes that should trigger notifications
        // Don't send notifications for intermediate statuses like 'group_approved_pending' or 'group_rejected_pending'
        if ($newStatus !== 'group_approved_pending' && $newStatus !== 'group_rejected_pending') {
            // Get updated request details
            $reqStmt = $pdo->prepare('SELECT r.fromDRRMO, r.toDRRMO, r.resourceID, res.resourceName, r.quantity FROM requests r JOIN resources res ON r.resourceID = res.resourceID WHERE r.requestID = ?');
            $reqStmt->execute([$requestId]);
            $reqDetails = $reqStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reqDetails) {
                $borrowerMunicipalityId = (int)$reqDetails['fromDRRMO'];
                
                if ($isHeadApproval) {
                    // Head approval/rejection: notify the admin who created the request
                    if ($action === 'accept') {
                        // Head approved: notify admin that request was forwarded
                        $providerMunicipalityId = (int)$reqDetails['toDRRMO'];
                        $notificationMessage = "Your request for {$reqDetails['resourceName']} (Qty: {$reqDetails['quantity']}) has been approved by Head of DRRMO and forwarded to the provider municipality.";
                    } else {
                        // Head rejected: notify admin
                        $notificationMessage = "Your request for {$reqDetails['resourceName']} (Qty: {$reqDetails['quantity']}) has been rejected by Head of DRRMO.";
                        if ($rejectionReason) {
                            $notificationMessage .= " Reason: {$rejectionReason}";
                        }
                        $providerMunicipalityId = (int)$toDrrmo; // Same municipality
                    }
                    
                    // Create notification for the admin
                    $notificationService->createRequestStatusNotification(
                        $borrowerMunicipalityId,
                        $providerMunicipalityId,
                        $reqDetails['resourceName'],
                        $reqDetails['quantity'],
                        $newStatus,
                        $requestId,
                        $notificationMessage
                    );
                    
                    // If Head approved, also notify the provider municipality
                    if ($action === 'accept') {
                        $notificationService->createRequestNotification(
                            $providerMunicipalityId,
                            $borrowerMunicipalityId,
                            $reqDetails['resourceName'],
                            $reqDetails['quantity'],
                            $requestId
                        );
                    }
                } else {
                    // Regular approval: notify borrower
                    $providerMunicipalityId = (int)$reqDetails['toDRRMO'];
                    
                    $notificationMessage = null;
                    if ($newStatus === 'rejected' && $rejectionReason) {
                        $notificationMessage = "Your request for {$reqDetails['resourceName']} (Qty: {$reqDetails['quantity']}) has been rejected. Reason: {$rejectionReason}";
                    }
                    
                    $notificationService->createRequestStatusNotification(
                        $borrowerMunicipalityId,
                        $providerMunicipalityId,
                        $reqDetails['resourceName'],
                        $reqDetails['quantity'],
                        $newStatus,
                        $requestId,
                        $notificationMessage
                    );
                }
            }
        }
    } catch (Throwable $ne) {
        error_log('[update_request_status][notif] ' . $ne->getMessage());
    }

    // Return additional context for frontend (include requestGroupId so UI can keep group visible/expanded)
    $responseData = [
        'requestId' => $requestId,
        'status' => $newStatus,
        'wasForwarded' => ($newStatus === 'pending' && !isset($requestGroupId)),
        'isGroupRequest' => !empty($requestGroupId),
        'requestGroupId' => !empty($requestGroupId) ? $requestGroupId : null,
        'groupStatus' => !empty($requestGroupId) ? ($newStatus === 'group_approved_pending' || $newStatus === 'group_rejected_pending' ? 'evaluated_but_waiting' : ($newStatus === 'pending' ? 'forwarded' : 'rejected')) : null
    ];
    
    $ok($responseData);
} catch (Throwable $e) {
    error_log('[update_request_status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'server_error', 'message' => 'Server error'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
}
?>



