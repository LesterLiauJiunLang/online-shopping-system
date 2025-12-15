<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/OrderModel.php';
require_once __DIR__ . '/../../app/models/UserModel.php';

$db = Database::getConnection();
$orderModel = new Order($db);
$userModel = new User($db);

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    $_SESSION['success_message'] = "Order #$order_id status updated to $new_status";
    header("Location: orders.php");
    exit();
}

// Handle order deletion
if (isset($_GET['delete'])) {
    $order_id = $_GET['delete'];
    
    $stmt = $db->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    
    $_SESSION['success_message'] = "Order #$order_id deleted successfully";
    header("Location: orders.php");
    exit();
}

// Get all orders with user details
$stmt = $db->prepare("
    SELECT o.*, u.username, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.user_id 
    ORDER BY o.order_date DESC
");
$stmt->execute();
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">📦 Manage Orders</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)</span>
            </div>
        </div>
        
        <div class="admin-nav">
            <a href="products.php">📋 Products</a>
            <a href="product_add.php">➕ Add Product</a>
            <a href="orders.php" class="active">📦 Orders</a>
            <a href="../dashboard.php">🏠 Dashboard</a>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from">
                </div>
                
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to">
                </div>
                
                <button type="submit" class="btn-filter">Filter</button>
                <a href="orders.php" class="btn-clear">Clear</a>
            </form>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 20px;">📭</div>
                <h3>No Orders Found</h3>
                <p>There are no orders in the system yet.</p>
            </div>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($order['username']); ?></strong><br>
                            <small><?php echo htmlspecialchars($order['email']); ?></small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                        <td>
                            <span class="status status-<?php echo $order['payment_status']; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="#" class="btn-view" onclick="showOrderDetails(<?php echo $order['order_id']; ?>)">View</a>
                                <a href="orders.php?delete=<?php echo $order['order_id']; ?>" class="btn-delete" onclick="return confirm('Delete order #<?php echo $order['order_id']; ?>?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="order-details" id="orderDetails">
                <!-- Order details will be loaded here via JavaScript -->
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function showOrderDetails(orderId) {
        // In a real application, you would fetch order details via AJAX
        // For now, we'll just show a simple message
        document.getElementById('orderDetails').innerHTML = `
            <h3>Order #${orderId} Details</h3>
            <p>Order details would be loaded here via AJAX in a real application.</p>
            <p>This would include:</p>
            <ul>
                <li>Order items</li>
                <li>Customer information</li>
                <li>Shipping address</li>
                <li>Payment details</li>
            </ul>
            <button onclick="document.getElementById('orderDetails').classList.remove('active')" class="btn-cancel">Close</button>
        `;
        document.getElementById('orderDetails').classList.add('active');
    }
    
    // Close order details when clicking outside
    document.addEventListener('click', function(event) {
        const details = document.getElementById('orderDetails');
        if (!details.contains(event.target) && !event.target.classList.contains('btn-view')) {
            details.classList.remove('active');
        }
    });
    </script>
</body>
</html>