<?php
// Upload user signature and store as base64 in users table
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

try {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID not found']);
        exit;
    }

    // Check if signature is uploaded or provided as base64
    $signature_data = null;
    
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        // Validate file type
        $tmp = $_FILES['signature']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        
        if (!str_starts_with($mime, 'image/')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Only image files allowed']);
            exit;
        }
        
        // Validate file size (2MB max)
        if ($_FILES['signature']['size'] > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'File size must be less than 2MB']);
            exit;
        }
        
        // Read file and convert to base64
        $image_data = file_get_contents($tmp);
        $signature_data = 'data:' . $mime . ';base64,' . base64_encode($image_data);
    } elseif (isset($_POST['signature_base64']) && !empty($_POST['signature_base64'])) {
        $signature_data = $_POST['signature_base64'];
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Signature is required']);
        exit;
    }

    // Check if signature column exists, create if not
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature'")->fetch();
        if (!$checkColumn) {
            $pdo->exec("ALTER TABLE users ADD COLUMN signature TEXT NULL");
        }
    } catch (Exception $e) {
        error_log('Could not create signature column: ' . $e->getMessage());
    }

    // Update user signature
    $update_sql = "UPDATE users SET signature = ? WHERE userID = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $result = $update_stmt->execute([$signature_data, $user_id]);

    if (!$result) {
        throw new Exception('Failed to update signature');
    }

    echo json_encode(['success' => true, 'message' => 'Signature uploaded successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


