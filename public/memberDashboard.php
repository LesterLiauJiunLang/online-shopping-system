<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ========================
   Include Dependencies
======================== */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/UserModel.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../lib/base.php';

// Include header BEFORE any output
include_once __DIR__ . '/../includes/header.php';

/* ========================
   Fetch Data for Member
======================== */
$db = db();

$userModel = new User($db);
$_user = $userModel->getUserById($_SESSION['user_id']);

// Customer Dashboard Data
$orderModel = new Order($db);
try {
    // Get current user info
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found, log out
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Get orders for this user
    $orderStmt = $db->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY order_date DESC
    ");
    $orderStmt->execute([$_SESSION['user_id']]);
    $orders = $orderStmt->fetchAll();
    
    // Get recent orders (limit 5)
    $recentOrderStmt = $db->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY order_date DESC 
        LIMIT 5
    ");
    $recentOrderStmt->execute([$_SESSION['user_id']]);
    $recentOrders = $recentOrderStmt->fetchAll();
    
    // Get support ticket count for this user
    $ticketStmt = $db->prepare("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = ?");
    $ticketStmt->execute([$_SESSION['user_id']]);
    $ticketCount = $ticketStmt->fetch()['count'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Database error in memberDashboard: " . $e->getMessage());
    $orders = [];
    $recentOrders = [];
    $ticketCount = 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <?php require_once '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Dashboard Styles */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #5a4bff 0%, #3a2ee0 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(90, 75, 255, 0.2);
        }
        
        .dashboard-title {
            font-size: 2em;
            margin: 0 0 10px 0;
        }
        
        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
            margin-left: 10px;
        }
        
        .badge-customer {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #eee;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #5a4bff;
            margin: 10px 0;
        }
        
        .dashboard-actions, .quick-links-section {
            margin: 30px 0;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
        }
        
        .dashboard-actions h3, .quick-links-section h3 {
            margin-top: 0;
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #5a4bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #4a3bdf;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90, 75, 255, 0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.2);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2);
        }
        
        .btn-orange {
            background: #fd7e14;
            color: white;
        }
        
        .btn-orange:hover {
            background: #e06c00;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(253, 126, 20, 0.2);
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9em;
        }
        
        /* New ticket button styles from your version */
        .btn-ticket {
            background: #20c997;
            color: white;
        }
        
        .btn-ticket:hover {
            background: #1ba87e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(32, 201, 151, 0.2);
        }
        
        .btn-faq {
            background: #9c27b0;
            color: white;
        }

        .btn-faq:hover {
            background: #7b1fa2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(156, 39, 176, 0.2);
        }
        
        .dashboard-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin: 20px 0;
        }
        
        .dashboard-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .dashboard-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-pending { background: #ffc107; color: #212529; }
        .status-processing { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        .status-delivered { background: #20c997; color: white; }
        
        .nav ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        
        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 1px solid #eee;
        }
        
        .nav a:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #5a4bff;
            color: #5a4bff;
        }
        
        .recent-activity {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .recent-activity ul {
            list-style: none;
            padding: 0;
        }
        
        .recent-activity li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        
        .recent-activity li:last-child {
            border-bottom: none;
        }
        
        .dashboard-empty {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .dashboard-empty-icon {
            font-size: 3em;
            margin-bottom: 20px;
        }
        
        .dashboard-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .view-all {
            text-align: center;
            margin-top: 20px;
        }
        
        .stats {
            margin: 30px 0;
        }
        
        .stats h3 {
            margin-bottom: 20px;
        }
        
        /* Message icon */
        .message-icon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="dashboard-header">
        <h2 class="dashboard-title">
            Welcome, <?php echo htmlspecialchars($user['username']) . " 👋"; ?>
        </h2>
        <!-- Welcome message using $_user (Practical 7 style) -->
        <p>Welcome, <?php echo encode($_user['username'] ?? $user['username'] ?? 'User'); ?>! Welcome to your customer dashboard!</p>
        
        <!-- Role badge -->
        <span class="role-badge"><?php echo $_user['role']; ?></span>
    </div>

    <div class="dashboard-info">
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Account Type:</strong> 
            <span class="badge badge-customer">
                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
            </span>
        </p>
    </div>

    <hr>
    
    <!-- ========================
           CUSTOMER DASHBOARD
    ======================== -->
    
    <!-- Customer Stats - ENHANCED with Support Tickets count -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Orders</h3>
            <p class="stat-value"><?php echo count($orders); ?></p>
        </div>
        <div class="stat-card">
            <h3>Pending Orders</h3>
            <p class="stat-value">
                <?php 
                $pending = 0;
                foreach ($orders as $order) {
                    if ($order['status'] === 'pending' || $order['status'] === 'Pending') {
                        $pending++;
                    }
                }
                echo $pending;
                ?>
            </p>
        </div>
        <div class="stat-card">
            <h3>Total Spent</h3>
            <p class="stat-value">
                RM <?php 
                $total = 0;
                foreach ($orders as $order) {
                    $total += $order['total_amount'];
                }
                echo number_format($total, 2);
                ?>
            </p>
        </div>
        <div class="stat-card">
            <h3>Support Tickets</h3>
            <p class="stat-value"><?php echo $ticketCount; ?></p>
        </div>
    </div>

    <!-- Quick Links Section - ENHANCED with your links -->
    <div class="quick-links-section">
        <h3>Quick Links</h3>
        <div class="action-buttons">
            <!-- From current version -->
            <a href="members/products.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
            <a href="members/cart.php" class="btn btn-secondary">
                <i class="fas fa-shopping-cart"></i> View Cart
            </a>
            <a href="members/wishlist.php" class="btn btn-info">
                <i class="fas fa-shopping-cart"></i> View WishList
            </a>
            <a href="profile.php" class="btn btn-success">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            
            <!-- From your version -->
            <a href="members/order_tracking.php" class="btn btn-info">
                <i class="fas fa-box-open"></i> Track Order
            </a>
            <a href="members/view_ticket.php" class="btn btn-ticket">
                <i class="fas fa-ticket-alt"></i> View Tickets
            </a>
            <a href="support/create_ticket.php" class="btn btn-warning">
                <i class="fas fa-headset"></i> Contact Support
            </a>
            <a href="faq.php" class="btn btn-faq">
                <i class="fas fa-life-ring"></i> Help & FAQ
            </a>
            
        
        </div>
    </div>


    
    <!-- STATISTICS SECTION (Role-based) - Enhanced with actual data -->
    <div class="stats">
        <h3>Your Statistics:</h3>
        <div class="stats-grid">
            <?php if ($_user['role'] === 'admin'): ?>
                <!-- Admin Statistics -->
                <div class="stat-card">
                    <h4>Total Members</h4>
                    <p>1,234</p>
                </div>
                <div class="stat-card">
                    <h4>Pending Orders</h4>
                    <p>56</p>
                </div>
                <div class="stat-card">
                    <h4>Low Stock Items</h4>
                    <p>12</p>
                </div>
                
            <?php else: ?>
                <!-- Member Statistics - Using actual data -->
                <div class="stat-card">
                    <h4>My Orders</h4>
                    <p><?php echo count($orders); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Pending Orders</h4>
                    <p>
                        <?php 
                        $pendingCount = 0;
                        foreach ($orders as $order) {
                            if ($order['status'] === 'pending' || $order['status'] === 'Pending') {
                                $pendingCount++;
                            }
                        }
                        echo $pendingCount;
                        ?>
                    </p>
                </div>
                <div class="stat-card">
                    <h4>Total Spent</h4>
                    <p>RM <?php 
                        $totalSpent = 0;
                        foreach ($orders as $order) {
                            $totalSpent += $order['total_amount'];
                        }
                        echo number_format($totalSpent, 2);
                        ?></p>
                </div>
                <!-- Additional member stats from your version -->
                <div class="stat-card">
                    <h4>Support Tickets</h4>
                    <p><?php echo $ticketCount; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Orders (Customer View) - ENHANCED with Order ID -->
    <div>
        <h3>Your Recent Orders</h3>
        
        <?php if (empty($recentOrders)): ?>
            <div class="dashboard-empty">
                <div class="dashboard-empty-icon">📦</div>
                <h3>No Orders Yet</h3>
                <p>You have not placed any orders yet.</p>
                <a href="products.php" class="btn btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Total Price (RM)</th>
                        <th>Status</th>
                        <th>Order Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($recentOrders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['order_id']; ?></td>
                            <td><?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($order['created_at'] ?? $order['order_date'] ?? '')); ?></td>
                            <td>
                                <a href="members/order_detail.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php 
                    $counter++;
                    endforeach; ?>
                </tbody>
            </table>
            
            <?php if (count($orders) > 5): ?>
                <div class="view-all">
                    <a href="members/order_tracking.php" class="btn-orange">View All Orders</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- RECENT ACTIVITY (Practical 7 style) -->
    <div class="recent-activity">
        <h3>Recent Activity</h3>
        <?php if ($_user['role'] === 'admin'): ?>
            <ul>
                <li>New member registered: John Doe</li>
                <li>Order #1234 placed</li>
                <li>Product "iPhone 15" updated</li>
            </ul>
        <?php else: ?>
            <ul>
                <?php if (!empty($recentOrders)): ?>
                    <li>Your most recent order: #<?php echo $recentOrders[0]['order_id']; ?> - 
                        RM <?php echo number_format($recentOrders[0]['total_amount'], 2); ?>
                        (Status: <?php echo ucfirst($recentOrders[0]['status']); ?>)
                    </li>
                <?php endif; ?>
                <li>Last login: <?php echo date('d/m/Y H:i', strtotime($user['last_login'] ?? 'Today')); ?></li>
                <li>Account created: <?php echo date('d/m/Y', strtotime($user['created_at'] ?? 'Recently')); ?></li>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>