<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

if (!is_post() || !isset($_POST['order_id'])) {
    redirect('order_history.php');
}

require_once __DIR__ . '/../../app/config/database.php';

$order_id = intval($_POST['order_id']);

// Verify the order belongs to the user 
$stmt = db()->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ? AND status = 'Completed'");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('order_history.php');
}

// Cancel the order
$update = db()->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ? LIMIT 1");
$update->execute([$order_id]);

// Restore product stock
$items = db()->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
$items->execute([$order_id]);
$items = $items->fetchAll();

foreach ($items as $item) {
    $restore = db()->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
    $restore->execute([$item['quantity'], $item['product_id']]);
}

redirect('order_history.php');
