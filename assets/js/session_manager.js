/**
 * Session Management JavaScript
 * Handles session timeout warnings and automatic logout
 */

class SessionManager {
    constructor() {
        this.sessionTimeout = 30 * 60 * 1000; // 30 minutes in milliseconds
        this.warningTimeout = 5 * 60 * 1000;  // Show warning 5 minutes before timeout
        this.checkInterval = 60 * 1000;       // Check every minute
        this.lastActivity = Date.now();
        this.timeoutModal = null;
        this.countdownInterval = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.startSessionMonitoring();
        this.setupTimeoutModal();
    }
    
    bindEvents() {
        // Track user activity
        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        
        activityEvents.forEach(event => {
            document.addEventListener(event, () => {
                this.updateLastActivity();
            }, { passive: true });
        });
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkSession();
            }
        });
        
        // Handle beforeunload to clean up
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }
    
    setupTimeoutModal() {
        const modalElement = document.getElementById('sessionTimeoutModal');
        if (modalElement) {
            this.timeoutModal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
            
            // Bind modal buttons
            document.getElementById('extendSessionBtn')?.addEventListener('click', () => {
                this.extendSession();
            });
            
            document.getElementById('logoutNowBtn')?.addEventListener('click', () => {
                this.forceLogout();
            });
        }
    }
    
    startSessionMonitoring() {
        setInterval(() => {
            this.checkSession();
        }, this.checkInterval);
    }
    
    updateLastActivity() {
        this.lastActivity = Date.now();
    }
    
    checkSession() {
        const now = Date.now();
        const timeSinceLastActivity = now - this.lastActivity;
        const timeUntilTimeout = this.sessionTimeout - timeSinceLastActivity;
        
        if (timeUntilTimeout <= 0) {
            // Session has expired
            this.handleSessionExpired();
        } else if (timeUntilTimeout <= this.warningTimeout && !this.isModalShown()) {
            // Show warning
            this.showTimeoutWarning(timeUntilTimeout);
        }
    }
    
    showTimeoutWarning(timeRemaining) {
        if (this.timeoutModal && !this.isModalShown()) {
            this.timeoutModal.show();
            this.startCountdown(timeRemaining);
        }
    }
    
    startCountdown(timeRemaining) {
        let remainingTime = timeRemaining;
        
        this.countdownInterval = setInterval(() => {
            remainingTime -= 1000;
            
            if (remainingTime <= 0) {
                this.handleSessionExpired();
                return;
            }
            
            const minutes = Math.floor(remainingTime / 60000);
            const seconds = Math.floor((remainingTime % 60000) / 1000);
            const countdownElement = document.getElementById('timeoutCountdown');
            
            if (countdownElement) {
                countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        }, 1000);
    }
    
    extendSession() {
        this.updateLastActivity();
        this.hideTimeoutWarning();
        
        // Optionally ping server to keep session alive
        this.pingServer();
        
        // Show success notification
        if (window.municipalityDashboard) {
            window.municipalityDashboard.showNotification('Session extended successfully', 'success', 3000);
        }
    }
    
    hideTimeoutWarning() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        
        if (this.timeoutModal && this.isModalShown()) {
            this.timeoutModal.hide();
        }
    }
    
    handleSessionExpired() {
        this.cleanup();
        
        // Show expiration message and redirect
        alert('Your session has expired due to inactivity. You will be redirected to the login page.');
        window.location.href = 'login.php?reason=timeout';
    }
    
    forceLogout() {
        this.cleanup();
        window.location.href = 'logout.php';
    }
    
    isModalShown() {
        const modalElement = document.getElementById('sessionTimeoutModal');
        return modalElement && modalElement.classList.contains('show');
    }
    
    pingServer() {
        // Send a lightweight request to keep session alive
        fetch('config/ping.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({ action: 'ping' })
        })
        .catch(error => {
            console.warn('Session ping failed:', error);
        });
    }
    
    cleanup() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }
    
    // Public method to manually refresh session
    refreshSession() {
        this.updateLastActivity();
        this.hideTimeoutWarning();
        this.pingServer();
    }
    
    // Get remaining session time
    getRemainingTime() {
        const now = Date.now();
        const timeSinceLastActivity = now - this.lastActivity;
        return Math.max(0, this.sessionTimeout - timeSinceLastActivity);
    }
    
    // Format time for display
    formatTime(milliseconds) {
        const totalSeconds = Math.floor(milliseconds / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

// Initialize session manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if user is logged in
    if (window.currentUser) {
        window.sessionManager = new SessionManager();
        
        // Add session info to footer or status bar if needed
        const sessionInfo = document.querySelector('.session-info');
        if (sessionInfo) {
            setInterval(() => {
                const remaining = window.sessionManager.getRemainingTime();
                const formatted = window.sessionManager.formatTime(remaining);
                sessionInfo.textContent = `Session expires in: ${formatted}`;
            }, 1000);
        }
    }
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SessionManager;
}