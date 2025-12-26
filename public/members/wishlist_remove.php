<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_GET['product_id'])) {
    redirect('wishlist.php');
}

$product_id = intval($_GET['product_id']);
$user_id = $_SESSION['user_id'];

$stmt = db()->prepare("
    DELETE FROM wishlist
    WHERE user_id = ? AND product_id = ?
");
$stmt->execute([$user_id, $product_id]);

redirect($_SERVER['HTTP_REFERER']);
