<?php
/**
 * Notification Cleanup Service
 * Run this periodically to clean up old notifications
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_service.php';

try {
    $notificationService = new NotificationService($pdo);
    
    // Clean up notifications older than 30 days
    $deletedCount = $notificationService->cleanupOldNotifications(30);
    
    echo "Cleanup completed. Deleted {$deletedCount} old notifications.\n";
    
    // Optional: Log cleanup statistics
    $totalNotifications = $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    $unreadNotifications = $pdo->query('SELECT COUNT(*) FROM notifications WHERE isRead = 0')->fetchColumn();
    
    echo "Current stats: {$totalNotifications} total notifications, {$unreadNotifications} unread\n";
    
} catch (Exception $e) {
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
