<?php
// members/order_card.php
// This is a partial file included in the main template
?>
<div class="order-card" style="border-left-color: <?php 
    $colors = [
        'Pending' => '#ffc107',
        'Processing' => '#17a2b8',
        'Shipped' => '#6f42c1',
        'Completed' => '#28a745',
        'Cancelled' => '#dc3545'
    ];
    echo $colors[$order['status']] ?? '#007bff';
?>;">
    <div class="order-header">
        <div>
            <h3 style="margin: 0;">Order #<?php echo $order['order_id']; ?></h3>
            <p style="margin: 5px 0; color: #666;">
                Placed on <?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?>
            </p>
        </div>
        <div style="text-align: right;">
            <span class="order-status status-<?php echo $order['status']; ?>">
                <?php echo $order['status']; ?>
            </span>
            <p style="margin: 10px 0 0 0; font-weight: bold; font-size: 1.2em;">
                RM <?php echo number_format($order['total_amount'], 2); ?>
            </p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">
        <div>
            <strong>Items:</strong> <?php echo $order['total_items']; ?> item(s)
        </div>
        <div>
            <strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?>
        </div>
        <div>
            <strong>Payment Status:</strong>
            <span style="padding: 3px 8px; border-radius: 12px; background: <?php 
                $payment_colors = [
                    'pending' => '#ffc107',
                    'paid' => '#28a745',
                    'failed' => '#dc3545'
                ];
                echo $payment_colors[strtolower($order['payment_status'])] ?? '#6c757d';
            ?>; color: white; font-size: 0.85em;">
                <?php echo ucfirst($order['payment_status']); ?>
            </span>
        </div>
    </div>
    
    <p><strong>Shipping Address:</strong><br><?php echo htmlspecialchars($order['shipping_address']); ?></p>
    
    <div class="order-actions">
        <a href="order_detail.php?id=<?php echo $order['order_id']; ?>" class="btn btn-view">
            📋 View Details
        </a>
        <a href="../support/create_ticket.php?order_id=<?php echo $order['order_id']; ?>" 
           class="btn btn-support">
            📞 Get Support
        </a>
        <?php if ($order['status'] == 'Shipped' || $order['status'] == 'Processing'): ?>
        <a href="order_track.php?id=<?php echo $order['order_id']; ?>" class="btn btn-track">
            🚚 Track Order
        </a>
        <?php endif; ?>
        <?php if ($order['status'] == 'Pending'): ?>
        <a href="cancel_order.php?id=<?php echo $order['order_id']; ?>" 
           class="btn" 
           style="background: #dc3545;"
           onclick="return confirm('Are you sure you want to cancel this order?')">
            ❌ Cancel Order
        </a>
        <?php endif; ?>
    </div>
</div>