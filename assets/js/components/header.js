/**
 * Header Component JavaScript
 */

class HeaderComponent {
    constructor() {
        this.previousUnreadCount = 0;
        this.notificationSound = null;
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updateUserInfo();
        this.setupDropdownClose();
        this.setupNotifications();
        this.initNotificationSound();
    }
    
    /**
     * Initialize notification sound
     * Supports both custom audio file and Web Audio API fallback
     */
    initNotificationSound() {
        // Audio context will be created on first use (requires user interaction)
        this.audioContext = null;
        
        // Try to load custom notification sound file
        // Place your notification sound at: assets/sounds/notification.mp3 (or .wav, .ogg)
        this.notificationAudio = null;
        this.audioFileLoaded = false;
        
        const audioPaths = [
            'assets/sounds/notification.mp3',
            'assets/sounds/notification.wav',
            'assets/sounds/notification.ogg',
            'assets/sounds/notify.mp3',
            'assets/sounds/notify.wav'
        ];
        
        // Try to load the first available audio file
        // We'll check if it loads successfully
        for (const path of audioPaths) {
            const audio = new Audio(path);
            audio.preload = 'auto';
            audio.volume = 0.5; // 50% volume
            
            // Try to load and see if it succeeds
            audio.addEventListener('canplaythrough', () => {
                // File loaded successfully
                if (!this.notificationAudio) {
                    this.notificationAudio = audio;
                    this.audioFileLoaded = true;
                }
            }, { once: true });
            
            audio.addEventListener('error', () => {
                // This file doesn't exist, continue checking
            }, { once: true });
            
            // Try to load by setting src (triggers loading)
            try {
                audio.load();
                // If we can create the audio object, assume it might work
                // Actual loading will be verified by the event listeners
                if (!this.notificationAudio) {
                    this.notificationAudio = audio;
                }
            } catch (e) {
                // Continue to next path
            }
        }
    }
    
    /**
     * Play notification sound
     * Uses custom audio file if available, otherwise falls back to Web Audio API beep
     */
    playNotificationSound() {
        // Try to play custom audio file first
        if (this.notificationAudio && this.audioFileLoaded) {
            try {
                // Reset audio to start if it's already played
                if (this.notificationAudio.currentTime > 0) {
                    this.notificationAudio.currentTime = 0;
                }
                // Play the sound
                const playPromise = this.notificationAudio.play();
                if (playPromise !== undefined) {
                    playPromise.catch((e) => {
                        // If custom audio fails (e.g., user interaction required), fall back to beep
                        this.playNotificationBeep();
                    });
                }
                return;
            } catch (e) {
                // Fall back to beep if custom audio fails
                this.playNotificationBeep();
            }
        }
        
        // Fallback to programmatic beep (works without file, more reliable)
        this.playNotificationBeep();
    }
    
