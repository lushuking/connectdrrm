/**
 * Sidebar Component JavaScript
 */

class SidebarComponent {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.handleActiveStates();
        this.setupMobileToggle();
        
        // Ensure active states are set after a delay to handle any race conditions
        setTimeout(() => {
            this.handleActiveStates();
        }, 100);
    }
    
    bindEvents() {
        const helpBtn = document.getElementById('helpBtn');
        const settingsBtn = document.getElementById('settingsBtn');
        
        if (helpBtn) {
            helpBtn.addEventListener('click', this.showHelp.bind(this));
        }
        
        if (settingsBtn) {
            settingsBtn.addEventListener('click', this.showSettings.bind(this));
        }
        
        // Handle nav link clicks with loading state
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', this.handleNavClick.bind(this));
        });
    }
    
    handleActiveStates() {
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
        console.log('Setting active state for page:', currentPage);
        
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            const href = link.getAttribute('href');
            const dataPage = link.getAttribute('data-page');
            
            // Check both href and data-page attributes
            if ((href && href.includes(`page=${currentPage}`)) || dataPage === currentPage) {
                link.classList.add('active');
                console.log('Activated link:', link.textContent.trim(), 'for page:', currentPage);
            }
        });
    }
    
    setupMobileToggle() {
        if (window.innerWidth <= 768) {
            this.createMobileToggle();
        }
        
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                this.createMobileToggle();
            } else {
                this.removeMobileToggle();
            }
        });
    }
    
    createMobileToggle() {
        const toggle = document.querySelector('.mobile-sidebar-toggle');
        if (toggle) {
            // Remove existing listener to prevent duplicates
            toggle.removeEventListener('click', this.toggleMobileSidebar.bind(this));
            toggle.addEventListener('click', this.toggleMobileSidebar.bind(this));
        }
        
        // Ensure overlay exists
        if (!document.querySelector('.sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.addEventListener('click', this.toggleMobileSidebar.bind(this));
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.parentNode) {
                sidebar.parentNode.insertBefore(overlay, sidebar.nextSibling);
            }
        }
    }
    
    removeMobileToggle() {
        const toggle = document.querySelector('.mobile-sidebar-toggle');
        if (toggle) {
            toggle.removeEventListener('click', this.toggleMobileSidebar.bind(this));
        }
    }
    
    toggleMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('active');
        }
    }
    
    handleNavClick(event) {
        // Add loading state with visual feedback
        const link = event.currentTarget;
        const originalOpacity = link.style.opacity;
        
        link.style.transition = 'opacity 0.2s ease';
        link.style.opacity = '0.6';
        
        // Add a subtle loading indicator
        const icon = link.querySelector('.nav-icon');
        if (icon) {
            icon.style.transform = 'scale(0.95)';
        }
        
        // Restore state after a short delay
        setTimeout(() => {
            link.style.opacity = originalOpacity || '1';
            if (icon) {
                icon.style.transform = 'scale(1)';
            }
        }, 200);
    }
    
    showHelp() {
        if (window.municipalityDashboard) {
            window.municipalityDashboard.showNotification('Help documentation will be available soon.', 'info');
        }
    }
    
    showSettings() {
        if (window.municipalityDashboard) {
            window.municipalityDashboard.showNotification('Settings panel will be available soon.', 'info');
        }
    }
}

// Initialize sidebar component when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Prevent multiple instances
    if (window.sidebarComponent) {
        return;
    }
    
    // Small delay to ensure all elements are rendered
    setTimeout(() => {
        window.sidebarComponent = new SidebarComponent();
        
        // Global function to refresh active states
        window.refreshSidebarActiveStates = () => {
            if (window.sidebarComponent) {
                window.sidebarComponent.handleActiveStates();
            }
        };
    }, 50);
});

// Add CSS to prevent flash of unstyled content
document.addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style');
    style.textContent = `
        .sidebar, .app-container {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    `;
    document.head.appendChild(style);
});