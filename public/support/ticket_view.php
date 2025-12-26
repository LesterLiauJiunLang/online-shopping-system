<?php
// support/ticket_view.php - View ticket and reply (for both admin and support)
if (session_status() === PHP_SESSION_NONE) session_start();

// Set base URL for consistent navigation
$base_url = '/online-shopping-system';

// Check if user is logged in and has proper role (support OR admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . $base_url . '/public/login.php');
    exit();
}

// Check if user is either support or admin
$user_role = $_SESSION['role'];
if (!in_array($user_role, ['support', 'admin'])) {
    header('Location: ' . $base_url . '/public/dashboard.php');
    exit();
}

// Include your base files
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../lib/base.php';
include_once __DIR__ . '/../../includes/header.php';
// Now we can use db() function from base.php
$db = db();

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user_id = $_SESSION['user_id'];

// Function to create support notifications
function createTicketNotification($db, $ticket_id, $agent_id, $message, $is_internal = false) {
    // Get ticket info and customer ID
    $stmt = $db->prepare("
        SELECT t.user_id, t.subject, u.username as customer_name
        FROM support_tickets t
        JOIN users u ON t.user_id = u.user_id
        WHERE t.ticket_id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    // Only create notification if not internal and agent is replying to customer
    if ($ticket && !$is_internal && $ticket['user_id'] != $agent_id) {
        // Truncate message for notification
        $short_message = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
        
        // Create notification for the customer
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_id, created_at)
            VALUES (?, 'support_reply', 'New Support Reply', ?, ?, NOW())
        ");
        $stmt->execute([
            $ticket['user_id'],
            'Support agent replied to your ticket: "' . $short_message . '"',
            $ticket_id
        ]);
        
        return true;
    }
    return false;
}

// Get ticket details
$stmt = $db->prepare("
    SELECT t.*, 
           u.username as customer_name, u.email as customer_email,
           o.order_id as order_number, o.status as order_status,
           a.username as assigned_agent, a.user_id as assigned_agent_id
    FROM support_tickets t
    JOIN users u ON t.user_id = u.user_id
    LEFT JOIN orders o ON t.order_id = o.order_id
    LEFT JOIN users a ON t.assigned_to = a.user_id
    WHERE t.ticket_id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}

// Check if admin has permission to view this ticket
// Admins can view all tickets, support agents can only view their assigned tickets
if ($user_role === 'support' && $ticket['assigned_to'] != $current_user_id && $ticket['assigned_to'] != NULL) {
    // Support agent trying to view ticket not assigned to them
    header('Location: tickets.php');
    exit();
}

