<?php
// Upload municipality logo and persist URL in drrmo.logo_url
// Expects multipart/form-data with field name "logo"
// Returns JSON { success: true, url: "assets/logos/{drrmoID}.ext" }

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

try {
    if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => ['code' => 'unauthorized', 'message' => 'Not logged in']]);
        exit;
    }

    // Resolve drrmoID for current user
    $drrmoID = $_SESSION['municipality_id'] ?? null;
    if (!$drrmoID && isset($_SESSION['user_id'])) {
        try {
            $q = $pdo->prepare('SELECT municipalityID FROM users WHERE userID = ? LIMIT 1');
            $q->execute([$_SESSION['user_id']]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['municipalityID'])) $drrmoID = $row['municipalityID'];
        } catch (Exception $e) {}
    }
    if (!$drrmoID) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'missing_drrmo', 'message' => 'Municipality (DRRMO) ID not found in session']]);
        exit;
    }

    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'upload_missing', 'message' => 'Logo file is required']]);
        exit;
    }

    // Validate file type
    $tmp = $_FILES['logo']['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/svg+xml' => 'svg'];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'invalid_type', 'message' => 'Only PNG, JPG, or SVG allowed']]);
        exit;
    }

    $ext = $allowed[$mime];

    // Ensure destination directory
    $root = realpath(__DIR__ . '/..'); // project root
    $destDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logos';
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new RuntimeException('Failed to create logos directory');
        }
    }

    // Destination path (overwrite per DRRMO)
    $filename = $drrmoID . '.' . $ext;
    $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;

    // If we are overwriting with different extension, remove old files for this drrmoID
    foreach (glob($destDir . DIRECTORY_SEPARATOR . $drrmoID . '.*') as $old) {
        @unlink($old);
    }

    if (!move_uploaded_file($tmp, $destPath)) {
        throw new RuntimeException('Failed to move uploaded file');
    }

    // Build relative URL for storage
    $relativeUrl = 'assets/logos/' . $filename;

    // Ensure drrmo has logo_url column, add if missing (best-effort)
    try {
        $pdo->query("ALTER TABLE drrmo ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) NULL");
    } catch (Exception $e) {
        // For MySQL versions without IF NOT EXISTS, attempt manual check
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM drrmo LIKE 'logo_url'")->fetchAll();
            if (!$cols) {
                $pdo->query("ALTER TABLE drrmo ADD COLUMN logo_url VARCHAR(255) NULL");
            }
        } catch (Exception $ignored) {}
    }

    // Persist URL
    $upd = $pdo->prepare('UPDATE drrmo SET logo_url = ? WHERE drrmoID = ?');
    $upd->execute([$relativeUrl, $drrmoID]);

    echo json_encode(['success' => true, 'url' => $relativeUrl]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'server_error', 'message' => $e->getMessage()]]);
}
