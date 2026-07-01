<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Auth check
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Get user's municipality ID
    $userMunicipalityId = $_SESSION['municipality_id'] ?? null;
    if (!$userMunicipalityId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Municipality not found']);
        exit;
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }

    // Validate required fields
    $requiredFields = ['name', 'category', 'unit', 'totalStock', 'availableStock'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit;
        }
    }

    // Validate stock values
    if ($input['availableStock'] > $input['totalStock']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Available stock cannot be greater than total stock']);
        exit;
    }

    // Prepare data
    $resourceName = trim($input['name']);
    $category = trim($input['category']);
    $subcategory = isset($input['subcategory']) && $input['subcategory'] ? trim($input['subcategory']) : null;
    $unit = trim($input['unit']);
    $description = isset($input['description']) ? trim($input['description']) : null;
    $totalStock = (int)$input['totalStock'];
    $availableStock = (int)$input['availableStock'];
    $damagedStock = isset($input['damagedStock']) ? (int)$input['damagedStock'] : 0;
    $minimumStock = isset($input['minimumStock']) ? (int)$input['minimumStock'] : 0;
    $storageLocation = isset($input['storageLocation']) && $input['storageLocation'] ? trim($input['storageLocation']) : null;
    $plateNumber = isset($input['plateNumber']) && $input['plateNumber'] ? trim($input['plateNumber']) : null;

    // Check if editing existing resource
    if (isset($input['id']) && $input['id']) {
        $resourceId = $input['id'];
        // Update existing resource
        $stmt = $pdo->prepare('
            UPDATE resources 
            SET resourceName = ?, category = ?, subcategory = ?, unit = ?, description = ?, 
                totalStock = ?, availableStock = ?, damagedStock = ?, minimumStock = ?, storageLocation = ?, plateNumber = ?,
                updatedAt = CURRENT_TIMESTAMP
            WHERE resourceID = ? AND drrmoID = ?
        ');
        
        $result = $stmt->execute([
            $resourceName, $category, $subcategory, $unit, $description,
            $totalStock, $availableStock, $damagedStock, $minimumStock, $storageLocation, $plateNumber,
            $resourceId, $userMunicipalityId
        ]);

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

        echo json_encode(['success' => true, 'message' => 'Resource updated successfully']);
    } else {
        // Insert new resource
        $stmt = $pdo->prepare('
            INSERT INTO resources (drrmoID, resourceName, category, subcategory, unit, description, 
                                 totalStock, availableStock, damagedStock, minimumStock, storageLocation, plateNumber)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $result = $stmt->execute([
            $userMunicipalityId, $resourceName, $category, $subcategory, $unit, $description,
            $totalStock, $availableStock, $damagedStock, $minimumStock, $storageLocation, $plateNumber
        ]);

        if ($result) {
            $resourceId = $pdo->lastInsertId();

            // Sync itemized units for new resource
            if (isset($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $item) {
                    $uniqueIdentifier = trim($item['uniqueIdentifier'] ?? '');
                    $status = trim($item['status'] ?? 'Available');
                    $storageLocation = trim($item['storageLocation'] ?? '');
                    $conditionNotes = trim($item['conditionNotes'] ?? '');

                    if ($uniqueIdentifier === '') {
                        continue;
                    }

                    $itemStmt = $pdo->prepare('
                        INSERT INTO resource_items (resourceID, uniqueIdentifier, status, storageLocation, conditionNotes)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $itemStmt->execute([$resourceId, $uniqueIdentifier, $status, $storageLocation, $conditionNotes]);
                }
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Resource added successfully',
                'resourceId' => $resourceId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to add resource']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>