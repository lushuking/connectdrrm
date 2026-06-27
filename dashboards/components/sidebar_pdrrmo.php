<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$navigation = [
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => 'dashboard',
        'url' => '?page=dashboard'
    ],
    [
        'id' => 'resources',
        'label' => 'Resources',
        'icon' => 'inventory',
        'url' => '?page=resources'
    ],
    [
        'id' => 'requests',
        'label' => 'Requests',
        'icon' => 'request_quote',
        'url' => '?page=requests'
    ],
    [
        'id' => 'monitor_requests',
        'label' => 'Monitor All Requests',
        'icon' => 'monitor',
        'url' => '?page=monitor_requests'
    ],
    [
        'id' => 'hazard',
        'label' => 'Hazard',
        'icon' => 'warning',
        'url' => '?page=hazard'
    ],
    [
        'id' => 'reports',
        'label' => 'Reports',
        'icon' => 'assessment',
        'url' => '?page=reports'
    ],
    [
        'id' => 'user_management',
        'label' => 'User Management',
        'icon' => 'people',
        'url' => '?page=user_management'
    ],
    [
        'id' => 'settings',
        'label' => 'Settings',
        'icon' => 'settings',
        'url' => '?page=settings'
    ]
];
?>

<!-- Initial state is handled by pdrrmo.php to prevent flash of unstyled content -->

<aside class="sidebar" data-component="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">
                <img src="assets/logos/LoginLogo.png" alt="Logo" class="sidebar-logo-img">
            </div>
            <h2 class="logo-text">ConnectDRRM</h2>
            <p class="logo-subtitle">PDRRMO Portal</p>
        </div>
    </div>
    
    <nav class="sidebar-nav" role="navigation" aria-label="Main navigation">
        <ul class="nav-menu">
            <?php foreach ($navigation as $nav_item):
                if (!isset($nav_item['url'], $nav_item['id'], $nav_item['icon'], $nav_item['label'])) { 
                    continue; 
                }
                
                $isActive = ($current_page === $nav_item['id']);
            ?>
                <li class="nav-item">
                    <a href="<?php echo htmlspecialchars($nav_item['url']); ?>" 
                       class="nav-link <?php echo $isActive ? 'active' : ''; ?>"
                       data-page="<?php echo htmlspecialchars($nav_item['id']); ?>"
                       aria-current="<?php echo $isActive ? 'page' : 'false'; ?>"
                       title="<?php echo htmlspecialchars($nav_item['label']); ?>">
                        <span class="nav-icon material-icons" aria-hidden="true">
                            <?php echo htmlspecialchars($nav_item['icon']); ?>
                        </span>
                        <span class="nav-label">
                            <?php echo htmlspecialchars($nav_item['label']); ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-actions">
            <a href="#" 
               class="btn btn-logout" 
               title="Logout"
               onclick="confirmLogout(event);">
                <span class="material-icons btn-icon">logout</span>
                <span class="btn-text">Logout</span>
            </a>
        </div>
        
        <!-- Optional: Add user info or additional actions -->
        <div class="sidebar-info">
            <small class="version-info">v1.0.0</small>
        </div>
    </div>
</aside>

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('active')"></div>

