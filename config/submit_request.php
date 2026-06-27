<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    // Log the raw input for debugging
    error_log('[submit_request] Raw JSON input: ' . $json);
    error_log('[submit_request] Decoded input: ' . print_r($input, true));
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Get session data
    if (!isset($_SESSION['municipality_id']) || !$_SESSION['municipality_id']) {
        throw new Exception('User session not found. Please log in again.');
    }
    if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
        throw new Exception('User ID not found. Please log in again.');
    }
    
    $fromDRRMO = $_SESSION['municipality_id'];
    $userRole = $_SESSION['role'] ?? 'drrmo_staff';
    
    // Combine expectedDurationNumber and expectedDurationUnit if sent separately
    if (isset($input['expectedDurationNumber']) && isset($input['expectedDurationUnit'])) {
        if (!empty($input['expectedDurationNumber']) && !empty($input['expectedDurationUnit'])) {
            $input['expectedDuration'] = $input['expectedDurationNumber'] . '-' . $input['expectedDurationUnit'];
        }
    }
    
    // Check if this is a multiple resources request
    $isMultipleResources = isset($input['resources']) && is_array($input['resources']) && count($input['resources']) > 0;
    
    if ($isMultipleResources) {
        // Handle multiple resources with grouping
        require_once __DIR__ . '/submit_multiple_requests.php';
        $result = handleMultipleResourceRequests($input, $pdo, $fromDRRMO, $userRole);
        echo json_encode($result);
        exit;
    }
    
    // Normalize input (for single resource)
    $normalized = [
        'resourceId' => $isMultipleResources ? ($input['resources'][0]['resourceId'] ?? null) : ($input['resourceId'] ?? null),
        'resourceName' => $isMultipleResources ? trim($input['resources'][0]['resourceName'] ?? '') : trim($input['resourceName'] ?? ''),
        'quantity' => $isMultipleResources ? intval($input['resources'][0]['quantity'] ?? 1) : intval($input['requestQuantity'] ?? $input['quantity'] ?? 1),
        'priority' => $isMultipleResources ? ($input['resources'][0]['priority'] ?? 'medium') : ($input['requestPriority'] ?? $input['priority'] ?? 'medium'),
        'notes' => $isMultipleResources ? ($input['resources'][0]['notes'] ?? '') : ($input['notes'] ?? '')
    ];
    
    error_log('[submit_request] Normalized values - resourceId: ' . ($normalized['resourceId'] ?? 'null') . ', resourceName: "' . $normalized['resourceName'] . '", quantity: ' . $normalized['quantity']);
    
    $resourceName = $normalized['resourceName'];
    $quantity = $normalized['quantity'];
    $priority = $normalized['priority'];
    $notes = $normalized['notes'];
    
    // Check if this is a direct resource request (PDRRMO) or a municipality request
    if (!empty($normalized['resourceId'])) {
        // Direct resource request (PDRRMO requesting from specific resource)
        $resourceId = intval($normalized['resourceId']);
        
        // Get resource details - handle NULL availableStock
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
            // Get resource details for better error message
            $debugSql = "SELECT resourceName, COALESCE(availableStock, 0) as availableStock FROM resources WHERE resourceID = ?";
            $debugStmt = $pdo->prepare($debugSql);
            $debugStmt->execute([$resourceId]);
            $debugResource = $debugStmt->fetch();
            
            if ($debugResource) {
                throw new Exception("Resource '{$debugResource['resourceName']}' has insufficient stock. Available: {$debugResource['availableStock']}, Needed: $quantity");
            } else {
                throw new Exception("Resource not found (ID: $resourceId)");
            }
        }
        
        $toDRRMO = $resource['drrmoID'];
        $resourceID = $resource['resourceID'];
        
    } else {
        // Municipality request - find a DRRMO that has the requested resource
        // Trim and normalize resource name for matching
        $resourceName = trim($resourceName);
        
        // First, check if any resources exist with this name (for debugging)
        $checkSql = "SELECT COUNT(*) as count FROM resources WHERE TRIM(resourceName) = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$resourceName]);
        $checkResult = $checkStmt->fetch();
        $resourceCount = $checkResult['count'] ?? 0;
        
        error_log("Looking for resource: '$resourceName', Quantity needed: $quantity, Found $resourceCount matching resources");
        
        // Use case-insensitive matching and handle NULL availableStock
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
            // Get more details for debugging
            $debugSql = "
                SELECT r.resourceID, r.resourceName, r.availableStock, d.name as drrmoName, r.drrmoID
                FROM resources r
                JOIN drrmo d ON r.drrmoID = d.drrmoID
                WHERE TRIM(r.resourceName) = ? AND r.drrmoID != ?
                ORDER BY r.availableStock DESC
                LIMIT 5
            ";
            $debugStmt = $pdo->prepare($debugSql);
            $debugStmt->execute([$resourceName, $fromDRRMO]);
            $debugResources = $debugStmt->fetchAll();
            
            $debugInfo = [];
            foreach ($debugResources as $dr) {
                $debugInfo[] = [
                    'name' => $dr['resourceName'],
                    'stock' => $dr['availableStock'],
                    'municipality' => $dr['drrmoName']
                ];
            }
            error_log("Debug - Resources found: " . json_encode($debugInfo));
            
            throw new Exception("No available DRRMO has sufficient stock of '$resourceName' (needed: $quantity). Found $resourceCount resource(s) with this name, but none have enough stock.");
        }
        
        $toDRRMO = $resource['drrmoID'];
        $resourceID = $resource['resourceID'];
    }
    
    // Store the original intended provider municipality
    $originalToDRRMO = $toDRRMO;
    $needsHeadApproval = false;
    
    // If user is drrmo_staff, check if there's a Head of DRRMO in the same municipality
    // If yes, route the request to the Head first for approval
    if ($userRole === 'drrmo_staff') {
        $headCheckSql = "SELECT userID FROM users WHERE drrmoID = ? AND role = 'approving_authority' LIMIT 1";
        $headCheckStmt = $pdo->prepare($headCheckSql);
        $headCheckStmt->execute([$fromDRRMO]);
        $headExists = $headCheckStmt->fetch();
        
        if ($headExists) {
            // Route to Head of DRRMO first (same municipality)
            $toDRRMO = $fromDRRMO;
            $needsHeadApproval = true;
            error_log("Request needs Head approval. Routing to Head first (municipality: $fromDRRMO)");
        }
    }
    
    // Build dynamic insert with optional fields from the modal
    $allFieldValues = [
        // Required/base columns
        'fromDRRMO' => $fromDRRMO,
        'toDRRMO' => $toDRRMO,
        'resourceID' => $resourceID,
        'quantity' => $quantity,
        'priority' => $priority,
        'notes' => $notes,
        // Store original intended provider if routing through Head
        'originalToDRRMO' => $needsHeadApproval ? $originalToDRRMO : null,
        // Optional modal fields (add if columns exist)
        'urgency' => $input['requestUrgency'] ?? null,
        'deliveryDate' => $input['deliveryDate'] ?? null,
        'deliveryLocation' => $input['deliveryLocation'] ?? null,
        'requestorName' => $input['requestorName'] ?? null,
        'requestorTitle' => $input['requestorTitle'] ?? null,
        'contactPhone' => $input['contactPhone'] ?? null,
        'contactEmail' => $input['contactEmail'] ?? null,
        'alternativeContact' => $input['alternativeContact'] ?? null,
        'purposeOfRequest' => $input['purposeOfRequest'] ?? null,
        'incidentReference' => $input['incidentReference'] ?? null,
        'expectedDuration' => $input['expectedDuration'] ?? null,
        'returnDate' => null, // Will be calculated below if not provided
        'transportationMethod' => $input['transportationMethod'] ?? null,
        'specialHandling' => $input['specialHandling'] ?? null,
        'approvingAuthority' => $input['approvingAuthority'] ?? null,
        'approverTitle' => $input['approverTitle'] ?? null,
        'requestorSignature' => $input['requestorSignature'] ?? null,
        'approverSignature' => $input['approverSignature'] ?? null,
        'budgetCode' => $input['budgetCode'] ?? null,
        'emergencyContact' => $input['emergencyContact'] ?? null,
        'requestingMunicipality' => $input['requestingMunicipality'] ?? null,
        'providerMunicipality' => $input['providerMunicipality'] ?? null,
        'resourceUnit' => $input['resourceUnit'] ?? null,
        'availableQuantity' => $input['availableQuantity'] ?? null,
    ];

    // Calculate returnDate if not provided, based on deliveryDate + expectedDuration
    if (empty($allFieldValues['returnDate']) && !empty($allFieldValues['deliveryDate']) && !empty($allFieldValues['expectedDuration'])) {
        $deliveryDate = $allFieldValues['deliveryDate'];
        $expectedDuration = $allFieldValues['expectedDuration'];
        
        // Parse datetime to date only (remove time portion if present)
        $deliveryTimestamp = strtotime($deliveryDate);
        if ($deliveryTimestamp !== false) {
            $daysToAdd = 7; // Default: 1 week
            
            $duration = strtolower(trim($expectedDuration));
            // Parse format: number-days, number-weeks, number-months
            if ($duration === 'indefinite') {
                $daysToAdd = 365; // 1 year
            } else {
                if (preg_match('/^(\d+)-(days|weeks|months)$/', $duration, $matches)) {
                    $number = (int)$matches[1];
                    $unit = $matches[2];
                    if ($unit === 'days') {
                        $daysToAdd = $number;
                    } elseif ($unit === 'weeks') {
                        $daysToAdd = $number * 7;
                    } elseif ($unit === 'months') {
                        $daysToAdd = $number * 30; // Approximate 30 days per month
                    }
                }
            }
            
            $returnTimestamp = strtotime("+{$daysToAdd} days", $deliveryTimestamp);
            if ($returnTimestamp !== false) {
                // Format as YYYY-MM-DD for database storage
                $allFieldValues['returnDate'] = date('Y-m-d', $returnTimestamp);
            }
        }
    }
    
    // If still empty, use the provided value (might be from form)
    if (empty($allFieldValues['returnDate']) && !empty($input['returnDate'])) {
        $allFieldValues['returnDate'] = $input['returnDate'];
    }

    // Get existing columns for 'requests' table
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests'");
    $colsStmt->execute();
    $existingCols = array_map(function($r){ return $r['COLUMN_NAME']; }, $colsStmt->fetchAll());
    
    // Add originalToDRRMO column if it doesn't exist and we need it
    if ($needsHeadApproval && !in_array('originalToDRRMO', $existingCols, true)) {
        try {
            $pdo->exec("ALTER TABLE requests ADD COLUMN originalToDRRMO INT NULL AFTER toDRRMO");
            $existingCols[] = 'originalToDRRMO';
            error_log("Added originalToDRRMO column to requests table");
        } catch (PDOException $e) {
            error_log("Could not add originalToDRRMO column: " . $e->getMessage());
            // Continue without the column - the request will still work
        }
    }

    // Always include requestDate and status as expressions
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

    // Append requestDate and status if those columns exist
    if (in_array('requestDate', $existingCols, true)) {
        $columns[] = 'requestDate';
    }
    if (in_array('status', $existingCols, true)) {
        $columns[] = 'status';
    }

    if (empty($columns)) {
        throw new Exception('No matching columns found in requests table');
    }

    $valuesSql = implode(', ', $placeholders);
    $columnsSql = implode(', ', $columns);

    // Build VALUES part with requestDate NOW() and status
    $valuesParts = $placeholders; // copy
    if (in_array('requestDate', $columns, true)) {
        $valuesParts[] = 'NOW()';
    }
    if (in_array('status', $columns, true)) {
        // Use appropriate status based on whether head approval is needed
        $statusValue = $needsHeadApproval ? 'pending_head_approval' : 'pending';
        $valuesParts[] = $pdo->quote($statusValue);
    }
    $finalValuesSql = implode(', ', $valuesParts);

    $sql = "INSERT INTO requests ($columnsSql) VALUES ($finalValuesSql)";
    error_log("SQL Query: " . $sql);
    error_log("Values: " . print_r($values, true));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($values);
    
    if (!$result) {
        error_log("Database insertion failed: " . print_r($stmt->errorInfo(), true));
        throw new Exception('Database insertion failed');
    }
    
    $requestID = $pdo->lastInsertId();
    
    // Create notifications
    try {
        require_once __DIR__ . '/notification_service.php';
        $notificationService = new NotificationService($pdo);
        
        if ($needsHeadApproval) {
            // Notify Head of DRRMO that a request needs approval
            $notificationService->createHeadApprovalNotification(
                $fromDRRMO, // Head's municipality (same as borrower)
                $resourceName,
                $quantity,
                $requestID
            );
            error_log("Request $requestID routed to Head of DRRMO for approval");
        } else {
            // Regular request: notify provider municipality
            $notificationService->createRequestNotification(
                $toDRRMO,  // Provider municipality
                $fromDRRMO, // Borrower municipality
                $resourceName,
                $quantity,
                $requestID
            );
        }
    } catch (Throwable $ne) {
        error_log('[submit_request][notification] ' . $ne->getMessage());
    }
    
    // Log the request
    error_log("New resource request created: ID $requestID, From DRRMO $fromDRRMO, To DRRMO $toDRRMO, Resource: $resourceName, Quantity: $quantity");

    echo json_encode([
        'success' => true,
        'message' => 'Request submitted successfully',
        'requestID' => $requestID,
        'toMunicipality' => $resource['drrmoName']
    ]);
    
} catch (Exception $e) {
    error_log('[submit_request] Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('[submit_request] Fatal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}