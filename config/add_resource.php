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
    $minimumStock = isset($input['minimumStock']) ? (int)$input['minimumStock'] : 0;
    $storageLocation = isset($input['storageLocation']) && $input['storageLocation'] ? trim($input['storageLocation']) : null;

    // Check if editing existing resource
    if (isset($input['id']) && $input['id']) {
        // Update existing resource
        $stmt = $pdo->prepare('
            UPDATE resources 
            SET resourceName = ?, category = ?, subcategory = ?, unit = ?, description = ?, 
                totalStock = ?, availableStock = ?, minimumStock = ?, storageLocation = ?, 
                updatedAt = CURRENT_TIMESTAMP
            WHERE resourceID = ? AND drrmoID = ?
        ');
        
        $result = $stmt->execute([
            $resourceName, $category, $subcategory, $unit, $description,
            $totalStock, $availableStock, $minimumStock, $storageLocation,
            $input['id'], $userMunicipalityId
        ]);

        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Resource updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Resource not found or no changes made']);
        }
    } else {
        // Insert new resource
        $stmt = $pdo->prepare('
            INSERT INTO resources (drrmoID, resourceName, category, subcategory, unit, description, 
                                 totalStock, availableStock, minimumStock, storageLocation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $result = $stmt->execute([
            $userMunicipalityId, $resourceName, $category, $subcategory, $unit, $description,
            $totalStock, $availableStock, $minimumStock, $storageLocation
        ]);

        if ($result) {
            $resourceId = $pdo->lastInsertId();
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