<?php
/**
 * Handle multiple resource requests with requestGroupId grouping
 */

function handleMultipleResourceRequests($input, $pdo, $fromDRRMO, $userRole) {
    try {
        error_log('[submit_multiple_requests] Processing ' . count($input['resources']) . ' resources');
        
        // Check/create requestGroupId column
        $checkGroupCol = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'requests' 
            AND COLUMN_NAME = 'requestGroupId'
        ");
        $checkGroupCol->execute();
        $hasRequestGroupId = $checkGroupCol->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$hasRequestGroupId) {
            try {
                $pdo->exec("ALTER TABLE requests ADD COLUMN requestGroupId VARCHAR(50) NULL AFTER requestID");
                $hasRequestGroupId = true;
                error_log("Added requestGroupId column to requests table");
            } catch (PDOException $e) {
                error_log("Could not add requestGroupId column: " . $e->getMessage());
            }
        }
        
        // Generate unique requestGroupId
        $requestGroupId = 'GRP-' . time() . '-' . bin2hex(random_bytes(4));
        error_log('[submit_multiple_requests] Generated requestGroupId: ' . $requestGroupId);
        
        $createdRequestIDs = [];
        $allResources = $input['resources'];
        $sharedFields = [
            'deliveryDate' => $input['deliveryDate'] ?? null,
            'deliveryLocation' => $input['deliveryLocation'] ?? null,
            'requestorName' => $input['requestorName'] ?? null,
            'requestorTitle' => $input['requestorTitle'] ?? null,
            'contactPhone' => $input['contactPhone'] ?? null,
            'contactEmail' => $input['contactEmail'] ?? null,
            'purposeOfRequest' => $input['purposeOfRequest'] ?? null,
            'expectedDuration' => $input['expectedDuration'] ?? null,
            'transportationMethod' => $input['transportationMethod'] ?? null,
            'approvingAuthority' => $input['approvingAuthority'] ?? null,
            'approverTitle' => $input['approverTitle'] ?? null,
        ];
        
        // Check if Head approval is needed
        $needsHeadApproval = false;
        if ($userRole === 'drrmo_staff') {
            $headCheckSql = "SELECT userID FROM users WHERE drrmoID = ? AND role = 'approving_authority' LIMIT 1";
            $headCheckStmt = $pdo->prepare($headCheckSql);
            $headCheckStmt->execute([$fromDRRMO]);
            $headExists = $headCheckStmt->fetch();
            if ($headExists) {
                $needsHeadApproval = true;
            }
        }
        
        // Get existing columns
        $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests'");
        $colsStmt->execute();
        $existingCols = array_map(function($r){ return $r['COLUMN_NAME']; }, $colsStmt->fetchAll());
        
        $providerMunicipalityName = null;
        
        // Process each resource
        foreach ($allResources as $resourceData) {
            $resourceId = $resourceData['resourceId'] ?? null;
            $resourceName = trim($resourceData['resourceName'] ?? '');
            $quantity = intval($resourceData['quantity'] ?? 1);
            $priority = $resourceData['priority'] ?? 'medium';
            $notes = $resourceData['notes'] ?? '';
            
            // Find the resource and get provider municipality
            $toDRRMO = null;
            $actualResourceID = null;
            $originalToDRRMO = null;
            
            if (!empty($resourceId)) {
                $resource_sql = "
                    SELECT r.drrmoID, r.resourceID, COALESCE(r.availableStock, 0) as availableStock, d.name as drrmoName
                    FROM resources r
                    JOIN drrmo d ON r.drrmoID = d.drrmoID
                    WHERE r.resourceID = ? AND COALESCE(r.availableStock, 0) >= ?
                ";
                $resource_stmt = $pdo->prepare($resource_sql);
                $resource_stmt->execute([$resourceId, $quantity]);
                $resource = $resource_stmt->fetch();
                
                if (!$resource) {
                    throw new Exception("Resource ID $resourceId has insufficient stock or not found");
                }
                
                $toDRRMO = $resource['drrmoID'];
                $actualResourceID = $resource['resourceID'];
                $originalToDRRMO = $toDRRMO;
                if (!$providerMunicipalityName) {
                    $providerMunicipalityName = $resource['drrmoName'];
                }
            } else {
                // Find by name
                $resource_sql = "
                    SELECT r.drrmoID, r.resourceID, COALESCE(r.availableStock, 0) as availableStock, d.name as drrmoName
                    FROM resources r
                    JOIN drrmo d ON r.drrmoID = d.drrmoID
                    WHERE TRIM(r.resourceName) = ? 
                    AND COALESCE(r.availableStock, 0) >= ? 
                    AND r.drrmoID != ?
                    ORDER BY COALESCE(r.availableStock, 0) DESC
                    LIMIT 1
                ";
                $resource_stmt = $pdo->prepare($resource_sql);
                $resource_stmt->execute([$resourceName, $quantity, $fromDRRMO]);
                $resource = $resource_stmt->fetch();
                
                if (!$resource) {
                    throw new Exception("No available DRRMO has sufficient stock of '$resourceName' (needed: $quantity)");
                }
                
                $toDRRMO = $resource['drrmoID'];
                $actualResourceID = $resource['resourceID'];
                $originalToDRRMO = $toDRRMO;
                if (!$providerMunicipalityName) {
                    $providerMunicipalityName = $resource['drrmoName'];
                }
            }
            
            // Route to Head if needed
            $finalToDRRMO = $needsHeadApproval ? $fromDRRMO : $toDRRMO;
            
            // Build field values
            $allFieldValues = [
                'fromDRRMO' => $fromDRRMO,
                'toDRRMO' => $finalToDRRMO,
                'resourceID' => $actualResourceID,
                'quantity' => $quantity,
                'priority' => $priority,
                'notes' => $notes,
                'originalToDRRMO' => $needsHeadApproval ? $originalToDRRMO : null,
                'requestGroupId' => $requestGroupId,
            ];
            
            // Add shared fields
            foreach ($sharedFields as $key => $value) {
                if (in_array($key, $existingCols, true)) {
                    $allFieldValues[$key] = $value;
                }
            }
            
            // Calculate returnDate if needed
            if (empty($allFieldValues['returnDate']) && !empty($allFieldValues['deliveryDate']) && !empty($allFieldValues['expectedDuration'])) {
                $deliveryDate = $allFieldValues['deliveryDate'];
                $expectedDuration = $allFieldValues['expectedDuration'];
                $deliveryTimestamp = strtotime($deliveryDate);
                if ($deliveryTimestamp !== false) {
                    $daysToAdd = 7;
                    $duration = strtolower(trim($expectedDuration));
                    if ($duration === 'indefinite') {
                        $daysToAdd = 365;
                    } else {
                        if (preg_match('/^(\d+)\s+(days?|weeks?|months?)$/', $duration, $matches)) {
                            $number = (int)$matches[1];
                            $unit = $matches[2];
                            if (strpos($unit, 'day') === 0) {
                                $daysToAdd = $number;
                            } elseif (strpos($unit, 'week') === 0) {
                                $daysToAdd = $number * 7;
                            } elseif (strpos($unit, 'month') === 0) {
                                $daysToAdd = $number * 30;
                            }
                        }
                    }
                    $returnTimestamp = strtotime("+{$daysToAdd} days", $deliveryTimestamp);
                    if ($returnTimestamp !== false && in_array('returnDate', $existingCols, true)) {
                        $allFieldValues['returnDate'] = date('Y-m-d', $returnTimestamp);
                    }
                }
            }
            
            // Build INSERT query
            $columns = [];
            $placeholders = [];
            $values = [];
            
            foreach ($allFieldValues as $col => $val) {
                if (in_array($col, $existingCols, true)) {
                    $columns[] = $col;
                    $placeholders[] = '?';
                    $values[] = $val;
                }
            }
            
            if (in_array('requestDate', $existingCols, true)) {
                $columns[] = 'requestDate';
            }
            if (in_array('status', $existingCols, true)) {
                $columns[] = 'status';
            }
            
            if (empty($columns)) {
                throw new Exception('No matching columns found in requests table');
            }
            
            $valuesParts = $placeholders;
            if (in_array('requestDate', $columns, true)) {
                $valuesParts[] = 'NOW()';
            }
            if (in_array('status', $columns, true)) {
                // Use appropriate status based on whether head approval is needed
                $statusValue = $needsHeadApproval ? 'pending_head_approval' : 'pending';
                $valuesParts[] = $pdo->quote($statusValue);
            }
            
            $columnsSql = implode(', ', $columns);
            $finalValuesSql = implode(', ', $valuesParts);
            $sql = "INSERT INTO requests ($columnsSql) VALUES ($finalValuesSql)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($values);
            
            if (!$result) {
                throw new Exception('Database insertion failed for resource: ' . $resourceName);
            }
            
            $requestID = $pdo->lastInsertId();
            $createdRequestIDs[] = $requestID;
            
            error_log('[submit_multiple_requests] Created request ID: ' . $requestID . ' for resource: ' . $resourceName);
        }
        
        // Create notifications
        try {
            require_once __DIR__ . '/notification_service.php';
            $notificationService = new NotificationService($pdo);
            
            if ($needsHeadApproval) {
                // Notify Head once for the group
                $totalQuantity = array_sum(array_column($allResources, 'quantity'));
                $resourceNames = array_map(function($r) { return $r['resourceName']; }, $allResources);
                $resourcesList = implode(', ', array_slice($resourceNames, 0, 3));
                if (count($resourceNames) > 3) {
                    $resourcesList .= ' and ' . (count($resourceNames) - 3) . ' more';
                }
                
                $notificationService->createHeadApprovalNotification(
                    $fromDRRMO,
                    $resourcesList,
                    $totalQuantity,
                    $createdRequestIDs[0] // Use first request ID for href
                );
            } else {
                // Notify provider for each resource
                // Note: We need to get the provider for each resource
                // For now, notify once with first resource details
                if (count($createdRequestIDs) > 0) {
                    $firstResource = $allResources[0];
                    $notificationService->createRequestNotification(
                        $toDRRMO ?? $fromDRRMO,
                        $fromDRRMO,
                        $firstResource['resourceName'],
                        array_sum(array_column($allResources, 'quantity')),
                        $createdRequestIDs[0]
                    );
                }
            }
        } catch (Throwable $ne) {
            error_log('[submit_multiple_requests][notification] ' . $ne->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => 'All requests submitted successfully',
            'requestID' => $createdRequestIDs[0] ?? null, // For backward compatibility
            'requestIDs' => $createdRequestIDs,
            'requestGroupId' => $requestGroupId,
            'count' => count($createdRequestIDs),
            'toMunicipality' => $providerMunicipalityName ?? 'Unknown'
        ];

        return $response;
        
    } catch (Exception $e) {
        error_log('[submit_multiple_requests] Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

