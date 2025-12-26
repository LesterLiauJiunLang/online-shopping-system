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
require_once __DIR__ . '/../../lib/base.php';

$db = db();
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

// Initialize filter variables
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build the SQL query with filters
$sql = "
    SELECT o.*, u.username, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.user_id 
    WHERE 1=1
";

$params = [];

// Add status filter
if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

// Add date range filters
if (!empty($date_from)) {
    $sql .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

// Add ordering
$sql .= " ORDER BY o.order_date ASC";

// Prepare and execute the query
$stmt = $db->prepare($sql);
$stmt->execute($params);
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
            <a href="../dashboard.php">🏠 Dashboard</a>
            <a href="products.php">📋 Products</a>
            <a href="product_add.php">➕ Add Product</a>
            <a href="orders.php" class="active">📦 Orders</a>
            <a href="categories.php">🏷️ Categories</a>
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
                        <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <button type="submit" class="btn-filter">Filter</button>
                <a href="orders.php" class="btn-clear">Clear</a>
            </form>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 20px;">📭</div>
                <h3>No Orders Found</h3>
                <p>
                    <?php if ($status_filter || $date_from || $date_to): ?>
                        No orders match your filter criteria. <a href="orders.php">Clear filters</a> to see all orders.
                    <?php else: ?>
                        There are no orders in the system yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="results-info">
                <p>
                    Showing <?php echo count($orders); ?> order(s)
                    <?php if ($status_filter): ?>
                        with status: <strong><?php echo htmlspecialchars($status_filter); ?></strong>
                    <?php endif; ?>
                    <?php if ($date_from || $date_to): ?>
                        from 
                        <?php if ($date_from): ?>
                            <strong><?php echo htmlspecialchars($date_from); ?></strong>
                        <?php endif; ?>
                        <?php if ($date_from && $date_to): ?>
                             to 
                        <?php endif; ?>
                        <?php if ($date_to): ?>
                            <strong><?php echo htmlspecialchars($date_to); ?></strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <table class="orders-table">
    <thead>
        <tr>
            <th>No.</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        // Add sequential numbering
        $counter = 1;
        foreach ($orders as $order): 
        ?>
        <tr>
            <td><?php echo $counter; ?></td>
            <td>
                <strong><?php echo htmlspecialchars($order['username']); ?></strong><br>
                <small><?php echo htmlspecialchars($order['email']); ?></small>
            </td>
            <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
            <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
            <td>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                    <select name="status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                        <option value="Completed" <?php echo $order['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $order['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                    <a href="orders.php?delete=<?php echo $order['order_id']; ?>" class="btn-delete" onclick="return confirm('Delete this order?')">Delete</a>
                </div>
            </td>
        </tr>
        <?php 
        $counter++;
        endforeach; 
        ?>
    </tbody>
</table>
            
            <div class="order-details" id="orderDetails">
                <!-- Order details will be loaded here via JavaScript -->
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function showOrderDetails(orderId) {
    const detailsDiv = document.getElementById('orderDetails');
    
    // Show loading state
    detailsDiv.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 24px; margin-bottom: 20px;">⏳</div>
            <p>Loading order details...</p>
        </div>
    `;
    detailsDiv.classList.add('active');
    
    // Fetch order details via AJAX
    fetch(`get_order_details.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                detailsDiv.innerHTML = `
                    <div class="error-message">
                        Error: ${data.error}
                    </div>
                    <button onclick="document.getElementById('orderDetails').classList.remove('active')" class="btn-cancel">Close</button>
                `;
                return;
            }
            
            const order = data.order;
            const items = data.items;
            const subtotal = data.subtotal;
            
            // Format date
            const orderDate = new Date(order.order_date);
            const formattedDate = orderDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Build HTML for order details
            let html = `
                <div class="order-details-header">
                    <h3>Order Details - #${order.order_id}</h3>
                    <span class="status status-${order.status}">${order.status.toUpperCase()}</span>
                </div>
                
                <div class="order-info-grid">
                    <div class="info-section">
                        <h4>📦 Order Information</h4>
                        <table class="info-table">
                            <tr>
                                <th>Order ID:</th>
                                <td>#${order.order_id}</td>
                            </tr>
                            <tr>
                                <th>Order Date:</th>
                                <td>${formattedDate}</td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="status status-${order.status}">
                                        ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Status:</th>
                                <td>
                                    <span class="status status-${order.payment_status}">
                                        ${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Method:</th>
                                <td>${order.payment_method || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td><strong>RM ${parseFloat(order.total_amount).toFixed(2)}</strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="info-section">
                        <h4>👤 Customer Information</h4>
                        <table class="info-table">
                            <tr>
                                <th>Customer ID:</th>
                                <td>${order.user_id}</td>
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td>${order.username}</td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td>${order.email}</td>
                            </tr>
                        </table>
                        
                        <h4 style="margin-top: 20px;">📍 Shipping Address</h4>
                        <div class="shipping-address">
                            ${order.shipping_address.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
                
                <div class="order-items-section">
                    <h4>🛒 Order Items (${items.length} items)</h4>
            `;
            
            if (items.length > 0) {
                html += `
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                items.forEach(item => {
                    html += `
                        <tr>
                            <td>
                                <strong>${item.product_name}</strong>
                                <br>
                                <small style="color: #666;">${item.product_description ? item.product_description.substring(0, 100) + '...' : 'No description'}</small>
                            </td>
                            <td>${item.quantity}</td>
                            <td>RM ${parseFloat(item.unit_price).toFixed(2)}</td>
                            <td>RM ${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                });
                
                html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right; font-weight: bold;">Total:</td>
                                <td style="font-weight: bold; font-size: 16px;">RM ${parseFloat(order.total_amount).toFixed(2)}</td>
                            </tr>
                        </tfoot>
                    </table>
                `;
            } else {
                html += `<p class="empty-state">No items found in this order.</p>`;
            }
            
            html += `
                </div>
                
                <div class="order-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px;">
                    <button onclick="printOrderDetails(${orderId})" class="btn-primary">
                        🖨️ Print Invoice
                    </button>
                    <button onclick="document.getElementById('orderDetails').classList.remove('active')" class="btn-cancel">Close</button>
                </div>
            `;
            
            detailsDiv.innerHTML = html;
            
        })
        .catch(error => {
            detailsDiv.innerHTML = `
                <div class="error-message">
                    Error loading order details: ${error.message}
                </div>
                <button onclick="document.getElementById('orderDetails').classList.remove('active')" class="btn-cancel">Close</button>
            `;
        });
}


// Function to print order details (keeping the original print functionality)
function printOrderDetails(orderId) {
    // Open print window with order details
    const printWindow = window.open(`print_invoice_order_detail.php?order_id=${orderId}`, '_blank');
    printWindow.focus();
}

    </script>
</body>
</html>