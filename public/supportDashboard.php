<?php
// supportDashboard.php - Customer Support Dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is support
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'support') {
    header('Location: login.php');
    exit();
}

// Include your base files
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../lib/base.php';

$db = db();

// Get support agent info
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

// Get employee_id
$employee_id = $agent['employee_id'] ?? 'SUP-001';

// Count pending support tickets
$pending_tickets = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
    $result = $stmt->fetch();
    $pending_tickets = $result['count'] ?? 0;
} catch (Exception $e) {
    // Table doesn't exist yet
}

// Count tickets resolved today (by current support agent)
$resolved_today = 0;
try {
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM support_tickets 
        WHERE status = 'resolved' 
        AND DATE(updated_at) = ?
        AND assigned_to = ?
    ");
    $stmt->execute([$today, $_SESSION['user_id']]);
    $result = $stmt->fetch();
    $resolved_today = $result['count'] ?? 0;
} catch (Exception $e) {
    // Error handling
}

// Calculate average response time (in minutes)
$avg_response_time = 0;
try {
    $stmt = $db->prepare("
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, created_at, 
                (SELECT MIN(created_at) 
                 FROM ticket_messages tm 
                 WHERE tm.ticket_id = st.ticket_id 
                 AND tm.user_id != st.user_id))) as avg_time
        FROM support_tickets st
        WHERE st.assigned_to = ?
        AND st.status IN ('resolved', 'closed')
        AND EXISTS (
            SELECT 1 FROM ticket_messages tm 
            WHERE tm.ticket_id = st.ticket_id 
            AND tm.user_id != st.user_id
        )
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $avg_response_time = $result['avg_time'] ?? 0;
    $avg_response_time = round($avg_response_time); // Round to nearest minute
} catch (Exception $e) {
    // Error handling
}

// Get REAL customer rating from ticket_ratings table
$customer_rating = 0;
$rating_count = 0;
$has_ratings = false;
$star_rating_html = 'N/A';

