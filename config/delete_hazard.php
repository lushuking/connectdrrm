<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

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
    $userID = $_SESSION['user_id'] ?? null;
    if (!$userID) {
        $err('bad_request', 'Missing user id', 400);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $hazardId = isset($body['hazardId']) ? (int)$body['hazardId'] : 0;
    if ($hazardId <= 0) {
        $err('bad_request', 'Invalid hazardId');
    }

    // Load hazard and validate ownership
    $stmt = $pdo->prepare('SELECT hazardID, drrmoID, reportedBy FROM hazards WHERE hazardID = ? LIMIT 1');
    $stmt->execute([$hazardId]);
    $hazard = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hazard) {
        $err('not_found', 'Hazard not found', 404);
    }
    
    // Check if user is the reporter or has admin privileges
    $isReporter = (int)$hazard['reportedBy'] === (int)$userID;
    $isAdmin = in_array(($_SESSION['user_type'] ?? ''), ['admin', 'emergency_coordinator'], true);
    $isSameMunicipality = (int)$hazard['drrmoID'] === (int)$drrmoID;
    
    if (!($isReporter || ($isAdmin && $isSameMunicipality))) {
        $err('forbidden', 'You can only delete your own hazards', 403);
    }

    // Remove associated images (best-effort)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `hazard_images` (
          `imageID` INT NOT NULL AUTO_INCREMENT,
          `hazardID` INT NOT NULL,
          `filePath` VARCHAR(500) NOT NULL,
          `uploadedAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`imageID`),
          KEY `idx_hazard_images_hazard` (`hazardID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $imgStmt = $pdo->prepare('SELECT filePath FROM hazard_images WHERE hazardID = ?');
        $imgStmt->execute([$hazardId]);
        $paths = $imgStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $delImgs = $pdo->prepare('DELETE FROM hazard_images WHERE hazardID = ?');
        $delImgs->execute([$hazardId]);

        $projectRoot = realpath(__DIR__ . '/..');
        if ($projectRoot) {
            foreach ($paths as $rel) {
                $rel = str_replace(['..', '\\'], ['', '/'], (string)$rel);
                $abs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
            // Remove hazard folder if empty
            $dir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'hazards' . DIRECTORY_SEPARATOR . (string)$hazardId;
            if (is_dir($dir)) {
                $files = @scandir($dir);
                if (is_array($files)) {
                    $files = array_values(array_diff($files, ['.', '..']));
                    if (count($files) === 0) {
                        @rmdir($dir);
                    }
                }
            }
        }
    } catch (Throwable $_) { /* ignore */ }

    // Delete hazard
    $del = $pdo->prepare('DELETE FROM hazards WHERE hazardID = ?');
    $del->execute([$hazardId]);

    $ok(['hazardId' => $hazardId]);
} catch (Throwable $e) {
    error_log('[delete_hazard] ' . $e->getMessage());
    $err('server_error', 'Server error', 500);
}
?>
