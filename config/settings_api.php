<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

try {

    if ($action === 'get_account_info') {
        $stmt = $pdo->prepare("SELECT userID, email, fullName, position, contactNumber, role, drrmoID FROM users WHERE userID = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Get municipality name
        $muni_name = null;
        if ($user['drrmoID']) {
            $muni_stmt = $pdo->prepare("SELECT name FROM drrmo WHERE drrmoID = ? LIMIT 1");
            $muni_stmt->execute([$user['drrmoID']]);
            $muni = $muni_stmt->fetch();
            $muni_name = $muni['name'] ?? null;
        }

        // Map role to display name
        $role_display = [
            'emergency_coordinator' => 'PDRRMO Coordinator',
            'drrmo_staff'           => 'Admin / DRRMO Officer',
            'approving_authority'   => 'Head of DRRMO',
            'admin'                 => 'System Administrator',
        ];

        echo json_encode([
            'success' => true,
            'data'    => [
                'userID'        => $user['userID'],
                'email'         => $user['email'],
                'fullName'      => $user['fullName'] ?? '',
                'position'      => $user['position'] ?? '',
                'contactNumber' => $user['contactNumber'] ?? '',
                'role'          => $user['role'],
                'roleDisplay'   => $role_display[$user['role']] ?? $user['role'],
                'municipality'  => $muni_name,
                'loginTime'     => $_SESSION['login_time'] ?? null,
                'lastActivity'  => $_SESSION['last_activity'] ?? null,
            ]
        ]);

    } elseif ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password     = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo json_encode(['success' => false, 'error' => 'All password fields are required']);
            exit;
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            exit;
        }

        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters long']);
            exit;
        }

        // Check complexity
        if (!preg_match('/[A-Z]/', $new_password)) {
            echo json_encode(['success' => false, 'error' => 'Password must contain at least one uppercase letter']);
            exit;
        }
        if (!preg_match('/[a-z]/', $new_password)) {
            echo json_encode(['success' => false, 'error' => 'Password must contain at least one lowercase letter']);
            exit;
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            echo json_encode(['success' => false, 'error' => 'Password must contain at least one number']);
            exit;
        }

        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE userID = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit;
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE userID = ?");
        $update->execute([$new_hash, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    error_log('[Settings API] DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('[Settings API] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>
