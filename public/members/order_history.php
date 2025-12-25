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

<h2>My Orders</h2>

<?php if (empty($orders)): ?>
    <p>You have not placed any orders yet.</p>
<?php else: ?>
<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>Order ID</th>
        <th>Date</th>
        <th>Total</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

    <?php foreach ($orders as $order): ?>
        <tr>
            <td>#<?= $order['order_id'] ?></td>
            <td><?= $order['order_date'] ?></td>
            <td>RM <?= number_format($order['total_amount'], 2) ?></td>
            <td><?= encode($order['status']) ?></td>
            <td>
                <a href="order_detail.php?id=<?= $order['order_id'] ?>">
                    View
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<p>
    <a href="products.php">Continue Shopping</a>
</p>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
