<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo "Unauthorized access";
    exit();
}

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';

$db = Database::getConnection();

// Get order_id from request
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo "Invalid order ID";
    exit();
}

try {
    // Get order details
    $stmt = $db->prepare("
        SELECT o.*, u.username, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id 
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo "Order not found";
        exit();
    }
    
    // Get order items
    $stmt = $db->prepare("
        SELECT oi.*, p.name as product_name, p.description as product_description
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['subtotal'];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Order #<?php echo $order['order_id']; ?></title>
    <style>
        @media print {
            body { font-family: Arial, sans-serif; margin: 20px; }
            .no-print { display: none !important; }
            .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
            .invoice-info { margin-bottom: 30px; }
            .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .invoice-table th { background-color: #f5f5f5; }
            .invoice-total { text-align: right; font-weight: bold; font-size: 16px; }
            .invoice-footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
        }
        
        body { font-family: Arial, sans-serif; margin: 20px; }
        .no-print { margin-bottom: 20px; }
        .print-btn { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; }
        .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
        .invoice-info { margin-bottom: 30px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .invoice-table th { background-color: #f5f5f5; }
        .invoice-total { text-align: right; font-weight: bold; font-size: 16px; }
        .invoice-footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-paid { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">🖨️ Print Invoice</button>
        <button class="print-btn" onclick="window.close()">Close</button>
    </div>
    
    <div class="invoice-header">
        <h1>INVOICE</h1>
        <h2>Order #<?php echo $order['order_id']; ?></h2>
        <p>Date: <?php echo date('F d, Y H:i', strtotime($order['order_date'])); ?></p>
    </div>
    
    <div class="invoice-info">
        <div class="info-grid">
            <div>
                <h3>Order Information</h3>
                <p><strong>Status:</strong> 
                    <span class="status status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </p>
                <p><strong>Payment Status:</strong> 
                    <span class="status status-<?php echo $order['payment_status']; ?>">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </p>
                <p><strong>Payment Method:</strong> <?php echo $order['payment_method'] ?: 'N/A'; ?></p>
                <p><strong>Total Amount:</strong> RM <?php echo number_format($order['total_amount'], 2); ?></p>
            </div>
            
            <div>
                <h3>Customer Information</h3>
                <p><strong>Customer ID:</strong> <?php echo $order['user_id']; ?></p>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                
                <h4>Shipping Address</h4>
                <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            </div>
        </div>
    </div>
    
    <h3>Order Items</h3>
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                    <br>
                    <small><?php echo htmlspecialchars(substr($item['product_description'] ?: 'No description', 0, 100)); ?>...</small>
                </td>
                <td><?php echo $item['quantity']; ?></td>
                <td>RM <?php echo number_format($item['unit_price'], 2); ?></td>
                <td>RM <?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="invoice-total">Total:</td>
                <td class="invoice-total">RM <?php echo number_format($order['total_amount'], 2); ?></td>
            </tr>
        </tfoot>
    </table>
    
    <div class="invoice-footer">
        <p>Invoice generated on <?php echo date('F d, Y H:i'); ?></p>
        <p>Generated by: <?php echo $_SESSION['username']; ?> (Admin)</p>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>