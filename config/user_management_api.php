<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Require PDRRMO-level role
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_type = $_SESSION['user_type'] ?? '';
if ($user_type !== 'emergency_coordinator' && $user_type !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden - PDRRMO access required']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'list') {
        // Get all municipalities (including PDRRMO so they can be monitored)
        $sql = "SELECT drrmoID, name, type, logo_url FROM drrmo ORDER BY name ASC";
        $stmt = $pdo->query($sql);
        $municipalities = $stmt->fetchAll();
        
        // Get all users
        $users_sql = "SELECT userID, email, fullName, position, contactNumber, role, drrmoID 
                      FROM users ORDER BY email ASC";
        $users_stmt = $pdo->query($users_sql);
        $all_users = $users_stmt->fetchAll();
        
        // Organize users by municipality
        $result = [];
        foreach ($municipalities as $mun) {
            $users = [];
            foreach ($all_users as $user) {
                if ($user['drrmoID'] == $mun['drrmoID']) {
                    // Determine profile completion status
                    $profile_completed = !empty($user['fullName']) && 
                                       !empty($user['position']) && 
                                       !empty($user['contactNumber']);
                    
                    // Map role to account type display name
                    $account_type_display = 'Unknown';
                    if ($user['role'] === 'drrmo_staff' || $user['role'] === 'admin') {
                        $account_type_display = 'Admin / DRRMO Officer';
                    } elseif ($user['role'] === 'approving_authority') {
                        $account_type_display = 'Head of DRRMO';
                    }
                    
                    $users[] = [
                        'userID' => $user['userID'],
                        'email' => $user['email'],
                        'fullName' => $user['fullName'] ?? '',
                        'position' => $user['position'] ?? '',
                        'contactNumber' => $user['contactNumber'] ?? '',
                        'role' => $user['role'],
                        'accountTypeDisplay' => $account_type_display,
                        'profileCompleted' => $profile_completed
                    ];
                }
            }
            
            $result[] = [
                'drrmoID' => $mun['drrmoID'],
                'name' => $mun['name'],
                'type' => $mun['type'],
                'logo_url' => $mun['logo_url'],
                'user_count' => count($users),
                'users' => $users
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
        
    } elseif ($action === 'reset_user_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Reset a user's password (PDRRMO only)
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user_id']);
            exit;
        }

        // Ensure user exists
        $check_sql = "SELECT userID, email, role FROM users WHERE userID = ? LIMIT 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id]);
        $user = $check_stmt->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Generate a temporary password
        $length = 12;
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $temp_password = '';
        // Guarantee complexity (at least one upper/lower/number/special)
        $temp_password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)];
        $temp_password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)];
        $temp_password .= '0123456789'[random_int(0, 9)];
        $temp_password .= '!@#$%&*'[random_int(0, 6)];
        while (strlen($temp_password) < $length) {
            $temp_password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        // Shuffle
        $temp_password = str_shuffle($temp_password);

        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ? WHERE userID = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$hashed_password, $user_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully',
            'userID' => (int)$user['userID'],
            'email' => $user['email'],
            'temporary_password' => $temp_password
        ]);

    } elseif ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new user account
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $drrmo_id = trim($_POST['drrmo_id'] ?? '');
        $account_type = trim($_POST['account_type'] ?? ''); // 'admin' or 'approving_authority'
        
        if (empty($email) || empty($password) || empty($drrmo_id) || empty($account_type)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email, password, municipality, and account type are required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            exit;
        }
        
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
            exit;
        }
        
        // Validate account type
        if ($account_type !== 'admin' && $account_type !== 'approving_authority') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid account type']);
            exit;
        }
        
        // Check if email already exists
        $check_sql = "SELECT userID FROM users WHERE email = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$email]);
        
        if ($check_stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email already exists. Please select a different municipality or contact support.']);
            exit;
        }
        
        // Check if drrmo exists
        $drrmo_check = $pdo->prepare("SELECT drrmoID, name FROM drrmo WHERE drrmoID = ?");
        $drrmo_check->execute([$drrmo_id]);
        $drrmo_data = $drrmo_check->fetch();
        if (!$drrmo_data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid municipality']);
            exit;
        }
        
        // Check existing accounts for this municipality
        $existing_users_sql = "SELECT userID, role FROM users WHERE drrmoID = ?";
        $existing_users_stmt = $pdo->prepare($existing_users_sql);
        $existing_users_stmt->execute([$drrmo_id]);
        $existing_users = $existing_users_stmt->fetchAll();
        
        // Count existing accounts
        $existing_count = count($existing_users);
        
        // Limit to 2 accounts per municipality
        if ($existing_count >= 2) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'This municipality already has 2 accounts. Maximum limit reached.']);
            exit;
        }
        
        // Check if account type already exists
        $role_map = [
            'admin' => 'drrmo_staff', // Admin/DRRMO Officer uses drrmo_staff role
            'approving_authority' => 'approving_authority' // New role for approving authority
        ];
        
        $target_role = $role_map[$account_type];
        
        // Check if this account type already exists
        foreach ($existing_users as $user) {
            if ($user['role'] === $target_role) {
                $account_type_name = $account_type === 'admin' ? 'Admin/DRRMO Officer' : 'Head of DRRMO';
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "This municipality already has a {$account_type_name} account."]);
                exit;
            }
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if profileCompleted column exists
        $checkColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'profileCompleted'")->fetch();
        
        if ($checkColumn) {
            $insert_sql = "INSERT INTO users (email, password, role, drrmoID, profileCompleted) VALUES (?, ?, ?, ?, 0)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([$email, $hashed_password, $target_role, $drrmo_id]);
        } else {
            $insert_sql = "INSERT INTO users (email, password, role, drrmoID) VALUES (?, ?, ?, ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([$email, $hashed_password, $target_role, $drrmo_id]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'User account created successfully',
            'email' => $email,
            'password' => $password,
            'account_type' => $account_type,
            'role' => $target_role
        ]);
        
    } elseif ($action === 'get_municipalities' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get list of all municipalities (excluding PDRRMO) for dropdown with account counts
        $sql = "SELECT drrmoID, name, type FROM drrmo WHERE type != 'PDRRMO' ORDER BY name ASC";
        $stmt = $pdo->query($sql);
        $municipalities = $stmt->fetchAll();
        
        // Get account counts for each municipality
        $account_counts_sql = "SELECT drrmoID, COUNT(*) as account_count FROM users GROUP BY drrmoID";
        $account_counts_stmt = $pdo->query($account_counts_sql);
        $account_counts = [];
        while ($row = $account_counts_stmt->fetch()) {
            $account_counts[$row['drrmoID']] = (int)$row['account_count'];
        }
        
        // Add account count and availability status to each municipality
        foreach ($municipalities as &$mun) {
            $mun['account_count'] = $account_counts[$mun['drrmoID']] ?? 0;
            $mun['can_create_account'] = ($mun['account_count'] < 2);
        }
        unset($mun); // Break reference
        
        echo json_encode(['success' => true, 'data' => $municipalities]);
        
    } elseif ($action === 'get_municipality' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['drrmo_id'])) {
        // Get single municipality details with account status
        $drrmo_id = trim($_GET['drrmo_id']);
        $sql = "SELECT drrmoID, name, type FROM drrmo WHERE drrmoID = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$drrmo_id]);
        $municipality = $stmt->fetch();
        
        if ($municipality) {
            // Get existing accounts for this municipality
            $users_sql = "SELECT userID, role FROM users WHERE drrmoID = ?";
            $users_stmt = $pdo->prepare($users_sql);
            $users_stmt->execute([$drrmo_id]);
            $existing_users = $users_stmt->fetchAll();
            
            // Determine which account types are available
            $has_admin = false;
            $has_approving_authority = false;
            
            foreach ($existing_users as $user) {
                if ($user['role'] === 'drrmo_staff' || $user['role'] === 'admin') {
                    $has_admin = true;
                } elseif ($user['role'] === 'approving_authority') {
                    $has_approving_authority = true;
                }
            }
            
            $municipality['account_status'] = [
                'has_admin' => $has_admin,
                'has_approving_authority' => $has_approving_authority,
                'total_accounts' => count($existing_users),
                'can_create_admin' => !$has_admin && count($existing_users) < 2,
                'can_create_approving_authority' => !$has_approving_authority && count($existing_users) < 2
            ];
            
            echo json_encode(['success' => true, 'data' => $municipality]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Municipality not found']);
        }
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log('[User Management API] PDO Error: ' . $e->getMessage());
    error_log('[User Management API] Error Info: ' . print_r($e->errorInfo ?? [], true));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('[User Management API] Error: ' . $e->getMessage());
    error_log('[User Management API] Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error occurred: ' . $e->getMessage()]);
}
?>

