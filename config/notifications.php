<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_service.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => ['code' => 'unauthorized', 'message' => 'Not authenticated'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => ['code' => 'bad_request', 'message' => 'Missing user id'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
    exit;
}

$action = $_GET['action'] ?? 'list';
$municipalityId = $_SESSION['municipality_id'] ?? null;

try {
    $notificationService = new NotificationService($pdo);
    
    if ($action === 'mark_read') {
        $updated = $notificationService->markAllAsRead($userId);
        echo json_encode(['success' => true, 'data' => ['updated' => $updated], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
        exit;
    }

    if ($action === 'mark_single_read') {
        $notificationId = $_GET['id'] ?? null;
        if (!$notificationId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => ['code' => 'bad_request', 'message' => 'Missing notification ID'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
            exit;
        }
        
        $success = $notificationService->markAsRead($notificationId, $userId);
        echo json_encode(['success' => $success, 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
        exit;
    }
    
    // Get pagination parameters
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    // Clean up orphaned notifications periodically (only for first request)
    if ($offset === 0 && rand(1, 10) === 1) {
        // 10% chance to run cleanup on each request to avoid overhead
        try {
            $notificationService->cleanupOrphanedNotifications($userId);
        } catch (Exception $e) {
            // Silently fail cleanup - don't block notification loading
            error_log('[notifications] Cleanup error: ' . $e->getMessage());
        }
    }
    // Get notifications from database
    $notifications = $notificationService->getUserNotifications($userId, $limit, $offset);
    // Get unread count
    $unreadCount = $notificationService->getUnreadCount($userId);
    // Get total count for pagination
    $totalCount = $notificationService->getTotalNotificationCount($userId);
    
    // Format notifications for frontend and validate href
    // OPTIMIZATION: Batch check all request IDs in a single query instead of N+1 queries
    $requestIds = [];
    foreach ($notifications as $notification) {
        if (!empty($notification['href']) && preg_match('/request=(\d+)/', $notification['href'], $matches)) {
            $requestIds[] = (int)$matches[1];
        }
    }
    
    // Batch check which requests exist (single query instead of N queries)
    $existingRequestIds = [];
    if (!empty($requestIds)) {
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $checkStmt = $pdo->prepare("SELECT requestID FROM requests WHERE requestID IN ($placeholders)");
        $checkStmt->execute($requestIds);
        $existingRequestIds = array_flip($checkStmt->fetchAll(PDO::FETCH_COLUMN));
    }
    // Format notifications using the batch-checked results
    $formattedNotifications = array_map(function($notification) use ($existingRequestIds) {
        $href = $notification['href'];
        
        // Validate href - check if it references a deleted request (using batch-checked results)
        if ($href && preg_match('/request=(\d+)/', $href, $matches)) {
            $requestId = (int)$matches[1];
            
            // If request doesn't exist (not in our batch-checked results), set href to null
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

    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $formattedNotifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
            'hasMore' => ($offset + count($formattedNotifications)) < $totalCount
        ],
        'meta' => ['ts' => (int)(microtime(true)*1000)]
    ]);

} catch (Exception $e) {
    error_log('[notifications] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'server_error', 'message' => 'Server error'], 'meta' => ['ts' => (int)(microtime(true)*1000)]]);
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