    /**
     * Play notification beep using Web Audio API (fallback)
     */
    playNotificationBeep() {
        try {
            // Create audio context on first use (browsers require user interaction)
            if (!this.audioContext) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                this.audioContext = new AudioContext();
            }
            
            // Resume audio context if suspended (some browsers suspend on page load)
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume().catch(() => {
                    // Silently fail if resume fails
                });
            }
            
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            // Simple beep: 800Hz for 0.1 seconds
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.1);
            
            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(this.audioContext.currentTime + 0.1);
        } catch (e) {
            // Silently fail if audio is not available
            console.warn('Failed to play notification beep:', e);
        }
    }
    
    /**
     * Trigger visual notification indicators
     */
    triggerNotificationIndicators() {
        const btn = document.getElementById('notificationsBtn');
        const badge = document.getElementById('notifBadge');
        
        if (badge && badge.style.display !== 'none') {
            // Add pulsing animation
            badge.classList.add('notification-pulse');
            setTimeout(() => {
                badge.classList.remove('notification-pulse');
            }, 1000);
        }
        
        if (btn) {
            // Add shake animation to bell icon
            btn.classList.add('notification-shake');
            setTimeout(() => {
                btn.classList.remove('notification-shake');
            }, 500);
        }
    }
    
    bindEvents() {
        const quickActionBtn = document.getElementById('quickActionBtn');
        if (quickActionBtn) {
            quickActionBtn.addEventListener('click', this.handleQuickAction.bind(this));
        }
        
        const userProfile = document.querySelector('.user-profile');
        if (userProfile) {
            userProfile.addEventListener('click', this.toggleUserMenu.bind(this));
        }
    }
    
    handleQuickAction() {
        // Show quick action dropdown or modal
        this.showQuickActionMenu();
    }
    
    showQuickActionMenu() {
        // Close any existing quick action menu
        const existingMenu = document.querySelector('.quick-action-menu');
        if (existingMenu) {
            existingMenu.remove();
            return; // Toggle off if already open
        }
        
        // Detect portal type from current URL
        const isPdrrmo = window.location.pathname.includes('pdrrmo');
        const portalBase = isPdrrmo ? 'pdrrmo.php' : 'municipality.php';
        
        // Build menu items based on portal
        let menuItems = '';
        
        // Common items for all users
        menuItems += `
            <a class="quick-action-item" href="${portalBase}?page=resources">
                <span class="material-icons" style="color: #0d6efd;">inventory_2</span>
                <div>
                    <div class="fw-semibold">View Resources</div>
                    <small class="text-muted">Check inventory & availability</small>
                </div>
            </a>
            <a class="quick-action-item" href="${portalBase}?page=requests">
                <span class="material-icons" style="color: #198754;">add_circle</span>
                <div>
                    <div class="fw-semibold">Resource Requests</div>
                    <small class="text-muted">Create or manage requests</small>
                </div>
            </a>
            <a class="quick-action-item" href="${portalBase}?page=hazard">
                <span class="material-icons" style="color: #dc3545;">warning</span>
                <div>
                    <div class="fw-semibold">Hazard Map</div>
                    <small class="text-muted">View hazard information</small>
                </div>
            </a>
            <a class="quick-action-item" href="${portalBase}?page=reports">
                <span class="material-icons" style="color: #6f42c1;">assessment</span>
                <div>
                    <div class="fw-semibold">Generate Report</div>
                    <small class="text-muted">Create analytics & PDF reports</small>
                </div>
            </a>
        `;
        
        // PDRRMO-only items
        if (isPdrrmo) {
            menuItems += `
                <div class="quick-action-divider"></div>
                <a class="quick-action-item" href="${portalBase}?page=monitor_requests">
                    <span class="material-icons" style="color: #fd7e14;">monitor</span>
                    <div>
                        <div class="fw-semibold">Monitor Requests</div>
                        <small class="text-muted">Track all municipality requests</small>
                    </div>
                </a>
                <a class="quick-action-item" href="${portalBase}?page=user_management">
                    <span class="material-icons" style="color: #20c997;">people</span>
                    <div>
                        <div class="fw-semibold">User Management</div>
                        <small class="text-muted">Manage accounts & passwords</small>
                    </div>
                </a>
            `;
        }
        
        // Settings & notifications (all users)
        menuItems += `
            <div class="quick-action-divider"></div>
            <a class="quick-action-item" href="${portalBase}?page=notifications">
                <span class="material-icons" style="color: #0dcaf0;">notifications_active</span>
                <div>
                    <div class="fw-semibold">Notifications</div>
                    <small class="text-muted">View all notifications</small>
                </div>
            </a>
            <a class="quick-action-item" href="${portalBase}?page=settings">
                <span class="material-icons" style="color: #6c757d;">settings</span>
                <div>
                    <div class="fw-semibold">Settings</div>
                    <small class="text-muted">Account & security settings</small>
                </div>
            </a>
        `;
        
        const menu = document.createElement('div');
        menu.className = 'quick-action-menu active';
        menu.innerHTML = `
            <div class="quick-action-header">
                <span class="material-icons" style="font-size:18px; color: var(--primary-color);">flash_on</span>
                <span class="fw-bold">Quick Actions</span>
            </div>
            ${menuItems}
        `;
        
        document.body.appendChild(menu);
        
        // Position the menu
        const btn = document.getElementById('quickActionBtn');
        const rect = btn.getBoundingClientRect();
        menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
        menu.style.top = rect.bottom + 8 + 'px';
        menu.style.zIndex = '10001';
        
        // Close notification dropdown if open
        const notificationDropdown = document.querySelector('.notifications.dropdown.active');
        if (notificationDropdown) {
            notificationDropdown.classList.remove('active');
        }
        
        // Close menu when clicking outside
        const closeMenu = (e) => {
            if (!menu.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
                document.removeEventListener('keydown', escHandler);
            }
        };
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                menu.remove();
                document.removeEventListener('click', closeMenu);
                document.removeEventListener('keydown', escHandler);
            }
        };
        
        // Delay to prevent the current click from immediately closing the menu
        setTimeout(() => {
            document.addEventListener('click', closeMenu);
            document.addEventListener('keydown', escHandler);
        }, 10);
    }
    
    toggleUserMenu(e) {
        e.stopPropagation();
        const dropdown = document.querySelector('.user-profile.dropdown');
        if (dropdown) {
            dropdown.classList.toggle('active');
        }
    }
    
    updateUserInfo() {
        // Update user information display
        // This could fetch from an API or local storage
    }
    
    setupDropdownClose() {
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.querySelector('.user-profile.dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const dropdown = document.querySelector('.user-profile.dropdown');
                if (dropdown) {
                    dropdown.classList.remove('active');
                }
            }
        });
    }

    setupNotifications() {
        const btn = document.getElementById('notificationsBtn');
        const badge = document.getElementById('notifBadge');
        const dropdown = document.getElementById('notificationsDropdown');
        const list = document.getElementById('notificationsList');
        if (!btn || !dropdown) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            
            // Close quick action menu if open
            const quickActionMenu = document.querySelector('.quick-action-menu');
            if (quickActionMenu) {
                quickActionMenu.remove();
            }
            
            dropdown.classList.toggle('active');
            if (dropdown.classList.contains('active')) {
                // Load full list when dropdown opens
                this.loadNotifications(list, badge);
            }
        });

        // Real-time notifications using Server-Sent Events (SSE)
        let eventSource = null;
        let reconnectTimeout = null;
        let reconnectAttempts = 0;
        const MAX_RECONNECT_ATTEMPTS = 5;
        const RECONNECT_DELAY = 3000; // 3 seconds
        
        const connectSSE = () => {
            // Close existing connection if any
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            // Only connect if tab is visible
            if (document.hidden) {
                return;
            }
            
            try {
                eventSource = new EventSource('config/notifications_sse.php');
                
                eventSource.onopen = () => {
                    console.log('[SSE] Connected to notification stream');
                    reconnectAttempts = 0;
                    // Immediately load notifications on connect
                    this.loadNotifications(null, badge);
                };
                
                eventSource.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        
                        if (data.type === 'connected') {
                            console.log('[SSE] Connection established');
                        } else if (data.type === 'update') {
                            // Real-time update received
                            const unreadCount = data.unreadCount || 0;
                            const notifications = data.notifications || [];

                            // Broadcast a lightweight "something changed" event.
                            // Other pages/modules can listen and refresh their tables in real-time.
                            try {
                                document.dispatchEvent(new CustomEvent('realtime:update', {
                                    detail: {
                                        reason: 'notifications_sse',
                                        unreadCount,
                                        timestamp: data.timestamp || Date.now(),
                                        notifications
                                    }
                                }));
                            } catch (_) {}
                            
                            // Update badge immediately
                            if (badge) {
                                const previousCount = this.previousUnreadCount || 0;
                                
                                if (unreadCount > 0) {
                                    badge.textContent = String(unreadCount);
                                    badge.style.display = 'inline-block';
                                    if (!badge.classList.contains('has-unread')) {
                                        badge.classList.add('has-unread');
                                    }
                                } else {
                                    badge.style.display = 'none';
                                    badge.classList.remove('has-unread');
                                }
                                
                                // Play sound and show indicators for new notifications
                                if (unreadCount > previousCount && previousCount > 0) {
                                    if (!document.hidden) {
                                        this.playNotificationSound();
                                    }
                                    this.triggerNotificationIndicators();
                                }
                                
                                this.previousUnreadCount = unreadCount;
                            }
                            
                            // Update list if dropdown is open
                            if (dropdown.classList.contains('active') && list) {
                                // Load full list when dropdown is open
                                this.loadNotifications(list, badge);
                            }
                        } else if (data.type === 'error') {
                            console.error('[SSE] Error:', data.message);
                        } else if (data.type === 'timeout') {
                            console.log('[SSE] Connection timeout, reconnecting...');
                            reconnectSSE();
                        }
                    } catch (e) {
                        console.error('[SSE] Error parsing message:', e);
                    }
                };
                
                eventSource.onerror = (error) => {
                    console.error('[SSE] Connection error:', error);
                    eventSource.close();
                    eventSource = null;
                    reconnectSSE();
                };
                
            } catch (e) {
                console.error('[SSE] Failed to create EventSource:', e);
                reconnectSSE();
            }
        };
        
        const reconnectSSE = () => {
            if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
                console.error('[SSE] Max reconnect attempts reached, falling back to polling');
                // Fallback to polling if SSE fails
                startPollingFallback();
                return;
            }
            
            reconnectAttempts++;
            console.log(`[SSE] Reconnecting in ${RECONNECT_DELAY}ms (attempt ${reconnectAttempts}/${MAX_RECONNECT_ATTEMPTS})`);
            
            reconnectTimeout = setTimeout(() => {
                connectSSE();
            }, RECONNECT_DELAY);
        };
        
        // Fallback to polling if SSE is not available
        const startPollingFallback = () => {
            console.log('[Notifications] Using polling fallback');
            let lastRequestTime = 0;
            const REQUEST_COOLDOWN = 3000;
            
            const poll = () => {
                if (document.hidden) return;
                
                const now = Date.now();
                if (now - lastRequestTime < REQUEST_COOLDOWN) return;
                
                lastRequestTime = now;
                const isOpen = dropdown.classList.contains('active');
                this.loadNotifications(isOpen ? list : null, badge);
            };
            
            // Poll every 5 seconds as fallback
            setInterval(poll, 5000);
            poll(); // Initial load
        };
        
        // Connect when page becomes visible
        const handleVisibilityChange = () => {
            if (document.hidden) {
                // Close connection when tab is hidden to save resources
                if (eventSource) {
                    eventSource.close();
                    eventSource = null;
                }
                if (reconnectTimeout) {
                    clearTimeout(reconnectTimeout);
                    reconnectTimeout = null;
                }
            } else {
                // Reconnect when tab becomes visible
                connectSSE();
            }
        };
        
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Initial connection
        connectSSE();
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (eventSource) {
                eventSource.close();
            }
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
            }
        });
        
        // Expose manual refresh function
        try {
            window.refreshHeaderNotifications = () => {
                this.loadNotifications(dropdown.classList.contains('active') ? list : null, badge);
            };
        } catch(_) {}
        
        // Listen for custom refresh events
        document.addEventListener('notifications:refresh', () => {
            this.loadNotifications(dropdown.classList.contains('active') ? list : null, badge);
        });
    }

    async loadNotifications(list, badge, signal = null) {
        try {
            console.log('Loading notifications from new API...');
            
            const fetchOptions = { 
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            
            // Add abort signal if provided
            if (signal) {
                fetchOptions.signal = signal;
            }
            
            const res = await fetch('config/notifications.php', fetchOptions);
            if (!res.ok) throw new Error('Request failed');
            const data = await res.json();
            if (!data || !data.success) throw new Error('Invalid response');

            console.log('Notifications data:', data);

            // Update badge only if not manually updating
            const unreadCount = data.data?.unreadCount || 0;
            const notifications = data.data?.notifications || [];
            
            // Check if we have new notifications
            const hasNewNotifications = unreadCount > this.previousUnreadCount && this.previousUnreadCount > 0;
            
            if (badge && !window.manualBadgeUpdate) {
                const previousCount = this.previousUnreadCount;
                
                if (unreadCount > 0) {
                    badge.textContent = String(unreadCount);
                    badge.style.display = 'inline-block';
                    
                    // Add pulsing class if there are unread notifications
                    if (!badge.classList.contains('has-unread')) {
                        badge.classList.add('has-unread');
                    }
                } else {
                    badge.style.display = 'none';
                    badge.classList.remove('has-unread');
                }
                
                // Trigger sound and visual indicators for new notifications
                if (hasNewNotifications && unreadCount > previousCount) {
                    // Only play sound if tab is visible and we're not on the notifications page
                    if (!document.hidden) {
                        this.playNotificationSound();
                    }
                    this.triggerNotificationIndicators();
                }
                
                console.log('Auto-updated badge to:', unreadCount);
            } else if (window.manualBadgeUpdate) {
                console.log('Skipped badge update due to manual update in progress');
            }
            
            // Update previous count after processing
            this.previousUnreadCount = unreadCount;

            // Render list
            if (list) {
                if (!notifications || notifications.length === 0) {
                    list.innerHTML = '<div class="empty">No notifications</div>';
                } else {
                    console.log('Rendering notifications:', notifications.length, 'items');
                    list.innerHTML = notifications.map(notification => {
                        const href = notification.href;
                        const isRead = notification.isRead === 1;
                        const isClickable = href !== null;
                        
                        // If href is null (orphaned notification), make it non-clickable
                        if (!isClickable) {
                            return `
                                <div class="item ${isRead ? 'read' : 'unread'} notification-orphaned" data-notif-id="${notification.notifID}">
                                    <div class="content">
                                        <div class="message ${isRead ? 'read-text' : 'unread-text'}">${this.escapeHtml(notification.message)}</div>
                                        <div class="time">${notification.timeAgo || new Date(notification.createdAt).toLocaleString()}</div>
                                        <small class="text-muted" style="font-size: 10px; color: #999;">(Related item no longer available)</small>
                                    </div>
                                    ${isRead ? '' : '<div class="unread-indicator"></div>'}
                                </div>
                            `;
                        }
                        
                        console.log('Notification href:', href, 'isRead:', isRead);
                        return `
                            <a class="item ${isRead ? 'read' : 'unread'}" href="${this.escapeHtml(href)}" 
                               data-notif-id="${notification.notifID}" onclick="markNotificationAsSeen(${notification.notifID});">
                                <div class="content">
                                    <div class="message ${isRead ? 'read-text' : 'unread-text'}">${this.escapeHtml(notification.message)}</div>
                                    <div class="time">${notification.timeAgo || new Date(notification.createdAt).toLocaleString()}</div>
                                </div>
                                ${isRead ? '' : '<div class="unread-indicator"></div>'}
                            </a>
                        `;
                    }).join('');
                }
            }
        } catch (e) {
            console.error('Failed to load notifications:', e);
            if (list) list.innerHTML = '<div class="empty">Failed to load</div>';
            throw e;
        }
    }

    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

// Global function to handle notification clicks
window.handleNotificationClick = function(event, msgKey, href) {
    console.log('Notification clicked:', msgKey, href);
    
    // Mark as seen first (but don't prevent navigation)
    if (msgKey) {
        markNotificationAsSeen(msgKey);
    }
    
    // Let the default navigation happen (don't prevent default)
    // The href attribute will handle the navigation
    console.log('Allowing navigation to:', href);
};

// Global flag to prevent automatic badge updates when manually updating
window.manualBadgeUpdate = false;

// Global function to mark notification as seen
window.markNotificationAsSeen = function(notifId) {
    if (!notifId) return;
    
    try {
        // Set flag to prevent automatic updates
        window.manualBadgeUpdate = true;
        
        console.log('Marking notification as seen:', notifId);
        
        // Call the API to mark notification as read
        fetch(`config/notifications.php?action=mark_single_read&id=${notifId}`, {
            method: 'GET',
            credentials: 'same-origin',
            keepalive: true
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Successfully marked notification as read');
                
                // Update the clicked notification immediately in the UI
                const notificationElement = document.querySelector(`[data-notif-id="${notifId}"]`);
                if (notificationElement) {
                    // Remove unread styling and add read styling
                    notificationElement.classList.remove('unread');
                    notificationElement.classList.add('read');
                    
                    // Update the message styling
                    const messageElement = notificationElement.querySelector('.message');
                    if (messageElement) {
                        messageElement.classList.remove('unread-text');
                        messageElement.classList.add('read-text');
                    }
                    
                    // Remove the unread indicator
                    const indicatorElement = notificationElement.querySelector('.unread-indicator');
                    if (indicatorElement) {
                        indicatorElement.remove();
                    }
                    
                    console.log('Updated notification styling for:', notifId);
                }
                
                // Update badge immediately
                const updateBadge = () => {
                    const badge = document.getElementById('notifBadge');
                    console.log('Badge element found:', badge);
                    
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        const newCount = Math.max(0, currentCount - 1);
                        console.log('Updating badge from', currentCount, 'to', newCount);
                        
                        if (newCount > 0) {
                            badge.textContent = String(newCount);
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                        
                        // Force the update by triggering a reflow
                        badge.offsetHeight;
                    } else {
                        console.error('Badge element not found!');
                    }
                };
                
                // Try multiple times to ensure the update works
                updateBadge();
                setTimeout(updateBadge, 50);
                setTimeout(updateBadge, 150);
            } else {
                console.error('Failed to mark notification as read:', data.error);
            }
        }).catch(error => {
            console.error('Error marking notification as read:', error);
        });
        
        // Reset flag after updates are complete
        setTimeout(() => {
            window.manualBadgeUpdate = false;
        }, 500);
        
    } catch (e) {
        console.error('Failed to mark notification as seen:', e);
    }
};

