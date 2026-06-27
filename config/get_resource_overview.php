<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Get user session data
    $userID = $_SESSION['userID'] ?? null;
    // Use drrmoID as the municipality identifier in this system
    $municipalityID = $_SESSION['municipality_id'] ?? ($_SESSION['drrmoID'] ?? null);
    $userRole = $_SESSION['userRole'] ?? null;

    $legacyLoggedIn = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    if (!$userID && !$legacyLoggedIn) {
        throw new Exception('User not authenticated');
    }

    // Optional municipality filter
    $where = '';
    $params = [];
    if (!empty($municipalityID)) {
        $where = 'WHERE r.drrmoID = ?';
        $params[] = $municipalityID;
    }

    $stmt = $pdo->prepare("
        SELECT 
            r.category,
            COUNT(r.resourceID) as resourceCount,
            COALESCE(SUM(r.availableStock),0) as totalStock,
            COALESCE(AVG(r.availableStock),0) as avgStock,
            COALESCE(MIN(r.availableStock),0) as minStock,
            COALESCE(MAX(r.availableStock),0) as maxStock
        FROM resources r
        $where
        GROUP BY r.category
        ORDER BY resourceCount DESC
    ");
    $stmt->execute($params);

    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get overall statistics (respect municipality filter)
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as totalResources,
            COALESCE(SUM(availableStock),0) as totalStock,
            SUM(CASE WHEN availableStock <= 10 THEN 1 ELSE 0 END) as lowStockCount,
            SUM(CASE WHEN availableStock > 0 THEN 1 ELSE 0 END) as availableCount
        FROM resources r
        $where
    ");
    $statsStmt->execute($params);

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate percentages
    $availabilityPercentage = $stats['totalResources'] > 0 ? 
        round(($stats['availableCount'] / $stats['totalResources']) * 100) : 0;

    // Determine total value (prefer real calc if unitValue column exists)
    $totalValue = 0.0;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM resources LIKE 'unitValue'");
        $hasValue = $check && $check->rowCount() > 0;
        if ($hasValue) {
            $valStmt = $pdo->prepare("SELECT COALESCE(SUM(availableStock * unitValue),0) as totalValue FROM resources r $where");
            $valStmt->execute($params);
            $valRow = $valStmt->fetch(PDO::FETCH_ASSOC);
            $totalValue = (float)($valRow['totalValue'] ?? 0);
        } else {
            $totalValue = ((float)$stats['totalStock']) * 100.0;
        }
    } catch (Exception $e2) {
        $totalValue = ((float)$stats['totalStock']) * 100.0;
    }

    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'categories' => array_map(function($cat) {
                return [
                    'categoryName' => $cat['category'],
                    'resourceCount' => (int)$cat['resourceCount'],
                    'totalStock' => (int)$cat['totalStock'],
                    'avgStock' => (float)$cat['avgStock'],
                    'minStock' => (int)$cat['minStock'],
                    'maxStock' => (int)$cat['maxStock']
                ];
            }, $categoryData),
            'stats' => [
                'totalResources' => (int)$stats['totalResources'],
                'totalStock' => (int)$stats['totalStock'],
                'lowStockCount' => (int)$stats['lowStockCount'],
                'availabilityPercentage' => $availabilityPercentage,
                'totalValue' => $totalValue
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
