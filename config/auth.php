<?php
/**
 * Authentication Configuration and Functions
 * ConnectDRRM System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection (resolve relative to this file)
require_once __DIR__ . '/db.php';

/**
 * Authenticate user credentials (auto-detect user type)
 */
function authenticateUser($username, $password) {
    global $pdo;

    try {
        // Find user by email only (no user type required)
        $sql = "SELECT userID, email, password, role, drrmoID, fullName FROM users WHERE email = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check profile completion status
            $profileCheckSql = "SELECT fullName, position, contactNumber, signature FROM users WHERE userID = ? LIMIT 1";
            $profileCheckStmt = $pdo->prepare($profileCheckSql);
            $profileCheckStmt->execute([$user['userID']]);
            $profileData = $profileCheckStmt->fetch();
            
            // Determine if profile is completed (all required fields must be filled)
            $hasProfileCompleted = !empty($profileData['fullName']) && 
                                   !empty($profileData['position']) && 
                                   !empty($profileData['contactNumber']) && 
                                   !empty($profileData['signature']);
            
            $result = [
                'success' => true,
                'user_id' => $user['userID'],
                'username' => $user['email'],
                'user_type' => $user['role'],
                'full_name' => $user['fullName'],
                'municipality_id' => $user['drrmoID'],
                'profile_completed' => $hasProfileCompleted
            ];
            
            return $result;
        }

        return [
            'success' => false,
            'message' => 'Invalid email or password'
        ];
    } catch (Exception $e) {
        error_log('[ConnectDRRM][AUTH] Login error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Login error occurred. Please try again.'
        ];
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

/**
 * Check if user profile is completed
 */
function isProfileCompleted($user_id = null) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = $user_id ?? $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        return false;
    }
    
    try {
        $user_type = $_SESSION['user_type'] ?? null;
        $is_drrmo_staff = ($user_type === 'drrmo_staff');
        
        // Check all required fields including new ones (signature and logo)
        $sql = "SELECT fullName, position, contactNumber, signature, drrmoID FROM users WHERE userID = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Check basic required fields
        if (empty($user['fullName']) || empty($user['position']) || empty($user['contactNumber'])) {
            return false;
        }
        
        // Check signature (required for all users)
        if (empty($user['signature'])) {
            return false;
        }
        
        // Check logo for drrmo_staff
        if ($is_drrmo_staff && !empty($user['drrmoID'])) {
            try {
                $logo_sql = "SELECT logo_url FROM drrmo WHERE drrmoID = ? LIMIT 1";
                $logo_stmt = $pdo->prepare($logo_sql);
                $logo_stmt->execute([$user['drrmoID']]);
                $drrmo = $logo_stmt->fetch();
                
                if (!$drrmo || empty($drrmo['logo_url'])) {
                    return false;
                }
            } catch (Exception $e) {
                // If drrmo table doesn't have logo_url, that's okay - just check user fields
                error_log('[ConnectDRRM][AUTH] Logo check error: ' . $e->getMessage());
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('[ConnectDRRM][AUTH] Profile check error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Require profile completion
 */
function requireProfileCompleted() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    if (!isProfileCompleted()) {
        header('Location: complete_profile.php');
        exit();
    }
}

/**
 * Get current user information
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'user_type' => $_SESSION['user_type'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null
    ];
}

/**
 * Require specific user type
 */
function requireUserType($required_type) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    // Allow multiple user types for PDRRMO
    if ($required_type === 'emergency_coordinator') {
        if ($_SESSION['user_type'] !== 'emergency_coordinator' && $_SESSION['user_type'] !== 'admin') {
            header('Location: login.php');
            exit();
        }
    } else {
        if ($_SESSION['user_type'] !== $required_type) {
            header('Location: login.php');
            exit();
        }
    }
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Logout user
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    $timeout = 8 * 60 * 60; // 8 hours
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        logoutUser();
        header('Location: login.php?reason=timeout');
        exit();
    }
    
    $_SESSION['last_activity'] = time();
}

// Auto-check session timeout for logged in users
if (isLoggedIn()) {
    checkSessionTimeout();
}
?>