// Get all messages for this ticket
// For non-internal messages: show to everyone
// For internal messages: only show to the person who created them OR to admins
$msg_stmt = $db->prepare("
    SELECT tm.*, u.username, u.role
    FROM ticket_messages tm
    JOIN users u ON tm.user_id = u.user_id
    WHERE tm.ticket_id = ?
    AND (
        tm.is_internal = 0 
        OR (tm.is_internal = 1 AND tm.user_id = ?)
        OR (tm.is_internal = 1 AND ? = 'admin')
    )
    ORDER BY tm.created_at ASC
");
$msg_stmt->execute([$ticket_id, $current_user_id, $user_role]);
$messages = $msg_stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply'])) {
        // Add new message
        $message = trim($_POST['message']);
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        
        if (!empty($message)) {
            $insert = $db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $insert->execute([$ticket_id, $current_user_id, $message, $is_internal]);
            
            // Create notification for customer (only if not internal note)
            if (!$is_internal) {
                createTicketNotification($db, $ticket_id, $current_user_id, $message);
            }
            
            // Update ticket status if replying to open ticket
            if ($ticket['status'] === 'open') {
                $update = $db->prepare("UPDATE support_tickets SET status = 'in_progress', assigned_to = ?, updated_at = NOW() WHERE ticket_id = ?");
                $update->execute([$current_user_id, $ticket_id]);
                $ticket['status'] = 'in_progress';
                $ticket['assigned_to'] = $current_user_id;
            } else {
                // Just update timestamp
                $update_time = $db->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE ticket_id = ?");
                $update_time->execute([$ticket_id]);
            }
            
            // Refresh messages
            $msg_stmt->execute([$ticket_id, $current_user_id, $user_role]);
            $messages = $msg_stmt->fetchAll();
        }
    }
    
    if (isset($_POST['update_status'])) {
        // Update ticket status/priority/assignment
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
        
        $update = $db->prepare("
            UPDATE support_tickets 
            SET status = ?, priority = ?, assigned_to = ?, updated_at = NOW()
            WHERE ticket_id = ?
        ");
        $update->execute([$status, $priority, $assigned_to, $ticket_id]);
        
        // Update local ticket data
        $ticket['status'] = $status;
        $ticket['priority'] = $priority;
        $ticket['assigned_to'] = $assigned_to;
        
        // Add internal note about status change
        $note = "Status changed to " . ucfirst($status) . ", Priority: " . ucfirst($priority);
        if ($assigned_to) {
            // Get assigned agent name
            $agent_stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
            $agent_stmt->execute([$assigned_to]);
            $agent = $agent_stmt->fetch();
            $note .= ", Assigned to: " . $agent['username'];
        }
        
        $insert_note = $db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_internal, created_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        $insert_note->execute([$ticket_id, $current_user_id, $note]);
        
        if ($status === 'resolved') {
            // Create notification for customer to rate the service
            $notification_stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, related_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $notification_message = 'Your ticket #' . $ticket_id . ' has been resolved. Please rate our support service.';
            
            $notification_stmt->execute([
                $ticket['user_id'], 
                'ticket_resolved',      // type
                'Ticket Resolved',      // title
                $notification_message,  // message
                $ticket_id              // related_id
            ]);
        }
        
        // Refresh messages
        $msg_stmt->execute([$ticket_id, $current_user_id, $user_role]);
        $messages = $msg_stmt->fetchAll();
    }
}

