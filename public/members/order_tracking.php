<?php
// members/order_tracking.php
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
require_once __DIR__ . '/../../app/config/database.php';

$db = db();

$user_id = $_SESSION['user_id'];

// Get user orders
$stmt = $db->prepare("
    SELECT o.*, 
           COUNT(oi.order_item_id) as item_count,
           SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Get user info (optional, for header display)
$user_stmt = $db->prepare("SELECT username, email FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        
        .orders-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
            border-left: 5px solid #007bff;
            transition: transform 0.2s;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            color: white;
        }
        
        .status-pending { background: #ffc107; }
        .status-confirmed { background: #17a2b8; }
        .status-processing { background: #6f42c1; }
        .status-shipped { background: #007bff; }
        .status-delivered { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            color: white;
            font-weight: bold;
            text-align: center;
            border: none;
            transition: background-color 0.3s;
        }
        
        .btn-view { background: #28a745; }
        .btn-view:hover { background: #218838; }
        
        .btn-support { background: #6c757d; }
        .btn-support:hover { background: #5a6268; }
        
        .btn-track { background: #007bff; }
        .btn-track:hover { background: #0056b3; }
        
        .no-orders {
            text-align: center;
            padding: 50px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .user-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .user-info p {
            margin: 5px 0;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<?php require_once __DIR__ . "/../../includes/header.php"; ?>
<body>
    
    <div class="orders-container">
        <?php if ($user): ?>
        <div class="user-info">
            <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        <?php endif; ?>
        
        <h1>📦 My Orders</h1>
        
        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <h3>No orders yet</h3>
                <p>Start shopping to see your orders here.</p>
                <a href="products.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                    Browse Products
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <h3 style="margin: 0;">Order #<?php echo $order['order_id']; ?></h3>
                            <p style="margin: 5px 0; color: #666;">
                                Placed on <?php echo date('F d, Y', strtotime($order['order_date'])); ?>
                            </p>
                        </div>
                        <div>
                            <span class="order-status status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                            <p style="margin: 10px 0 0 0; font-weight: bold; font-size: 1.2em;">
                                RM <?php echo number_format($order['total_amount'], 2); ?>
                            </p>
                        </div>
                    </div>
                    
                    <p><strong>Items:</strong> <?php echo $order['total_items']; ?> item(s)</p>
                    <p><strong>Shipping to:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                    <p><strong>Payment:</strong> <?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?> 
                        (<span class="status-<?php echo strtolower($order['payment_status']); ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>)</p>
                    
                    <div class="order-actions">
                        <a href="order_detail.php?id=<?php echo $order['order_id']; ?>" class="btn btn-view">
                            View Details
                        </a>
                        <a href="../support/create_ticket.php?order_id=<?php echo $order['order_id']; ?>" 
                           class="btn btn-support">
                            📞 Get Support
                        </a>
                        <a href="order_track.php?id=<?php echo $order['order_id']; ?>" class="btn btn-track">
                            Track Order
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="../memberDashboard.php" style="color: #007bff; text-decoration: none;">
                ← Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
