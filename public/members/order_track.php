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

// Get order details including payment_status
$stmt = $db->prepare("
    SELECT o.*, u.username, u.email, 
           o.payment_status, o.status as order_status
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

// FAKE TRACKING SIMULATION - UPDATED WITH PAYMENT CONFIRMATION
function generateFakeTracking($order, $order_id, $order_date) {
    if (!$order) {
        return null;
    }
    
    // Check if payment is confirmed
    $is_paid = isset($order['payment_status']) && $order['payment_status'] === 'paid';
    $db_status = $order['order_status']; // This is the main order status
    
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
            'time' => $is_paid ? date('M d, Y H:i', strtotime($order_date . ' +30 minutes')) : null,
            'show' => true // Always show if order exists
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

    // Determine current step based on BOTH payment_status AND order_status
    $current_step = 'order_placed'; // Default
    
    if ($db_status === 'Cancelled') {
        $current_step = 'order_placed'; // Show at start if cancelled
    } elseif (!$is_paid) {
        // If not paid, show payment as pending
        $current_step = 'order_placed';
    } else {
        // Payment is confirmed, now check order status
        switch ($db_status) {
            case 'Pending':
                $current_step = $is_paid ? 'payment_confirmed' : 'order_placed';
                break;
            case 'Processing':
                $current_step = 'processing';
                break;
            case 'Shipped':
                $current_step = 'shipped';
                break;
            case 'Completed':
                $current_step = 'completed';
                break;
            default:
                $current_step = 'order_placed';
        }
    }
    
    // Debug info (remove in production)
    // echo "<pre>Order Status: $db_status</pre>";
    // echo "<pre>Payment Status: " . $order['payment_status'] . "</pre>";
    // echo "<pre>Is Paid: " . ($is_paid ? 'Yes' : 'No') . "</pre>";
    // echo "<pre>Current Step: $current_step</pre>";

    return [
        'statuses' => $statuses,
        'current_step' => $current_step,
        'is_paid' => $is_paid,
        'order_status' => $db_status
    ];
}

$tracking = $order ? generateFakeTracking($order, $order_id, $order['order_date']) : null;

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
        
        /* Pending steps */
        .step-pending .step-icon {
            background: #ff9800;
            color: white;
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
        
        .payment-status-display {
            font-size: 1.1em;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: bold;
            display: inline-block;
            margin: 5px;
        }
        
        .payment-pending { background: #ffc107; color: #000; }
        .payment-paid { background: #28a745; color: white; }
        .payment-failed { background: #dc3545; color: white; }
        
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
        
        .payment-note {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .warning-note {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            font-size: 14px;
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
                <p style="margin: 10px 0 5px 0; font-size: 1.1em;">
                    Order Status: 
                    <span class="status-display status-<?php echo $order['order_status']; ?>">
                        <?php echo $order['order_status']; ?>
                    </span>
                </p>
                <p style="margin: 5px 0 0 0; font-size: 1.1em;">
                    Payment Status: 
                    <?php if (isset($order['payment_status'])): ?>
                        <span class="payment-status-display payment-<?php echo $order['payment_status']; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #6c757d;">Not Set</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Payment Status Note -->
            <?php if (isset($order['payment_status'])): ?>
                <?php if ($order['payment_status'] === 'paid'): ?>
                    <div class="payment-note">
                        <strong>✅ Payment Confirmed:</strong> Your payment has been successfully processed. 
                        Your order is now being prepared for shipment.
                    </div>
                <?php elseif ($order['payment_status'] === 'pending'): ?>
                    <div class="warning-note">
                        <strong>⚠️ Payment Pending:</strong> Your order is on hold until payment is confirmed. 
                        Please complete your payment to proceed with order processing.
                    </div>
                <?php elseif ($order['payment_status'] === 'failed'): ?>
                    <div class="warning-note" style="background: #ffebee; border-left-color: #f44336;">
                        <strong>❌ Payment Failed:</strong> There was an issue processing your payment. 
                        Please contact support or try placing your order again.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Progress Bar -->
            <div class="progress-info">
                <?php 
                // Calculate progress percentage based on payment AND order status
                $progress_map = [
                    'pending_payment' => 14,      // Order placed, payment pending
                    'payment_confirmed' => 29,    // Payment confirmed, order still pending
                    'processing' => 43,           // Order processing
                    'shipped' => 71,              // Order shipped
                    'completed' => 100,           // Order completed
                    'cancelled' => 14             // Cancelled
                ];
                
                // Determine progress state
                $progress_state = 'pending_payment'; // Default
                $payment_status = isset($order['payment_status']) ? $order['payment_status'] : 'pending';
                $order_status = $order['order_status'];
                
                if ($order_status === 'Cancelled') {
                    $progress_state = 'cancelled';
                } elseif ($payment_status === 'paid') {
                    // Payment is confirmed, check order status
                    switch ($order_status) {
                        case 'Pending':
                            $progress_state = 'payment_confirmed';
                            break;
                        case 'Processing':
                            $progress_state = 'processing';
                            break;
                        case 'Shipped':
                            $progress_state = 'shipped';
                            break;
                        case 'Completed':
                            $progress_state = 'completed';
                            break;
                        default:
                            $progress_state = 'payment_confirmed';
                    }
                } else {
                    // Payment not confirmed
                    $progress_state = 'pending_payment';
                }
                
                $progress = $progress_map[$progress_state] ?? 14;
                
                // Get current step text
                $step_text = [
                    'pending_payment' => 'Waiting for Payment',
                    'payment_confirmed' => 'Payment Confirmed',
                    'processing' => 'Processing Order',
                    'shipped' => 'Shipped',
                    'completed' => 'Delivered',
                    'cancelled' => 'Cancelled'
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
                    Current: <?php echo $step_text[$progress_state] ?? 'Processing'; ?>
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
                        <strong>Payment Method:</strong><br>
                        <?php echo isset($order['payment_method']) ? htmlspecialchars($order['payment_method']) : 'Not specified'; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tracking Timeline -->
            <div class="tracking-timeline">
                <h3>Order Journey</h3>
                
                <?php if ($tracking): 
                    $step_count = 0;
                    $steps = array_keys($tracking['statuses']);
                    $is_paid = $tracking['is_paid'];
                    
                    foreach ($tracking['statuses'] as $key => $step):
                        // Skip certain steps based on status
                        if (!$is_paid && $key === 'processing') {
                            // Don't show processing if payment not confirmed
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
                        
                        // Special handling for payment step
                        if ($key === 'payment_confirmed') {
                            if (!$is_paid && $tracking['order_status'] === 'Pending') {
                                $step_class = 'step-pending';
                            }
                        }
                        
                        $step_count++;
                ?>
                        <div class="timeline-step <?php echo $step_class; ?>">
                            <div class="step-icon"><?php echo $step['icon']; ?></div>
                            <div class="step-content">
                                <div class="step-title"><?php echo $step['status']; ?></div>
                                <div class="step-description"><?php echo $step['description']; ?></div>
                                <?php if (isset($step['time']) && $step['time']): ?>
                                    <div class="step-time"><?php echo $step['time']; ?></div>
                                <?php elseif ($key === 'payment_confirmed' && !$is_paid): ?>
                                    <div class="step-time" style="color: #ff9800;">
                                        ⏳ Waiting for payment confirmation
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($step_class === 'step-current'): ?>
                                    <div style="color: #2196F3; font-weight: bold; margin-top: 5px; font-size: 14px;">
                                        ✓ You are here
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($key === 'payment_confirmed' && !$is_paid): ?>
                                    <div style="color: #ff9800; font-weight: bold; margin-top: 5px; font-size: 14px;">
                                        ⚠️ Awaiting payment confirmation
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
                    'Pending' => $is_paid 
                        ? 'Your payment is confirmed! We\'ll start processing your order soon.' 
                        : 'Your order is waiting for payment confirmation. Once confirmed, we\'ll start processing.',
                    'Processing' => 'Your order is being prepared for shipment. This usually takes 1-2 business days.',
                    'Shipped' => 'Your package is on the way! Expected delivery in 2-3 business days.',
                    'Completed' => 'Your order has been delivered successfully. Thank you for shopping with us!',
                    'Cancelled' => 'This order has been cancelled. Please contact support if you have any questions.'
                ];
                echo '<p>' . ($tips[$order['order_status']] ?? 'Your order is being processed.') . '</p>';
                ?>
                
                <?php if ($order['order_status'] === 'Pending' && !$is_paid): ?>
                    <p><strong>Next Steps:</strong> Complete payment → Payment confirmation → Processing → Shipping → Delivery</p>
                <?php elseif ($order['order_status'] === 'Pending' && $is_paid): ?>
                    <p><strong>Next Steps:</strong> Processing → Packing → Shipping → Delivery</p>
                <?php elseif ($order['order_status'] === 'Processing'): ?>
                    <p><strong>Next Steps:</strong> Packing → Shipping → Delivery</p>
                <?php elseif ($order['order_status'] === 'Shipped'): ?>
                    <p><strong>Next Steps:</strong> Out for delivery → Delivery</p>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons based on status -->
            <div style="display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap;">
                <?php if (!$is_paid && $order['order_status'] !== 'Cancelled'): ?>
                    <a href="../checkout/payment.php?order_id=<?php echo $order_id; ?>" 
                       style="background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                        💳 Complete Payment
                    </a>
                <?php endif; ?>
                
                <?php if ($is_paid && $order['order_status'] === 'Shipped'): ?>
                    <a href="javascript:void(0)" onclick="alert('This feature will be available soon!')" 
                       style="background: #17a2b8; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;">
                       📍 Track Package
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Support Section -->
            <div class="support-section">
                <h3>❓ Need Help With This Order?</h3>
                <p>Our support team is here to help you with any questions about your order:</p>
                <ul>
                    <li>Payment issues or questions</li>
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