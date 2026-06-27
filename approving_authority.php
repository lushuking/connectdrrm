<?php
session_start();
require_once __DIR__ . '/config/auth.php';

// Check if user is logged in and is approving_authority
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['user_type'] !== 'approving_authority') {
    header('Location: login.php');
    exit();
}

// Ensure function exists (in case of caching issues)
if (!function_exists('requireProfileCompleted')) {
    // Fallback: manually check profile completion
    if (!isProfileCompleted()) {
        header('Location: complete_profile.php');
        exit();
    }
} else {
    requireProfileCompleted();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Head of DRRMO Dashboard - ConnectDRRM</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/logos/LoginLogo.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" 
          crossorigin="anonymous">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Global Base CSS -->
    <link rel="stylesheet" href="assets/css/base.css">
    
    <!-- Component CSS -->
    <link rel="stylesheet" href="assets/css/components/header.css">
    <link rel="stylesheet" href="assets/css/components/sidebar.css">
    <link rel="stylesheet" href="assets/css/components/modal.css">
    <link rel="stylesheet" href="assets/css/components/confirmation-modal.css">
    
    <!-- Page-specific CSS -->
    <?php 
    $page = isset($_GET['page']) ? $_GET['page'] : 'approvals';
    $page_css = "assets/css/pages/{$page}.css";
    if (file_exists($page_css)): 
    ?>
        <link rel="stylesheet" href="<?php echo $page_css; ?>">
    <?php endif; ?>
</head>
<body>
    <div class="app-container">
        <?php include 'dashboards/components/sidebar_approving_authority.php'; ?>
        
        <div class="main-wrapper">
            <?php include 'dashboards/components/header.php'; ?>
            
            <main class="main-content">
                <?php include 'dashboards/page_loader_approving.php'; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
            crossorigin="anonymous"></script>
    
    <!-- Core JS -->
    <script src="assets/js/core.js"></script>
    <script src="assets/js/session_manager.js"></script>
    <script src="assets/js/components/sidebar.js"></script>
    <script src="assets/js/components/header.js"></script>
    <script src="assets/js/components/confirmation-modal.js"></script>

    <!-- Real-time table refresh (SSE-driven + polling fallback) -->
    <script src="assets/js/modules/realtime-tables.js"></script>
    
    <!-- Page-specific JS -->
    <?php 
    $page_js = "assets/js/pages/{$page}.js";
    if (file_exists($page_js)): 
    ?>
        <script src="<?php echo $page_js; ?>"></script>
    <?php endif; ?>
    
    <script>
        // Logout confirmation function
        function confirmLogout(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (window.confirmationModal) {
                window.confirmationModal.show({
                    title: 'Confirm Logout',
                    message: 'Are you sure you want to logout?',
                    type: 'warning',
                    confirmText: 'Yes, Logout',
                    cancelText: 'Cancel',
                    onConfirm: () => {
                        window.location.href = 'logout.php';
                    }
                });
            } else {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'logout.php';
                }
            }
        }
    </script>
</body>
</html>

