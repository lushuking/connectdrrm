<?php
session_start();
include 'config/auth.php';

// Log the logout action (optional - for audit trail)
$logout_info = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'user_type' => $_SESSION['user_type'] ?? null,
    'logout_time' => date('Y-m-d H:i:s'),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
];

// In a real application, you might want to log this to a database
// logUserAction('logout', $logout_info);

// Clear all session data
logoutUser();

// Prevent caching of this page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect to login page with logout message
header('Location: login.php?logout=success');
exit();
?>