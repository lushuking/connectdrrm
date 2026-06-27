<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Determine requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'approvals';

// Allowed pages for approving authority
$allowedPages = ['approvals', 'dashboard', 'reports'];

if (!in_array($page, $allowedPages, true)) {
    $page = 'approvals';
}

// Resolve page file
$pageFile = __DIR__ . "/pages/approving_authority/{$page}.php";
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    echo "<div class='error-page'><h1>Page Not Found</h1><p>The requested page could not be found.</p></div>";
}
?>

