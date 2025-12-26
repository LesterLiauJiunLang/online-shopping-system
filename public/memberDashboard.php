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
    
} catch (PDOException $e) {
    error_log("Database error in memberDashboard: " . $e->getMessage());
    $orders = [];
    $recentOrders = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <?php require_once '../includes/header.php'; ?>
</head>
<body>
<div class="container">
    <div class="dashboard-header">
        <h2 class="dashboard-title">
            Welcome, <?php echo htmlspecialchars($user['username']) . " 👋"; ?>
        </h2>
        <!-- Welcome message using $_user (Practical 7 style) -->
        <p>Welcome, <?php echo encode($_user['username'] ?? $user['username'] ?? 'User'); ?>!</p>
        
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
    
    <!-- Customer Stats -->
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
                    if ($order['status'] === 'pending') {
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
    </div>

    <!-- Quick Links for Customers (Combined from both versions) -->
    <div class="dashboard-actions">
        <h3>Quick Links</h3>
        <div class="action-buttons">
            <a href="members/products.php" class="btn btn-primary">Continue Shopping</a>
            <a href="members/cart.php" class="btn btn-secondary">View Cart</a>
            <a href="profile.php" class="btn btn-success">Edit Profile</a>
            
            <!-- Additional links from Practical 7 style -->
            <a href="change-password.php" class="btn btn-warning">Change Password</a>
            <a href="../../members/products.php" class="btn btn-primary">Browse Products</a>
        </div>
    </div>

    <!-- NAVIGATION MENU (Practical 7 style) -->
    <div class="nav">
        <h3>Quick Navigation:</h3>
        
        <!-- COMMON LINKS (All users) -->
        <ul>
            <li><a href="profile.php">Update Profile</a></li>
            <li><a href="change-password.php">Change Password</a></li>
            
            <!-- MEMBER-SPECIFIC LINKS -->    
            <?php if ($_user['role'] === 'member' || $_user['role'] === 'customer'): ?>
                <li><a href="members/products.php">Browse Products</a></li>
                <li><a href="members/cart.php">Shopping Cart</a></li>
                <li><a href="members/orders.php">My Orders</a></li>
                <li><a href="members/wishlist.php">My Wishlist</a></li>
                <li><a href="reviews.php">My Reviews</a></li>
            <?php endif; ?>
            
            <!-- MORE COMMON LINKS -->
            <li><a href="messages.php">Messages</a></li>
            <li><a href="settings.php">Account Settings</a></li>
        </ul>
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
                            if ($order['status'] === 'pending') {
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
                <!-- Additional member stats -->
                <div class="stat-card">
                    <h4>Completed Orders</h4>
                    <p>
                        <?php 
                        $completed = 0;
                        foreach ($orders as $order) {
                            if ($order['status'] === 'completed' || $order['status'] === 'delivered') {
                                $completed++;
                            }
                        }
                        echo $completed;
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Orders (Customer View) -->
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
                        <th>No.</th>
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
                            <td><?php echo $counter; ?></td>
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
                    <a href="members/order_history.php" class="btn-orange">View All Orders</a>
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