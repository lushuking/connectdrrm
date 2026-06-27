<?php
/**
 * Server-Sent Events (SSE) endpoint for real-time notifications
 * Streams notification updates to connected clients
 */

session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_service.php';

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Prevent output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Only allow authenticated users
if (!isLoggedIn()) {
    echo "data: " . json_encode(['error' => 'unauthorized']) . "\n\n";
    flush();
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo "data: " . json_encode(['error' => 'missing_user_id']) . "\n\n";
    flush();
    exit;
}

/**
 * CRITICAL: Release the PHP session lock for long-lived SSE connections.
 * Without this, any other endpoint that calls session_start() will block until SSE ends,
 * which looks like "random" 6s/20s/80s delays on dashboard/resources requests.
 */
session_write_close();

// Send initial connection message
echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
flush();

$notificationService = new NotificationService($pdo);
$lastUnreadCount = null;
$lastNotificationId = null;
$checkInterval = 2; // Check every 2 seconds for updates
$maxExecutionTime = 300; // Max 5 minutes per connection
$startTime = time();

// Keep connection alive and check for updates
while (true) {
    // Check if connection is still alive (client may have disconnected)
    if (connection_aborted()) {
        break;
    }
    
    // Prevent infinite execution
    if (time() - $startTime > $maxExecutionTime) {
        echo "data: " . json_encode(['type' => 'timeout']) . "\n\n";
        flush();
        break;
    }
    
    try {
        // Get current unread count
        $currentUnreadCount = $notificationService->getUnreadCount($userId);
        
        // Get latest notification ID to detect new notifications
        $latestStmt = $pdo->prepare('
            SELECT notifID FROM notifications 
            WHERE userID = ? 
            ORDER BY createdAt DESC, notifID DESC 
            LIMIT 1
        ');
        $latestStmt->execute([$userId]);
        $latestNotification = $latestStmt->fetch(PDO::FETCH_ASSOC);
        $currentNotificationId = $latestNotification ? (int)$latestNotification['notifID'] : null;
        
        // Check if there are changes
        $hasChanges = false;
        if ($lastUnreadCount === null) {
            // First check - send initial state
            $hasChanges = true;
        } else if ($currentUnreadCount !== $lastUnreadCount) {
            // Unread count changed
            $hasChanges = true;
        } else if ($currentNotificationId !== null && $currentNotificationId !== $lastNotificationId) {
            // New notification detected
            $hasChanges = true;
        }
        
        if ($hasChanges) {
            // Get latest notifications (first 5 for quick update)
            $notifications = $notificationService->getUserNotifications($userId, 5, 0);
            $totalCount = $notificationService->getTotalNotificationCount($userId);
            
            // Format notifications
            $requestIds = [];
            foreach ($notifications as $notification) {
                if (!empty($notification['href']) && preg_match('/request=(\d+)/', $notification['href'], $matches)) {
                    $requestIds[] = (int)$matches[1];
                }
            }
            
            // Batch check which requests exist
            $existingRequestIds = [];
            if (!empty($requestIds)) {
                $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                $checkStmt = $pdo->prepare("SELECT requestID FROM requests WHERE requestID IN ($placeholders)");
                $checkStmt->execute($requestIds);
                $existingRequestIds = array_flip($checkStmt->fetchAll(PDO::FETCH_COLUMN));
            }
            
            // Format notifications
            $formattedNotifications = array_map(function($notification) use ($existingRequestIds) {
                $href = $notification['href'];
                
                if ($href && preg_match('/request=(\d+)/', $href, $matches)) {
                    $requestId = (int)$matches[1];
                    if (!isset($existingRequestIds[$requestId])) {
                        $href = null;
                    }
                }
                
                return [
                    'notifID' => (int)$notification['notifID'],
                    'message' => $notification['message'],
                    'isRead' => (int)$notification['isRead'],
                    'createdAt' => $notification['createdAt'],
                    'href' => $href,
                    'priority' => $notification['priority'] ?? 'normal',
                    'timeAgo' => getTimeAgo($notification['createdAt'])
                ];
            }, $notifications);
            
            // Send update
            echo "data: " . json_encode([
                'type' => 'update',
                'unreadCount' => $currentUnreadCount,
                'totalCount' => $totalCount,
                'notifications' => $formattedNotifications,
                'timestamp' => time()
            ]) . "\n\n";
            flush();
            
            // Update last known state
            $lastUnreadCount = $currentUnreadCount;
            $lastNotificationId = $currentNotificationId;
        } else {
            // Send heartbeat to keep connection alive
            echo ": heartbeat\n\n";
            flush();
        }
        
    } catch (Exception $e) {
        error_log('[notifications_sse] Error: ' . $e->getMessage());
        echo "data: " . json_encode(['type' => 'error', 'message' => 'Server error']) . "\n\n";
        flush();
    }
    
    // Wait before next check
    sleep($checkInterval);
}

/**
 * Get human-readable time ago string
 */
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}
?>
