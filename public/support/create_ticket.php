<?php
// support/create_ticket.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

/* ========================
   Include Dependencies
======================== */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../lib/base.php';

$db = db();

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

// Get user's recent orders for dropdown
$stmt = $db->prepare("SELECT order_id, order_date, total_amount FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 10");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $ticket_order_id = $_POST['order_id'] ?: null;
    
    $stmt = $db->prepare("
        INSERT INTO support_tickets (user_id, order_id, subject, description, priority) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$user_id, $ticket_order_id, $subject, $description, $priority])) {
        $ticket_id = $db->lastInsertId();
        
        // Insert first message
        $stmt = $db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $user_id, $description]);
        
        // 🔔 SEND NOTIFICATIONS
        
        // Notify member that ticket was created
        if (function_exists('createNotification')) {
            createNotification(
                $user_id,
                'ticket_created',
                'Support Ticket Created',
                "Your support ticket #{$ticket_id} has been created. Our team will respond soon.",
                $ticket_id
            );
        }
        
        // Notify all admins about new ticket
        if (function_exists('notifyAllAdmins')) {
            notifyAllAdmins(
                'new_ticket',
                'New Support Ticket',
                "Member " . $_SESSION['username'] . " created a new support ticket: {$subject}",
                $ticket_id
            );
        }
        
        // Notify all support agents about new ticket
        if (function_exists('notifyAllSupportAgents')) {
            notifyAllSupportAgents(
                'new_ticket',
                'New Support Ticket',
                "New ticket #{$ticket_id} from " . $_SESSION['username'] . ": {$subject}",
                $ticket_id
            );
        }
        
        header("Location: ticket_success.php?id=$ticket_id");
        exit();
    }
}

// Include header AFTER all PHP processing and BEFORE any HTML output
include_once __DIR__ . '/../../includes/header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Support Ticket</title>
    <style>
        .ticket-form {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        textarea {
            min-height: 150px;
            resize: vertical;
        }
        .btn-submit {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
        }
        .btn-submit:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="ticket-form">
        <h1>📞 Create Support Ticket</h1>
        <p>Our support team will get back to you within 24 hours.</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" required 
                       placeholder="Brief description of your issue">
            </div>
            
            <div class="form-group">
                <label for="order_id">Related Order (Optional)</label>
                <select id="order_id" name="order_id">
                    <option value="">-- Select an order --</option>
                    <?php if ($order_id): ?>
                        <option value="<?php echo $order_id; ?>" selected>
                            Order #<?php echo $order_id; ?> (selected)
                        </option>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <?php if ($order['order_id'] != $order_id): ?>
                            <option value="<?php echo $order['order_id']; ?>">
                                Order #<?php echo $order['order_id']; ?> - 
                                RM <?php echo number_format($order['total_amount'], 2); ?> - 
                                <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="priority">Priority *</label>
                <select id="priority" name="priority" required>
                    <option value="low">Low - General inquiry</option>
                    <option value="medium" selected>Medium - Order issue</option>
                    <option value="high">High - Urgent problem</option>
                    <option value="urgent">Urgent - Payment/Account issue</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" required 
                          placeholder="Please provide detailed information about your issue..."></textarea>
            </div>
            
            <button type="submit" class="btn-submit">Submit Ticket</button>
        </form>
        
    </div>
</body>
</html>
