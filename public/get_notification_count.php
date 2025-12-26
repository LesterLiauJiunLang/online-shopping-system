<?php
// get_notification_count.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/database.php';

$response = ['success' => false, 'count' => 0, 'new_notifications' => false];

if (isset($_SESSION['user_id'])) {
    $db = db();
    $user_id = $_SESSION['user_id'];
    
    try {
        // Get total unread notifications
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        // Check for new notifications since last check
        $last_check = $_SESSION['last_notification_check'] ?? 0;
        $current_time = time();
        
        $stmt = $db->prepare("SELECT COUNT(*) as new_count FROM notifications WHERE user_id = ? AND created_at > FROM_UNIXTIME(?)");
        $stmt->execute([$user_id, $last_check]);
        $new_result = $stmt->fetch();
        
        $response = [
            'success' => true,
            'count' => $result['count'] ?? 0,
            'new_notifications' => ($new_result['new_count'] ?? 0) > 0
        ];
        
        // Update last check time (only if more than 5 seconds have passed)
        if ($current_time - $last_check > 5) {
            $_SESSION['last_notification_check'] = $current_time;
        }
        
    } catch (Exception $e) {
        error_log("Notification count error: " . $e->getMessage());
        $response = ['success' => false, 'count' => 0, 'new_notifications' => false, 'error' => $e->getMessage()];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