try {
    // Check if ticket_ratings table exists
    $stmt = $db->query("SHOW TABLES LIKE 'ticket_ratings'");
    if ($stmt->rowCount() > 0) {
        // Get average rating and count
        $stmt = $db->prepare("
            SELECT 
                AVG(tr.rating) as avg_rating,
                COUNT(tr.rating) as rating_count
            FROM ticket_ratings tr
            INNER JOIN support_tickets st ON tr.ticket_id = st.ticket_id
            WHERE st.assigned_to = ?
            AND tr.rating IS NOT NULL
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        if ($result && $result['rating_count'] > 0) {
            $customer_rating = $result['avg_rating'] ?? 0;
            $rating_count = $result['rating_count'] ?? 0;
            $has_ratings = true;
            
            // Format rating to 1 decimal place
            $customer_rating = round($customer_rating, 1);
            
            // Generate star rating HTML
            $full_stars = floor($customer_rating);
            $half_star = ($customer_rating - $full_stars) >= 0.5;
            $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
            
            $star_rating_html = '';
            for ($i = 0; $i < $full_stars; $i++) {
                $star_rating_html .= '★';
            }
            if ($half_star) {
                $star_rating_html .= '½';
            }
            for ($i = 0; $i < $empty_stars; $i++) {
                $star_rating_html .= '☆';
            }
            $star_rating_html .= ' ' . number_format($customer_rating, 1);
        }
    }
} catch (Exception $e) {
    // Error handling - table might not exist yet
    error_log("Rating calculation error: " . $e->getMessage());
}

// Calculate resolution rate as fallback if no ratings
$resolution_rate = 0;
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets
        FROM support_tickets 
        WHERE assigned_to = ?
        AND status IN ('resolved', 'closed', 'in_progress', 'open')
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    $total = $result['total_tickets'] ?? 0;
    $resolved = $result['resolved_tickets'] ?? 0;
    
    if ($total > 0) {
        $resolution_rate = round(($resolved / $total) * 100, 1);
    }
} catch (Exception $e) {
    // Error handling
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Dashboard - Online Shopping System</title>
        <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        /* FIX: Make sure header container doesn't hide our content */
        .header {
            position: relative;
            z-index: 100;
        }
        
        /* MAIN CONTAINER - Adjusted for header */
        .support-container {
            max-width: 1800px;
            width: 95%;
            margin: 20px auto;
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
            overflow: hidden;
            min-height: calc(85vh - 100px);
            border: 3px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 10;
        }
        
        /* Header - Make smaller */
        .support-header {
            background: linear-gradient(to right, #4a6fa5, #3a5988);
            color: white;
            padding: 30px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .support-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 20%);
        }
        
        .support-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .support-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        /* Agent Info - Make smaller and remove logout button space */
        .agent-info {
            background: linear-gradient(to right, #f8f9ff, #eef0ff);
            padding: 20px 40px;
            border-bottom: 2px solid #e6e9ff;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            text-align: center;
        }
        
        .agent-details h2 {
            color: #333;
            margin-bottom: 8px;
            font-size: 1.5rem;
        }
        
        .agent-details p {
            margin: 6px 0;
            color: #555;
            font-size: 0.9rem;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        
        .badge-support {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #212529;
        }
        
        /* Stats Grid - Make cards smaller and tighter */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            padding: 30px 40px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.2rem;
            margin-bottom: 12px;
            display: block;
        }
        
        .stat-card h3 {
            color: #555;
            margin-bottom: 10px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 8px 0;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .star-rating {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 8px 0;
            color: #FFD700;
            letter-spacing: 2px;
        }
        
        .stat-label {
            color: #777;
            font-size: 0.85rem;
        }
        
        .rating-count {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
            font-style: italic;
        }
        
        /* Quick Actions - Make everything smaller and tighter */
        .quick-actions {
            padding: 30px 40px;
            background: white;
        }
        
        .quick-actions h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.6rem;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #5a4bff, #3a2ee0);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-align: left;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(90, 75, 255, 0.2);
            min-height: 80px;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(90, 75, 255, 0.3);
            background: linear-gradient(135deg, #4a3bff, #2a1ed0);
        }
        
        .action-icon {
            font-size: 1.8rem;
            margin-right: 15px;
            width: 50px;
            text-align: center;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #ffb300, #f57c00);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.2);
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #218838, #1c7430);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }
        
        /* Adjust action button content */
        .action-btn div {
            flex: 1;
        }
        
        .action-btn strong {
            display: block;
            font-size: 1.2rem;
            line-height: 1.3;
        }
        
        /* Responsive adjustments for smaller content */
        @media (max-width: 1200px) {
            .support-container {
                max-width: 95%;
            }
            
            .action-buttons {
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .support-container {
                width: 100%;
                border-radius: 25px;
                margin: 10px auto;
            }
            
            .support-header {
                padding: 25px 20px;
            }
            
            .support-header h1 {
                font-size: 2rem;
            }
            
            .support-header p {
                font-size: 1rem;
            }
            
            .agent-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 20px;
            }
            
            .agent-details h2 {
                font-size: 1.3rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 25px 20px;
            }
            
            .stat-card {
                padding: 18px;
            }
            
            .stat-value {
                font-size: 2.2rem;
            }
            
            .star-rating {
                font-size: 1.8rem;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .quick-actions {
                padding: 25px 20px;
            }
            
            .quick-actions h2 {
                font-size: 1.4rem;
                margin-bottom: 20px;
            }
            
            .action-btn {
                padding: 16px;
                font-size: 0.95rem;
                min-height: 70px;
            }
            
            .action-icon {
                font-size: 1.6rem;
                margin-right: 15px;
                width: 45px;
            }
            
            .action-btn strong {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 480px) {
            .support-container {
                border-radius: 20px;
                margin: 5px auto;
            }
            
            .support-header {
                padding: 20px 15px;
            }
            
            .support-header h1 {
                font-size: 1.8rem;
            }
            
            .support-header p {
                font-size: 0.9rem;
            }
            
            .agent-details h2 {
                font-size: 1.2rem;
            }
            
            .agent-details p {
                font-size: 0.85rem;
            }
            
            .action-btn strong {
                font-size: 1rem;
            }
            
            .action-icon {
                font-size: 1.5rem;
                margin-right: 12px;
                width: 40px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .star-rating {
                font-size: 1.6rem;
            }
            
            .stat-icon {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Include header inside the body
    require_once __DIR__ . '/../includes/header.php'; 
    ?>
    
    <!-- Add some space between header and dashboard -->
    <div style="height: 20px;"></div>
    
    <div class="support-container">
        <!-- Header -->
        <div class="support-header">
            <h1>👨‍💼 Customer Support Dashboard</h1>
            <p>Welcome to your support and service management portal</p>
        </div>
        
        <!-- Agent Information -->
        <div class="agent-info">
            <div class="agent-details">
                <h2>Hello, <?php echo htmlspecialchars($agent['username']); ?>! <span class="badge badge-support">Support Agent</span></h2>
                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee_id); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($agent['email']); ?></p>
                <p><strong>Joined:</strong> <?php echo date('F d, Y', strtotime($agent['created_at'] ?? 'now')); ?></p>
            </div>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">📞</span>
                <h3>Pending Tickets</h3>
                <div class="stat-value"><?php echo $pending_tickets; ?></div>
                <p class="stat-label">Awaiting your response</p>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon">✅</span>
                <h3>Resolved Today</h3>
                <div class="stat-value"><?php echo $resolved_today; ?></div>
                <p class="stat-label">Tickets closed today</p>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon">⏱️</span>
                <h3>Avg Response Time</h3>
                <div class="stat-value"><?php echo $avg_response_time > 0 ? $avg_response_time . 'm' : 'N/A'; ?></div>
                <p class="stat-label">Minutes to first reply</p>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon">⭐</span>
                <h3>Customer Rating</h3>
                <?php if ($has_ratings): ?>
                    <div class="star-rating"><?php echo $star_rating_html; ?></div>
                    <p class="stat-label">Based on <?php echo $rating_count; ?> rating(s)</p>
                    <?php if ($rating_count < 3): ?>
                        <p class="rating-count">(More ratings needed for accurate score)</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-value">N/A</div>
                    <p class="stat-label">No ratings yet</p>
                    <p class="rating-count">Resolution Rate: <?php echo $resolution_rate; ?>%</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>🛠️ Quick Actions</h2>
            <div class="action-buttons">
                <a href="support/tickets.php" class="action-btn">
                    <span class="action-icon">📋</span>
                    <div>
                        <strong>View Customer Tickets</strong>
                    </div>
                </a>
                
                <a href="support/orders.php" class="action-btn btn-info">
                    <span class="action-icon">📦</span>
                    <div>
                        <strong>View Orders</strong>
                    </div>
                </a>
                
                <a href="support/faq_manage.php" class="action-btn btn-success">
                    <span class="action-icon">❓</span>
                    <div>
                        <strong>Manage FAQ</strong>
                    </div>
                </a>
                
                <a href="support/customers.php" class="action-btn">
                    <span class="action-icon">👤</span>
                    <div>
                        <strong>Customer Profiles</strong>
                    </div>
                </a>

                <a href="admin/support_chat.php" class="btn btn-secondary">
                    <span class="action-icon">💬</span>
                    <div>
                        <strong>Admin Chat</strong>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Add some space at bottom -->
    <div style="height: 40px;"></div>
</body>
</html>
