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

// Define status order for display
$status_order = ['Pending', 'Processing', 'Shipped', 'Completed', 'Cancelled'];

// Get user orders grouped by status
$orders_by_status = [];
foreach ($status_order as $status) {
    $stmt = $db->prepare("
        SELECT o.*, 
               COUNT(oi.order_item_id) as item_count,
               SUM(oi.quantity) as total_items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = ? AND o.status = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$user_id, $status]);
    $orders_by_status[$status] = $stmt->fetchAll();
}

// Get all orders count
$stmt_all = $db->prepare("
    SELECT COUNT(*) as total_orders 
    FROM orders 
    WHERE user_id = ?
");
$stmt_all->execute([$user_id]);
$total_orders = $stmt_all->fetch()['total_orders'];

// Get user info
$user_stmt = $db->prepare("SELECT username, email FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Get active tab from URL or default to first tab
$active_tab = isset($_GET['status']) ? $_GET['status'] : 'all';
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
            margin-bottom: 20px;
        }
        
        /* Tabs Styles */
        .tabs-container {
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            flex-wrap: wrap;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
            gap: 5px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: bold;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            position: relative;
            min-width: 120px;
            text-align: center;
        }
        
        .tab:hover {
            color: #007bff;
            background-color: #f8f9fa;
        }
        
        .tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .tab-badge {
            display: inline-block;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            background: #6c757d;
            color: white;
            border-radius: 10px;
            font-size: 12px;
            line-height: 20px;
            margin-left: 5px;
            vertical-align: middle;
        }
        
        .tab.active .tab-badge {
            background: #007bff;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Order Cards */
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
            font-size: 0.9em;
        }
        
        .status-Pending { 
            background: #ffc107;
            border-left-color: #ffc107;
        }
        .status-Processing { 
            background: #17a2b8;
            border-left-color: #17a2b8;
        }
        .status-Shipped { 
            background: #6f42c1;
            border-left-color: #6f42c1;
        }
        .status-Completed { 
            background: #28a745;
            border-left-color: #28a745;
        }
        .status-Cancelled { 
            background: #dc3545;
            border-left-color: #dc3545;
        }
        
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
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid;
        }
        
        .stat-box.pending { border-top-color: #ffc107; }
        .stat-box.processing { border-top-color: #17a2b8; }
        .stat-box.shipped { border-top-color: #6f42c1; }
        .stat-box.completed { border-top-color: #28a745; }
        .stat-box.cancelled { border-top-color: #dc3545; }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
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
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: left;
                border-bottom: 1px solid #dee2e6;
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
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
        
        <!-- Order Statistics -->
        <div class="stats-summary">
            <div class="stat-box">
                <span class="stat-number"><?php echo $total_orders; ?></span>
                <span class="stat-label">Total Orders</span>
            </div>
            <?php foreach ($orders_by_status as $status => $orders): ?>
            <div class="stat-box <?php echo strtolower($status); ?>">
                <span class="stat-number"><?php echo count($orders); ?></span>
                <span class="stat-label"><?php echo $status; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab <?php echo $active_tab == 'all' ? 'active' : ''; ?>" 
                        onclick="showTab('all')">
                    All Orders <span class="tab-badge"><?php echo $total_orders; ?></span>
                </button>
                
                <?php foreach ($orders_by_status as $status => $orders): 
                    $count = count($orders);
                    if ($count > 0): ?>
                <button class="tab <?php echo $active_tab == strtolower($status) ? 'active' : ''; ?>" 
                        onclick="showTab('<?php echo strtolower($status); ?>')">
                    <?php echo $status; ?> <span class="tab-badge"><?php echo $count; ?></span>
                </button>
                <?php endif; endforeach; ?>
            </div>
            
            <!-- All Orders Tab -->
            <div id="tab-all" class="tab-content <?php echo $active_tab == 'all' ? 'active' : ''; ?>">
                <?php if ($total_orders == 0): ?>
                    <div class="no-orders">
                        <h3>No orders yet</h3>
                        <p>Start shopping to see your orders here.</p>
                        <a href="products.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                            Browse Products
                        </a>
                    </div>
                <?php else: 
                    $all_orders = [];
                    foreach ($orders_by_status as $status => $orders) {
                        $all_orders = array_merge($all_orders, $orders);
                    }
                    // Sort by order date descending
                    usort($all_orders, function($a, $b) {
                        return strtotime($b['order_date']) - strtotime($a['order_date']);
                    });
                    
                    foreach ($all_orders as $order): ?>
                        <?php include 'order_card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Status-specific Tabs -->
            <?php foreach ($orders_by_status as $status => $orders): 
                $tab_id = strtolower($status);
            ?>
            <div id="tab-<?php echo $tab_id; ?>" class="tab-content <?php echo $active_tab == $tab_id ? 'active' : ''; ?>">
                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <h3>No <?php echo $status; ?> Orders</h3>
                        <p>You don't have any orders with status "<?php echo $status; ?>"</p>
                        <a href="products.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">
                            Continue Shopping
                        </a>
                    </div>
                <?php else: 
                    foreach ($orders as $order): ?>
                        <?php include 'order_card.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="../memberDashboard.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">
                ← Back to Dashboard
            </a>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Update URL without page reload
            history.pushState(null, null, `?status=${tabName}`);
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(`tab-${tabName}`).classList.add('active');
            
            // Update active tab button
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status') || 'all';
            showTab(status);
        });
    </script>
</body>
</html>