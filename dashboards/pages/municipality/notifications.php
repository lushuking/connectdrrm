<div class="container-fluid">
    <style>
        /* Notifications page (View all) - keep styling scoped to this page */
        #notificationsPageList {
            padding: 8px 8px 0 8px;
        }
        #notificationsPageList .notification-item {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 12px 14px;
            margin: 10px 0;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
        }
        #notificationsPageList .notification-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.10);
        }
        #notificationsPageList .notification-item .message {
            line-height: 1.25;
            font-size: 0.98rem;
        }
        #notificationsPageList .notification-item small {
            font-size: 0.8rem;
        }
        #notificationsPageList .material-icons {
            font-size: 18px;
        }
        #loadMoreContainer {
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            background: #f8fafc;
        }
        #loadMoreBtn {
            border-radius: 999px;
            padding: 10px 18px;
        }
        #notificationsPageList .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        /* Make badges slightly smaller on the notifications list */
        #notificationsPageList .badge.bg-primary {
            font-size: 0.72rem;
            padding: 0.35rem 0.55rem;
            border-radius: 999px;
        }
    </style>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="card-title mb-1">Notifications</h2>
                            <p class="text-muted mb-0">Latest updates and alerts for your municipality</p>
                        </div>
                        <div>
                            <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                                <span class="material-icons me-1">done_all</span>
                                Mark All as Read
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div id="notificationsPageList">
                        <div class="text-center p-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading notifications...</p>
                        </div>
                    </div>
                    <!-- Load More Button Container -->
                    <div id="loadMoreContainer" class="text-center p-3" style="display: none;">
                        <button id="loadMoreBtn" class="btn btn-outline-secondary" onclick="loadMoreNotifications()">
                            <span class="material-icons me-1">expand_more</span>
                            Load More Notifications
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
let currentOffset = 0;
const notificationsPerPage = 20;
let hasMoreNotifications = true;

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    
    // Refresh header notifications if function exists
    if (typeof window.refreshHeaderNotifications === 'function') {
        window.refreshHeaderNotifications();
    }
});

/**
 * Load notifications from API
 */
