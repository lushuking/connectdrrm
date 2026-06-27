<?php
/**
 * Notification Service for ConnectDRRM
 * Centralized service for managing all notifications
 */

class NotificationService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a notification for a specific user
     */
    public function createNotification($userId, $message, $href = null, $priority = 'normal') {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO notifications (userID, message, isRead, createdAt, href, priority) 
                VALUES (?, ?, 0, NOW(), ?, ?)
            ');
            
            $result = $stmt->execute([$userId, $message, $href, $priority]);
            
            if ($result) {
                return $this->pdo->lastInsertId();
            }
            return false;
        } catch (Exception $e) {
            error_log('[NotificationService] Error creating notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notifications for all users in a municipality
     */
    public function createMunicipalityNotification($municipalityId, $message, $href = null, $priority = 'normal', $roleFilter = null) {
        try {
            $whereClause = 'WHERE u.drrmoID = ?';
            $params = [$message, $href, $priority, $municipalityId];
            
            // Filter by role if specified
            if ($roleFilter !== null) {
                $whereClause .= ' AND u.role = ?';
                $params[] = $roleFilter;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (userID, message, isRead, createdAt, href, priority) 
                SELECT u.userID, ?, 0, NOW(), ?, ? 
                FROM users u 
                $whereClause
            ");
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return $stmt->rowCount();
            }
            return false;
        } catch (Exception $e) {
            error_log('[NotificationService] Error creating municipality notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notifications for specific users (array of user IDs)
     */
    public function createBulkNotification($userIds, $message, $href = null, $priority = 'normal') {
        if (empty($userIds)) return false;
        
        try {
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (userID, message, isRead, createdAt, href, priority) 
                VALUES " . implode(',', array_fill(0, count($userIds), "(?, ?, 0, NOW(), ?, ?)"))
            );
            
            $params = [];
            foreach ($userIds as $userId) {
                $params[] = $userId;
                $params[] = $message;
                $params[] = $href;
                $params[] = $priority;
            }
            
            $result = $stmt->execute($params);
            
            if ($result) {
                return $stmt->rowCount();
            }
            return false;
        } catch (Exception $e) {
            error_log('[NotificationService] Error creating bulk notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId = null) {
        try {
            if ($userId) {
                $stmt = $this->pdo->prepare('UPDATE notifications SET isRead = 1 WHERE notifID = ? AND userID = ?');
                $result = $stmt->execute([$notificationId, $userId]);
            } else {
                $stmt = $this->pdo->prepare('UPDATE notifications SET isRead = 1 WHERE notifID = ?');
                $result = $stmt->execute([$notificationId]);
            }
            
            return $result && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('[NotificationService] Error marking notification as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->pdo->prepare('UPDATE notifications SET isRead = 1 WHERE userID = ? AND isRead = 0');
            $result = $stmt->execute([$userId]);
            
            return $result ? $stmt->rowCount() : false;
        } catch (Exception $e) {
            error_log('[NotificationService] Error marking all notifications as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user (filtered by municipality and role)
     */
    public function getUserNotifications($userId, $limit = 50, $offset = 0, $unreadOnly = false) {
        try {
            // First get the user's municipality and role
            $userStmt = $this->pdo->prepare('SELECT drrmoID, role FROM users WHERE userID = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            
            if (!$user) {
                return []; // User not found
            }
            
            $municipalityId = $user['drrmoID'];
            $userRole = $user['role'] ?? '';
            
            $whereClause = 'WHERE n.userID = ? AND u.drrmoID = ?';
            $params = [$userId, $municipalityId];
            
            // Filter by role: approving_authority should only see approval-related notifications
            if ($userRole === 'approving_authority') {
                // Only show notifications that are approval-related (check message or href)
                $whereClause .= " AND (n.message LIKE '%pending approval%' OR n.message LIKE '%approval%' OR n.href LIKE '%approving_authority%' OR n.href LIKE '%approvals%')";
            }
            // For drrmo_staff, show all their notifications (no additional filter)
            
            if ($unreadOnly) {
                $whereClause .= ' AND n.isRead = 0';
            }
            
            $stmt = $this->pdo->prepare("
                SELECT n.notifID, n.message, n.isRead, n.createdAt, n.href, n.priority 
                FROM notifications n
                LEFT JOIN users u ON n.userID = u.userID
                $whereClause 
                ORDER BY n.createdAt DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('[NotificationService] Error getting user notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of notifications for a user
     */
    public function getTotalNotificationCount($userId, $unreadOnly = false) {
        try {
            $userStmt = $this->pdo->prepare('SELECT drrmoID, role FROM users WHERE userID = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            
            if (!$user) {
                return 0;
            }
            
            $municipalityId = $user['drrmoID'];
            $userRole = $user['role'] ?? '';
            
            $whereClause = 'WHERE n.userID = ? AND u.drrmoID = ?';
            $params = [$userId, $municipalityId];
            
            // Filter by role: approving_authority should only see approval-related notifications
            if ($userRole === 'approving_authority') {
                $whereClause .= " AND (n.message LIKE '%pending approval%' OR n.message LIKE '%approval%' OR n.href LIKE '%approving_authority%' OR n.href LIKE '%approvals%')";
            }
            
            if ($unreadOnly) {
                $whereClause .= ' AND n.isRead = 0';
            }
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM notifications n
                LEFT JOIN users u ON n.userID = u.userID
                $whereClause
            ");
            
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('[NotificationService] Error getting total notification count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get unread count for a user (filtered by municipality and role)
     */
    public function getUnreadCount($userId) {
        try {
            // First get the user's municipality and role
            $userStmt = $this->pdo->prepare('SELECT drrmoID, role FROM users WHERE userID = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            
            if (!$user) {
                return 0; // User not found
            }
            
            $municipalityId = $user['drrmoID'];
            $userRole = $user['role'] ?? '';
            
            $whereClause = 'WHERE n.userID = ? AND u.drrmoID = ? AND n.isRead = 0';
            $params = [$userId, $municipalityId];
            
            // Filter by role: approving_authority should only see approval-related notifications
            if ($userRole === 'approving_authority') {
                $whereClause .= " AND (n.message LIKE '%pending approval%' OR n.message LIKE '%approval%' OR n.href LIKE '%approving_authority%' OR n.href LIKE '%approvals%')";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM notifications n
                LEFT JOIN users u ON n.userID = u.userID
                $whereClause
            ");
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('[NotificationService] Error getting unread count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Clean up old notifications (older than specified days)
     */
    public function cleanupOldNotifications($daysOld = 30) {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM notifications WHERE createdAt < DATE_SUB(NOW(), INTERVAL ? DAY)');
            $result = $stmt->execute([$daysOld]);
            
            return $result ? $stmt->rowCount() : false;
        } catch (Exception $e) {
            error_log('[NotificationService] Error cleaning up old notifications: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create out-of-stock notification
     */
    public function createOutOfStockNotification($userId, $resourceName, $resourceId = null) {
        $message = "Out of stock: {$resourceName}";
        $href = $resourceId ? "municipality.php?page=resources&manage=1&tab=inventory&resource={$resourceId}" : "municipality.php?page=resources&manage=1&tab=inventory";
        
        return $this->createNotification($userId, $message, $href, 'high');
    }
    
    /**
     * Create request notification
     */
    public function createRequestNotification($providerMunicipalityId, $borrowerMunicipalityId, $resourceName, $quantity, $requestId) {
        $providerMessage = "New request: {$resourceName} (Qty: {$quantity})";
        $borrowerMessage = "Request submitted: {$resourceName} (Qty: {$quantity})";
        
        $href = "municipality.php?page=requests&tab=incoming-requests&request={$requestId}";
        
        // Notify admin (drrmo_staff) only, not head (approving_authority)
        $this->createMunicipalityNotification($providerMunicipalityId, $providerMessage, $href, 'normal', 'drrmo_staff');
        $this->createMunicipalityNotification($borrowerMunicipalityId, $borrowerMessage, $href, 'normal', 'drrmo_staff');
    }
    
    /**
     * Create head approval notification (notify head that a request needs approval)
     */
    public function createHeadApprovalNotification($municipalityId, $resourceName, $quantity, $requestId) {
        $message = "New request pending approval: {$resourceName} (Qty: {$quantity})";
        $href = "approving_authority.php?page=approvals&request={$requestId}";
        
        // Notify head (approving_authority) only
        return $this->createMunicipalityNotification($municipalityId, $message, $href, 'high', 'approving_authority');
    }
    
    /**
     * Create request status notification
     */
    public function createRequestStatusNotification($borrowerMunicipalityId, $providerMunicipalityId, $resourceName, $quantity, $status, $requestId, $customBorrowerMessage = null, $roleFilter = 'drrmo_staff') {
        $statusText = $status === 'approved' ? 'approved' : 'rejected';
        $borrowerMessage = $customBorrowerMessage ?: "Your request for {$resourceName} (Qty: {$quantity}) has been {$statusText}";
        $providerMessage = "You {$statusText} a request for {$resourceName} (Qty: {$quantity})";
        
        $href = "municipality.php?page=requests&tab=your-requests&request={$requestId}";
        
        // Notify admin (drrmo_staff) only by default, unless roleFilter is specified
        $this->createMunicipalityNotification($borrowerMunicipalityId, $borrowerMessage, $href, 'normal', $roleFilter);
        $this->createMunicipalityNotification($providerMunicipalityId, $providerMessage, $href, 'normal', $roleFilter);
    }
    
    /**
     * Create return notification
     */
    public function createReturnNotification($providerMunicipalityId, $borrowerMunicipalityId, $resourceName, $requestId) {
        $providerMessage = "Return requested: {$resourceName}";
        $borrowerMessage = "Return submitted: {$resourceName}";
        
        $href = "municipality.php?page=requests&tab=returns&request={$requestId}";
        
        // Notify admin (drrmo_staff) only, not head
        $this->createMunicipalityNotification($providerMunicipalityId, $providerMessage, $href, 'normal', 'drrmo_staff');
        $this->createMunicipalityNotification($borrowerMunicipalityId, $borrowerMessage, $href, 'normal', 'drrmo_staff');
    }
    
    /**
     * Create fulfillment notification
     */
    public function createFulfillmentNotification($providerMunicipalityId, $borrowerMunicipalityId, $resourceName, $requestId) {
        $providerMessage = "Resource delivered: {$resourceName}";
        $borrowerMessage = "Resource received: {$resourceName}";
        
        $href = "municipality.php?page=requests&tab=fulfilled&request={$requestId}";
        
        // Notify admin (drrmo_staff) only, not head
        $this->createMunicipalityNotification($providerMunicipalityId, $providerMessage, $href, 'normal', 'drrmo_staff');
        $this->createMunicipalityNotification($borrowerMunicipalityId, $borrowerMessage, $href, 'normal', 'drrmo_staff');
    }
    
    /**
     * Clean up notifications for a deleted request
     */
    public function cleanupRequestNotifications($requestId) {
        try {
            // Delete notifications that reference this request in their href
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications 
                WHERE href LIKE ? OR href LIKE ?
            ");
            
            $pattern1 = "%request={$requestId}%";
            $pattern2 = "%request={$requestId}&%";
            $result = $stmt->execute([$pattern1, $pattern2]);
            
            return $result ? $stmt->rowCount() : 0;
        } catch (Exception $e) {
            error_log('[NotificationService] Error cleaning up request notifications: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Validate and clean up orphaned notifications (notifications pointing to non-existent requests)
     */
    public function cleanupOrphanedNotifications($userId = null) {
        try {
            $whereClause = "WHERE n.href LIKE '%page=requests%' AND n.href LIKE '%request=%'";
            $params = [];
            
            if ($userId) {
                $whereClause .= " AND n.userID = ?";
                $params[] = $userId;
            }
            
            // Extract request IDs from href and check if they exist
            $stmt = $this->pdo->prepare("
                SELECT n.notifID, n.href 
                FROM notifications n
                $whereClause
            ");
            
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $deletedCount = 0;
            foreach ($notifications as $notif) {
                // Extract request ID from href
                if (preg_match('/request=(\d+)/', $notif['href'], $matches)) {
                    $requestId = (int)$matches[1];
                    
                    // Check if request exists
                    $checkStmt = $this->pdo->prepare('SELECT requestID FROM requests WHERE requestID = ? LIMIT 1');
                    $checkStmt->execute([$requestId]);
                    $exists = $checkStmt->fetch();
                    
                    // If request doesn't exist, delete the notification
                    if (!$exists) {
                        $delStmt = $this->pdo->prepare('DELETE FROM notifications WHERE notifID = ?');
                        $delStmt->execute([$notif['notifID']]);
                        $deletedCount += $delStmt->rowCount();
                    }
                }
            }
            
            return $deletedCount;
        } catch (Exception $e) {
            error_log('[NotificationService] Error cleaning up orphaned notifications: ' . $e->getMessage());
            return 0;
        }
    }
}
?>
