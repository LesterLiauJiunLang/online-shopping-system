<?php
require __DIR__ . '/../../lib/base.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$product_id = req('product_id');
$rating = intval(req('rating'));
$comment = req('comment');

// Validate
if (!$product_id || !$rating || !$comment) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Check if user already reviewed this product
$stmt = db()->prepare("SELECT COUNT(*) FROM reviews WHERE user_id=? AND product_id=?");
$stmt->execute([$user_id, $product_id]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
    exit;
}

// Handle file uploads
$uploaded_paths = [];
if (!empty($_FILES['images'])) {
    foreach ($_FILES['images']['tmp_name'] as $index => $tmp) {
        $filename = time() . '_' . basename($_FILES['images']['name'][$index]);
        $target = __DIR__ . '/../../public/uploads/reviews/' . $product_id . '/';
            if (!is_dir($target)) mkdir($target, 0755, true);
            $filename = time() . '_' . basename($_FILES['images']['name'][$index]);
            $fullpath = $target . $filename;

            if (move_uploaded_file($tmp, $fullpath)) {
            $uploaded_paths[] = 'uploads/reviews/' . $product_id . '/' . $filename; // path relative to public
}
    }
}

// Insert review
$stmt = db()->prepare("INSERT INTO reviews (user_id, product_id, rating, comment, images) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $product_id, $rating, $comment, json_encode($uploaded_paths)]);

echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
