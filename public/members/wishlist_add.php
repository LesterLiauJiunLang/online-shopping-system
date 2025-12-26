<?php
require __DIR__ . '/../../lib/base.php';

header('Content-Type: application/json'); // return JSON

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../../app/config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['product_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$product_id = intval($input['product_id']);
$user_id = $_SESSION['user_id'];

// Check if already in wishlist
$stmt = db()->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user_id, $product_id]);
$exists = $stmt->fetch();

if ($exists) {
    // Remove from wishlist
    $stmt = db()->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    echo json_encode(['success' => true, 'added' => false]);
} else {
    // Add to wishlist
    $stmt = db()->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $product_id]);
    echo json_encode(['success' => true, 'added' => true]);
}
