<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

$order_id = req('order_id');

$stmt = db()->prepare("
    SELECT o.*, u.username, u.email
    FROM orders o
    JOIN users u ON u.user_id = o.user_id
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);



$stmt = db()->prepare("
    SELECT 
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.product_id,
        p.name,
        COALESCE(pi.file_path, p.image_url) AS image_url
    FROM order_items oi
    JOIN products p 
        ON p.product_id = oi.product_id
    LEFT JOIN product_image pi 
        ON pi.product_id = p.product_id
        AND pi.sort_order = 0
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/../../includes/header.php";
?>


<link rel="stylesheet" href="../../assets/css/orders.css">
<script src="../../assets/js/review.js"></script>

<div class="order-detail-container">
    <h2 class="order-detail-title">Order <?= $order['order_id'] ?>.</h2>
    
    <!-- Order Information -->
    <div class="order-info-box">
        <div class="order-info-grid">
            <div class="order-info-item">
                <span class="order-info-label">Order Date</span>
                <span class="order-info-value"><?= date('F d, Y H:i', strtotime($order['order_date'])) ?></span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">Status</span>
                <span class="status-badge status-<?= strtolower($order['status']) ?>">
                    <?= encode($order['status']) ?>
                </span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">Payment Method</span>
                <span class="order-info-value"><?= encode($order['payment_method']) ?></span>
            </div>
            <div class="order-info-item">
                <span class="order-info-label">Payment Status</span>
                <span class="order-info-value"><?= encode($order['payment_status']) ?></span>
            </div>
        </div>
        
        <div class="order-info-item">
            <span class="order-info-label">Order Total</span>
            <span class="order-info-value" style="font-size: 1.5rem; color: #e44d26;">
                RM <?= number_format($order['total_amount'], 2) ?>
            </span>
        </div>
    </div>
    
    <!-- Order Items -->
    <h3 style="margin-bottom: 20px; color: #333;">Order Items</h3>
    <table class="order-items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="order-product-cell" data-label="Product">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= encode($item['image_url']) ?>" alt="<?= encode($item['name']) ?>" class="order-product-img">
                        <?php endif; ?>
                        <span class="order-product-name"><?= encode($item['name']) ?></span>
                    </td>
                    <td class="order-item-price" data-label="Price">
                        RM <?= number_format($item['unit_price'], 2) ?>
                    </td>
                    <td class="order-item-quantity" data-label="Quantity">
                        <?= $item['quantity'] ?>
                    </td>
                    <td class="order-item-subtotal" data-label="Subtotal">
                        RM <?= number_format($item['subtotal'], 2) ?>
                    </td>
                </tr>
         <?php endforeach; ?>
            
            <tr class="order-total-row">
                <td colspan="3" class="order-total-label">Total</td>
                <td class="order-total-value">RM <?= number_format($order['total_amount'], 2) ?></td>
            </tr>
        </tbody>
    </table>
    
    <!-- Shipping Address -->
    <div class="shipping-address-box">
        <h3>Shipping Address</h3>
        <div class="shipping-address-text">
            <?= nl2br(encode($order['shipping_address'])) ?>
        </div>
    </div>
    
    <!-- Order Actions -->
    <div class="order-actions">
        <a href="orders.php" class="back-to-orders-btn">← Back to Orders</a>
        
    </div>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>