// Mark all notifications as seen (Facebook-style "Mark all as read")
window.markAllNotificationsAsSeen = function() {
    try {
        // Set flag to prevent automatic updates
        window.manualBadgeUpdate = true;
        
        console.log('Marking all notifications as read...');
        
        // Call the API to mark all notifications as read
        fetch('config/notifications.php?action=mark_read', {
            method: 'GET',
            credentials: 'same-origin'
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Successfully marked all notifications as read');
                
                // Get all current notification items from the DOM
                const notificationItems = document.querySelectorAll('#notificationsList .item[data-notif-id]');
                
                notificationItems.forEach(item => {
                    // Update UI immediately for each notification
                    item.classList.remove('unread');
                    item.classList.add('read');
                    
                    const messageElement = item.querySelector('.message');
                    if (messageElement) {
                        messageElement.classList.remove('unread-text');
                        messageElement.classList.add('read-text');
                    }
                    
                    const indicatorElement = item.querySelector('.unread-indicator');
                    if (indicatorElement) {
                        indicatorElement.remove();
                    }
                });
                
                // Update badge immediately to 0
                const badge = document.getElementById('notifBadge');
                if (badge) {
                    badge.style.display = 'none';
                }
                
                console.log('Marked all notifications as seen');
            } else {
                console.error('Failed to mark all notifications as read:', data.error);
            }
        }).catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
        
        // Reset flag after updates are complete
        setTimeout(() => {
            window.manualBadgeUpdate = false;
        }, 500);
        
    } catch (e) {
        console.error('Failed to mark all notifications as seen:', e);
    }
};

// Initialize header component
document.addEventListener('DOMContentLoaded', () => {
    window.headerComponent = new HeaderComponent();
});