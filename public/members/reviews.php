<?php
require __DIR__ . '/../../lib/base.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . "/../../includes/header.php";

// Ensure member is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

$stmt = db()->prepare("
    SELECT r.review_id, r.rating, r.comment, r.images, r.created_at, 
           u.username, p.name AS product_name
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    JOIN products p ON r.product_id = p.product_id
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../../assets/css/orders.css">
<link rel="stylesheet" href="../../assets/css/review.css">

<div class="reviews-container">
    <h2>My Product Reviews</h2>

    <?php if(empty($reviews)): ?>
        <p>You have not submitted any reviews yet.</p>
    <?php else: ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <strong><?= encode($review['username']) ?></strong> reviewed 
                    <em><?= encode($review['product_name']) ?></em> 
                    on <?= date('F d, Y', strtotime($review['created_at'])) ?>
                </div>
                <div class="review-rating">Rating: <?= $review['rating'] ?> / 5</div>
                <div class="review-comment"><?= nl2br(encode($review['comment'])) ?></div>
                
                <?php if (!empty($review['images'])): 
                    $images = json_decode($review['images'], true);
                ?>
                    <div class="review-images">
                        <?php foreach ($images as $imgPath): ?>
                            <img src="/online-shopping-system/public/<?= $imgPath ?>" alt="Review Image" class="review-image">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
