<?php
session_start();
require_once __DIR__ . '/config/auth.php';
requireUserType('drrmo_staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipality Dashboard - ConnectDRRM</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/logos/LoginLogo.png">
    <!-- Sidebar is always expanded - no initial state script needed -->
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" 
          crossorigin="anonymous">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS for Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
    
    <!-- Global Base CSS -->
    <link rel="stylesheet" href="assets/css/base.css">
    
    <!-- Component CSS -->
    <link rel="stylesheet" href="assets/css/components/header.css?v=1.0.2">
    <link rel="stylesheet" href="assets/css/components/sidebar.css">
    <link rel="stylesheet" href="assets/css/components/modal.css">
    <link rel="stylesheet" href="assets/css/components/confirmation-modal.css">
    <link rel="stylesheet" href="assets/css/location-matcher.css">
    <link rel="stylesheet" href="assets/css/components/hazard-modal-enhanced.css">
    
    <!-- Page-specific CSS -->
    <?php 
    $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
    $page_css = "assets/css/pages/{$page}.css";
    if (file_exists($page_css)): 
    ?>
        <link rel="stylesheet" href="<?php echo $page_css; ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Rest of your content remains the same -->
    <div class="app-container">
        <?php include 'dashboards/components/sidebar_municipality.php'; ?>
        
        <div class="main-wrapper">
            <?php include 'dashboards/components/header.php'; ?>
            
            <main class="main-content">
                <?php include __DIR__ . '/dashboards/page_loader.php'; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
            crossorigin="anonymous"></script>
    
    <!-- Core JS -->
    <script src="assets/js/core.js"></script>
    
    <!-- Component JS -->
    <script src="assets/js/components/header.js"></script>
    <script src="assets/js/components/sidebar.js"></script>
    <script src="assets/js/components/confirmation-modal.js"></script>
    
    <!-- Leaflet JS for Map -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    <!-- Proj4 for coordinate reprojection (projected GeoJSON -> WGS84) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.9.2/proj4.min.js"></script>
    
    <!-- Location Matcher Module -->
    <script src="assets/js/modules/location-matcher.js"></script>

    <!-- Real-time table refresh (SSE-driven + polling fallback) -->
    <script src="assets/js/modules/realtime-tables.js"></script>
    
    <!-- Page-specific JS -->
    <?php 
    $page_js = "assets/js/pages/{$page}.js";
    if (file_exists($page_js)): 
    ?>
        <script src="<?php echo $page_js; ?>?v=1.0.2"></script>
    <?php endif; ?>
    
    <!-- Initialize Dashboard -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing dashboard...');
            if (typeof DashboardPage !== 'undefined') {
                window.dashboardPage = new DashboardPage();
            } else {
                console.error('DashboardPage class not found');
            }
            
            // REMOVED: Defer notifications refresh - now handled by HeaderComponent with proper deduplication
            // Notifications will load automatically after page is fully interactive (2-3 seconds delay)
            
            // Refresh sidebar active states after page load
            setTimeout(() => {
                if (window.refreshSidebarActiveStates) {
                    window.refreshSidebarActiveStates();
                }
            }, 200);
        });
        
        // Logout confirmation function
        function confirmLogout(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (window.confirmationModal) {
                window.confirmationModal.show({
                    title: 'Confirm Logout',
                    message: 'Are you sure you want to logout? You will need to log in again to access your account.',
                    type: 'warning',
                    confirmText: 'Yes, Logout',
                    cancelText: 'Cancel',
                    showCancel: true,
                    dangerAction: true,
                    onConfirm: () => {
                        // Redirect to logout
                        window.location.href = 'logout.php';
                    },
                    onCancel: () => {
                        // Do nothing, just close modal
                    }
                });
            } else {
                // Fallback to basic confirm if modal not available
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'logout.php';
                }
            }
        }
    </script>
</body>
</html>