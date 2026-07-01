<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get current user's DRRMO ID
$drrmoID = $_SESSION['municipality_id'] ?? null;
if (!$drrmoID) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User DRRMO ID not found']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $resourceId = $input['id'] ?? null;
    $resourceName = $input['name'] ?? null;
    $category = $input['category'] ?? null;
    $subcategory = $input['subcategory'] ?? null;
    $unit = $input['unit'] ?? null;
    $description = $input['description'] ?? '';
    $totalStock = $input['totalStock'] ?? null;
    $availableStock = $input['availableStock'] ?? null;
    $damagedStock = $input['damagedStock'] ?? 0;
    $minimumStock = $input['minimumStock'] ?? 0;
    $storageLocation = $input['storageLocation'] ?? null;
    $plateNumber = $input['plateNumber'] ?? null;
    
    if (!$resourceId || !$resourceName || !$category || !$unit || $totalStock === null || $availableStock === null) {
        throw new Exception('Missing required fields');
    }
    
    if ($availableStock > $totalStock) {
        throw new Exception('Available stock cannot be greater than total stock');
    }
    
    if (($availableStock + $damagedStock) > $totalStock) {
        throw new Exception('Available stock + Damaged stock cannot be greater than total stock');
    }
    
    // Verify the resource belongs to the current user's DRRMO
    $checkSql = "SELECT resourceID FROM resources WHERE resourceID = ? AND drrmoID = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$resourceId, $drrmoID]);
    
    if (!$checkStmt->fetch()) {
        throw new Exception('Resource not found or access denied');
    }
    
    // Read current stock for transition checks (e.g., to out-of-stock)
    $curStmt = $pdo->prepare("SELECT availableStock FROM resources WHERE resourceID = ? AND drrmoID = ?");
    $curStmt->execute([$resourceId, $drrmoID]);
    $curRow = $curStmt->fetch(PDO::FETCH_ASSOC);
    $prevStock = isset($curRow['availableStock']) ? (int)$curRow['availableStock'] : null;

    // Update the resource
    $sql = "UPDATE resources SET 
            resourceName = ?, 
            category = ?, 
            subcategory = ?, 
            unit = ?, 
            description = ?, 
            totalStock = ?, 
            availableStock = ?, 
            damagedStock = ?, 
            minimumStock = ?, 
            storageLocation = ?,
            plateNumber = ?
            WHERE resourceID = ? AND drrmoID = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $resourceName,
        $category,
        $subcategory,
        $unit,
        $description,
        $totalStock,
        $availableStock,
        $damagedStock,
        $minimumStock,
        $storageLocation,
        $plateNumber,
        $resourceId,
        $drrmoID
    ]);
    
    if ($result) {
        // Sync itemized units
        if (isset($input['items']) && is_array($input['items'])) {
            $submittedItemIds = [];
            foreach ($input['items'] as $item) {
                $itemId = $item['id'] ?? null;
                $uniqueIdentifier = trim($item['uniqueIdentifier'] ?? '');
                $status = trim($item['status'] ?? 'Available');
                $storageLocation = trim($item['storageLocation'] ?? '');
                $conditionNotes = trim($item['conditionNotes'] ?? '');

                if ($uniqueIdentifier === '') {
                    continue; // Skip invalid entries
                }

                if ($itemId) {
                    // Update existing item
                    $itemStmt = $pdo->prepare('
                        UPDATE resource_items 
                        SET uniqueIdentifier = ?, status = ?, storageLocation = ?, conditionNotes = ?
                        WHERE itemID = ? AND resourceID = ?
                    ');
                    $itemStmt->execute([$uniqueIdentifier, $status, $storageLocation, $conditionNotes, $itemId, $resourceId]);
                    $submittedItemIds[] = $itemId;
                } else {
                    // Insert new item
                    $itemStmt = $pdo->prepare('
                        INSERT INTO resource_items (resourceID, uniqueIdentifier, status, storageLocation, conditionNotes)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $itemStmt->execute([$resourceId, $uniqueIdentifier, $status, $storageLocation, $conditionNotes]);
                    $submittedItemIds[] = $pdo->lastInsertId();
                }
            }

            // Delete any items that are no longer in the list (e.g. if total stock was decreased)
            if (!empty($submittedItemIds)) {
                $inQuery = implode(',', array_fill(0, count($submittedItemIds), '?'));
                $deleteStmt = $pdo->prepare("DELETE FROM resource_items WHERE resourceID = ? AND itemID NOT IN ($inQuery)");
                $deleteStmt->execute(array_merge([$resourceId], $submittedItemIds));
            } else {
                $deleteStmt = $pdo->prepare("DELETE FROM resource_items WHERE resourceID = ?");
                $deleteStmt->execute([$resourceId]);
            }
        }

        // Create out-of-stock notification using NotificationService
        try {
            require_once __DIR__ . '/notification_service.php';
            $notificationService = new NotificationService($pdo);
            
            $newQty = (int)$availableStock;
            if ($prevStock !== null && $prevStock > 0 && $newQty <= 0) {
                $userId = $_SESSION['user_id'] ?? null;
                if ($userId) {
                    $notificationService->createOutOfStockNotification($userId, $resourceName, $resourceId);
                }
            }
        } catch (Throwable $e) {
            // Fail silently for notifications to not block the main update
            error_log('[NOTIF][update_resource] ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Resource updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update resource');
    }
    
} catch (Exception $e) {
    error_log('Update resource error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating resource: ' . $e->getMessage()
    ]);
}
?>

