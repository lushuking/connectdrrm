<?php
/**
 * Session Ping Handler
 * Keep session alive
 */

session_start();
require_once 'auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check CSRF token if provided
$headers = getallheaders();
if (isset($headers['X-CSRF-Token'])) {
    if (!verifyCSRFToken($headers['X-CSRF-Token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Session updated',
    'timestamp' => time()
]);
?>