async function loadNotifications(append = false) {
    const container = document.getElementById('notificationsPageList');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    
    if (!append) {
        container.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading notifications...</p>
            </div>
        `;
        currentOffset = 0;
        hasMoreNotifications = true;
    } else {
        loadMoreBtn.disabled = true;
        loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
    }
    
    try {
        const response = await fetch(`config/notifications.php?limit=${notificationsPerPage}&offset=${currentOffset}`, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) throw new Error('Request failed');
        const data = await response.json();
        if (!data || !data.success) throw new Error('Invalid response');
        
        const notifications = data.data.notifications || [];
        hasMoreNotifications = data.data.hasMore || false;
        
        if (notifications.length === 0 && !append) {
            container.innerHTML = `
                <div class="text-center p-5">
                    <span class="material-icons" style="font-size: 48px; color: #ccc; margin-bottom: 16px;">notifications_none</span>
                    <p class="text-muted mb-0">No notifications yet</p>
                </div>
            `;
            loadMoreContainer.style.display = 'none';
            return;
        }
        
        // Render notifications
        const notificationsHtml = notifications.map(notification => {
            const isRead = notification.isRead === 1;
            const href = notification.href;
            const isClickable = href !== null;
            
            // Get icon based on priority and message content
            let iconClass = 'info';
            let iconName = 'info';
            if (notification.priority === 'high' || notification.message.toLowerCase().includes('alert') || notification.message.toLowerCase().includes('urgent')) {
                iconClass = 'danger';
                iconName = 'warning';
            } else if (notification.message.toLowerCase().includes('approved') || notification.message.toLowerCase().includes('delivered')) {
                iconClass = 'success';
                iconName = 'check_circle';
            } else if (notification.message.toLowerCase().includes('request')) {
                iconClass = 'primary';
                iconName = 'inventory';
            }
            
            if (!isClickable) {
                return `
                    <div class="list-group-item border-0 ${isRead ? '' : 'bg-light'} notification-item" data-notif-id="${notification.notifID}">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <div class="bg-${iconClass} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="material-icons text-${iconClass}">${iconName}</span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="message ${isRead ? 'text-muted' : ''}" style="font-weight: ${isRead ? '400' : '500'};">
                                            ${escapeHtml(notification.message)}
                                        </div>
                                        <small class="text-muted">${notification.timeAgo || 'recently'}</small>
                                        <small class="d-block text-muted" style="font-size: 10px; margin-top: 4px;">(Related item no longer available)</small>
                                    </div>
                                    ${isRead ? '' : '<span class="badge bg-primary">New</span>'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            return `
                <a href="${escapeHtml(href)}" class="list-group-item border-0 ${isRead ? '' : 'bg-light'} notification-item text-decoration-none" 
                   data-notif-id="${notification.notifID}" 
                   onclick="markNotificationAsSeen(${notification.notifID});">
                    <div class="d-flex align-items-start">
                        <div class="me-3">
                            <div class="bg-${iconClass} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <span class="material-icons text-${iconClass}">${iconName}</span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="message ${isRead ? 'text-muted' : 'text-dark'}" style="font-weight: ${isRead ? '400' : '500'};">
                                        ${escapeHtml(notification.message)}
                                    </div>
                                    <small class="text-muted">${notification.timeAgo || 'recently'}</small>
                                </div>
                                ${isRead ? '' : '<span class="badge bg-primary">New</span>'}
                            </div>
                        </div>
                    </div>
                </a>
            `;
        }).join('');
        
        if (append) {
            container.insertAdjacentHTML('beforeend', notificationsHtml);
        } else {
            container.innerHTML = notificationsHtml;
        }
        
        // Show/hide load more button
        if (hasMoreNotifications) {
            loadMoreContainer.style.display = 'block';
            loadMoreBtn.disabled = false;
            loadMoreBtn.innerHTML = '<span class="material-icons me-1">expand_more</span>Load More Notifications';
        } else {
            loadMoreContainer.style.display = 'none';
        }
        
        currentOffset += notifications.length;
        
    } catch (error) {
        console.error('Failed to load notifications:', error);
        container.innerHTML = `
            <div class="text-center p-5">
                <span class="material-icons" style="font-size: 48px; color: #dc3545; margin-bottom: 16px;">error_outline</span>
                <p class="text-danger mb-2">Failed to load notifications</p>
                <button class="btn btn-outline-primary btn-sm" onclick="loadNotifications()">Retry</button>
            </div>
        `;
        loadMoreContainer.style.display = 'none';
    }
}

/**
 * Load more notifications
 */
async function loadMoreNotifications() {
    if (!hasMoreNotifications) return;
    await loadNotifications(true);
}

/**
 * Mark all notifications as read
 */
async function markAllAsRead() {
    try {
        const response = await fetch('config/notifications.php?action=mark_read', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        if (data.success) {
            showToast('All notifications marked as read', 'success');
            // Reload notifications
            await loadNotifications();
            // Refresh header if function exists
            if (typeof window.refreshHeaderNotifications === 'function') {
                window.refreshHeaderNotifications();
            }
        } else {
            showToast('Failed to mark all as read', 'error');
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
        showToast('Failed to mark all as read', 'error');
    }
}

/**
 * Mark single notification as seen
 */
async function markNotificationAsSeen(notifId) {
    if (!notifId) return;
    
    try {
        await fetch(`config/notifications.php?action=mark_single_read&id=${notifId}`, {
            method: 'GET',
            credentials: 'same-origin',
            keepalive: true
        });
        
        // Update UI immediately
        const item = document.querySelector(`[data-notif-id="${notifId}"]`);
        if (item) {
            item.classList.remove('bg-light');
            const badge = item.querySelector('.badge');
            if (badge) badge.remove();
            const message = item.querySelector('.message');
            if (message) {
                message.classList.remove('text-dark');
                message.classList.add('text-muted');
                message.style.fontWeight = '400';
            }
        }
        
        // Refresh header if function exists
        if (typeof window.refreshHeaderNotifications === 'function') {
            window.refreshHeaderNotifications();
        }
    } catch (error) {
        console.error('Error marking notification as seen:', error);
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '10000';
    
    const icon = type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <span class="material-icons me-2">${icon}</span>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

</script>