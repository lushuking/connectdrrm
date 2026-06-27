<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Auth check (mirror other endpoints)
    if (!isset($_SESSION['userID'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    // Inspect resources table schema
    $colsStmt = $pdo->query("SHOW COLUMNS FROM resources");
    $cols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    $hasCategory = in_array('category', $cols, true);
    $hasType = in_array('type', $cols, true);
    $hasAvailableStock = in_array('availableStock', $cols, true);

    // Choose group field
    $groupField = $hasCategory ? 'category' : ($hasType ? 'type' : null);
    if ($groupField === null) {
        echo json_encode([ 'success' => true, 'data' => [ 'groupBy' => null, 'items' => [] ] ]);
        exit;
    }

    // Build query
    $sumExpr = $hasAvailableStock ? 'COALESCE(SUM(r.availableStock),0)' : '0';
    $sql = "SELECT r.`{$groupField}` AS label, COUNT(*) AS itemCount, {$sumExpr} AS totalStock FROM resources r GROUP BY r.`{$groupField}` ORDER BY itemCount DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(function($row) {
        return [
            'label' => (string)($row['label'] ?? 'Unspecified'),
            'itemCount' => (int)($row['itemCount'] ?? 0),
            'totalStock' => (int)($row['totalStock'] ?? 0)
        ];
    }, $rows ?: []);

    echo json_encode([
        'success' => true,
        'data' => [
            'groupBy' => $groupField,
            'items' => $items
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>


