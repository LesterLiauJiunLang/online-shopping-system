<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

$stmt = db()->prepare("
    SELECT *
    FROM orders
    WHERE user_id = ?
    ORDER BY order_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/../../includes/header.php";
?>

<link rel="stylesheet" href="../../assets/css/orders.css">

<div class="order-history-container">
    <h2 class="order-history-title">My Orders</h2>

    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <div class="empty-orders-icon">📦</div>
            <h3>No Orders Yet</h3>
            <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
            <a href="products.php" class="shop-now-btn">Start Shopping</a>
        </div>
    <?php else: ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                    // Determine status badge class
                    $status_class = 'status-' . strtolower($order['status']);
                    ?>
                    <tr>
                        <td data-label="Order ID">#<?= $order['order_id'] ?></td>
                        <td data-label="Date"><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                        <td data-label="Total">RM <?= number_format($order['total_amount'], 2) ?></td>
                        <td data-label="Status">
                            <span class="status-badge <?= $status_class ?>">
                                <?= encode($order['status']) ?>
                            </span>
                        </td>
                        <td data-label="Action">
                            <a href="order_detail.php?id=<?= $order['order_id'] ?>" class="view-order-btn">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="cart-actions">
        <a href="products.php" class="continue-shopping-btn">← Continue Shopping</a>
    </div>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>