// Get available support agents for assignment
$agents_stmt = $db->prepare("
    SELECT user_id, username 
    FROM users 
    WHERE role IN ('support', 'admin') 
    ORDER BY username
");
$agents_stmt->execute();
$agents = $agents_stmt->fetchAll();

// Determine if current user can reassign tickets (admin only)
$can_reassign = ($user_role === 'admin');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ticket #<?php echo $ticket_id; ?> - <?php echo ucfirst($user_role); ?> View</title>
    <style>
        .ticket-view-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .ticket-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .meta-item {
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .messages-container {
            margin: 30px 0;
        }
        
        .message {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
            position: relative;
        }
        
        .message-customer {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            margin-left: 30px;
        }
        
        .message-agent {
            background: #f1f8e9;
            border-left: 4px solid #4CAF50;
            margin-right: 30px;
        }
        
        .message-admin {
            background: #f3e5f5;
            border-left: 4px solid #9c27b0;
            margin-right: 30px;
        }
        
        .message-internal {
            background: #fff3e0;
            border-left: 4px solid #FF9800;
            border: 1px dashed #FF9800;
            position: relative;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .message-sender {
            font-weight: bold;
        }
        
        .message-role {
            color: #666;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .message-time {
            color: #999;
            font-size: 12px;
        }
        
        .reply-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            resize: vertical;
            font-family: inherit;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-admin {
            background: #9c27b0;
            color: white;
        }
        .btn-admin:hover {
            background: #7b1fa2;
        }
        
        .status-controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .control-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        select, input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-left: 5px;
        }
        
        .badge-open { background: #dc3545; color: white; }
        .badge-in_progress { background: #ffc107; color: #333; }
        .badge-resolved { background: #28a745; color: white; }
        .badge-closed { background: #6c757d; color: white; }
        
        .priority-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: inline-block;
            margin-left: 5px;
        }
        
        .priority-urgent { background: #dc3545; }
        .priority-high { background: #fd7e14; }
        .priority-medium { background: #ffc107; color: #333; }
        .priority-low { background: #6c757d; }
        
        .internal-tag {
            background: #FF9800;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin-right: 5px;
        }
        
        .internal-note-indicator {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #FF9800;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .admin-note {
            background: #f3e5f5;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            border-left: 3px solid #9c27b0;
            font-size: 13px;
        }
        
        .access-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .internal-warning {
            background: #fff8e1;
            border: 1px solid #ffe0b2;
            color: #5d4037;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hidden-message {
            background: #f5f5f5;
            border: 1px dashed #ddd;
            color: #757575;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            font-style: italic;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="ticket-view-container">
        <!-- Ticket Header -->
        <div class="ticket-header">
            <h1 style="margin: 0; display: flex; align-items: center;">
                Ticket #<?php echo $ticket_id; ?>
                <span class="role-badge"><?php echo strtoupper($user_role); ?></span>
            </h1>
            <h2 style="margin: 10px 0 0 0; font-size: 1.3em;">
                <?php echo htmlspecialchars($ticket['subject']); ?>
            </h2>
            
            <div class="ticket-meta">
                <div class="meta-item">
                    <strong>Customer:</strong> <?php echo htmlspecialchars($ticket['customer_name']); ?>
                </div>
                <div class="meta-item">
                    <strong>Email:</strong> <?php echo htmlspecialchars($ticket['customer_email']); ?>
                </div>
                <?php if ($ticket['order_number']): ?>
                <div class="meta-item">
                    <strong>Order #:</strong> 
                    <a href="<?php echo $base_url; ?>/public/members/order_track.php?id=<?php echo $ticket['order_number']; ?>" 
                       style="color: white; text-decoration: underline;">
                        <?php echo $ticket['order_number']; ?>
                    </a>
                    (<?php echo $ticket['order_status']; ?>)
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <strong>Status:</strong> 
                    <span class="badge badge-<?php echo $ticket['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                    </span>
                </div>
                <div class="meta-item">
                    <strong>Priority:</strong> 
                    <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                        <?php echo ucfirst($ticket['priority']); ?>
                    </span>
                </div>
                <?php if ($ticket['assigned_agent']): ?>
                <div class="meta-item">
                    <strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_agent']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Admin access note (if viewing unassigned ticket) -->
        <?php if ($user_role === 'admin' && !$ticket['assigned_agent']): ?>
            <div class="admin-note">
                <strong>📋 Admin Note:</strong> This ticket is currently unassigned. As an admin, you can reply and manage it directly.
            </div>
        <?php elseif ($user_role === 'admin' && $ticket['assigned_agent_id'] != $current_user_id): ?>
            <div class="admin-note">
                <strong>📋 Admin Note:</strong> This ticket is assigned to another agent, but you can still view and manage it as an administrator.
            </div>
        <?php elseif ($user_role === 'support' && $ticket['assigned_agent_id'] != $current_user_id && !$ticket['assigned_agent_id']): ?>
            <div class="access-warning">
                <strong>⚠️ Note:</strong> This ticket is not assigned to you, but you can view it as it's currently unassigned.
            </div>
        <?php endif; ?>
        
        <!-- Original Ticket Description -->
        <div style="background: #e8f4fc; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
            <h3 style="margin-top: 0;">📝 Original Request</h3>
            <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
            <small style="color: #666;">
                Created: <?php echo date('F j, Y g:i A', strtotime($ticket['created_at'])); ?>
                | Last updated: <?php echo date('F j, Y g:i A', strtotime($ticket['updated_at'])); ?>
            </small>
        </div>
        
        <!-- Internal Note Warning -->
        <?php if ($user_role === 'admin'): ?>
            <div class="internal-warning">
                <i class="fas fa-eye-slash"></i>
                <span><strong>Admin View:</strong> You can see all internal notes (including those from other agents).</span>
            </div>
        <?php else: ?>
            <div class="internal-warning">
                <i class="fas fa-user-secret"></i>
                <span><strong>Note:</strong> Internal notes are only visible to the person who created them.</span>
            </div>
        <?php endif; ?>
        
        <!-- Status Update Controls (Show for admin and assigned support agents) -->
        <?php if ($can_reassign || $ticket['assigned_to'] == $current_user_id || !$ticket['assigned_to']): ?>
            <div class="status-controls">
                <h3 style="margin-top: 0;">⚙️ Update Ticket Settings</h3>
                <form method="POST">
                    <div class="control-grid">
                        <div>
                            <label>Status:</label>
                            <select name="status" required>
                                <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div>
                            <label>Priority:</label>
                            <select name="priority" required>
                                <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $ticket['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label>Assign to:</label>
                            <select name="assigned_to" <?php echo !$can_reassign ? 'disabled' : ''; ?>>
                                <option value="">Unassigned</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['user_id']; ?>" 
                                        <?php echo $ticket['assigned_to'] == $agent['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($agent['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$can_reassign): ?>
                                <small style="color: #666;">Only admins can reassign tickets</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-success" style="margin-top: 15px;">
                        Update Ticket Settings
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Messages Thread -->
        <div class="messages-container">
            <h3>💬 Conversation History</h3>
            
            <?php if (empty($messages)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">
                    No messages yet. Be the first to respond.
                </p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message 
                        <?php echo $msg['is_internal'] ? 'message-internal' : 
                               ($msg['role'] === 'admin' ? 'message-admin' :
                               ($msg['role'] === 'support' ? 'message-agent' : 'message-customer')); ?>">
                        
                        <?php if ($msg['is_internal']): ?>
                            <div class="internal-note-indicator" title="Internal Note">
                                <i class="fas fa-lock"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="message-header">
                            <div>
                                <?php if ($msg['is_internal']): ?>
                                    <span class="internal-tag">INTERNAL NOTE</span>
                                    <?php if ($msg['user_id'] == $current_user_id): ?>
                                        <span style="color: #FF9800; font-size: 11px;">(Your private note)</span>
                                    <?php elseif ($user_role === 'admin'): ?>
                                        <span style="color: #9c27b0; font-size: 11px;">(From: <?php echo htmlspecialchars($msg['username']); ?>)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <span class="message-sender"><?php echo htmlspecialchars($msg['username']); ?></span>
                                <span class="message-role">(<?php echo ucfirst($msg['role']); ?>)</span>
                            </div>
                            <div class="message-time">
                                <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Reply Form (Show for admin and assigned support agents) -->
        <?php if ($user_role === 'admin' || $ticket['assigned_to'] == $current_user_id || !$ticket['assigned_to']): ?>
            <div class="reply-form">
                <h3 style="margin-top: 0;">📤 Send Reply</h3>
                <form method="POST">
                    <div class="form-group">
                        <textarea name="message" rows="5" 
                                  placeholder="Type your response here..." required></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_internal" name="is_internal" value="1">
                        <label for="is_internal">
                            <strong><i class="fas fa-lock"></i> Internal Note</strong> (only visible to you<?php echo $user_role === 'admin' ? ' and other admins' : ''; ?>)
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                        <button type="submit" name="reply" class="btn <?php echo $user_role === 'admin' ? 'btn-admin' : 'btn-primary'; ?>">
                            <i class="fas fa-paper-plane"></i> Send Reply as <?php echo ucfirst($user_role); ?>
                        </button>
                        <a href="tickets.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Tickets
                        </a>
                        <a href="tickets.php?status=<?php echo $ticket['status']; ?>" class="btn btn-secondary">
                            <i class="fas fa-filter"></i> View Similar Tickets
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="access-warning" style="text-align: center; padding: 20px;">
                <strong>⚠️ Limited Access</strong>
                <p>This ticket is assigned to another support agent. You can view it but cannot reply or modify it.</p>
                <a href="tickets.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Your Tickets</a>
            </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center;">
            <small style="color: #666;">
                <i class="fas fa-ticket-alt"></i> Ticket ID: <?php echo $ticket_id; ?> | 
                <i class="fas fa-calendar-plus"></i> Created: <?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?> | 
                <i class="fas fa-history"></i> Last activity: <?php echo date('Y-m-d H:i', strtotime($ticket['updated_at'])); ?> |
                <i class="fas fa-user-tag"></i> Viewed as: <?php echo ucfirst($user_role); ?>
            </small>
            <br>
            <a href="tickets.php" style="color: #667eea; margin-top: 10px; display: inline-block;">
                <i class="fas fa-arrow-left"></i> Return to Tickets List
            </a>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>