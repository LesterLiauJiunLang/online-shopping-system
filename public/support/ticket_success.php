<?php
// support/ticket_success.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$ticket_id = $_GET['id'] ?? 0;

require_once __DIR__ . '/../../app/config/database.php';
$db = db();

// Get ticket info
$stmt = $db->prepare("
    SELECT t.*, u.username 
    FROM support_tickets t
    JOIN users u ON t.user_id = u.user_id
    WHERE t.ticket_id = ? AND t.user_id = ?
");
$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: ../members/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ticket Submitted Successfully</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 50px;
            max-width: 600px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
            animation: bounce 1s infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 2.5rem;
        }
        
        .ticket-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
            border-left: 5px solid #007bff;
        }
        
        .ticket-info h3 {
            margin-top: 0;
            color: #007bff;
        }
        
        .ticket-info p {
            margin: 10px 0;
        }
        
        .next-steps {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .next-steps h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .next-steps ul {
            padding-left: 20px;
        }
        
        .next-steps li {
            margin: 10px 0;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            color: white;
            box-shadow: 0 5px 15px rgba(108,117,125,0.3);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .ticket-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #007bff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .success-container {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1>Ticket Submitted Successfully!</h1>
        <p style="font-size: 1.2em; color: #555; margin-bottom: 20px;">
            Your support request has been received. Our team will respond within 24 hours.
        </p>
        
        <div class="ticket-number">Ticket #<?php echo $ticket_id; ?></div>
        
        <div class="ticket-info">
            <h3>📋 Ticket Details</h3>
            <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
            <p><strong>Priority:</strong> 
                <span style="color: 
                    <?php 
                    switch($ticket['priority']) {
                        case 'urgent': echo '#dc3545'; break;
                        case 'high': echo '#fd7e14'; break;
                        case 'medium': echo '#ffc107'; break;
                        default: echo '#6c757d';
                    }
                    ?>; font-weight: bold;">
                    <?php echo ucfirst($ticket['priority']); ?>
                </span>
            </p>
            <p><strong>Status:</strong> 
                <span style="color: #28a745; font-weight: bold;">Open</span>
            </p>
            <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($ticket['username']); ?></p>
            <p><strong>Date Submitted:</strong> <?php echo date('F d, Y H:i', strtotime($ticket['created_at'])); ?></p>
        </div>
        
        <div class="next-steps">
            <h3>📝 What Happens Next?</h3>
            <ul>
                <li>Our support team will review your ticket within 24 hours</li>
                <li>You'll receive email notifications for updates</li>
                <li>You can track progress from your dashboard</li>
                <li>For urgent matters, check the live chat option</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="../members/dashboard.php" class="btn btn-secondary">
                <span>🏠</span> Back to Dashboard
            </a>
            <a href="view_ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-primary">
                <span>👁️</span> View Ticket
            </a>
            <a href="../support/create_ticket.php" class="btn btn-secondary">
                <span>➕</span> Create Another Ticket
            </a>
        </div>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            Need immediate assistance? <a href="live_chat.php" style="color: #007bff; text-decoration: none; font-weight: bold;">
                Try Live Chat
            </a> (if available)
        </p>
    </div>
</body>
</html>


