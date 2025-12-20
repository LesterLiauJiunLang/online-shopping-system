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

include_once __DIR__ . '/../includes/header.php';

/* ========================
   Fetch Data for Member
======================== */
$db = Database::getConnection();

$userModel = new User($db);
$user = $userModel->getUserById($_SESSION['user_id']);

// Customer Dashboard Data
$orderModel = new Order($db);
try {
    $orders = $orderModel->getOrdersByUser($_SESSION['user_id']);
    $recentOrders = $orderModel->getRecentOrdersByUser($_SESSION['user_id'], 5);
} catch (Exception $e) {
    $orders = [];
    $recentOrders = [];
    error_log("Customer dashboard error: " . $e->getMessage());
}
?>

<div class="container">
    <div class="dashboard-header">
        <h2 class="dashboard-title">
            Welcome, <?php echo htmlspecialchars($user['username']) . " 👋"; ?>
        </h2>
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

    <!-- Quick Links for Customers -->
    <div class="dashboard-actions">
        <h3>Quick Links</h3>
        <div class="action-buttons">
            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
            <a href="cart.php" class="btn btn-secondary">View Cart</a>
            <a href="profile.php" class="btn btn-success">Edit Profile</a>
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
                                <a href="#" class="btn btn-primary btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php 
                    $counter++;
                    endforeach; ?>
                </tbody>
            </table>
            
            <?php if (count($orders) > 5): ?>
                <div class="view-all">
    <a href="order_history.php" class="btn-orange">View All Orders</a>
</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>