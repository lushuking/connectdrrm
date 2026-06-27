<?php
// Start output buffering to prevent any accidental output
if (!ob_get_level()) {
    ob_start();
}

session_start();

// Suppress errors that might break JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/db.php';
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => ['code' => 'init_error', 'message' => 'Failed to initialize'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
}

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json; charset=utf-8');

// Allow both DELETE and POST methods (some servers don't handle DELETE properly)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'DELETE' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed. Use DELETE or POST.'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
}

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
    if (!isLoggedIn()) {
        $err('unauthorized', 'Not authenticated', 401);
    }
    $drrmoID = $_SESSION['municipality_id'] ?? null;
    if (!$drrmoID) {
        $err('bad_request', 'Missing municipality id', 400);
    }

    // Get request body - try php://input first, fallback to $_POST for POST requests
    $rawInput = file_get_contents('php://input');
    $body = [];
    
    if (!empty($rawInput)) {
        $body = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $err('bad_request', 'Invalid JSON in request body');
        }
        if (empty($body) || !is_array($body)) {
            $body = [];
        }
    } elseif ($method === 'POST' && !empty($_POST)) {
        $body = $_POST;
    }
    
    $resourceId = isset($body['id']) ? (int)$body['id'] : 0;
    if ($resourceId <= 0) {
        $err('bad_request', 'Invalid resource id');
    }

    // Load resource and validate ownership
    $stmt = $pdo->prepare('SELECT resourceID, drrmoID FROM resources WHERE resourceID = ? LIMIT 1');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resource) {
        $err('not_found', 'Resource not found', 404);
    }
    
    if ((int)$resource['drrmoID'] !== (int)$drrmoID) {
        $err('forbidden', 'You can only delete your own resources', 403);
    }

    // Check if resource is referenced in any requests
    $checkRequests = $pdo->prepare('SELECT COUNT(*) as count FROM requests WHERE resourceID = ?');
    $checkRequests->execute([$resourceId]);
    $requestCount = $checkRequests->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($requestCount > 0) {
        $err('cannot_delete', 'Cannot delete resource: This resource is currently referenced in ' . $requestCount . ' request(s). Please resolve or cancel these requests first.', 409);
    }

    // Delete resource
    try {
        $del = $pdo->prepare('DELETE FROM resources WHERE resourceID = ?');
        $del->execute([$resourceId]);
        
        // Check for PDO errors
        if ($del->errorCode() !== '00000') {
            $errorInfo = $del->errorInfo();
            // Get more specific error message
            $errorMessage = $errorInfo[2] ?? 'Database error occurred';
            
            // Check if it's a foreign key constraint error
            if (strpos($errorMessage, 'foreign key constraint') !== false || strpos($errorMessage, 'Cannot delete') !== false) {
                $err('cannot_delete', 'Cannot delete resource: This resource is currently being used in active requests. Please resolve these requests first.', 409);
            }
            
            $err('database_error', 'Database error: ' . $errorMessage, 500);
        }
        
        // Verify deletion was successful
        $rowsAffected = $del->rowCount();
        
        if ($rowsAffected === 0) {
            $err('delete_failed', 'Resource was not deleted. It may have already been removed.', 500);
        }

        $ok(['id' => $resourceId]);
    } catch (PDOException $e) {
        $errorMessage = $e->getMessage();
        
        // Check if it's a foreign key constraint error
        if (strpos($errorMessage, 'foreign key constraint') !== false || 
            strpos($errorMessage, 'Cannot delete') !== false ||
            strpos($errorMessage, 'a foreign key constraint fails') !== false) {
            $err('cannot_delete', 'Cannot delete resource: This resource is currently being used in active requests. Please resolve or cancel these requests first.', 409);
        }
        
        $err('database_error', 'Database error occurred while deleting resource: ' . $errorMessage, 500);
    }
} catch (Throwable $e) {
    // Make sure we output valid JSON even on error
    ob_clean();
    $err('server_error', 'An error occurred while deleting the resource. Please try again.', 500);
}
?>
