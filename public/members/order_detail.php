<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../lib/base.php';
$db = db();

$order_id = req('id');

$stmt = $db->prepare("
    SELECT *
    FROM orders
    WHERE order_id = ? AND user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect('order_history.php');
}

$stmt = $db->prepare("
    SELECT 
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.name,
        p.image_url
    FROM order_items oi
    JOIN products p ON p.product_id = oi.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . "/../../includes/header.php";
?>

<h2>Order #<?= $order['order_id'] ?></h2>

<p>
    <strong>Order Date:</strong> <?= $order['order_date'] ?><br>
    <strong>Status:</strong> <?= encode($order['status']) ?><br>
    <strong>Payment:</strong> <?= encode($order['payment_method']) ?> (<?= encode($order['payment_status']) ?>)
</p>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <th>Product</th>
        <th>Price</th>
        <th>Quantity</th>
        <th>Subtotal</th>
    </tr>

    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= encode($item['name']) ?></td>
            <td>RM <?= number_format($item['unit_price'], 2) ?></td>
            <td><?= $item['quantity'] ?></td>
            <td>RM <?= number_format($item['subtotal'], 2) ?></td>
        </tr>
    <?php endforeach; ?>

    <tr>
        <td colspan="3" align="right"><strong>Total</strong></td>
        <td><strong>RM <?= number_format($order['total_amount'], 2) ?></strong></td>
    </tr>
</table>

<p>
    <strong>Shipping Address:</strong><br>
    <?= nl2br(encode($order['shipping_address'])) ?>
</p>

<p>
    <a href="order_history.php">Back to Orders</a>
</p>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
