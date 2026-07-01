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
        $stmt = $pdo->prepare("SELECT userID, email, fullName, position, contactNumber, role, drrmoID, signature FROM users WHERE userID = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Get municipality name and logo
        $muni_name = null;
        $logo_url = null;
        if ($user['drrmoID']) {
            $muni_stmt = $pdo->prepare("SELECT name, logo_url FROM drrmo WHERE drrmoID = ? LIMIT 1");
            $muni_stmt->execute([$user['drrmoID']]);
            $muni = $muni_stmt->fetch();
            $muni_name = $muni['name'] ?? null;
            $logo_url = $muni['logo_url'] ?? null;
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
                'signature'     => $user['signature'] ?? '',
                'logoUrl'       => $logo_url,
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

    } elseif ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $signature_base64 = trim($_POST['signature_base64'] ?? '');

        if (empty($full_name) || empty($position) || empty($contact_number)) {
            echo json_encode(['success' => false, 'error' => 'Full Name, Position, and Contact Number are required.']);
            exit;
        }

        if (!preg_match('/^[0-9+\-\s()]+$/', $contact_number)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid contact number.']);
            exit;
        }

        // Get user's current record
        $stmt = $pdo->prepare("SELECT drrmoID, role FROM users WHERE userID = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Ensure signature column exists
        try {
            $checkSigColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'signature'")->fetch();
            if (!$checkSigColumn) {
                $pdo->exec("ALTER TABLE users ADD COLUMN signature LONGTEXT NULL");
            }
        } catch (Exception $e) {
            error_log('Could not create signature column: ' . $e->getMessage());
        }

        // Update users table
        if (!empty($signature_base64)) {
            $upd = $pdo->prepare("UPDATE users SET fullName = ?, position = ?, contactNumber = ?, signature = ? WHERE userID = ?");
            $upd->execute([$full_name, $position, $contact_number, $signature_base64, $user_id]);
        } else {
            $upd = $pdo->prepare("UPDATE users SET fullName = ?, position = ?, contactNumber = ? WHERE userID = ?");
            $upd->execute([$full_name, $position, $contact_number, $user_id]);
        }

        // Update session
        $_SESSION['full_name'] = $full_name;

        // Handle logo upload for drrmo_staff
        $is_drrmo_staff = ($user['role'] === 'drrmo_staff');
        if ($is_drrmo_staff && isset($_FILES['municipality_logo']) && $_FILES['municipality_logo']['error'] === UPLOAD_ERR_OK) {
            $municipality_id = $user['drrmoID'] ?? null;
            if ($municipality_id) {
                $tmp = $_FILES['municipality_logo']['tmp_name'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/svg+xml' => 'svg'];

                if (isset($allowed[$mime])) {
                    $ext = $allowed[$mime];
                    $root = realpath(__DIR__ . '/..');
                    $destDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logos';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0775, true);
                    }

                    $filename = $municipality_id . '.' . $ext;
                    $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;

                    foreach (glob($destDir . DIRECTORY_SEPARATOR . $municipality_id . '.*') as $old) {
                        @unlink($old);
                    }

                    if (move_uploaded_file($tmp, $destPath)) {
                        $relativeUrl = 'assets/logos/' . $filename;
                        $upd_logo = $pdo->prepare('UPDATE drrmo SET logo_url = ? WHERE drrmoID = ?');
                        $upd_logo->execute([$relativeUrl, $municipality_id]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to save logo file.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid logo image format. Allowed formats: PNG, JPG, JPEG, SVG']);
                    exit;
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        exit;

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
