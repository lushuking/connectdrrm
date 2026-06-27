<?php
/**
 * API endpoint to fetch page content for SPA routing
 * Returns only the HTML content of the requested page (no full HTML document)
 */
session_start();
require_once __DIR__ . '/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$userType = $_SESSION['user_type'] ?? 'guest';
$isPdrrmo = ($userType === 'emergency_coordinator' || $userType === 'admin');

// Allowed pages
$allowedPages = ['dashboard', 'resources', 'requests', 'hazard', 'notifications', 'reports'];
if ($isPdrrmo) {
    $allowedPages[] = 'monitor_requests';
    $allowedPages[] = 'user_management';
}

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

// Resolve page file by role
$pageFile = null;
if ($isPdrrmo) {
    if ($page === 'monitor_requests' || $page === 'user_management' || $page === 'reports') {
        $primary = __DIR__ . "/../dashboards/pages/pdrrmo/{$page}.php";
        $fallback = __DIR__ . "/../dashboards/pages/municipality/{$page}.php";
        if (file_exists($primary)) {
            $pageFile = $primary;
        } elseif (file_exists($fallback)) {
            $pageFile = $fallback;
        }
    } else {
        $primary = __DIR__ . "/../dashboards/pages/municipality/{$page}.php";
        $fallback = __DIR__ . "/../dashboards/pages/pdrrmo/{$page}.php";
        if (file_exists($primary)) {
            $pageFile = $primary;
        } elseif (file_exists($fallback)) {
            $pageFile = $fallback;
        }
    }
} else {
    $pageFile = __DIR__ . "/../dashboards/pages/municipality/{$page}.php";
}

if (!$pageFile || !file_exists($pageFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Page not found']);
    exit;
}

// Capture page output
ob_start();
include $pageFile;
$content = ob_get_clean();

// Get page-specific CSS and JS paths
$pageCss = "assets/css/pages/{$page}.css";
$pageJs = "assets/js/pages/{$page}.js";
$cssExists = file_exists(__DIR__ . "/../{$pageCss}");
$jsExists = file_exists(__DIR__ . "/../{$pageJs}");

// Special handling for reports page (PDRRMO)
$jsFiles = [];
if ($page === 'reports' && $isPdrrmo) {
    if (file_exists(__DIR__ . "/../assets/js/pages/reports.js")) {
        $jsFiles[] = "assets/js/pages/reports.js";
    }
    if (file_exists(__DIR__ . "/../assets/js/pages/reports_pdrrmo.js")) {
        $jsFiles[] = "assets/js/pages/reports_pdrrmo.js";
    }
} else if ($jsExists) {
    $jsFiles[] = $pageJs;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'page' => $page,
    'content' => $content,
    'css' => $cssExists ? $pageCss : null,
    'js' => $jsFiles,
    'timestamp' => time()
]);
?>
