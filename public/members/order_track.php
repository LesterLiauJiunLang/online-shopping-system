<?php
// members/order_track.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$order_id = $_GET['id'] ?? 0;

require_once __DIR__ . '/../../app/config/database.php';
$db = db();

// Get order details
$stmt = $db->prepare("
    SELECT o.*, u.username, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

// FAKE TRACKING SIMULATION - UPDATED FOR NEW STATUSES
function generateFakeTracking($order, $order_id, $order_date) {
    if (!$order) {
        return null;
    }
    
    // Timeline steps
    $statuses = [
        'order_placed' => [
            'status' => 'Order Placed',
            'description' => 'Your order has been received',
            'icon' => '📝',
            'time' => date('M d, Y H:i', strtotime($order_date))
        ],
        'payment_confirmed' => [
            'status' => 'Payment Confirmed',
            'description' => 'Payment has been processed successfully',
            'icon' => '💳',
            'time' => date('M d, Y H:i', strtotime($order_date . ' +30 minutes'))
        ],
        'processing' => [
            'status' => 'Processing',
            'description' => 'Order is being prepared for shipment',
            'icon' => '🏭',
            'time' => date('M d, Y H:i', strtotime($order_date . ' +2 hours'))
        ],
        'packed' => [
            'status' => 'Packed',
            'description' => 'Items have been packed securely',
            'icon' => '📦',
            'time' => date('M d, Y H:i', strtotime($order_date . ' +4 hours'))
        ],
        'shipped' => [
            'status' => 'Shipped',
            'description' => 'Package handed over to delivery partner',
            'icon' => '🚚',
            'time' => date('M d, Y H:i', strtotime($order_date . ' +1 day'))
        ],
        'out_for_delivery' => [
            'status' => 'Out for Delivery',
            'description' => 'Package is on its way to you',
            'icon' => '🚗',
            'time' => date('M d, Y H:i', strtotime($order_date . ' +2 days'))
        ],
        'completed' => [
            'status' => 'Delivered',
            'description' => 'Package has been delivered',
            'icon' => '✅',
            'time' => date('M d, Y H:i', strtotime($order_date . ' +3 days'))
        ]
    ];

    // STATUS MAPPING FOR UPDATED DATABASE
    // New statuses: Pending, Processing, Shipped, Completed, Cancelled
    $status_mapping = [
        'Pending' => 'order_placed',      // Step 1
        'Processing' => 'processing',     // Step 3
        'Shipped' => 'shipped',           // Step 5
        'Completed' => 'completed',       // Step 7
        'Cancelled' => 'order_placed'     // Cancelled shows at start
    ];

    $db_status = $order['status']; // Keep original case
    $current_step = $status_mapping[$db_status] ?? 'order_placed';

    return [
        'statuses' => $statuses,
        'current_step' => $current_step
    ];
}

$tracking = $order ? generateFakeTracking($order, $order_id, $order['order_date']) : null;

// Debug: Show what we got from database
// echo "<pre>Database Status: " . $order['status'] . "</pre>";
// echo "<pre>Mapped to: " . $tracking['current_step'] . "</pre>";

// Determine the correct header path
$header_path = file_exists(__DIR__ . '/../includes/header.php') ? '../includes/header.php' : '../../includes/header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Track Order #<?php echo $order_id; ?></title>
    <style>
        .tracking-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .tracking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .tracking-timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            position: relative;
        }
        
        .timeline-step::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 40px;
            bottom: -30px;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-step:last-child::before {
            display: none;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 20px;
            z-index: 2;
            position: relative;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .step-description {
            color: #666;
            font-size: 14px;
        }
        
        .step-time {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }
        
        /* Completed steps */
        .step-completed .step-icon {
            background: #4CAF50;
            color: white;
        }
        
        .step-completed::before {
            background: #4CAF50;
        }
        
        /* Current step */
        .step-current .step-icon {
            background: #2196F3;
            color: white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(33, 150, 243, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(33, 150, 243, 0); }
            100% { box-shadow: 0 0 0 0 rgba(33, 150, 243, 0); }
        }
        
        /* Future steps */
        .step-future .step-icon {
            background: #f0f0f0;
            color: #999;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .support-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .btn-support {
            display: inline-block;
            padding: 12px 25px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .btn-support:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .status-display {
            font-size: 1.2em;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin: 5px;
            color: white;
        }
        
        .status-Pending { background: #ffc107; color: #000; }
        .status-Processing { background: #17a2b8; }
        .status-Shipped { background: #007bff; }
        .status-Completed { background: #28a745; }
        .status-Cancelled { background: #dc3545; }
        
        .progress-info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #2196F3);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .order-progress {
            margin: 20px 0;
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php if (file_exists($header_path)): ?>
        <?php include_once $header_path; ?>
    <?php else: ?>
        <!-- Simple header if file doesn't exist -->
        <div style="background: #333; color: white; padding: 15px;">
            <h2 style="margin: 0;">Order Tracking</h2>
            <a href="order_tracking.php" style="color: white;">← Back to Orders</a>
        </div>
    <?php endif; ?>
    
    <div class="tracking-container">
        <?php if (!$order): ?>
            <div style="text-align: center; padding: 50px;">
                <h2>Order Not Found</h2>
                <p>The order you're looking for doesn't exist or you don't have permission to view it.</p>
                <a href="order_tracking.php" class="btn-primary">Back to Orders</a>
            </div>
        <?php else: ?>
            <!-- Header -->
            <div class="tracking-header">
                <h1 style="margin: 0;">📦 Track Order #<?php echo $order_id; ?></h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">
                    Estimated Delivery: <?php echo date('F d, Y', strtotime($order['order_date'] . ' +3 days')); ?>
                </p>
                <p style="margin: 5px 0 0 0; font-size: 1.1em;">
                    Current Status: 
                    <span class="status-display status-<?php echo $order['status']; ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </p>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-info">
                <?php 
                // Calculate progress percentage based on new statuses
                $progress_map = [
                    'Pending' => 14,      // 1/7 steps
                    'Processing' => 43,    // 3/7 steps
                    'Shipped' => 71,       // 5/7 steps
                    'Completed' => 100,    // 7/7 steps
                    'Cancelled' => 14      // Back to start
                ];
                $progress = $progress_map[$order['status']] ?? 14;
                
                // Get current step text
                $step_text = [
                    'Pending' => 'Order Placed',
                    'Processing' => 'Processing Order',
                    'Shipped' => 'Shipped',
                    'Completed' => 'Delivered',
                    'Cancelled' => 'Cancelled'
                ];
                ?>
                <div class="progress-stats">
                    <span>Order Progress</span>
                    <span><?php echo $progress; ?>% Complete</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                </div>
                <p style="text-align: center; margin-top: 10px; font-weight: bold;">
                    Current: <?php echo $step_text[$order['status']] ?? $order['status']; ?>
                </p>
            </div>
            
            <!-- Order Summary -->
            <div class="order-details">
                <h3>Order Summary</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>Order Date:</strong><br>
                        <?php echo date('F d, Y H:i', strtotime($order['order_date'])); ?>
                    </div>
                    <div>
                        <strong>Total Amount:</strong><br>
                        RM <?php echo number_format($order['total_amount'], 2); ?>
                    </div>
                    <div>
                        <strong>Shipping Address:</strong><br>
                        <?php echo htmlspecialchars($order['shipping_address']); ?>
                    </div>
                    <div>
                        <strong>Payment Status:</strong><br>
                        <?php if (isset($order['payment_status'])): ?>
                            <span style="color: <?php echo $order['payment_status'] === 'paid' ? '#28a745' : '#ffc107'; ?>; font-weight: bold;">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #6c757d;">Not Set</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tracking Timeline -->
            <div class="tracking-timeline">
                <h3>Order Journey</h3>
                
                <?php if ($tracking): 
                    $step_count = 0;
                    $steps = array_keys($tracking['statuses']);
                    
                    // Skip payment_confirmed step for orders beyond Pending
                    $skip_payment_step = ($order['status'] === 'Processing' || $order['status'] === 'Shipped' || $order['status'] === 'Completed');
                    
                    foreach ($tracking['statuses'] as $key => $step):
                        // Skip payment_confirmed step for orders that are already processing or beyond
                        if ($skip_payment_step && $key === 'payment_confirmed') {
                            continue;
                        }
                        
                        $step_class = '';
                        
                        // Determine step state
                        if ($key === $tracking['current_step']) {
                            $step_class = 'step-current';
                        } elseif (array_search($key, $steps) < array_search($tracking['current_step'], $steps)) {
                            $step_class = 'step-completed';
                        } else {
                            $step_class = 'step-future';
                        }
                        
                        $step_count++;
                ?>
                        <div class="timeline-step <?php echo $step_class; ?>">
                            <div class="step-icon"><?php echo $step['icon']; ?></div>
                            <div class="step-content">
                                <div class="step-title"><?php echo $step['status']; ?></div>
                                <div class="step-description"><?php echo $step['description']; ?></div>
                                <div class="step-time"><?php echo $step['time']; ?></div>
                                <?php if ($step_class === 'step-current'): ?>
                                    <div style="color: #2196F3; font-weight: bold; margin-top: 5px; font-size: 14px;">
                                        ✓ You are here
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                <?php endforeach; endif; ?>
            </div>
            
            <!-- Quick Tips based on status -->
            <div class="progress-info">
                <h4>💡 What's Next?</h4>
                <?php 
                $tips = [
                    'Pending' => 'Your order has been received. We\'ll confirm your payment and start processing soon.',
                    'Processing' => 'Your order is being prepared for shipment. This usually takes 1-2 business days.',
                    'Shipped' => 'Your package is on the way! Expected delivery in 2-3 business days.',
                    'Completed' => 'Your order has been delivered successfully. Thank you for shopping with us!',
                    'Cancelled' => 'This order has been cancelled. Please contact support if you have any questions.'
                ];
                echo '<p>' . ($tips[$order['status']] ?? 'Your order is being processed.') . '</p>';
                ?>
                
                <?php if ($order['status'] === 'Pending'): ?>
                    <p><strong>Next Steps:</strong> Payment confirmation → Processing → Shipping → Delivery</p>
                <?php elseif ($order['status'] === 'Processing'): ?>
                    <p><strong>Next Steps:</strong> Packing → Shipping → Delivery</p>
                <?php elseif ($order['status'] === 'Shipped'): ?>
                    <p><strong>Next Steps:</strong> Out for delivery → Delivery</p>
                <?php elseif ($order['status'] === 'Completed'): ?>
                    <p><strong>Order Complete!</strong> Thank you for your purchase.</p>
                <?php endif; ?>
            </div>
            
            <!-- Support Section -->
            <div class="support-section">
                <h3>❓ Need Help With This Order?</h3>
                <p>Our support team is here to help you with any questions about your order:</p>
                <ul>
                    <li>Delivery running late?</li>
                    <li>Wrong item received?</li>
                    <li>Need to change shipping address?</li>
                    <li>Any other questions</li>
                </ul>
                <a href="../support/create_ticket.php?order_id=<?php echo $order_id; ?>" 
                   class="btn-support">
                    📞 Contact Support About This Order
                </a>
            </div>
            
            <!-- Back Button -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="order_tracking.php" style="color: #667eea; text-decoration: none; font-weight: bold; padding: 10px 20px; border: 2px solid #667eea; border-radius: 5px;">
                    ← Back to All Orders
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php 
    // Try to find footer
    $footer_path = file_exists(__DIR__ . '/../includes/footer.php') ? '../includes/footer.php' : '../../includes/footer.php';
    if (file_exists($footer_path)): 
    ?>
        <?php include_once $footer_path; ?>
    <?php endif; ?>
</body>
</html>


