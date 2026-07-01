<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    // Get current user's DRRMO ID
    $currentUserDRRMO = $_SESSION['municipality_id'] ?? null;
    if (!$currentUserDRRMO) {
        throw new Exception('User DRRMO ID not found');
    }
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    // Allow client to specify limit within safe bounds
    $limitParam = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $limit = ($limitParam >= 5 && $limitParam <= 50) ? $limitParam : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    // Build the SQL query with filters
    $sql = "
        SELECT 
            r.resourceID,
            r.resourceName,
            r.totalStock,
            r.availableStock,
            r.damagedStock,
            r.minimumStock,
            r.unit,
            r.category,
            r.subcategory,
            r.description,
            r.storageLocation,
            r.plateNumber,
            d.name as municipality,
            d.drrmoID,
            CASE 
                WHEN r.availableStock > 0 AND NOT (r.availableStock = 1 AND r.damagedStock = 1) THEN 'Available'
                ELSE 'Unavailable'
            END as status,
            (
                SELECT MIN(req.returnDate)
                FROM requests req
                WHERE req.resourceID = r.resourceID
                  AND req.status IN ('approved', 'fulfilled', 'return pending')
                  AND req.returnedAt IS NULL
                  AND req.returnDate IS NOT NULL
            ) AS nextAvailableDate
        FROM resources r
        JOIN drrmo d ON r.drrmoID = d.drrmoID
        WHERE r.drrmoID != ?
    ";
    
    $params = [$currentUserDRRMO];
    
    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (r.resourceName LIKE ? OR d.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Add category filter
    if (!empty($category)) {
        $sql .= " AND r.category = ?";
        $params[] = $category;
    }
    
    // Add status filter (two-tier)
    if (!empty($status)) {
        if ($status === 'Available') {
            $sql .= " AND r.availableStock > 0 AND NOT (r.availableStock = 1 AND r.damagedStock = 1)";
        } elseif ($status === 'Unavailable') {
            $sql .= " AND (r.availableStock = 0 OR (r.availableStock = 1 AND r.damagedStock = 1))";
        }
    }
    
    // Add ordering
    $orderSql = "
        ORDER BY 
            CASE 
                WHEN r.availableStock > 0 AND NOT (r.availableStock = 1 AND r.damagedStock = 1) THEN 1
                ELSE 2
            END,
            r.resourceName, 
            d.name
    ";
    
    // Count total matching rows (single fast query)
    $countSql = "SELECT COUNT(*) FROM resources r JOIN drrmo d ON r.drrmoID = d.drrmoID WHERE r.drrmoID != ?";
    $countParams = [$currentUserDRRMO];
    if (!empty($search)) {
        $countSql .= " AND (r.resourceName LIKE ? OR d.name LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }
    if (!empty($category)) {
        $countSql .= " AND r.category = ?";
        $countParams[] = $category;
    }
    if (!empty($status)) {
        if ($status === 'Available') {
            $countSql .= " AND r.availableStock > 0 AND NOT (r.availableStock = 1 AND r.damagedStock = 1)";
        } elseif ($status === 'Unavailable') {
            $countSql .= " AND (r.availableStock = 0 OR (r.availableStock = 1 AND r.damagedStock = 1))";
        }
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalResources = (int) $countStmt->fetchColumn();
    
    // Fetch only the current page (database-level pagination)
    $sql .= $orderSql . " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $paginatedResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resourceIds = array_column($paginatedResources, 'resourceID');
    $itemsByResource = [];
    if (!empty($resourceIds)) {
        $inQuery = implode(',', array_fill(0, count($resourceIds), '?'));
        $itemStmt = $pdo->prepare("SELECT itemID, resourceID, uniqueIdentifier, status, storageLocation, conditionNotes FROM resource_items WHERE resourceID IN ($inQuery)");
        $itemStmt->execute($resourceIds);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $itemsByResource[$item['resourceID']][] = [
                'id' => (int)$item['itemID'],
                'uniqueIdentifier' => $item['uniqueIdentifier'],
                'status' => $item['status'],
                'storageLocation' => $item['storageLocation'],
                'conditionNotes' => $item['conditionNotes']
            ];
        }
    }
    
    // Calculate pagination info
    $totalPages = $totalResources > 0 ? (int) ceil($totalResources / $limit) : 1;
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    // Format the data for the frontend
    $formattedResources = array_map(function($resource) use ($itemsByResource) {
        return [
            'id' => $resource['resourceID'],
            'name' => $resource['resourceName'],
            'resourceName' => $resource['resourceName'],
            'municipality' => $resource['municipality'],
            'totalStock' => $resource['totalStock'],
            'availableStock' => $resource['availableStock'],
            'damagedStock' => $resource['damagedStock'],
            'minimumStock' => $resource['minimumStock'],
            'unit' => $resource['unit'],
            'category' => $resource['category'],
            'subcategory' => $resource['subcategory'],
            'description' => $resource['description'],
            'storageLocation' => $resource['storageLocation'],
            'plateNumber' => $resource['plateNumber'],
            'items' => $itemsByResource[$resource['resourceID']] ?? [],
            'status' => $resource['status'],
            'drrmoID' => $resource['drrmoID'],
            'nextAvailableDate' => $resource['nextAvailableDate'] ?? null
        ];
    }, $paginatedResources);
    
    echo json_encode([
        'success' => true,
        'resources' => $formattedResources,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResources' => $totalResources,
            'count' => count($formattedResources),
            'hasNextPage' => $hasNextPage,
            'hasPrevPage' => $hasPrevPage,
            'limit' => $limit
        ],
        'count' => count($formattedResources)
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching resources: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching resources: ' . $e->getMessage()
    ]);
}
?>

