<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$ts = (int)(microtime(true) * 1000);

function respond_ok($data, $code = 200) {
    global $ts; http_response_code($code);
    echo json_encode([ 'success' => true, 'data' => $data, 'meta' => [ 'ts' => $ts ] ]);
    exit;
}
function respond_err($code, $message) {
    global $ts; http_response_code($code);
    echo json_encode([ 'success' => false, 'error' => [ 'code' => 'request_failed', 'message' => $message ], 'meta' => [ 'ts' => $ts ] ]);
    exit;
}

try {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        respond_err(401, 'Not authenticated');
    }

    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    $municipalityId = isset($body['municipalityId']) ? (int)$body['municipalityId'] : 0;
    if ($municipalityId <= 0) {
        // Default to current user's DRRMO/municipality if not provided
        $sessionMunicipality = $_SESSION['municipality_id'] ?? ($_SESSION['drrmoID'] ?? 0);
        $municipalityId = (int)$sessionMunicipality;
    }
    if ($municipalityId <= 0) {
        respond_err(400, 'municipalityId is required');
    }

    $stmt = $pdo->prepare("SELECT r.resourceID, r.drrmoID, r.resourceName, r.category, r.subcategory, r.totalStock, r.availableStock, r.minimumStock, r.unit, r.description, r.storageLocation, r.updatedAt
                            FROM resources r WHERE r.drrmoID = ? ORDER BY r.resourceName");
    $stmt->execute([$municipalityId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resources = array_map(function($r) {
        return [
            'id' => (int)$r['resourceID'],
            'drrmoID' => (int)$r['drrmoID'],
            'name' => $r['resourceName'],
            'resourceName' => $r['resourceName'],
            'category' => $r['category'],
            'subcategory' => $r['subcategory'],
            'quantity' => (int)$r['availableStock'], // For display compatibility
            'totalStock' => (int)$r['totalStock'],
            'availableStock' => (int)$r['availableStock'],
            'minimumStock' => (int)$r['minimumStock'],
            'minQuantity' => (int)$r['minimumStock'], // For display compatibility
            'unit' => $r['unit'],
            'description' => $r['description'],
            'storageLocation' => $r['storageLocation'],
            'lastUpdated' => $r['updatedAt'] ?? date('Y-m-d H:i:s')
        ];
    }, $rows ?: []);

    respond_ok([ 'resources' => $resources ]);
} catch (Throwable $e) {
    error_log('[get_resources_by_municipality] ' . $e->getMessage());
    respond_err(500, 'Server error');
}
?>




