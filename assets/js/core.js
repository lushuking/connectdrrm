/**
 * Enhanced Core JavaScript for ConnectDRRM Municipality Dashboard
 * Global utilities and shared functionality with sidebar integration
 */

class MunicipalityDashboard {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindGlobalEvents();
        this.initializeTooltips();
        this.setupAjaxDefaults();
        this.initializeSidebarIntegration();
    }
    
    setupSPARouting() {
        // Handle browser back/forward buttons
        window.addEventListener('popstate', (event) => {
            const url = new URL(window.location);
            const page = url.searchParams.get('page') || 'dashboard';
            this.loadPageContent(page, {}, false); // false = don't push state (already changed)
        });
        
        // Intercept sidebar links and other internal navigation
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href*="?page="], a[href*="page="]');
            if (link && link.hostname === window.location.hostname) {
                const href = link.getAttribute('href');
                if (href && href.includes('?page=')) {
                    e.preventDefault();
                    const url = new URL(href, window.location.origin);
                    const page = url.searchParams.get('page') || 'dashboard';
                    const params = {};
                    url.searchParams.forEach((value, key) => {
                        if (key !== 'page') params[key] = value;
                    });
                    this.navigateTo(page, params);
                }
            }
        });
    }
    
    async loadPageContent(page, params = {}, pushState = true) {
        // Cancel any pending notification requests
        if (typeof window.cancelNotificationRequests === 'function') {
            window.cancelNotificationRequests();
        }
        
        // Build URL
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.set(key, params[key]);
            }
        });
        
        // Show loading state
        this.showPageLoading();
        
        try {
            // Fetch page content via AJAX
            const response = await fetch(`config/get_page_content.php?page=${encodeURIComponent(page)}`, {
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to load page');
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load page');
            }
            
            // Update URL without reload (only if pushState is true)
            if (pushState) {
                window.history.pushState({ page: page, params: params }, '', url.toString());
            }
            
            // Update main content area
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                // Extract and execute scripts separately (innerHTML doesn't execute scripts)
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data.content;
                
                // Extract scripts
                const scripts = tempDiv.querySelectorAll('script');
                const scriptsToExecute = [];
                scripts.forEach(script => {
                    scriptsToExecute.push({
                        text: script.textContent,
                        src: script.src,
                        type: script.type
                    });
                    script.remove(); // Remove from temp div
                });
                
                // Insert content without scripts
                mainContent.innerHTML = tempDiv.innerHTML;
                
                // Execute inline scripts
                scriptsToExecute.forEach(scriptInfo => {
                    if (scriptInfo.text) {
                        // Inline script - execute it
                        try {
                            // Replace DOMContentLoaded with pageContentLoaded so scripts run immediately
                            const modifiedScript = scriptInfo.text
                                .replace(/document\.addEventListener\(['"]DOMContentLoaded['"]/g, 
                                         "document.addEventListener('pageContentLoaded'");
                            
                            // Create a function context to execute the script
                            const func = new Function(modifiedScript);
                            func();
                        } catch (e) {
                            console.error('Error executing inline script:', e);
                        }
                    }
                });
            }
            
            // Load page-specific CSS if needed
            if (data.css) {
                const cssId = `page-css-${page}`;
                if (!document.getElementById(cssId)) {
                    const link = document.createElement('link');
                    link.id = cssId;
                    link.rel = 'stylesheet';
                    link.href = data.css;
                    document.head.appendChild(link);
                }
            }
            
            // Clean up old page instances before loading new page
            this.cleanupPageInstances();
            
            // Load page-specific JS dynamically
            if (data.js && Array.isArray(data.js)) {
                // Remove old page JS (if any)
                document.querySelectorAll('script[data-page-js]').forEach(script => {
                    script.remove();
                });
                
                // Load new page JS
                for (const jsPath of data.js) {
                    const script = document.createElement('script');
                    script.src = jsPath + '?v=' + Date.now(); // Cache busting
                    script.setAttribute('data-page-js', page);
                    script.async = false;
                    document.body.appendChild(script);
                    
                    // Wait for script to load before initializing
                    await new Promise((resolve, reject) => {
                        script.onload = resolve;
                        script.onerror = () => {
                            console.warn('Failed to load script:', jsPath);
                            resolve(); // Continue even if script fails
                        };
                    });
                }
                
                // Small delay to ensure scripts are fully parsed
                await new Promise(resolve => setTimeout(resolve, 50));
            }
            
            // Trigger a custom event to simulate DOMContentLoaded for page scripts
            // This allows inline scripts that listen for DOMContentLoaded to run
            const domReadyEvent = new Event('DOMContentLoaded', { bubbles: true });
            document.dispatchEvent(domReadyEvent);
            
            // Also trigger pageContentLoaded for scripts that listen for it
            const pageContentEvent = new Event('pageContentLoaded', { bubbles: true });
            document.dispatchEvent(pageContentEvent);
            
            // Initialize page-specific components (with retry for dynamic script loading)
            let retries = 0;
            const maxRetries = 10;
            const tryInitialize = () => {
                try {
                    this.initializePage(page);
                } catch (e) {
                    if (retries < maxRetries) {
                        retries++;
                        setTimeout(tryInitialize, 50);
                        return;
                    }
                    console.error('Failed to initialize page:', e);
                }
            };
            // Small delay to ensure scripts are parsed
            setTimeout(tryInitialize, 100);
            
            // Update sidebar active state
            setTimeout(() => {
                if (window.refreshSidebarActiveStates) {
                    window.refreshSidebarActiveStates();
                }
            }, 150);
            
        } catch (error) {
            console.error('Navigation error:', error);
            // Fallback to full page reload on error
            window.location.href = url.toString();
        } finally {
            // Hide loading state
            this.hidePageLoading();
        }
    }
    
    bindGlobalEvents() {
        document.addEventListener('DOMContentLoaded', () => {
            this.handleResponsiveElements();
        });
        
        window.addEventListener('resize', () => {
            this.handleResponsiveElements();
        });
        
        // Listen for sidebar toggle events
        window.addEventListener('sidebarToggled', (event) => {
            this.handleSidebarToggle(event.detail);
        });
    }
    
    initializeSidebarIntegration() {
        // Listen for page navigation to maintain sidebar state
        window.addEventListener('beforeunload', () => {
            if (window.sidebarComponent) {
                const isCollapsed = (typeof window.sidebarComponent.isCollapsed === 'function')
                    ? window.sidebarComponent.isCollapsed()
                    : null;
                // State is automatically saved by sidebar component
            }
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', () => {
            if (window.sidebarComponent) {
                setTimeout(() => {
                    window.sidebarComponent.handleActiveStates();
                }, 100);
            }
        });
    }
    
    handleSidebarToggle(detail) {
        const { collapsed } = detail;
        
        // Adjust main content layout
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.style.transition = 'margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        }
        
        // Update any charts or components that need to resize
        this.triggerContentResize();
        
        // Save analytics or user preferences
        this.trackSidebarUsage(collapsed);
    }
    
    triggerContentResize() {
        // Trigger resize event for charts, tables, etc.
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 350);
    }
    
    trackSidebarUsage(collapsed) {
        // Optional: Track sidebar usage for analytics
        try {
            const event = {
                action: 'sidebar_toggle',
                state: collapsed ? 'collapsed' : 'expanded',
                timestamp: new Date().toISOString(),
                page: new URLSearchParams(window.location.search).get('page') || 'dashboard'
            };
            
            // You can send this to your analytics service
            // this.sendAnalytics(event);
        } catch (error) {
            console.log('Analytics tracking failed:', error);
        }
    }
    
    initializeTooltips() {
        // Initialize tooltips for elements with data-tooltip attribute
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip.bind(this));
            element.addEventListener('mouseleave', this.hideTooltip.bind(this));
        });
    }
    
    setupAjaxDefaults() {
        // Set up default AJAX configurations if using fetch
        this.defaultFetchOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
    }
    
    handleResponsiveElements() {
        const isMobile = window.innerWidth <= 768;
        const isTablet = window.innerWidth <= 1024 && window.innerWidth > 768;
        
        document.body.classList.toggle('mobile-view', isMobile);
        document.body.classList.toggle('tablet-view', isTablet);
        
        // Handle sidebar visibility on mobile
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && isMobile) {
            this.setupMobileSidebar(sidebar);
        }
        
        // Adjust content layout based on screen size
        this.adjustContentLayout();
    }
    
    adjustContentLayout() {
        const mainWrapper = document.querySelector('.main-wrapper');
        if (!mainWrapper) return;
        
        if (window.innerWidth <= 1024) {
            mainWrapper.style.marginLeft = '0';
        } else {
            // Reset to sidebar-dependent margin
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('collapsed')) {
                mainWrapper.style.marginLeft = '64px';
            } else {
                mainWrapper.style.marginLeft = '260px';
            }
        }
    }
    
    setupMobileSidebar(sidebar) {
        if (!document.querySelector('.mobile-sidebar-toggle')) {
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'mobile-sidebar-toggle';
            toggleBtn.innerHTML = '<span class="material-icons">menu</span>';
            toggleBtn.setAttribute('aria-label', 'Toggle Navigation Menu');
            
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                document.body.classList.toggle('sidebar-open');
            });
            
            // Add to header or create a fixed position
            const header = document.querySelector('.page-header') || document.body;
            if (header === document.body) {
                toggleBtn.style.position = 'fixed';
                toggleBtn.style.top = '20px';
                toggleBtn.style.left = '20px';
                toggleBtn.style.zIndex = '1001';
            }
            
            header.appendChild(toggleBtn);
        }
    }
    
    showTooltip(event) {
        const existingTooltip = document.querySelector('.tooltip');
        if (existingTooltip) {
            existingTooltip.remove();
        }
        
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = event.target.getAttribute('data-tooltip');
        document.body.appendChild(tooltip);
        
        const rect = event.target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        // Position tooltip above the element, centered
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.top - tooltipRect.height - 8;
        
        // Adjust if tooltip would go off screen
        if (left < 8) left = 8;
        if (left + tooltipRect.width > window.innerWidth - 8) {
            left = window.innerWidth - tooltipRect.width - 8;
        }
        if (top < 8) {
            top = rect.bottom + 8;
        }
        
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        tooltip.style.opacity = '1';
    }
    
    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.style.opacity = '0';
            setTimeout(() => {
                if (tooltip.parentElement) {
                    tooltip.remove();
                }
            }, 200);
        }
    }
    
    // Enhanced utility functions
    formatNumber(number, options = {}) {
        const defaultOptions = {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        };
        return new Intl.NumberFormat('en-US', { ...defaultOptions, ...options }).format(number);
    }
    
    formatCurrency(amount, currency = 'PHP') {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2
        }).format(amount);
    }
    
    formatDate(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };
        return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(new Date(date));
    }
    
    formatRelativeTime(date) {
        const now = new Date();
        const targetDate = new Date(date);
        const diffInSeconds = Math.floor((now - targetDate) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`;
        
        return this.formatDate(date);
    }
    
    showNotification(message, type = 'info', duration = 5000) {
        // Remove existing notifications of the same type
        document.querySelectorAll(`.notification-${type}`).forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const iconMap = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        notification.innerHTML = `
            <div class="notification-content">
                <span class="material-icons notification-icon">${iconMap[type] || 'info'}</span>
                <span class="notification-message">${message}</span>
            </div>
            <button class="notification-close" onclick="this.parentElement.remove()" aria-label="Close notification">
                <span class="material-icons">close</span>
            </button>
        `;
        
        // Add to notification container or body
        const container = document.querySelector('.notification-container') || document.body;
        container.appendChild(notification);
        
        // Animate in
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });
        
        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.add('hide');
                    setTimeout(() => notification.remove(), 300);
                }
            }, duration);
        }
        
        return notification;
    }
    
    async apiRequest(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...this.defaultFetchOptions,
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            console.error('API Request failed:', error);
            this.showNotification('Network error. Please check your connection and try again.', 'error');
            throw error;
        }
    }
    
    // Page navigation with loading states (full reload)
    navigateTo(page, params = {}) {
        // Cancel any pending notification requests to prevent blocking navigation
        if (typeof window.cancelNotificationRequests === 'function') {
            window.cancelNotificationRequests();
        }
        
        const url = new URL(window.location);
        url.searchParams.set('page', page);
        
        // Add additional parameters
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.set(key, params[key]);
            }
        });
        
        // Show loading state
        this.showPageLoading();
        
        // Navigate with full page reload
        window.location.href = url.toString();
    }
    
    cleanupPageInstances() {
        // Clean up existing page instances to prevent memory leaks
        if (window.dashboardPage && typeof window.dashboardPage.destroy === 'function') {
            window.dashboardPage.destroy();
        }
        if (window.resourcesPage && typeof window.resourcesPage.destroy === 'function') {
            window.resourcesPage.destroy();
        }
        if (window.requestsPage && typeof window.requestsPage.destroy === 'function') {
            window.requestsPage.destroy();
        }
        if (window.monitorRequestsPage && typeof window.monitorRequestsPage.destroy === 'function') {
            window.monitorRequestsPage.destroy();
        }
        if (window.hazardDashboard && typeof window.hazardDashboard.destroy === 'function') {
            window.hazardDashboard.destroy();
        }
        
        // Clear references
        window.dashboardPage = null;
        window.resourcesPage = null;
        window.requestsPage = null;
        window.monitorRequestsPage = null;
        window.hazardDashboard = null;
    }
    
    initializePage(page) {
        // Initialize page-specific classes
        if (page === 'dashboard' && typeof DashboardPage !== 'undefined') {
            window.dashboardPage = new DashboardPage();
        } else if (page === 'resources' && typeof ResourcesPage !== 'undefined') {
            window.resourcesPage = new ResourcesPage();
        } else if (page === 'requests' && typeof RequestsPage !== 'undefined') {
            window.requestsPage = new RequestsPage();
        } else if (page === 'monitor_requests' && typeof MonitorRequestsPage !== 'undefined') {
            window.monitorRequestsPage = new MonitorRequestsPage();
        } else if (page === 'hazard' && typeof HazardDashboard !== 'undefined') {
            window.hazardDashboard = new HazardDashboard();
        }
        
        // Trigger DOMContentLoaded-like event for page scripts
        const event = new Event('pageContentLoaded');
        document.dispatchEvent(event);
    }
    
    hidePageLoading() {
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.remove();
        }
    }
    
    showPageLoading() {
        // Remove existing loader if any
        this.hidePageLoading();
        
        const loader = document.createElement('div');
        loader.className = 'page-loader';
        loader.innerHTML = `
            <div class="loader-content">
                <div class="loader-spinner"></div>
                <span>Loading...</span>
            </div>
        `;
        document.body.appendChild(loader);
    }
    
    // Local storage utilities with error handling
    setStorage(key, value, useSession = false) {
        try {
            const storage = useSession ? sessionStorage : localStorage;
            storage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.warn('Storage not available:', error);
            return false;
        }
    }
    
    getStorage(key, defaultValue = null, useSession = false) {
        try {
            const storage = useSession ? sessionStorage : localStorage;
            const item = storage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.warn('Storage not available:', error);
            return defaultValue;
        }
    }
    
    removeStorage(key, useSession = false) {
        try {
            const storage = useSession ? sessionStorage : localStorage;
            storage.removeItem(key);
            return true;
        } catch (error) {
            console.warn('Storage not available:', error);
            return false;
        }
    }
    
    // Lazy loading utilities for performance optimization
    static lazyLoadAPI(url, options = {}) {
        const cacheKey = `api_cache_${url}`;
        const cache = sessionStorage.getItem(cacheKey);
        const cacheTime = options.cacheTime || 30000; // 30 seconds default
        
        // Return cached response if valid
        if (cache && !options.forceRefresh) {
            try {
                const cached = JSON.parse(cache);
                if (Date.now() - cached.timestamp < cacheTime) {
                    return Promise.resolve(cached.data);
                }
            } catch (e) {
                // Invalid cache, continue to fetch
            }
        }
        
        // Check if request is already pending
        if (window._pendingRequests && window._pendingRequests[url]) {
            return window._pendingRequests[url];
        }
        
        // Create new request
        const requestPromise = fetch(url, {
            credentials: 'same-origin',
            ...options.fetchOptions
        })
        .then(response => response.json())
        .then(data => {
            // Cache the response
            try {
                sessionStorage.setItem(cacheKey, JSON.stringify({
                    data: data,
                    timestamp: Date.now()
                }));
            } catch (e) {
                // Cache full, ignore
            }
            
            // Remove from pending requests
            if (window._pendingRequests) {
                delete window._pendingRequests[url];
            }
            
            return data;
        })
        .catch(error => {
            // Remove from pending requests on error
            if (window._pendingRequests) {
                delete window._pendingRequests[url];
            }
            throw error;
        });
        
        // Track pending request
        if (!window._pendingRequests) {
            window._pendingRequests = {};
        }
        window._pendingRequests[url] = requestPromise;
        
        return requestPromise;
    }
    
    static deferUntilVisible(element, callback, options = {}) {
        if (!element || !callback) return;
        
        // If element is already visible, call immediately
        const rect = element.getBoundingClientRect();
        if (rect.top < window.innerHeight && rect.bottom > 0) {
            callback();
            return;
        }
        
        // Use IntersectionObserver if available
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        callback();
                        observer.disconnect();
                    }
                });
            }, {
                rootMargin: options.rootMargin || '50px',
                threshold: options.threshold || 0.1
            });
            
            observer.observe(element);
        } else {
            // Fallback to scroll event
            const checkVisibility = () => {
                const rect = element.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    callback();
                    window.removeEventListener('scroll', checkVisibility);
                }
            };
            window.addEventListener('scroll', checkVisibility);
            // Also check after a delay
            setTimeout(checkVisibility, 1000);
        }
    }
    
    static deferUntilIdle(callback, timeout = 5000) {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(callback, { timeout });
        } else {
            // Fallback to setTimeout with 0 delay
            setTimeout(callback, 0);
        }
    }
}

// Enhanced global navigation functions with loading states
function navigateToResources(params = {}) {
    window.municipalityDashboard.navigateTo('resources', params);
}

function navigateToRequests(params = {}) {
    window.municipalityDashboard.navigateTo('requests', params);
}

function navigateToInventory(params = {}) {
    window.municipalityDashboard.navigateTo('inventory', params);
}

function navigateToNotifications(params = {}) {
    window.municipalityDashboard.navigateTo('notifications', params);
}

function navigateToDashboard(params = {}) {
    window.municipalityDashboard.navigateTo('dashboard', params);
}

// Initialize dashboard
window.municipalityDashboard = new MunicipalityDashboard();

// Add some basic styles for notifications and tooltips
document.addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style');
    style.textContent = `
        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            z-index: 10000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
            white-space: nowrap;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 400px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            display: flex;
            align-items: center;
            padding: 16px;
            gap: 12px;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.hide {
            transform: translateX(100%);
        }
        
        .notification-success {
            border-left: 4px solid #10B981;
        }
        
        .notification-error {
            border-left: 4px solid #EF4444;
        }
        
        .notification-warning {
            border-left: 4px solid #F59E0B;
        }
        
        .notification-info {
            border-left: 4px solid #3B82F6;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        .notification-icon {
            font-size: 20px;
        }
        
        .notification-success .notification-icon {
            color: #10B981;
        }
        
        .notification-error .notification-icon {
            color: #EF4444;
        }
        
        .notification-warning .notification-icon {
            color: #F59E0B;
        }
        
        .notification-info .notification-icon {
            color: #3B82F6;
        }
        
        .notification-message {
            font-size: 0.9rem;
            color: #374151;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #6B7280;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-close:hover {
            background: #F3F4F6;
        }
        
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .loader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            color: #374151;
        }
        
        .loader-spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #E5E7EB;
            border-top: 3px solid #3B82F6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    `;
    document.head.appendChild(style);
});