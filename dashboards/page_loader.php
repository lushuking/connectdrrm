<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Determine requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Determine user role
$userType = $_SESSION['user_type'] ?? 'guest';
$isPdrrmo = ($userType === 'emergency_coordinator' || $userType === 'admin');

// Allowed pages
$allowedPages = ['dashboard', 'resources', 'requests', 'hazard', 'notifications', 'reports', 'settings'];
if ($isPdrrmo) {
    $allowedPages[] = 'monitor_requests';
    $allowedPages[] = 'user_management';
}

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

// Resolve page file by role
if ($isPdrrmo) {
    // PDRRMO/Admin: use PDRRMO-specific pages for reports, monitor_requests, user_management, settings
    if ($page === 'monitor_requests' || $page === 'user_management' || $page === 'reports' || $page === 'settings') {
        $primary = __DIR__ . "/pages/pdrrmo/{$page}.php";
        $fallback = __DIR__ . "/pages/municipality/{$page}.php";
    } else {
        $primary = __DIR__ . "/pages/municipality/{$page}.php";
        $fallback = __DIR__ . "/pages/pdrrmo/{$page}.php";
    }
    if (file_exists($primary)) {
        include $primary;
    } elseif (file_exists($fallback)) {
        include $fallback;
    } else {
        echo "<div class='error-page'><h1>Page Not Found</h1><p>The requested page could not be found.</p></div>";
    }
} else {
    $pageFile = __DIR__ . "/pages/municipality/{$page}.php";
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        echo "<div class='error-page'><h1>Page Not Found</h1><p>The requested page could not be found.</p></div>";
    }
}
?>

