<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header("Location: ../login.php");
    exit();
}

$user_role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'member';
if (!in_array($user_role, ['admin', 'support'])) {
    header("Location: ../memberDashboard.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../lib/base.php';

$db = db();
$user_id = $_SESSION['user_id'];
$is_admin = ($user_role === 'admin');

// CSRF token - Only create if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token mismatch";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Rate limiting - max 10 messages per minute
    $rate_limit_key = 'chat_rate_' . $user_id;
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => time()];
    }
    
    $rate_data = $_SESSION[$rate_limit_key];
    if (time() - $rate_data['time'] > 60) {
        // Reset after 1 minute
        $_SESSION[$rate_limit_key] = ['count' => 1, 'time' => time()];
    } elseif ($rate_data['count'] >= 10) {
        $_SESSION['error'] = "Rate limit exceeded. Please wait a minute.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION[$rate_limit_key]['count']++;
    }
    
    if (isset($_POST['send_message'])) {
        $receiver_id = $is_admin ? ($_POST['receiver_id'] ?? null) : ($_POST['receiver_id'] ?? null); // Support can now choose receiver
        $message = trim($_POST['message'] ?? '');
        $is_broadcast = isset($_POST['is_broadcast']) && $is_admin;
        $reply_to = $_POST['reply_to'] ?? null;
        
        // Server-side validation
        $errors = [];
        
        if (empty($message)) {
            $errors[] = "Message cannot be empty";
        } elseif (strlen($message) > 1000) {
            $errors[] = "Message too long (max 1000 characters)";
        }
        
        if (!$is_broadcast && !$receiver_id) {
            $errors[] = "Receiver not specified";
        }
        
        if ($receiver_id && !is_numeric($receiver_id)) {
            $errors[] = "Invalid receiver";
        }
        
        if (empty($errors)) {
            // Convert empty string to NULL for reply_to
            $reply_to = (empty($reply_to) || $reply_to === '') ? null : (int)$reply_to;
            
            try {
                if ($is_broadcast) {
                    // Admin sends broadcast to all support agents
                    $stmt = $db->prepare("
                        INSERT INTO internal_messages 
                        (sender_id, receiver_id, message_type, message, reply_to_message_id) 
                        VALUES (?, NULL, 'broadcast', ?, ?)
                    ");
                    $stmt->execute([$user_id, $message, $reply_to]);
                    
                    // Create notifications for all support agents
                    $support_agents_stmt = $db->prepare("
                        SELECT user_id FROM users WHERE role = 'support' AND is_active = 1 AND user_id != ?
                    ");
                    $support_agents_stmt->execute([$user_id]);
                    $support_agents = $support_agents_stmt->fetchAll();
                    
                    foreach ($support_agents as $agent) {
                        $notif_stmt = $db->prepare("
                            INSERT INTO notifications (user_id, title, message, type, related_id)
                            VALUES (?, 'New Broadcast Message', ?, 'internal_chat', ?)
                        ");
                        $notif_stmt->execute([
                            $agent['user_id'],
                            "You have received a broadcast message from admin",
                            $user_id
                        ]);
                    }
                    
                    $_SESSION['success'] = "Message broadcasted to all support agents!";
                    
                } elseif ($receiver_id) {
                    // Direct message
                    $stmt = $db->prepare("
                        INSERT INTO internal_messages 
                        (sender_id, receiver_id, message_type, message, reply_to_message_id) 
                        VALUES (?, ?, 'direct', ?, ?)
                    ");
                    $stmt->execute([$user_id, $receiver_id, $message, $reply_to]);
                    
                    // Get sender info for notification
                    $sender_stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
                    $sender_stmt->execute([$user_id]);
                    $sender = $sender_stmt->fetch();
                    
                    // Create notification for receiver
                    $notif_stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, related_id)
                        VALUES (?, ?, ?, 'internal_chat', ?)
                    ");
                    
                    // Shorten message for notification
                    $short_message = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
                    
                    $notif_stmt->execute([
                        $receiver_id,
                        'New Internal Message',
                        $sender['username'] . ': ' . $short_message,
                        $user_id
                    ]);
                    
                    $_SESSION['success'] = "Message sent!";
                }
                
                // Redirect to prevent form resubmission
                $redirect_url = $_SERVER['PHP_SELF'];
                if (isset($_GET['chat_with']) && !$is_broadcast) {
                    $redirect_url .= '?chat_with=' . $_GET['chat_with'];
                }
                
                header("Location: " . $redirect_url);
                exit();
                
            } catch (Exception $e) {
                $_SESSION['error'] = "Error sending message: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    }
    
    // Handle delete message
    if (isset($_POST['delete_message']) && isset($_POST['message_id'])) {
        $message_id = $_POST['message_id'];
        
        // Validation
        if (!is_numeric($message_id)) {
            $_SESSION['error'] = "Invalid message ID";
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM internal_messages WHERE message_id = ? AND sender_id = ?");
                if ($stmt->execute([$message_id, $user_id])) {
                    $_SESSION['success'] = "Message deleted!";
                } else {
                    $_SESSION['error'] = "Failed to delete message";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error deleting message: " . $e->getMessage();
            }
        }
        
        // Preserve chat_with parameter on redirect
        $redirect_url = $_SERVER['PHP_SELF'];
        if (isset($_GET['chat_with'])) {
            $redirect_url .= '?chat_with=' . $_GET['chat_with'];
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// Check for success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get current user info
$user_stmt = $db->prepare("SELECT username, email FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch();

// Get users to chat with
if ($is_admin) {
    // Admin can chat with support agents
    $agents_stmt = $db->prepare("
        SELECT user_id, username, email 
        FROM users 
        WHERE role = 'support' AND is_active = 1 AND user_id != ?
        ORDER BY username ASC
    ");
    $agents_stmt->execute([$user_id]);
    $agents = $agents_stmt->fetchAll();
} else {
    // Support can chat with admin AND other support agents
    $agents_stmt = $db->prepare("
        SELECT user_id, username, email, role 
        FROM users 
        WHERE (role = 'admin' OR (role = 'support' AND user_id != ?)) 
        AND is_active = 1
        ORDER BY 
            CASE role 
                WHEN 'admin' THEN 1 
                WHEN 'support' THEN 2 
                ELSE 3 
            END,
            username ASC
    ");
    $agents_stmt->execute([$user_id]);
    $agents = $agents_stmt->fetchAll();
}

// Determine which conversation to show - FIXED: Check if agents array is not empty
$chat_with_id = $_GET['chat_with'] ?? (isset($agents[0]) ? $agents[0]['user_id'] : null);
$chat_with_user = null;
if ($chat_with_id) {
    $user_stmt = $db->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ? AND is_active = 1");
    $user_stmt->execute([$chat_with_id]);
    $chat_with_user = $user_stmt->fetch();
}

// MARK ALL CHAT NOTIFICATIONS AS READ WHEN OPENING CHAT PAGE - FIX FOR HEADER COUNT
// This is the key fix: When user opens chat page, ALL their chat notifications should be marked as read
if ($user_id) {
    try {
        // Mark ALL chat notifications as read for this user
        $mark_all_notif_stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? 
            AND type = 'internal_chat' 
            AND is_read = 0
        ");
        $mark_all_notif_stmt->execute([$user_id]);
        
        // Also mark direct messages from this conversation as read
        if ($chat_with_id && $chat_with_user) {
            $mark_read_stmt = $db->prepare("
                UPDATE internal_messages 
                SET is_read = 1 
                WHERE receiver_id = ? 
                AND sender_id = ? 
                AND message_type = 'direct' 
                AND is_read = 0
            ");
            $mark_read_stmt->execute([$user_id, $chat_with_id]);
        }
        
    } catch (Exception $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
    }
}

// Get messages for current chat WITH REPLY INFO
if ($chat_with_id && $chat_with_user) {
    $messages_stmt = $db->prepare("
        SELECT m.*, 
               s.username as sender_name,
               s.user_id as sender_id,
               r.username as receiver_name,
               rm.message as replied_message,
               rm.sender_id as replied_sender_id,
               rs.username as replied_sender_name
        FROM internal_messages m
        LEFT JOIN users s ON m.sender_id = s.user_id
        LEFT JOIN users r ON m.receiver_id = r.user_id
        LEFT JOIN internal_messages rm ON m.reply_to_message_id = rm.message_id
        LEFT JOIN users rs ON rm.sender_id = rs.user_id
        WHERE (
            -- Direct messages between you and the other user
            (m.message_type = 'direct' AND m.sender_id = ? AND m.receiver_id = ?) OR
            (m.message_type = 'direct' AND m.sender_id = ? AND m.receiver_id = ?) OR
            -- Broadcast messages
            (m.message_type = 'broadcast')
        )
        ORDER BY m.created_at ASC
    ");
    
    $messages_stmt->execute([$user_id, $chat_with_id, $chat_with_id, $user_id]);
    $messages = $messages_stmt->fetchAll();
} else {
    $messages = [];
}

// Get unread counts for each conversation
$unread_counts = [];
$unread_stmt = $db->prepare("
    SELECT sender_id, COUNT(*) as count 
    FROM internal_messages 
    WHERE receiver_id = ? AND is_read = 0 AND message_type = 'direct'
    GROUP BY sender_id
");
$unread_stmt->execute([$user_id]);
$unread_counts = $unread_stmt->fetchAll(PDO::FETCH_KEY_PAIR);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_admin ? 'Admin Chat' : 'Support Chat'; ?></title>
    
    <!-- Use project CSS instead of Bootstrap -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/chat.css">
    
    <!-- jQuery (required per spec) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        /* Minimal inline CSS - most should be in chat.css */
        body { margin: 0; padding: 0; background: #f5f5f5; }
        .alert-fixed { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1000; 
            max-width: 300px; 
        }
        
        /* Chat container */
        .chat-container {
            display: flex;
            height: calc(100vh - 70px);
            background: white;
        }
        
        /* Sidebar */
        .chat-sidebar {
            width: 300px;
            background: #fff;
            border-right: 1px solid #dee2e6;
            display: flex;
            flex-direction: column;
        }
        
        .user-profile {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            margin-right: 12px;
        }
        
        .contact-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .contact-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .contact-item:hover {
            background: #f8f9fa;
        }
        
        .contact-item.active {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
        }
        
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
        }
        
        /* Chat Area */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px 20px;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .messages-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message-wrapper {
            display: flex;
            max-width: 70%;
            position: relative;
        }
        
        .message-wrapper.sent {
            margin-left: auto;
        }
        
        .message-wrapper.received {
            margin-right: auto;
        }
        
        .message-wrapper.broadcast {
            max-width: 80%;
            margin: 10px auto;
        }
        
        .message-bubble {
            padding: 12px 16px;
            border-radius: 8px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            max-width: 100%;
        }
        
        .message-bubble.sent {
            background: #007bff;
            color: white;
            padding-right: 60px;
        }
        
        .message-bubble.received {
            background: white;
            border: 1px solid #dee2e6;
            padding-left: 60px;
        }
        
        .message-bubble.broadcast {
            background: #ffc107;
            color: #212529;
            text-align: center;
            font-weight: 500;
            border-radius: 20px;
        }
        
        .message-reply {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid rgba(255, 255, 255, 0.3);
            padding: 8px 12px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 0.9em;
            color: rgba(255, 255, 255, 0.8);
            max-width: 100%;
            overflow: hidden;
        }
        
        .message-bubble.received .message-reply {
            background: rgba(0,0,0,0.05);
            border-left: 3px solid #6c757d;
            color: #666;
        }
        
        .message-text {
            margin: 5px 0;
            line-height: 1.4;
        }
        
        .message-time {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
            text-align: right;
        }
        
        .message-bubble.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Message Actions - Hover buttons */
        .message-actions {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            gap: 5px;
        }
        
        .message-wrapper.sent .message-actions {
            right: 10px;
        }
        
        .message-wrapper.received .message-actions {
            left: 10px;
        }
        
        .message-wrapper:hover .message-actions {
            opacity: 1;
        }
        
        .message-action-btn {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .message-action-btn:hover {
            background: #f8f9fa;
            transform: scale(1.05);
        }
        
        .message-bubble.sent .message-action-btn {
            background: rgba(255, 255, 255, 0.9);
        }
        
        /* Input Area */
        .input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #dee2e6;
        }
        
        .message-input {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 12px 16px;
            resize: none;
            transition: all 0.2s;
            font-family: inherit;
            width: 100%;
            box-sizing: border-box;
        }
        
        .message-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }
        
        .reply-preview {
            background: #f8f9fa;
            border-left: 3px solid #007bff;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .reply-preview .cancel-reply {
            color: #dc3545;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            background: none;
            border: none;
        }
        
        .reply-preview .cancel-reply:hover {
            opacity: 0.8;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
        }
        
        .modal-header {
            padding: 20px;
            background: #ffc107;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-outline-secondary {
            background: transparent;
            border: 1px solid #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }
        
        /* Input group */
        .input-group {
            display: flex;
            gap: 10px;
        }
        
        .input-group .message-input {
            flex: 1;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Text styles */
        .text-muted {
            color: #6c757d;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Flex utilities */
        .d-flex {
            display: flex;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .justify-content-center {
            justify-content: center;
        }
        
        .justify-content-between {
            justify-content: space-between;
        }
        
        .flex-column {
            flex-direction: column;
        }
        
        /* Spacing */
        .mb-0 { margin-bottom: 0; }
        .mb-3 { margin-bottom: 1rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .m-2 { margin: 0.5rem; }
        .ms-2 { margin-left: 0.5rem; }
        .ms-3 { margin-left: 1rem; }
        .px-3 { padding-left: 1rem; padding-right: 1rem; }
        .px-4 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .pt-3 { padding-top: 1rem; }
        .pb-2 { padding-bottom: 0.5rem; }
        .p-5 { padding: 3rem; }
        
        /* Borders */
        .border-bottom {
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Links */
        .text-decoration-none {
            text-decoration: none;
        }
        
        /* Icons */
        .fa-4x { font-size: 4rem; }
        .fa-6x { font-size: 6rem; }
        
        /* Auto resizing */
        .h-100 { height: 100%; }
        .my-auto { margin-top: auto; margin-bottom: auto; }
        
        /* Role badges */
        .role-badge {
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .role-admin {
            background: #dc3545;
            color: white;
        }
        
        .role-support {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="chat-container">
        <?php if ($success): ?>
            <div class="alert alert-success alert-fixed">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-fixed">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="user-profile d-flex align-items-center">
                <div class="user-avatar">
                    <?php 
                    // FIX: Check if username exists before using substr
                    $username_initials = isset($current_user['username']) ? strtoupper(substr($current_user['username'], 0, 1)) : '?';
                    echo $username_initials; 
                    ?>
                </div>
                <div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($current_user['username'] ?? 'User'); ?></h5>
                    <small class="text-muted"><?php echo ucfirst($user_role); ?></small>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
                <button class="btn btn-warning m-2" onclick="showBroadcastModal()">
                    <i class="fas fa-bullhorn"></i> Broadcast to All Support
                </button>
            <?php endif; ?>
            
            <div class="contact-list">
                <h6 class="px-3 pt-3 pb-2 text-muted mb-0 border-bottom">
                    TEAM MEMBERS
                </h6>
                
                <?php if (!empty($agents)): ?>
                    <?php foreach ($agents as $agent): ?>
                        <a href="?chat_with=<?php echo $agent['user_id']; ?>" class="text-decoration-none text-dark">
                            <div class="contact-item <?php echo ($chat_with_id == $agent['user_id']) ? 'active' : ''; ?>">
                                <div class="user-avatar" style="width: 36px; height: 36px; font-size: 16px;">
                                    <?php 
                                    // FIX: Check if username exists before using substr
                                    $agent_initials = isset($agent['username']) ? strtoupper(substr($agent['username'], 0, 1)) : '?';
                                    echo $agent_initials; 
                                    ?>
                                </div>
                                <div class="ms-2">
                                    <h6 class="mb-0">
                                        <?php echo htmlspecialchars($agent['username'] ?? 'Unknown User'); ?>
                                        <?php if (isset($agent['role'])): ?>
                                            <span class="role-badge role-<?php echo $agent['role']; ?>">
                                                <?php echo strtoupper(substr($agent['role'], 0, 1)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($agent['email'] ?? ''); ?></small>
                                </div>
                                <?php if (($unread_counts[$agent['user_id']] ?? 0) > 0): ?>
                                    <span class="unread-badge"><?php echo $unread_counts[$agent['user_id']]; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="px-3 pt-3 text-center text-muted">
                        <p>No team members available to chat with.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Chat Area -->
        <div class="chat-main">
            <?php if ($chat_with_user): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="d-flex align-items-center">
                        <div class="user-avatar" style="width: 36px; height: 36px; font-size: 16px;">
                            <?php 
                            // FIX: Check if username exists before using substr
                            $chat_with_initials = isset($chat_with_user['username']) ? strtoupper(substr($chat_with_user['username'], 0, 1)) : '?';
                            echo $chat_with_initials; 
                            ?>
                        </div>
                        <div class="ms-3">
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($chat_with_user['username'] ?? 'Unknown User'); ?>
                                <?php if (isset($chat_with_user['role'])): ?>
                                    <span class="role-badge role-<?php echo $chat_with_user['role']; ?>">
                                        <?php echo ucfirst($chat_with_user['role']); ?>
                                    </span>
                                <?php endif; ?>
                            </h5>
                            <small class="text-muted"><?php echo htmlspecialchars($chat_with_user['email'] ?? ''); ?></small>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="refreshChat()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Messages Area -->
                <div class="messages-area" id="messagesArea">
                    <?php foreach ($messages as $msg): ?>
                        <?php
                        $is_sent = ($msg['sender_id'] == $user_id);
                        $is_broadcast = ($msg['message_type'] === 'broadcast');
                        $wrapper_class = $is_sent ? 'sent' : 'received';
                        $bubble_class = $is_sent ? 'sent' : 'received';
                        if ($is_broadcast) {
                            $wrapper_class = 'broadcast';
                            $bubble_class = 'broadcast';
                        }
                        ?>
                        <div class="message-wrapper <?php echo $wrapper_class; ?>">
                            <div class="message-bubble <?php echo $bubble_class; ?>">
                                <?php if ($msg['reply_to_message_id'] && $msg['replied_message']): ?>
                                    <div class="message-reply">
                                        <small class="text-muted">
                                            <?php 
                                            $reply_prefix = ($msg['replied_sender_id'] == $user_id) ? 'You' : htmlspecialchars($msg['replied_sender_name'] ?? 'Unknown');
                                            echo "Replying to {$reply_prefix}";
                                            ?>
                                        </small>
                                        <div class="text-truncate"><?php echo nl2br(htmlspecialchars(substr($msg['replied_message'] ?? '', 0, 100))); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message-text">
                                    <?php echo nl2br(htmlspecialchars($msg['message'] ?? '')); ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div class="message-time">
                                        <?php 
                                        if ($is_broadcast) {
                                            echo 'Broadcast from: ' . htmlspecialchars($msg['sender_name'] ?? 'Unknown') . ' • ';
                                        } else if (!$is_sent) {
                                            echo 'From: ' . htmlspecialchars($msg['sender_name'] ?? 'Unknown') . ' • ';
                                        }
                                        echo isset($msg['created_at']) ? date('H:i', strtotime($msg['created_at'])) : ''; 
                                        ?>
                                        <?php if ($is_broadcast): ?>
                                            <i class="fas fa-bullhorn ms-2"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Action buttons that appear on hover -->
                                <div class="message-actions">
                                    <?php if (!$is_broadcast): ?>
                                        <button class="message-action-btn" 
                                                onclick="replyToMessage(<?php echo $msg['message_id']; ?>, '<?php echo addslashes(substr($msg['message'] ?? '', 0, 50)); ?>')"
                                                title="Reply">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($is_sent && !$is_broadcast): ?>
                                        <button class="message-action-btn" 
                                                onclick="deleteMessage(<?php echo $msg['message_id']; ?>)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-muted my-auto">
                            <i class="fas fa-comments fa-4x mb-3"></i>
                            <h4>No messages yet</h4>
                            <p>Start the conversation!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Input Area -->
                <div class="input-area">
                    <!-- Reply Preview -->
                    <div id="replyPreview" style="display: none;" class="reply-preview">
                        <div>
                            <small class="text-muted">Replying to:</small>
                            <div id="replyText" class="text-truncate"></div>
                        </div>
                        <div class="cancel-reply" onclick="cancelReply()" title="Cancel reply">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                    
                    <form method="POST" id="messageForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="receiver_id" id="receiverId" value="<?php echo $chat_with_id; ?>">
                        <input type="hidden" name="reply_to" id="replyTo" value="">
                        
                        <div class="input-group">
                            <textarea name="message" 
                                      class="message-input" 
                                      id="messageInput" 
                                      rows="1" 
                                      placeholder="Type a message..." 
                                      required
                                      oninput="autoResize(this)"></textarea>
                            <button type="submit" 
                                    name="send_message" 
                                    class="btn btn-primary ms-3 px-4">
                                Send <i class="fas fa-paper-plane ms-1"></i>
                            </button>
                        </div>
                        
                        <div class="text-muted mt-3 text-end">
                            Press <kbd>Ctrl</kbd> + <kbd>Enter</kbd> to send
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-comments fa-6x text-muted"></i>
                    </div>
                    <h3>Welcome to Internal Chat</h3>
                    <p class="text-muted mb-4">
                        <?php echo empty($agents) ? 'No team members available to chat with.' : 'Select a conversation from the sidebar to start chatting'; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Broadcast Modal -->
    <div class="modal-overlay" id="broadcastModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="mb-0">
                    <i class="fas fa-bullhorn"></i> Broadcast to All Support
                </h5>
                <button type="button" class="close-btn" onclick="hideBroadcastModal()">&times;</button>
            </div>
            <form method="POST" id="broadcastForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Message:</label>
                        <textarea name="message" 
                                  class="message-input" 
                                  id="broadcastMessage"
                                  rows="4" 
                                  placeholder="Enter your broadcast message..." 
                                  required></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This message will be sent to all support agents.
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="is_broadcast" value="1">
                    <input type="hidden" name="reply_to" value="">
                    <button type="button" class="btn btn-secondary" onclick="hideBroadcastModal()">Cancel</button>
                    <button type="submit" name="send_message" class="btn btn-warning">
                        <i class="fas fa-bullhorn"></i> Send Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Reply functionality
        let replyToId = null;
        let replyToText = '';
        
        function replyToMessage(messageId, text) {
            replyToId = messageId;
            replyToText = text;
            
            document.getElementById('replyTo').value = messageId;
            document.getElementById('replyText').textContent = text;
            document.getElementById('replyPreview').style.display = 'flex';
            document.getElementById('messageInput').focus();
            
            scrollToBottom();
        }
        
        function cancelReply() {
            replyToId = null;
            replyToText = '';
            document.getElementById('replyTo').value = '';
            document.getElementById('replyPreview').style.display = 'none';
            document.getElementById('messageInput').focus();
        }
        
        // Delete message
        function deleteMessage(messageId) {
            if (confirm('Are you sure you want to delete this message?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo $csrf_token; ?>';
                
                const input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'delete_message';
                input1.value = '1';
                
                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'message_id';
                input2.value = messageId;
                
                form.appendChild(csrfInput);
                form.appendChild(input1);
                form.appendChild(input2);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showBroadcastModal() {
            document.getElementById('broadcastModal').style.display = 'flex';
        }
        
        function hideBroadcastModal() {
            document.getElementById('broadcastModal').style.display = 'none';
            document.getElementById('broadcastMessage').value = '';
        }
        
        function refreshChat() {
            window.location.reload();
        }
        
        // Auto-resize textarea
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }
        
        // Scroll to bottom
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            if (messagesArea) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
            
            // Auto-hide alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-fixed');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
            
            // Form validation
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    const messageInput = document.getElementById('messageInput');
                    if (messageInput && messageInput.value.trim() === '') {
                        e.preventDefault();
                        alert('Please enter a message');
                        messageInput.focus();
                    }
                });
            }
            
            const broadcastForm = document.getElementById('broadcastForm');
            if (broadcastForm) {
                broadcastForm.addEventListener('submit', function(e) {
                    const broadcastMessage = document.getElementById('broadcastMessage');
                    if (broadcastMessage && broadcastMessage.value.trim() === '') {
                        e.preventDefault();
                        alert('Please enter a broadcast message');
                        broadcastMessage.focus();
                    }
                });
            }
            
            // Handle Ctrl+Enter to send message
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    const messageInput = document.getElementById('messageInput');
                    if (messageInput && messageInput === document.activeElement) {
                        const messageForm = document.getElementById('messageForm');
                        if (messageForm) {
                            messageForm.submit();
                        }
                    }
                }
            });
            
            // Close modal when clicking outside
            document.getElementById('broadcastModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    hideBroadcastModal();
                }
            });
        });
    </script>
</body>
</html>