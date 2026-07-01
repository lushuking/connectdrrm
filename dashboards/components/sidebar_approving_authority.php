<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'approvals';

$navigation = [
    [
        'id' => 'approvals',
        'label' => 'Approvals',
        'icon' => 'check_circle',
        'url' => '?page=approvals'
    ],
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => 'dashboard',
        'url' => '?page=dashboard'
    ],
    [
        'id' => 'reports',
        'label' => 'Reports',
        'icon' => 'assessment',
        'url' => '?page=reports'
    ],
    [
        'id' => 'settings',
        'label' => 'Settings',
        'icon' => 'settings',
        'url' => '?page=settings'
    ]
];
?>

<aside class="sidebar" data-component="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">
                <img src="assets/logos/LoginLogo.png" alt="Logo" class="sidebar-logo-img">
            </div>
            <h2 class="logo-text">ConnectDRRM</h2>
            <p class="logo-subtitle">Head of DRRMO</p>
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
        
        <div class="sidebar-info">
            <small class="version-info">v1.0.0</small>
        </div>
    </div>
</aside>

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('active')"></div>

