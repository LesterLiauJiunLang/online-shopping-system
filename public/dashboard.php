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
require_once __DIR__ . '/../app/models/ProductModel.php';

include_once __DIR__ . '/../includes/header.php';


/* ========================
   Fetch Data
======================== */
$db = Database::getConnection();

$userModel = new User($db);
$user = $userModel->getUserById($_SESSION['user_id']);

// Check user role
$isAdmin = ($user['role'] === 'admin' || $user['role'] === 'Admin');

// Different data fetching based on role
if ($isAdmin) {
    // Admin Dashboard Data
    $orderModel = new Order($db);
    $productModel = new Product($db);
    
    // Try to get data, handle errors gracefully
    try {
        $totalOrders = $orderModel->getTotalOrdersCount();
        $totalRevenue = $orderModel->getTotalRevenue();
        $totalProducts = $productModel->getTotalProductsCount();
        $totalCustomers = $userModel->getTotalCustomersCount();
        $recentOrders = $orderModel->getRecentOrders(10);
        $lowStockProducts = $productModel->getLowStockProducts();
    } catch (Exception $e) {
        // Set default values if tables don't exist yet
        $totalOrders = 0;
        $totalRevenue = 0;
        $totalProducts = 0;
        $totalCustomers = 0;
        $recentOrders = [];
        $lowStockProducts = [];
        error_log("Dashboard error: " . $e->getMessage());
    }
} else {
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
}
?>

<div class="container">
    <div class="dashboard-header">
        <h2 class="dashboard-title">
            <?php 
            if ($isAdmin) {
                echo "Admin Dashboard 👑";
            } else {
                echo "Welcome, " . htmlspecialchars($user['username']) . " 👋";
            }
            ?>
        </h2>
    </div>

    <div class="dashboard-info">
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Account Type:</strong> 
            <span class="badge <?php echo $isAdmin ? 'badge-admin' : 'badge-customer'; ?>">
                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
            </span>
        </p>
    </div>

    <hr>

    <?php if ($isAdmin): ?>
        <!-- ========================
               ADMIN DASHBOARD
        ======================== -->
        
        <!-- Admin Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <p class="stat-value">RM <?php echo number_format($totalRevenue, 2); ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Orders</h3>
                <p class="stat-value"><?php echo $totalOrders; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Products</h3>
                <p class="stat-value"><?php echo $totalProducts; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Customers</h3>
                <p class="stat-value"><?php echo $totalCustomers; ?></p>
            </div>
        </div>

        <!-- Admin Navigation -->
        <div class="dashboard-actions">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="admin/products.php" class="btn btn-primary">Manage Products</a>
                <a href="admin/orders.php" class="btn btn-secondary">View All Orders</a>
                <a href="admin/product_add.php" class="btn btn-success">Add New Product</a>
            </div>
        </div>

        <!-- Recent Orders (Admin View) -->
        <div>
            <h3>Recent Orders</h3>
            <?php if (empty($recentOrders)): ?>
                <div class="dashboard-empty">
                    <div class="dashboard-empty-icon">📭</div>
                    <h3>No Orders Found</h3>
                    <p>There are no orders in the system yet.</p>
                </div>
            <?php else: ?>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total (RM)</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?? $order['username'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($order['created_at'] ?? $order['order_date'] ?? '')); ?></td>
                                <td>
                                    <a href="admin/orders.php?view=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockProducts)): ?>
            <div class="alert alert-warning">
                <h3>⚠️ Low Stock Alert</h3>
                <ul>
                    <?php foreach ($lowStockProducts as $product): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong> - 
                            Only <?php echo $product['stock_quantity']; ?> left
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    <?php else: ?>
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
                            <th>Order ID</th>
                            <th>Total Price (RM)</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
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
                                    <a href="#" class="btn btn-primary btn-sm">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (count($orders) > 5): ?>
                    <div class="view-all">
                        <a href="order_history.php" class="btn-link">View All Orders</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>